<?php

namespace App\Services;

/**
 * Detects photo "openings" (slots) inside a frame template image.
 *
 * Every slot is returned as:
 *   x, y, width, height   – bounding box in ORIGINAL template pixels
 *   shape                 – rectangle | circle | ellipse | rounded_rectangle | heart | polygon
 *   rotation              – degrees (always 0 from auto-detection)
 *   points                – OPTIONAL. Only for irregular shapes (heart / polygon).
 *                           List of [x, y] pairs, each normalised 0..1 inside the slot's bounding box.
 *
 * Detection strategies, in order (first one that finds something wins):
 *   0. External CV microservice (SLOT_DETECTOR_URL), if configured
 *   1. Transparent cut-outs in a PNG template
 *   2. NEW  Dark-ring openings  – any shape (heart, star, circle, arch...) enclosed by a dark border,
 *                                 including hearts hanging from strings (like a "heart dream-catcher" frame)
 *   3. White / blank placeholder openings
 *   4. Dark divider compartments (collage grids)
 *   5. Edge contour detection
 */
class SlotDetector
{
    /** Number of dark blobs the last ring-detector run looked at (valid or not). */
    private int $ringCandidates = 0;

    /** Share of the working image that is edge / tiny fragments in the last flat-region run (texture measure). */
    private float $flatFragmentation = 0.0;

    public function detect(string $path, array $options = []): array
    {
        $raw = @file_get_contents($path);
        $src = $raw ? @imagecreatefromstring($raw) : false;
        if (!$src) return ['width' => 0, 'height' => 0, 'slots' => []];

        $origWidth  = imagesx($src);
        $origHeight = imagesy($src);

        // 0. External AI / Computer Vision Microservice (Python OpenCV / FastAPI) if configured
        $externalUrl = env('SLOT_DETECTOR_URL');
        if ($externalUrl) {
            $externalSlots = $this->callExternalDetector($externalUrl, $path);
            if ($externalSlots !== null && !empty($externalSlots)) {
                imagedestroy($src);
                return [
                    'width' => $origWidth,
                    'height' => $origHeight,
                    'slots' => $this->sortReadingOrder($externalSlots),
                ];
            }
        }

        $maxWorkingDim = (int) ($options['max_working_dim'] ?? 800);
        $scale = 1.0;
        $maxDim = max($origWidth, $origHeight);
        if ($maxDim > $maxWorkingDim) $scale = $maxWorkingDim / $maxDim;
        $w = max(1, (int) round($origWidth * $scale));
        $h = max(1, (int) round($origHeight * $scale));

        $work = imagecreatetruecolor($w, $h);
        imagealphablending($work, false);
        imagesavealpha($work, true);
        imagecopyresampled($work, $src, 0, 0, 0, 0, $w, $h, $origWidth, $origHeight);

        // Smaller luminance map used by the dark-ring detector (keeps PHP memory/time low on big templates)
        $ringMaxDim = (int) ($options['ring_max_dim'] ?? 560);
        $ringScale  = min(1.0, $ringMaxDim / max(1, $maxDim));
        $rw = max(1, (int) round($origWidth * $ringScale));
        $rh = max(1, (int) round($origHeight * $ringScale));
        $ringWork = imagecreatetruecolor($rw, $rh);
        imagealphablending($ringWork, false);
        imagesavealpha($ringWork, true);
        imagecopyresampled($ringWork, $src, 0, 0, 0, 0, $rw, $rh, $origWidth, $origHeight);
        imagedestroy($src);
        $ringLum = $this->luminanceOnWhite($ringWork, $rw, $rh);
        $ringChroma = $this->chromaOnWhite($ringWork, $rw, $rh);
        imagedestroy($ringWork);

        $hasSignificantAlpha = false;
        $alphaStr = str_repeat("\0", $w * $h);
        $lumStr = str_repeat("\0", $w * $h);

        for ($y = 0; $y < $h; $y++) {
            $rowOffset = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($work, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                $idx = $rowOffset + $x;
                $alphaStr[$idx] = chr($a);
                if ($a >= 60) $hasSignificantAlpha = true;
                $lumStr[$idx] = chr((int) (0.299 * $r + 0.587 * $g + 0.114 * $b));
            }
        }
        imagedestroy($work);

        $slots = [];

        // 1. Transparent PNG cutouts (e.g. digital frame templates)
        if ($hasSignificantAlpha) {
            $slots = $this->detectAlphaCutouts($alphaStr, $w, $h, $scale);
        }

        // 2. Closed-in openings: "flat" windows (white / dark / grey placeholders) and dark-ring photo openings.
        //    Both are tried; the one that explains more openings wins (a tie goes to the flat-region result,
        //    whose outline is the exact inner border of the frame).
        if (empty($slots)) {
            $flatSlots = [];
            if (empty($options['skip_flat'])) {
                try {
                    $flatSlots = $this->detectFlatRegions($ringLum, $rw, $rh, $ringScale, $options, $ringChroma);
                } catch (\Throwable $e) {
                    $flatSlots = [];
                }
                // a single window is only trusted when it is big (one large opening, e.g. a diamond or heart wreath)
                if (count($flatSlots) < 2) {
                    $only = $flatSlots[0] ?? null;
                    if (!$only || $only['width'] * $only['height'] < 0.12 * $origWidth * $origHeight) $flatSlots = [];
                }
            }
            $ringSlots = [];
            if (empty($options['skip_ring'])) {
                try {
                    $ringSlots = $this->detectRingOpeningsBest($ringLum, $rw, $rh, $ringScale, $options);
                } catch (\Throwable $e) {
                    $ringSlots = [];
                }
            }
            $chosen = count($flatSlots) >= count($ringSlots) ? $flatSlots : $ringSlots;
            // Sheets of thin line-art rings / badges (stamps, sticker sheets): each closed outline is an opening.
            // Only used when it clearly explains more (round) openings than the strategies above.
            if (empty($options['skip_lineart'])) {
                try {
                    $lineSlots = $this->detectLineArtRings($ringLum, $rw, $rh, $ringScale, $options);
                } catch (\Throwable $e) {
                    $lineSlots = [];
                }
                if (count($lineSlots) >= 4 && count($lineSlots) > count($chosen)) $chosen = $lineSlots;
            }
            // The classic white-window scan sometimes sees clearly more windows (e.g. a glowing grid whose outer cells
            // are tinted): when it does, trust it instead.
            if (!empty($chosen) && $chosen === $flatSlots) {
                $whiteSlots = $this->detectWhitePlaceholders($lumStr, $w, $h, $scale);
                if (count($whiteSlots) > count($chosen)) {
                    $slots = $whiteSlots;
                    $chosen = [];
                }
            }
            if (!empty($chosen)) {
                // These slots are disjoint by construction, so skip the bbox-overlap filter
                // (a big heart's bounding box legitimately overlaps neighbouring small hearts).
                return [
                    'width'  => $origWidth,
                    'height' => $origHeight,
                    'slots'  => $this->sortReadingOrder($chosen),
                ];
            }
        }

        // 3. White / Blank placeholder openings
        if (empty($slots)) {
            $slots = $this->detectWhitePlaceholders($lumStr, $w, $h, $scale);
        }

        // 4. Grid / Dark Divider Compartments (Collage Frames with black/dark wood borders)
        if (empty($slots)) {
            $wallThresh = isset($options['edge_threshold']) ? max(20, min(80, (int)$options['edge_threshold'] + 15)) : 45;
            $slots = $this->detectDividerCompartments($lumStr, $w, $h, $scale, $wallThresh);
        }

        // 5. Edge contour detection (for general framed photos/templates)
        if (empty($slots)) {
            $edgeThresh = (int) ($options['edge_threshold'] ?? 28);
            $slots = $this->detectEdgeSlots($lumStr, $w, $h, $scale, $edgeThresh);
        }

        // Textured templates (a wall with frames hanging on it): the legacy boxes are only approximate.
        // Snap each of them to the exact flat window found at the same place, when there is one.
        if (!empty($slots) && empty($options['skip_flat'])) {
            try {
                $flatAll = $this->detectFlatRegions($ringLum, $rw, $rh, $ringScale, $options, $ringChroma);
                if (!empty($flatAll)) $slots = $this->snapToFlat($slots, $flatAll);
            } catch (\Throwable $e) {
                // keep the legacy boxes
            }
        }

        $slots = $this->removeOverlapping($slots);
        $slots = $this->sortReadingOrder($slots);

        return ['width' => $origWidth, 'height' => $origHeight, 'slots' => $slots];
    }

    /* ======================================================================
     *  Slot normalisation helpers (used by the controllers when saving)
     * ==================================================================== */

    /**
     * Cleans the slot list coming from the browser and makes sure irregular shapes keep their outline.
     *
     * If a slot is a heart/polygon but the browser did not send its outline ("points"), the template is
     * re-analysed on the server and the outline of the best matching detected opening is attached.
     */
    public function normalizeSlots(array $slots, ?string $templatePath = null): array
    {
        $detected = null;
        $out = [];

        foreach ($slots as $s) {
            if (!is_array($s)) continue;

            $slot = [
                'x'        => (int) round($s['x'] ?? 0),
                'y'        => (int) round($s['y'] ?? 0),
                'width'    => (int) round($s['width'] ?? 0),
                'height'   => (int) round($s['height'] ?? 0),
                'shape'    => (string) ($s['shape'] ?? 'rectangle'),
                'rotation' => (float) ($s['rotation'] ?? 0),
            ];

            $points = $this->sanitizePoints($s['points'] ?? null);

            if (!$points && in_array($slot['shape'], ['heart', 'polygon'], true) && $templatePath && is_file($templatePath)) {
                if ($detected === null) {
                    $detected = $this->detect($templatePath)['slots'] ?? [];
                }
                $match = $this->matchDetectedSlot($slot, $detected);
                if ($match && !empty($match['points'])) {
                    $points = $match['points'];
                }
            }

            if ($points && in_array($slot['shape'], ['heart', 'polygon'], true)) {
                $slot['points'] = $points;
            }

            $out[] = $slot;
        }

        return $out;
    }

    /**
     * Validates an outline coming from the client: 3..400 pairs of numbers, clamped to 0..1.
     */
    public function sanitizePoints(mixed $points): ?array
    {
        if (!is_array($points) || count($points) < 3 || count($points) > 400) return null;

        $clean = [];
        foreach ($points as $p) {
            if (!is_array($p) || count($p) < 2 || !is_numeric($p[0]) || !is_numeric($p[1])) return null;
            $clean[] = [
                round(min(1.0, max(0.0, (float) $p[0])), 4),
                round(min(1.0, max(0.0, (float) $p[1])), 4),
            ];
        }
        return $clean;
    }

    private function matchDetectedSlot(array $slot, array $detected): ?array
    {
        $best = null;
        $bestIou = 0.5;
        foreach ($detected as $d) {
            if (empty($d['points'])) continue;
            $ix = max(0, min($slot['x'] + $slot['width'], $d['x'] + $d['width']) - max($slot['x'], $d['x']));
            $iy = max(0, min($slot['y'] + $slot['height'], $d['y'] + $d['height']) - max($slot['y'], $d['y']));
            $inter = $ix * $iy;
            $union = $slot['width'] * $slot['height'] + $d['width'] * $d['height'] - $inter;
            if ($union <= 0) continue;
            $iou = $inter / $union;
            if ($iou > $bestIou) { $bestIou = $iou; $best = $d; }
        }
        return $best;
    }

    /* ======================================================================
     *  2. DARK-RING OPENINGS  (hearts, circles, stars... any shape)
     * ==================================================================== */

    /**
     * Runs the ring detector with a few "how dark is dark" thresholds (templates differ: pure black rims,
     * dark-grey bezels...) and keeps the run that found the most valid openings (ties: the darkest threshold).
     */
    private function detectRingOpeningsBest(string $lumStr, int $w, int $h, float $scale, array $options): array
    {
        $thresholds = isset($options['dark_threshold']) ? [(int) $options['dark_threshold']] : [45, 75, 105];

        $best = [];
        $maxCandidates = 0;
        foreach ($thresholds as $t) {
            $this->ringCandidates = 0;
            try {
                $found = $this->detectRingOpenings($lumStr, $w, $h, $scale, array_merge($options, ['dark_threshold' => $t]));
            } catch (\Throwable $e) {
                $found = [];
            }
            $maxCandidates = max($maxCandidates, $this->ringCandidates, count($found));
            if (count($found) > count($best)) $best = $found;
        }

        // A darker threshold may only "see" some of the dark shapes. If a lighter threshold reveals many more
        // dark blobs than we could turn into openings, this is not a "photo inside a dark rim" template.
        if (empty($best) || count($best) < 0.75 * $maxCandidates) return [];

        return $best;
    }

    /**
     * Finds openings that are enclosed by a dark border ("ring").
     *
     * 1. dark pixels = frame material; the background is every large light region that touches the image border.
     * 2. everything that is NOT background (frame + whatever sits inside it, e.g. sample photos) is "solid".
     * 3. a morphological opening removes thin hanging strings, so each ring becomes its own solid blob.
     * 4. for every blob the thickness of its dark rim is measured, and the rim is peeled off.
     *    What remains is the opening, whatever its shape.
     * 5. the opening's outline is traced and simplified into a normalised polygon.
     */
    private function detectRingOpenings(string $lumStr, int $w, int $h, float $scale, array $options): array
    {
        $n = $w * $h;
        if ($w < 40 || $h < 40) return [];

        $this->raiseMemoryLimit();

        $darkThr = (int) ($options['dark_threshold'] ?? 45);

        // 1. dark mask (3x3 closing seals 1-2px anti-aliasing gaps in the ring)
        $dark = array_fill(0, $n, 0);
        $darkCount = 0;
        for ($i = 0; $i < $n; $i++) {
            if (ord($lumStr[$i]) < $darkThr) { $dark[$i] = 1; $darkCount++; }
        }
        if ($darkCount < $n * 0.004 || $darkCount > $n * 0.80) return [];
        $dark = $this->morph3($this->morph3($dark, $w, $h, true), $w, $h, false);

        // 2. background = big light regions that run along the image border.
        //    A band along the image edge is "sealed" (treated as dark) while labelling, so an opening whose ring is
        //    cut off by the image edge (like a heart touching the bottom of a screenshot) does not leak into the
        //    background. A region only counts as background if a real stretch of the border belongs to it.
        $sw = max(1, (int) round(0.01 * max($w, $h)));
        $inBand = static fn (int $x, int $y): bool => $x < $sw || $y < $sw || $x >= $w - $sw || $y >= $h - $sw;

        $light = array_fill(0, $n, 0);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $i = $y * $w + $x;
                $light[$i] = ($dark[$i] || $inBand($x, $y)) ? 0 : 1;
            }
        }
        [$labels, $comps] = $this->labelComponents($light, $w, $h, false);
        unset($light);

        // how much of the image border band does each light region own? (anchor = nearest pixel inside the band)
        $contact = [];
        $anchorLabel = function (int $x, int $y) use ($labels, $w, $h, $sw): int {
            $ax = min(max($x, $sw), $w - 1 - $sw);
            $ay = min(max($y, $sw), $h - 1 - $sw);
            return $labels[$ay * $w + $ax];
        };
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if (!$inBand($x, $y)) { $x = max($x, $w - $sw - 1); continue; }
                if ($dark[$y * $w + $x]) continue;
                $l = $anchorLabel($x, $y);
                if ($l !== 0) $contact[$l] = ($contact[$l] ?? 0) + 1;
            }
        }

        $perimeter = (2 * ($w + $h) - 4) * $sw;
        $isBg = [];
        $hasBg = false;
        foreach ($comps as $id => $c) {
            $touch = $contact[$id] ?? 0;
            if (($touch >= 0.08 * $perimeter && $c['area'] >= 0.02 * $n)
                || ($touch >= 0.03 * $perimeter && $c['area'] >= 0.15 * $n)) {
                $isBg[$id] = true;
                $hasBg = true;
            }
        }
        if (!$hasBg) return [];

        $solid = array_fill(0, $n, 1);
        $solidCount = $n;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $i = $y * $w + $x;
                $l = $labels[$i];
                if ($l !== 0) {
                    if (isset($isBg[$l])) { $solid[$i] = 0; $solidCount--; }
                } elseif (!$dark[$i] && $inBand($x, $y)) {
                    // sealed band pixel that was really light: it belongs to an opening only when the region just
                    // inside the band is a real (non-background) region - otherwise it is background.
                    $al = $anchorLabel($x, $y);
                    if ($al === 0 || isset($isBg[$al])) { $solid[$i] = 0; $solidCount--; }
                }
            }
        }
        unset($labels, $comps, $isBg, $contact);
        if ($solidCount < $n * 0.01 || $solidCount > $n * 0.92) return [];

        // 3. cut the hanging strings: opening with a disk wider than the strings
        $stringRadius = (int) ($options['string_radius'] ?? max(3, (int) round(0.02 * max($w, $h))));
        $blobs = $this->openDisk($solid, $w, $h, $stringRadius);

        [$blobLabels, $blobComps] = $this->labelComponents($blobs, $w, $h, false);
        unset($blobs);

        $slots = [];
        $candidates = 0;
        foreach ($blobComps as $id => $c) {
            if ($c['area'] < 0.0015 * $n || $c['area'] > 0.75 * $n) continue;
            $candidates++;

            // crop with a margin of (string radius + 2) so the rim can be restored around the string joints
            $pad = $stringRadius + 2;
            $x0 = max(0, $c['minX'] - $pad);
            $y0 = max(0, $c['minY'] - $pad);
            $x1 = min($w - 1, $c['maxX'] + $pad);
            $y1 = min($h - 1, $c['maxY'] + $pad);
            $cw = $x1 - $x0 + 1;
            $ch = $y1 - $y0 + 1;

            // the opened blob of this ring...
            $core = array_fill(0, $cw * $ch, 0);
            $cd = array_fill(0, $cw * $ch, 0);
            $cs = array_fill(0, $cw * $ch, 0);
            for ($y = 0; $y < $ch; $y++) {
                $gi = ($y0 + $y) * $w + $x0;
                $ci = $y * $cw;
                for ($x = 0; $x < $cw; $x++, $gi++, $ci++) {
                    if ($blobLabels[$gi] === $id) $core[$ci] = 1;
                    $cd[$ci] = $dark[$gi];
                    $cs[$ci] = $solid[$gi];
                }
            }

            // ...grown back by the opening radius inside the original solid area: this gives back the ring's true
            // outer edge where a string joined it (the strings themselves only survive as short stubs)
            $inv = array_fill(0, $cw * $ch, 0);
            for ($i = 0, $m = $cw * $ch; $i < $m; $i++) $inv[$i] = $core[$i] ? 0 : 1;
            $dd = $this->edtSquared($inv, $cw, $ch);
            $r2 = $stringRadius * $stringRadius;
            $cm = array_fill(0, $cw * $ch, 0);
            for ($i = 0, $m = $cw * $ch; $i < $m; $i++) {
                if ($core[$i] || ($cs[$i] && $dd[$i] <= $r2)) $cm[$i] = 1;
            }
            unset($core, $inv, $dd, $cs);

            $d2 = $this->edtSquared($cm, $cw, $ch);

            // measure the dark rim: consecutive layers (distance from the blob edge) that are mostly dark
            $bw = $c['maxX'] - $c['minX'] + 1;
            $bh = $c['maxY'] - $c['minY'] + 1;
            $cap = max(3, (int) round(0.30 * min($bw, $bh)));
            $cnt = array_fill(0, $cap + 2, 0);
            $dk  = array_fill(0, $cap + 2, 0);
            for ($i = 0, $m = $cw * $ch; $i < $m; $i++) {
                if (!$cm[$i]) continue;
                $L = (int) round(sqrt($d2[$i]));
                if ($L > $cap) continue;
                $cnt[$L]++;
                if ($cd[$i]) $dk[$L]++;
            }
            $rim = 0;
            for ($L = 1; $L <= $cap; $L++) {
                if ($cnt[$L] === 0) break;
                if ($dk[$L] / $cnt[$L] >= 0.4) $rim = $L; else break;
            }
            if ($rim < 1) continue;
            if ($rim >= $cap) continue; // the "rim" never ends -> solid dark box, not a ring

            // peel the rim -> opening
            $limit = ($rim + 0.5) * ($rim + 0.5);
            $inner = array_fill(0, $cw * $ch, 0);
            for ($i = 0, $m = $cw * $ch; $i < $m; $i++) {
                if ($cm[$i] && $d2[$i] > $limit) $inner[$i] = 1;
            }

            // remove spurs left where strings joined the ring
            $inner = $this->openDisk($inner, $cw, $ch, max(2, min(6, $rim)));

            // keep the biggest connected piece
            [$iLabels, $iComps] = $this->labelComponents($inner, $cw, $ch, false);
            if (empty($iComps)) continue;
            $bestId = null; $bestArea = 0;
            foreach ($iComps as $iid => $ic) {
                if ($ic['area'] > $bestArea) { $bestArea = $ic['area']; $bestId = $iid; }
            }
            if ($bestId === null) continue;

            $open = array_fill(0, $cw * $ch, 0);
            $nonDark = 0;
            for ($i = 0, $m = $cw * $ch; $i < $m; $i++) {
                if ($iLabels[$i] === $bestId) {
                    $open[$i] = 1;
                    if (!$cd[$i]) $nonDark++;
                }
            }

            // sanity checks: a real opening is a big part of the blob and is not just more dark material
            if ($bestArea < 0.20 * $c['area']) continue;
            if ($nonDark < 0.30 * $bestArea) continue;

            // contrast: the opening must be clearly lighter than the rim around it
            $rimSum = 0; $rimN = 0; $inSum = 0; $inN = 0;
            for ($y = 0; $y < $ch; $y++) {
                $gi = ($y0 + $y) * $w + $x0;
                $ci = $y * $cw;
                for ($x = 0; $x < $cw; $x++, $gi++, $ci++) {
                    if ($open[$ci]) { $inSum += ord($lumStr[$gi]); $inN++; }
                    elseif ($cm[$ci] && $d2[$ci] <= $limit) { $rimSum += ord($lumStr[$gi]); $rimN++; }
                }
            }
            if ($inN > 0 && $rimN > 0 && ($inSum / $inN - $rimSum / $rimN) < 25) continue;

            // grid rule: several rectangular light cells inside = a grid / collage of separate slots, not one opening
            $cellMask = array_fill(0, $cw * $ch, 0);
            for ($i = 0, $m = $cw * $ch; $i < $m; $i++) {
                if ($open[$i] && !$cd[$i]) $cellMask[$i] = 1;
            }
            [, $cellComps] = $this->labelComponents($cellMask, $cw, $ch, false);
            $cells = 0;
            foreach ($cellComps as $cc) {
                $cellW = $cc['maxX'] - $cc['minX'] + 1;
                $cellH = $cc['maxY'] - $cc['minY'] + 1;
                if ($cc['area'] >= 0.008 * $bestArea && $cc['area'] / ($cellW * $cellH) >= 0.85 && min($cellW, $cellH) >= 6) $cells++;
            }
            unset($cellMask);
            if ($cells >= 3) continue;

            $slot = $this->slotFromMask($open, $cw, $ch, $x0, $y0, $scale, 10);
            if (!$slot) continue;
            if ($slot['width'] < 24 || $slot['height'] < 24) continue;

            $slots[] = $slot;
        }

        // Only trust this strategy when it explains (almost) every dark blob it saw. If many blobs were rejected
        // (dark boxes, bezel frames on a wall, grids...) the template is not a "photo inside a dark rim" design,
        // so the other strategies get a chance.
        $this->ringCandidates = $candidates;
        if (empty($slots) || count($slots) < 0.75 * $candidates) return [];

        return $slots;
    }

    /**
     * Line-art rings: dark thin outlines (even dashed / dotted) close into rings; the light area enclosed by each
     * ring is an opening. Only mostly round / compact openings that do not touch the image edge are kept, and
     * an opening that sits inside another one's box is dropped (rings inside rings).
     */
    private function detectLineArtRings(string $lumStr, int $w, int $h, float $scale, array $options): array
    {
        $r0 = max(2, (int) round(0.005 * max($w, $h)));
        $configs = isset($options['lineart_threshold'])
            ? [[(int) $options['lineart_threshold'], $r0]]
            : [[190, $r0], [215, $r0], [235, $r0 + 2]];
        $best = [];
        foreach ($configs as [$thr, $rad]) {
            $found = $this->detectLineArtRingsAt($lumStr, $w, $h, $scale, $thr, $rad);
            if (count($found) > count($best)) $best = $found;
        }
        return $best;
    }

    private function detectLineArtRingsAt(string $lumStr, int $w, int $h, float $scale, int $thr, int $r): array
    {
        $n = $w * $h;
        if ($w < 60 || $h < 60) return [];
        $this->raiseMemoryLimit();

        $dark = array_fill(0, $n, 0);
        $cnt = 0;
        for ($i = 0; $i < $n; $i++) if (ord($lumStr[$i]) < $thr) { $dark[$i] = 1; $cnt++; }
        if ($cnt < 0.01 * $n || $cnt > 0.45 * $n) return [];

        for ($k = 0; $k < $r; $k++) $dark = $this->morph3($dark, $w, $h, true);
        for ($k = 0; $k < $r; $k++) $dark = $this->morph3($dark, $w, $h, false);

        $light = array_fill(0, $n, 0);
        for ($i = 0; $i < $n; $i++) $light[$i] = $dark[$i] ? 0 : 1;
        [$labels, $comps] = $this->labelComponents($light, $w, $h, false);
        unset($light);

        $minSide = max(14, (int) round(0.04 * max($w, $h)));
        $cands = [];
        foreach ($comps as $id => $c) {
            $bw = $c['maxX'] - $c['minX'] + 1;
            $bh = $c['maxY'] - $c['minY'] + 1;
            if ($c['minX'] <= 0 || $c['minY'] <= 0 || $c['maxX'] >= $w - 1 || $c['maxY'] >= $h - 1) continue;
            if ($c['area'] < 0.0015 * $n || $c['area'] > 0.5 * $n) continue;
            if ($bw < $minSide || $bh < $minSide) continue;
            $fill = $c['area'] / ($bw * $bh);
            if ($fill < 0.45) continue;
            $ratio = $bw / $bh;
            if ($ratio < 0.7 || $ratio > 1.43) continue;
            $cands[$id] = $c + ['bw' => $bw, 'bh' => $bh];
        }

        // drop openings lying inside another candidate's box (ring inside ring, diamond inside a circle...)
        $keep = [];
        foreach ($cands as $id => $c) {
            $inside = false;
            foreach ($cands as $id2 => $d) {
                if ($id2 === $id || $d['area'] <= $c['area']) continue;
                if ($c['minX'] >= $d['minX'] - 2 && $c['maxX'] <= $d['maxX'] + 2 && $c['minY'] >= $d['minY'] - 2 && $c['maxY'] <= $d['maxY'] + 2) { $inside = true; break; }
            }
            if (!$inside) $keep[$id] = $c;
        }

        $slots = [];
        $round = 0;
        foreach ($keep as $id => $c) {
            $pad = 3;
            $x0 = max(0, $c['minX'] - $pad); $y0 = max(0, $c['minY'] - $pad);
            $x1 = min($w - 1, $c['maxX'] + $pad); $y1 = min($h - 1, $c['maxY'] + $pad);
            $cw = $x1 - $x0 + 1; $ch = $y1 - $y0 + 1;
            $m = array_fill(0, $cw * $ch, 0);
            for ($y = 0; $y < $ch; $y++) {
                $gi = ($y0 + $y) * $w + $x0; $ci = $y * $cw;
                for ($x = 0; $x < $cw; $x++, $gi++, $ci++) if ($labels[$gi] === $id) $m[$ci] = 1;
            }
            $slot = $this->slotFromMask($m, $cw, $ch, $x0, $y0, $scale, 10);
            if (!$slot || $slot['width'] < 24 || $slot['height'] < 24) continue;
            if (in_array($slot['shape'], ['circle', 'ellipse'], true)) {
                $round++;
            } elseif ($slot['shape'] !== 'rectangle') {
                // gaps between neighbouring badges are concave / star shaped; real openings are compact
                // (measured on the original mask, before any outline clean-up)
                $cont = $this->traceContour($m, $cw, $ch);
                $ha = count($cont) >= 3 ? $this->convexHullArea($cont) : 0.0;
                $sol = $ha > 0 ? $c['area'] / $ha : 0.0;
                if ($sol < 0.9) continue;
                if ($sol >= 0.93) $round++;
            }
            $slots[] = $slot;
        }
        if (count($slots) < 4 || $round < 0.6 * count($slots)) return [];
        return $slots;
    }

    /* ======================================================================
     *  Mask -> slot (bbox, shape, outline polygon)
     * ==================================================================== */

    /**
     * Converts a binary mask (crop of the working image) into a slot array.
     *
     * @param array $m      flat 0/1 mask, $mw x $mh
     * @param int   $offX   x of the mask's (0,0) inside the working image
     * @param int   $offY   y of the mask's (0,0) inside the working image
     * @param float $scale  working px per original px
     */
    private function slotFromMask(array $m, int $mw, int $mh, int $offX, int $offY, float $scale, int $minSide = 4, bool $strict = false): ?array
    {
        // bbox + area
        $minX = $mw; $minY = $mh; $maxX = -1; $maxY = -1; $area = 0;
        for ($y = 0; $y < $mh; $y++) {
            $row = $y * $mw;
            for ($x = 0; $x < $mw; $x++) {
                if ($m[$row + $x]) {
                    $area++;
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }
        if ($area === 0) return null;

        $bw = $maxX - $minX + 1;
        $bh = $maxY - $minY + 1;
        if ($bw < $minSide || $bh < $minSide) return null;

        // A clean rectangle with a few spikes / bumps on its edges (decoration, glow): measure it without them
        $tb = $this->trimmedRectangle($m, $mw, $minX, $minY, $bw, $bh, $area);
        if ($tb !== null && $tb[2] >= $minSide && $tb[3] >= $minSide) {
            return [
                'x'        => (int) round(($offX + $tb[0]) / $scale),
                'y'        => (int) round(($offY + $tb[1]) / $scale),
                'width'    => max(1, (int) round($tb[2] / $scale)),
                'height'   => max(1, (int) round($tb[3] / $scale)),
                'shape'    => 'rectangle',
                'rotation' => 0,
            ];
        }

        $shape = $this->classifyMask($m, $mw, $minX, $minY, $bw, $bh, $area);

        $slot = [
            'x'        => (int) round(($offX + $minX) / $scale),
            'y'        => (int) round(($offY + $minY) / $scale),
            'width'    => max(1, (int) round($bw / $scale)),
            'height'   => max(1, (int) round($bh / $scale)),
            'shape'    => $shape,
            'rotation' => 0,
        ];

        if ($shape === 'heart' || $shape === 'polygon') {
            $contour = $this->traceContour($m, $mw, $mh);
            if ($strict && count($contour) >= 3) {
                // noisy / leaky blobs (texture, shadows) are not trustworthy outlines: let the caller fall back
                $hull = $this->convexHullArea($contour);
                if ($hull > 0 && $area / $hull < 0.82) return null;
            }
            if (count($contour) >= 3) {
                $eps = max(0.7, 0.004 * max($bw, $bh));
                // plain polygons (not hearts): a slightly larger tolerance removes pixel jaggies and rim ripples, then
                // clipped corners are restored to their sharp tips
                if ($shape === 'polygon') {
                    // pixel jaggies and rim ripples are smoothed away along the curves and straight runs, while the
                    // corners are pinned so tips stay sharp and curves stay curved
                    $contour = $this->smoothContourKeepingCorners($contour, (float) max($bw, $bh));
                    $eps = max(0.5, 0.0025 * max($bw, $bh));
                }
                $poly = $this->simplifyClosed($contour, $eps);
                if ($shape === 'polygon' && count($poly) >= 4) $poly = $this->sharpenCorners($poly, (float) max($bw, $bh));
                $guard = 0;
                while (count($poly) > 160 && $guard++ < 6) {
                    $eps *= 1.4;
                    $poly = $this->simplifyClosed($contour, $eps);
                }
                if (count($poly) >= 3) {
                    // straight-sided shapes (diamonds, kites, tilted photos, cut-corner frames): clean straight edges and
                    // sharp corners instead of a wobbly pixel outline
                    $reg = $shape === 'polygon' ? $this->regularizePolygon($contour, $m, $mw, $minX, $minY, $bw, $bh, $area) : null;
                    if ($reg !== null) {
                        $poly = $reg;
                        $slot['shape'] = 'polygon';
                    }
                    if ($shape === 'polygon') {
                        // restored tips / straightened edges can reach outside the mask's own box: take the polygon's box
                        $nx0 = min(array_column($poly, 0)); $nx1 = max(array_column($poly, 0));
                        $ny0 = min(array_column($poly, 1)); $ny1 = max(array_column($poly, 1));
                        if ($nx0 < $minX || $nx1 > $maxX || $ny0 < $minY || $ny1 > $maxY || $reg !== null) {
                            $slot['x']      = (int) round(($offX + $nx0) / $scale);
                            $slot['y']      = (int) round(($offY + $ny0) / $scale);
                            $slot['width']  = max(1, (int) round(($nx1 - $nx0 + 1) / $scale));
                            $slot['height'] = max(1, (int) round(($ny1 - $ny0 + 1) / $scale));
                            $minX = $nx0; $maxX = $nx1; $minY = $ny0; $maxY = $ny1;
                        }
                    }
                    $dx = max(1, $maxX - $minX);
                    $dy = max(1, $maxY - $minY);
                    $points = [];
                    foreach ($poly as [$px, $py]) {
                        $points[] = [
                            round(min(1.0, max(0.0, ($px - $minX) / $dx)), 4),
                            round(min(1.0, max(0.0, ($py - $minY) / $dy)), 4),
                        ];
                    }
                    $slot['points'] = $points;
                } else {
                    $slot['shape'] = 'rectangle';
                }
            } else {
                $slot['shape'] = 'rectangle';
            }
        }

        return $slot;
    }

    /**
     * Same as slotFromMask but starting from a list of pixel indexes of the working image
     * (used by the alpha / white / divider / edge strategies).
     */
    private function slotFromPixels(array $pixels, int $w, int $minX, int $minY, int $maxX, int $maxY, float $scale): ?array
    {
        $bw = $maxX - $minX + 1;
        $bh = $maxY - $minY + 1;
        $m = array_fill(0, $bw * $bh, 0);
        foreach ($pixels as $p) {
            $x = $p % $w;
            $y = intdiv($p, $w);
            $m[($y - $minY) * $bw + ($x - $minX)] = 1;
        }
        return $this->slotFromMask($m, $bw, $bh, $minX, $minY, $scale, 2, true);
    }

    /**
     * Names the shape of a mask by comparing it with ideal shapes.
     */
    private function classifyMask(array $m, int $mw, int $bx, int $by, int $bw, int $bh, int $area): string
    {
        $cx = $bx + $bw / 2;
        $cy = $by + $bh / 2;
        $rx = $bw / 2;
        $ry = $bh / 2;
        $rr = 0.15 * min($bw, $bh);

        $interE = 0; $unionE = 0;
        $interR = 0; $unionR = 0;

        for ($y = $by; $y < $by + $bh; $y++) {
            $py = $y + 0.5;
            $row = $y * $mw;
            for ($x = $bx; $x < $bx + $bw; $x++) {
                $px = $x + 0.5;
                $inM = $m[$row + $x] === 1;

                $ex = ($px - $cx) / $rx;
                $ey = ($py - $cy) / $ry;
                $inE = ($ex * $ex + $ey * $ey) <= 1.0;

                $dxr = abs($px - $cx) - ($rx - $rr);
                $dyr = abs($py - $cy) - ($ry - $rr);
                $inR = ($dxr <= 0 || $dyr <= 0) ? true : (($dxr * $dxr + $dyr * $dyr) <= $rr * $rr);

                if ($inM && $inE) $interE++;
                if ($inM || $inE)  $unionE++;
                if ($inM && $inR) $interR++;
                if ($inM || $inR)  $unionR++;
            }
        }

        $fill = $area / max(1, $bw * $bh);
        $iouE = $unionE > 0 ? $interE / $unionE : 0;
        $iouR = $unionR > 0 ? $interR / $unionR : 0;

        if ($fill >= 0.965 && $fill >= $iouR - 0.01) return 'rectangle';
        if ($iouR >= 0.955) return 'rounded_rectangle';
        if ($iouE >= 0.92) {
            $rs = $this->radialWobble($m, $mw, $bx, $by, $bw, $bh);
            if ($rs <= 0.02) return abs($bw / $bh - 1) <= 0.12 ? 'circle' : 'ellipse';
        }
        if ($this->looksLikeHeart($m, $mw, $bx, $by, $bw, $bh)) return 'heart';

        return 'polygon';
    }

    /**
     * How much the distance from the centre to the outline wobbles (relative to the fitted ellipse): ~0.005-0.015 for a
     * round shape, clearly more for polygons such as octagons (straight sides, corners).
     */
    private function radialWobble(array $m, int $mw, int $bx, int $by, int $bw, int $bh): float
    {
        $cx = $bx + $bw / 2; $cy = $by + $bh / 2; $rx = $bw / 2; $ry = $bh / 2;
        $vals = [];
        for ($y = $by; $y < $by + $bh; $y++) {
            for ($x = $bx; $x < $bx + $bw; $x++) {
                if ($m[$y * $mw + $x] !== 1) continue;
                $edge = ($x === $bx || $x === $bx + $bw - 1 || $y === $by || $y === $by + $bh - 1
                    || $m[$y * $mw + $x - 1] !== 1 || $m[$y * $mw + $x + 1] !== 1
                    || $m[($y - 1) * $mw + $x] !== 1 || $m[($y + 1) * $mw + $x] !== 1);
                if (!$edge) continue;
                $ex = ($x + 0.5 - $cx) / $rx; $ey = ($y + 0.5 - $cy) / $ry;
                $vals[] = sqrt($ex * $ex + $ey * $ey);
            }
        }
        $k = count($vals);
        if ($k < 12) return 0.0;
        $mean = array_sum($vals) / $k;
        $var = 0.0;
        foreach ($vals as $q) $var += ($q - $mean) ** 2;
        return sqrt($var / $k) / max(1e-6, $mean);
    }

    /**
     * Robust circle fit of a mask's outline. Returns [cx, cy, r] (mask pixels) when at least ~60% of the boundary lies
     * within 8% of the fitted radius, otherwise null.
     */
    private function robustCircle(array $g, int $cw, int $ch): ?array
    {
        $pts = [];
        for ($y = 0; $y < $ch; $y++) {
            for ($x = 0; $x < $cw; $x++) {
                if ($g[$y * $cw + $x] !== 1) continue;
                if ($x === 0 || $y === 0 || $x === $cw - 1 || $y === $ch - 1
                    || $g[$y * $cw + $x - 1] !== 1 || $g[$y * $cw + $x + 1] !== 1
                    || $g[($y - 1) * $cw + $x] !== 1 || $g[($y + 1) * $cw + $x] !== 1) $pts[] = [$x + 0.5, $y + 0.5];
            }
        }
        $n = count($pts);
        if ($n < 30) return null;

        $cx = array_sum(array_column($pts, 0)) / $n;
        $cy = array_sum(array_column($pts, 1)) / $n;
        $r = 0.0;
        $sel = $pts;
        for ($it = 0; $it < 6; $it++) {
            $rad = [];
            foreach ($pts as $p) $rad[] = sqrt(($p[0] - $cx) ** 2 + ($p[1] - $cy) ** 2);
            $sorted = $rad; sort($sorted);
            $r = $sorted[intdiv($n, 2)];
            if ($r < 8) return null;
            $sel = [];
            foreach ($pts as $k => $p) if (abs($rad[$k] - $r) <= 0.08 * $r) $sel[] = $p;
            if (count($sel) < 12) return null;
            // algebraic least-squares circle through the inliers: x^2+y^2 + Dx + Ey + F = 0
            $sx = $sy = $sxx = $syy = $sxy = $sz = $sxz = $syz = 0.0;
            foreach ($sel as [$px, $py]) {
                $z = $px * $px + $py * $py;
                $sx += $px; $sy += $py; $sxx += $px * $px; $syy += $py * $py; $sxy += $px * $py;
                $sz += $z; $sxz += $px * $z; $syz += $py * $z;
            }
            $m = count($sel);
            // normal equations for [D, E, F]
            $A = [[$sxx, $sxy, $sx], [$sxy, $syy, $sy], [$sx, $sy, (float) $m]];
            $B = [-$sxz, -$syz, -$sz];
            $det = fn (array $M) => $M[0][0] * ($M[1][1] * $M[2][2] - $M[1][2] * $M[2][1])
                - $M[0][1] * ($M[1][0] * $M[2][2] - $M[1][2] * $M[2][0])
                + $M[0][2] * ($M[1][0] * $M[2][1] - $M[1][1] * $M[2][0]);
            $d0 = $det($A);
            if (abs($d0) < 1e-9) return null;
            $sol = [];
            for ($c = 0; $c < 3; $c++) {
                $M = $A;
                for ($rw = 0; $rw < 3; $rw++) $M[$rw][$c] = $B[$rw];
                $sol[$c] = $det($M) / $d0;
            }
            $ncx = -$sol[0] / 2; $ncy = -$sol[1] / 2;
            $rr2 = $ncx * $ncx + $ncy * $ncy - $sol[2];
            if ($rr2 <= 0) return null;
            $cx = $ncx; $cy = $ncy; $r = sqrt($rr2);
        }
        $in = 0;
        foreach ($pts as $p) if (abs(sqrt(($p[0] - $cx) ** 2 + ($p[1] - $cy) ** 2) - $r) <= 0.08 * $r) $in++;
        if ($in < 0.6 * $n) return null;
        return [$cx, $cy, $r];
    }

    /**
     * If the mask is a rectangle once thin spikes / bumps on its edges are ignored, returns [x, y, w, h] of that
     * rectangle (mask coordinates), otherwise null. Only returns something when spikes really changed the box.
     */
    private function trimmedRectangle(array $m, int $mw, int $bx, int $by, int $bw, int $bh, int $area): ?array
    {
        if ($bw < 12 || $bh < 12) return null;
        $rows = array_fill(0, $bh, 0);
        $cols = array_fill(0, $bw, 0);
        for ($y = 0; $y < $bh; $y++) {
            $base = ($by + $y) * $mw + $bx;
            for ($x = 0; $x < $bw; $x++) {
                if ($m[$base + $x]) { $rows[$y]++; $cols[$x]++; }
            }
        }
        $rmax = max($rows); $cmax = max($cols);
        $y0 = 0; while ($y0 < $bh && $rows[$y0] < 0.5 * $rmax) $y0++;
        $y1 = $bh - 1; while ($y1 > $y0 && $rows[$y1] < 0.5 * $rmax) $y1--;
        $x0 = 0; while ($x0 < $bw && $cols[$x0] < 0.5 * $cmax) $x0++;
        $x1 = $bw - 1; while ($x1 > $x0 && $cols[$x1] < 0.5 * $cmax) $x1--;
        $tw = $x1 - $x0 + 1; $th = $y1 - $y0 + 1;
        if ($tw < 8 || $th < 8) return null;
        if ($tw === $bw && $th === $bh) return null;           // no spikes: the normal classification handles it
        if ($tw * $th > 0.995 * $bw * $bh && ($bw - $tw) + ($bh - $th) < 2) return null;
        $in = 0;
        for ($y = $y0; $y <= $y1; $y++) {
            $base = ($by + $y) * $mw + $bx;
            for ($x = $x0; $x <= $x1; $x++) if ($m[$base + $x]) $in++;
        }
        if ($in / ($tw * $th) < 0.965) return null;
        // what was cut away must be small compared to the rectangle (a spike, not a second shape)
        if ($area - $in > 0.04 * $tw * $th) return null;
        return [$bx + $x0, $by + $y0, $tw, $th];
    }

    /**
     * Straight-sided outline of a mask: Douglas-Peucker with a generous tolerance, accepted only when the result has few
     * vertices and matches the mask closely (so curved shapes keep their detailed outline). Clipped acute corners
     * (a short chamfer between two long edges, left by the smoothing) are extended to their sharp tip.
     * Returns the polygon in mask pixel coordinates or null.
     */
    private function regularizePolygon(array $contour, array $m, int $mw, int $bx, int $by, int $bw, int $bh, int $area): ?array
    {
        $r = $this->regularizePolygonMode($contour, $m, $mw, $bx, $by, $bw, $bh, false);
        if ($r !== null) return $r;
        // only nearly convex shapes: puzzle pieces, hearts and other curved shapes keep their detailed outline
        $hullA = $this->convexHullArea($contour);
        if ($hullA <= 0 || $area / $hullA < 0.91) return null;
        // frames whose edges are covered by decoration (flowers, ribbons): the convex hull is the plain polygon
        return $this->regularizePolygonMode($contour, $m, $mw, $bx, $by, $bw, $bh, true);
    }

    private function regularizePolygonMode(array $contour, array $m, int $mw, int $bx, int $by, int $bw, int $bh, bool $useHull): ?array
    {
        $D = max($bw, $bh);
        if ($D < 20 || count($contour) < 8) return null;

        if ($useHull) {
            $hp = $this->convexHullPoints($contour);
            if (count($hp) < 3) return null;
            $poly = [];
            foreach ([0.03, 0.045, 0.06, 0.08] as $f) {
                $poly = $this->simplifyClosed($hp, max(2.0, $f * $D));
                if (count($poly) <= 6) break;
            }
            if (count($poly) > 7) return null;
        } else {
            $poly = $this->simplifyClosed($contour, max(1.5, 0.022 * $D));
        }
        $n = count($poly);
        if ($n < 3 || $n > 12) return null;

        $poly = $this->sharpenCorners($poly, $D);
        if (count($poly) < 3 || count($poly) > ($useHull ? 8 : 12)) return null;

        {
            // most of the outline must really lie on the straight edges (curved shapes have long arcs far from any chord)
            $tol = max(0.9, ($useHull ? 0.012 : 0.006) * $D);
            $np2 = count($poly);
            $near = 0;
            foreach ($contour as [$cx2, $cy2]) {
                for ($i = 0; $i < $np2; $i++) {
                    $p1 = $poly[$i]; $p2 = $poly[($i + 1) % $np2];
                    $ex = $p2[0] - $p1[0]; $ey = $p2[1] - $p1[1];
                    $l2 = $ex * $ex + $ey * $ey;
                    $tt = $l2 > 0 ? max(0.0, min(1.0, (($cx2 - $p1[0]) * $ex + ($cy2 - $p1[1]) * $ey) / $l2)) : 0.0;
                    if (hypot($cx2 - ($p1[0] + $tt * $ex), $cy2 - ($p1[1] + $tt * $ey)) <= $tol) { $near++; break; }
                }
            }
            if ($near < ($useHull ? 0.8 : 0.9) * count($contour)) return null;
        }

        // it must match the mask: sampled symmetric difference (inside-test of the polygon vs the mask)
        $step = max(1, (int) floor($D / 70));
        $diff = 0; $tot = 0;
        $np = count($poly);
        for ($y = $by; $y < $by + $bh; $y += $step) {
            for ($x = $bx; $x < $bx + $bw; $x += $step) {
                $px = $x; $py = $y;
                $in = false;
                for ($i = 0, $j = $np - 1; $i < $np; $j = $i++) {
                    if ((($poly[$i][1] > $py) !== ($poly[$j][1] > $py))
                        && ($px < ($poly[$j][0] - $poly[$i][0]) * ($py - $poly[$i][1]) / ($poly[$j][1] - $poly[$i][1]) + $poly[$i][0])) $in = !$in;
                }
                $mm = $m[$y * $mw + $x] === 1;
                if ($in || $mm) $tot++;
                if ($in !== $mm) $diff++;
            }
        }
        if ($tot === 0 || $diff / $tot > ($useHull ? 0.14 : 0.045) + 2.5 / $D) return null;
        return $poly;
    }

    /**
     * Restores sharp tips: a short edge (a clipped corner left by smoothing) between two long edges is replaced by the
     * point where the two long edges meet, when that point is close by.
     */
    private function sharpenCorners(array $poly, float $D): array
    {
        for ($pass = 0; $pass < 3; $pass++) {
            $changed = false;
            $n = count($poly);
            if ($n < 4) break;
            for ($i = 0; $i < $n; $i++) {
                $a = $poly[($i + $n - 1) % $n]; $b = $poly[$i]; $c = $poly[($i + 1) % $n]; $d = $poly[($i + 2) % $n];
                $lb = hypot($c[0] - $b[0], $c[1] - $b[1]);
                $la = hypot($b[0] - $a[0], $b[1] - $a[1]);
                $lc = hypot($d[0] - $c[0], $d[1] - $c[1]);
                if ($lb < 1.0 || $lb > 0.2 * $D || $la < 2 * $lb || $lc < 2 * $lb) continue;
                $t1 = ($b[0] - $a[0]) * ($c[1] - $b[1]) - ($b[1] - $a[1]) * ($c[0] - $b[0]);
                $t2 = ($c[0] - $b[0]) * ($d[1] - $c[1]) - ($c[1] - $b[1]) * ($d[0] - $c[0]);
                if ($t1 * $t2 <= 0) continue;                       // not a convex clipped corner
                $r1x = $b[0] - $a[0]; $r1y = $b[1] - $a[1];
                $r2x = $d[0] - $c[0]; $r2y = $d[1] - $c[1];
                $den = $r1x * $r2y - $r1y * $r2x;
                if (abs($den) < 1e-6) continue;
                $u = (($c[0] - $a[0]) * $r2y - ($c[1] - $a[1]) * $r2x) / $den;
                $X = [$a[0] + $u * $r1x, $a[1] + $u * $r1y];
                $mid = [($b[0] + $c[0]) / 2, ($b[1] + $c[1]) / 2];
                if (hypot($X[0] - $mid[0], $X[1] - $mid[1]) > min(6.0 * $lb + 3, 0.2 * $D)) continue;
                // the tip must lie beyond the chamfer (outside), not inside the shape
                $side = ($c[0] - $b[0]) * ($X[1] - $b[1]) - ($c[1] - $b[1]) * ($X[0] - $b[0]);
                if ($side * $t1 >= 0) continue;
                $new = [];
                for ($k = 0; $k < $n; $k++) {
                    if ($k === $i) $new[] = $X;
                    elseif ($k !== ($i + 1) % $n) $new[] = $poly[$k];
                }
                $poly = $new;
                $changed = true;
                break;
            }
            if (!$changed) break;
        }
        return $poly;
    }

    /**
     * Low-pass filter of a closed outline that keeps the corners: the outline is cut at its sharp corners and every run
     * between two corners is smoothed with a moving average whose ends stay pinned. Curves stay curves, straight runs
     * get straight, corners (tips) stay where they are.
     */
    private function smoothContourKeepingCorners(array $pts, float $D): array
    {
        $n = count($pts);
        if ($n < 24) return $pts;
        $k = max(3, (int) round(0.03 * $D));
        if ($k * 2 >= $n) return $pts;
        $r = max(2, (int) round(0.012 * $D));

        // turning angle at every point, measured over +-k points
        $ang = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            $a = $pts[($i - $k + $n) % $n]; $b = $pts[$i]; $c = $pts[($i + $k) % $n];
            $v1x = $b[0] - $a[0]; $v1y = $b[1] - $a[1]; $v2x = $c[0] - $b[0]; $v2y = $c[1] - $b[1];
            $l1 = hypot($v1x, $v1y); $l2 = hypot($v2x, $v2y);
            if ($l1 < 1e-6 || $l2 < 1e-6) continue;
            $cos = max(-1.0, min(1.0, ($v1x * $v2x + $v1y * $v2y) / ($l1 * $l2)));
            $ang[$i] = rad2deg(acos($cos));
        }
        $corners = [];
        for ($i = 0; $i < $n; $i++) {
            if ($ang[$i] < 38) continue;
            $isMax = true;
            for ($d = -$k; $d <= $k && $isMax; $d++) {
                if ($d === 0) continue;
                $j = ($i + $d + $n) % $n;
                if ($ang[$j] > $ang[$i] || ($ang[$j] === $ang[$i] && $d < 0)) $isMax = false;
            }
            if ($isMax) $corners[] = $i;
        }

        $smoothChain = static function (array $chain, int $r): array {
            $len = count($chain);
            if ($len < 5) return $chain;
            $out = $chain;
            for ($t = 1; $t < $len - 1; $t++) {
                $w = min($r, $t, $len - 1 - $t);
                $sx = 0.0; $sy = 0.0;
                for ($q = $t - $w; $q <= $t + $w; $q++) { $sx += $chain[$q][0]; $sy += $chain[$q][1]; }
                $cnt = 2 * $w + 1;
                $out[$t] = [$sx / $cnt, $sy / $cnt];
            }
            return $out;
        };

        if (count($corners) === 0) {
            // no corner at all (a closed smooth curve): circular moving average
            $out = [];
            for ($i = 0; $i < $n; $i++) {
                $sx = 0.0; $sy = 0.0;
                for ($q = -$r; $q <= $r; $q++) { $p = $pts[($i + $q + $n) % $n]; $sx += $p[0]; $sy += $p[1]; }
                $out[] = [$sx / (2 * $r + 1), $sy / (2 * $r + 1)];
            }
            return $out;
        }

        $out = [];
        $m = count($corners);
        for ($ci = 0; $ci < $m; $ci++) {
            $from = $corners[$ci];
            $to = $corners[($ci + 1) % $m];
            $chain = [];
            $i = $from;
            while (true) {
                $chain[] = $pts[$i];
                if ($i === $to && count($chain) > 1) break;
                $i = ($i + 1) % $n;
                if (count($chain) > $n + 1) break;
            }
            if ($m === 1) { /* one corner: the chain runs all the way round */ }
            $sm = $smoothChain($chain, $r);
            array_pop($sm);                                   // the next chain starts with this corner
            foreach ($sm as $p) $out[] = $p;
        }
        return count($out) >= 3 ? $out : $pts;
    }

    /**
     * Heart = a notch in the middle of the top edge, two lobes of similar height, narrow bottom tip.
     */
    private function looksLikeHeart(array $m, int $mw, int $bx, int $by, int $bw, int $bh): bool
    {
        if ($bw < 12 || $bh < 12) return false;

        $top = [];
        for ($x = 0; $x < $bw; $x++) {
            $top[$x] = $bh;
            for ($y = 0; $y < $bh; $y++) {
                if ($m[($by + $y) * $mw + $bx + $x]) { $top[$x] = $y; break; }
            }
        }

        $notch = 0;
        for ($x = (int) floor(0.42 * $bw); $x <= (int) ceil(0.58 * $bw) && $x < $bw; $x++) {
            $notch = max($notch, $top[$x]);
        }

        $lobeL = $bh;
        for ($x = (int) floor(0.10 * $bw); $x <= (int) floor(0.40 * $bw); $x++) $lobeL = min($lobeL, $top[$x]);
        $lobeR = $bh;
        for ($x = (int) ceil(0.60 * $bw); $x <= (int) ceil(0.90 * $bw) && $x < $bw; $x++) $lobeR = min($lobeR, $top[$x]);

        if (($notch - max($lobeL, $lobeR)) < 0.035 * $bh) return false;
        if (abs($lobeL - $lobeR) > 0.15 * $bh) return false;

        $row = min($bh - 1, (int) floor(0.90 * $bh));
        $width = 0;
        for ($x = 0; $x < $bw; $x++) {
            if ($m[($by + $row) * $mw + $bx + $x]) $width++;
        }
        if ($width > 0.45 * $bw) return false;

        // a heart is (nearly) left/right symmetric; a puzzle piece or any other lopsided shape is not
        $inter = 0; $union = 0;
        for ($y = 0; $y < $bh; $y++) {
            $base = ($by + $y) * $mw + $bx;
            for ($x = 0; $x < $bw; $x++) {
                $a = $m[$base + $x] === 1;
                $b = $m[$base + $bw - 1 - $x] === 1;
                if ($a && $b) $inter++;
                if ($a || $b) $union++;
            }
        }
        return $union > 0 && $inter / $union >= 0.86;
    }

    /* ======================================================================
     *  Contour tracing / polygon simplification
     * ==================================================================== */

    /**
     * Moore-neighbour outer contour of the first blob found in raster order.
     * Returns pixel coordinates [[x,y],...] clockwise.
     */
    private function traceContour(array $m, int $w, int $h): array
    {
        $total = $w * $h;
        $start = null;
        for ($i = 0; $i < $total; $i++) {
            if ($m[$i]) { $start = $i; break; }
        }
        if ($start === null) return [];

        $sx = $start % $w;
        $sy = intdiv($start, $w);

        $dx = [-1, -1, 0, 1, 1, 1, 0, -1];
        $dy = [0, -1, -1, -1, 0, 1, 1, 1];
        $dirIndex = [];
        foreach ($dx as $i => $v) $dirIndex[$v . ',' . $dy[$i]] = $i;

        $pts = [[$sx, $sy]];
        $cx = $sx; $cy = $sy;
        $bd = 0; // direction of the "backtrack" (background) pixel; west of the start pixel is background
        $maxSteps = $total * 4;

        for ($step = 0; $step < $maxSteps; $step++) {
            $found = false;
            for ($k = 1; $k <= 8; $k++) {
                $d = ($bd + $k) % 8;
                $nx = $cx + $dx[$d];
                $ny = $cy + $dy[$d];
                if ($nx >= 0 && $ny >= 0 && $nx < $w && $ny < $h && $m[$ny * $w + $nx]) {
                    $pd = ($d + 7) % 8;
                    $rx = ($cx + $dx[$pd]) - $nx;
                    $ry = ($cy + $dy[$pd]) - $ny;
                    $bd = $dirIndex[$rx . ',' . $ry] ?? 0;
                    $cx = $nx; $cy = $ny;
                    $found = true;
                    break;
                }
            }
            if (!$found) break;
            if ($cx === $sx && $cy === $sy) break;
            $pts[] = [$cx, $cy];
        }

        return $pts;
    }

    /** Area of the convex hull of a point list (monotone chain). */
    /** Convex hull (monotone chain) of a point list; returns the hull vertices. */
    private function convexHullPoints(array $pts): array
    {
        usort($pts, static fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);
        $n = count($pts);
        if ($n < 3) return $pts;
        $cross = static fn ($o, $a, $b) => ($a[0] - $o[0]) * ($b[1] - $o[1]) - ($a[1] - $o[1]) * ($b[0] - $o[0]);
        $lower = [];
        foreach ($pts as $p) {
            while (count($lower) >= 2 && $cross($lower[count($lower) - 2], $lower[count($lower) - 1], $p) <= 0) array_pop($lower);
            $lower[] = $p;
        }
        $upper = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $p = $pts[$i];
            while (count($upper) >= 2 && $cross($upper[count($upper) - 2], $upper[count($upper) - 1], $p) <= 0) array_pop($upper);
            $upper[] = $p;
        }
        array_pop($lower); array_pop($upper);
        return array_merge($lower, $upper);
    }

    private function convexHullArea(array $pts): float
    {
        $n = count($pts);
        if ($n < 3) return 0.0;
        usort($pts, fn ($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $cross = fn ($o, $a, $b) => ($a[0] - $o[0]) * ($b[1] - $o[1]) - ($a[1] - $o[1]) * ($b[0] - $o[0]);

        $lower = [];
        foreach ($pts as $p) {
            while (count($lower) >= 2 && $cross($lower[count($lower) - 2], $lower[count($lower) - 1], $p) <= 0) array_pop($lower);
            $lower[] = $p;
        }
        $upper = [];
        foreach (array_reverse($pts) as $p) {
            while (count($upper) >= 2 && $cross($upper[count($upper) - 2], $upper[count($upper) - 1], $p) <= 0) array_pop($upper);
            $upper[] = $p;
        }
        array_pop($lower);
        array_pop($upper);
        $hull = array_merge($lower, $upper);

        $area = 0.0;
        $m = count($hull);
        for ($i = 0; $i < $m; $i++) {
            $j = ($i + 1) % $m;
            $area += $hull[$i][0] * $hull[$j][1] - $hull[$j][0] * $hull[$i][1];
        }
        return abs($area) / 2;
    }

    private function simplifyClosed(array $pts, float $eps): array
    {
        $n = count($pts);
        if ($n <= 4) return $pts;

        [$x0, $y0] = $pts[0];
        $far = 0; $best = -1;
        foreach ($pts as $i => [$x, $y]) {
            $d = ($x - $x0) ** 2 + ($y - $y0) ** 2;
            if ($d > $best) { $best = $d; $far = $i; }
        }
        if ($far === 0) return $pts;

        $a = $this->simplifyOpen(array_slice($pts, 0, $far + 1), $eps);
        $b = $this->simplifyOpen(array_merge(array_slice($pts, $far), [$pts[0]]), $eps);
        array_pop($a);
        array_pop($b);

        return array_merge($a, $b);
    }

    /** Douglas-Peucker (iterative). */
    private function simplifyOpen(array $p, float $eps): array
    {
        $n = count($p);
        if ($n < 3) return $p;

        $keep = array_fill(0, $n, false);
        $keep[0] = true;
        $keep[$n - 1] = true;
        $stack = [[0, $n - 1]];

        while ($stack) {
            [$s, $e] = array_pop($stack);
            [$x1, $y1] = $p[$s];
            [$x2, $y2] = $p[$e];
            $vx = $x2 - $x1;
            $vy = $y2 - $y1;
            $len2 = $vx * $vx + $vy * $vy;

            $maxD = 0.0;
            $idx = -1;
            for ($i = $s + 1; $i < $e; $i++) {
                [$px, $py] = $p[$i];
                if ($len2 == 0) {
                    $d = sqrt(($px - $x1) ** 2 + ($py - $y1) ** 2);
                } else {
                    $d = abs($vy * ($px - $x1) - $vx * ($py - $y1)) / sqrt($len2);
                }
                if ($d > $maxD) { $maxD = $d; $idx = $i; }
            }
            if ($idx !== -1 && $maxD > $eps) {
                $keep[$idx] = true;
                $stack[] = [$s, $idx];
                $stack[] = [$idx, $e];
            }
        }

        $out = [];
        foreach ($p as $i => $pt) {
            if ($keep[$i]) $out[] = $pt;
        }
        return $out;
    }

    /* ======================================================================
     *  Small image-processing toolbox (flat int arrays, row-major)
     * ==================================================================== */

    /** Luminance string (0-255 per pixel) with transparent pixels composited on white. */
    private function luminanceOnWhite($img, int $w, int $h): string
    {
        $out = str_repeat("\xFF", $w * $h);
        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                if ($a > 0) {
                    $o = (127 - $a) / 127;
                    $lum = $lum * $o + 255 * (1 - $o);
                }
                $out[$row + $x] = chr((int) round($lum));
            }
        }
        return $out;
    }

    /** 3x3 square dilate (true) or erode (false), separable. */
    private function morph3(array $m, int $w, int $h, bool $dilate): array
    {
        $tmp = $m;
        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $c = $m[$row + $x];
                $l = $x > 0 ? $m[$row + $x - 1] : ($dilate ? 0 : $c);
                $r = $x < $w - 1 ? $m[$row + $x + 1] : ($dilate ? 0 : $c);
                $tmp[$row + $x] = $dilate ? ($c | $l | $r) : ($c & $l & $r);
            }
        }
        $out = $tmp;
        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $c = $tmp[$row + $x];
                $u = $y > 0 ? $tmp[$row - $w + $x] : ($dilate ? 0 : $c);
                $d = $y < $h - 1 ? $tmp[$row + $w + $x] : ($dilate ? 0 : $c);
                $out[$row + $x] = $dilate ? ($c | $u | $d) : ($c & $u & $d);
            }
        }
        return $out;
    }

    /** Morphological opening with a disk of radius $r (removes features thinner than 2r+1). */
    private function openDisk(array $m, int $w, int $h, int $r): array
    {
        $r2 = $r * $r;
        $n = $w * $h;

        // erode: keep pixels whose nearest 0-pixel is farther than r
        $d2 = $this->edtSquared($m, $w, $h);
        $core = array_fill(0, $n, 0);
        $any = false;
        for ($i = 0; $i < $n; $i++) {
            if ($m[$i] && $d2[$i] > $r2) { $core[$i] = 1; $any = true; }
        }
        if (!$any) return $core;

        // dilate: set pixels within r of a kept pixel
        $inv = array_fill(0, $n, 0);
        for ($i = 0; $i < $n; $i++) $inv[$i] = $core[$i] ? 0 : 1;
        $d2 = $this->edtSquared($inv, $w, $h);
        $out = $core;
        for ($i = 0; $i < $n; $i++) {
            if (!$core[$i] && $d2[$i] <= $r2) $out[$i] = 1;
        }
        return $out;
    }

    /**
     * Exact squared Euclidean distance from every 1-pixel to the nearest 0-pixel (Felzenszwalb & Huttenlocher).
     * 0-pixels get 0. If the mask has no 0-pixel at all the values are huge.
     */
    private function edtSquared(array $mask, int $w, int $h): array
    {
        $INF = 1.0e12;
        $n = $w * $h;
        $f = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            if ($mask[$i]) $f[$i] = $INF;
        }

        // columns
        $col = array_fill(0, $h, 0.0);
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0, $i = $x; $y < $h; $y++, $i += $w) $col[$y] = $f[$i];
            $d = $this->edt1d($col, $h);
            for ($y = 0, $i = $x; $y < $h; $y++, $i += $w) $f[$i] = $d[$y];
        }

        // rows
        $row = array_fill(0, $w, 0.0);
        for ($y = 0; $y < $h; $y++) {
            $base = $y * $w;
            for ($x = 0; $x < $w; $x++) $row[$x] = $f[$base + $x];
            $d = $this->edt1d($row, $w);
            for ($x = 0; $x < $w; $x++) $f[$base + $x] = $d[$x];
        }

        return $f;
    }

    private function edt1d(array $f, int $n): array
    {
        $v = [0];
        $z = [-1.0e20, 1.0e20];
        $k = 0;

        for ($q = 1; $q < $n; $q++) {
            $fq = $f[$q] + $q * $q;
            $p = $v[$k];
            $s = ($fq - ($f[$p] + $p * $p)) / (2 * $q - 2 * $p);
            while ($s <= $z[$k]) {
                $k--;
                $p = $v[$k];
                $s = ($fq - ($f[$p] + $p * $p)) / (2 * $q - 2 * $p);
            }
            $k++;
            $v[$k] = $q;
            $z[$k] = $s;
            $z[$k + 1] = 1.0e20;
        }

        $d = [];
        $k = 0;
        for ($q = 0; $q < $n; $q++) {
            while ($z[$k + 1] < $q) $k++;
            $p = $v[$k];
            $d[$q] = ($q - $p) * ($q - $p) + $f[$p];
        }
        return $d;
    }

    /**
     * Connected-component labelling of the 1-pixels.
     *
     * @return array{0: array, 1: array} [labels (0 = none), components[id] = area/bbox/touches-border]
     */
    private function labelComponents(array $mask, int $w, int $h, bool $eight = false): array
    {
        $n = $w * $h;
        $labels = array_fill(0, $n, 0);
        $comps = [];
        $next = 0;

        for ($i = 0; $i < $n; $i++) {
            if (!$mask[$i] || $labels[$i] !== 0) continue;

            $next++;
            $labels[$i] = $next;
            $stack = [$i];
            $area = 0;
            $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;
            $touches = false;

            while ($stack) {
                $p = array_pop($stack);
                $x = $p % $w;
                $y = intdiv($p, $w);
                $area++;
                if ($x < $minX) $minX = $x;
                if ($x > $maxX) $maxX = $x;
                if ($y < $minY) $minY = $y;
                if ($y > $maxY) $maxY = $y;
                if ($x === 0 || $y === 0 || $x === $w - 1 || $y === $h - 1) $touches = true;

                if ($x > 0 && $mask[$p - 1] && $labels[$p - 1] === 0) { $labels[$p - 1] = $next; $stack[] = $p - 1; }
                if ($x < $w - 1 && $mask[$p + 1] && $labels[$p + 1] === 0) { $labels[$p + 1] = $next; $stack[] = $p + 1; }
                if ($y > 0 && $mask[$p - $w] && $labels[$p - $w] === 0) { $labels[$p - $w] = $next; $stack[] = $p - $w; }
                if ($y < $h - 1 && $mask[$p + $w] && $labels[$p + $w] === 0) { $labels[$p + $w] = $next; $stack[] = $p + $w; }

                if ($eight) {
                    if ($x > 0 && $y > 0 && $mask[$p - $w - 1] && $labels[$p - $w - 1] === 0) { $labels[$p - $w - 1] = $next; $stack[] = $p - $w - 1; }
                    if ($x < $w - 1 && $y > 0 && $mask[$p - $w + 1] && $labels[$p - $w + 1] === 0) { $labels[$p - $w + 1] = $next; $stack[] = $p - $w + 1; }
                    if ($x > 0 && $y < $h - 1 && $mask[$p + $w - 1] && $labels[$p + $w - 1] === 0) { $labels[$p + $w - 1] = $next; $stack[] = $p + $w - 1; }
                    if ($x < $w - 1 && $y < $h - 1 && $mask[$p + $w + 1] && $labels[$p + $w + 1] === 0) { $labels[$p + $w + 1] = $next; $stack[] = $p + $w + 1; }
                }
            }

            $comps[$next] = [
                'area' => $area,
                'minX' => $minX, 'minY' => $minY, 'maxX' => $maxX, 'maxY' => $maxY,
                'touches' => $touches,
            ];
        }

        return [$labels, $comps];
    }

    private function raiseMemoryLimit(): void
    {
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '-1') return;

        $bytes = (int) $limit;
        $unit = strtolower(substr(trim($limit), -1));
        if ($unit === 'g') $bytes *= 1024 * 1024 * 1024;
        elseif ($unit === 'm') $bytes *= 1024 * 1024;
        elseif ($unit === 'k') $bytes *= 1024;

        if ($bytes < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
    }


    /* ======================================================================
     *  Flat-region openings  (white / blank / dark placeholders, any shape)
     * ==================================================================== */

    /**
     * Every opening is a "flat" area (uniform colour) that is closed in by the frame: white photo windows inside a
     * black heart, dark boxes on a white sheet, white cells separated by hairlines, dark recesses in a wall...
     * Edges (strong gradients and thin dark/light lines) act as walls; the flat areas between the walls are the
     * candidates. The contour of the flat area IS the inner border of the frame, so hearts, arches, circles and
     * any other outline come out exact.
     *
     * Returns the slots (possibly empty). $stats receives diagnostics for the caller.
     */
    private function detectFlatRegions(string $lumStr, int $w, int $h, float $scale, array $options = [], ?string $chroma = null): array
    {
        $n = $w * $h;
        if ($w < 40 || $h < 40) return [];
        $this->raiseMemoryLimit();

        $v = array_fill(0, $n, 0);
        for ($i = 0; $i < $n; $i++) $v[$i] = ord($lumStr[$i]);
        $b = $this->boxBlur3($v, $w, $h);

        $gradThr  = (int) ($options['flat_gradient'] ?? 18);
        $ridgeThr = (int) ($options['flat_ridge'] ?? 9);

        // thin lines (dark or light) = difference to a closing / opening with a 5x5 window
        $closed = $this->grayMorph($this->grayMorph($b, $w, $h, 2, true), $w, $h, 2, false);
        $opened = $this->grayMorph($this->grayMorph($b, $w, $h, 2, false), $w, $h, 2, true);
        // wide, faint grooves / shadow lines (white cells separated by soft grey lines)
        $wideThr = (int) ($options['flat_ridge_wide'] ?? 7);
        $wide = $this->grayMorph($this->grayMorph($b, $w, $h, 5, true), $w, $h, 5, false);

        $free = array_fill(0, $n, 0);
        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $i = $row + $x;
                $gx = ($x > 0 && $x < $w - 1) ? abs($b[$i + 1] - $b[$i - 1]) : 0;
                $gy = ($y > 0 && $y < $h - 1) ? abs($b[$i + $w] - $b[$i - $w]) : 0;
                $wall = ($gx >= $gradThr || $gy >= $gradThr)
                    || ($closed[$i] - $b[$i] >= $ridgeThr)
                    || ($wide[$i] - $b[$i] >= $wideThr && $wide[$i] - $b[$i] <= 45)
                    || ($b[$i] - $opened[$i] >= $ridgeThr * 2);
                $free[$i] = $wall ? 0 : 1;
            }
        }
        unset($closed, $opened, $wide);

        [$labels, $comps] = $this->labelComponents($free, $w, $h, false);

        // fragmentation: share of the image that is wall or belongs to tiny flat bits (textured walls, stone, noise)
        $tiny = 0;
        foreach ($comps as $c) if ($c['area'] < 40) $tiny += $c['area'];
        $freeCount = 0;
        foreach ($free as $q) $freeCount += $q;
        $this->flatFragmentation = (($n - $freeCount) + $tiny) / $n;

        // textured walls (stone, brick, fabric) break into hundreds of fragments. There, only windows that are clearly
        // darker (or brighter) than the wall itself are openings.
        $textured = $this->flatFragmentation > 0.30;

        // brightness of the background = the most common brightness along the image border
        $bgMean = null;
        {
            $bins = array_fill(0, 32, 0); $cnt = 0;
            $take = function (int $i) use (&$bins, &$cnt, $b): void { $bins[min(31, intdiv($b[$i], 8))]++; $cnt++; };
            for ($x = 0; $x < $w; $x++) { $take($x); $take(($h - 1) * $w + $x); }
            for ($y = 1; $y < $h - 1; $y++) { $take($y * $w); $take($y * $w + $w - 1); }
            $top = 0; $topBin = 0;
            for ($q = 0; $q < 32; $q++) {
                $s3 = $bins[$q] + ($bins[$q - 1] ?? 0) + ($bins[$q + 1] ?? 0);
                if ($s3 > $top) { $top = $s3; $topBin = $q; }
            }
            if ($cnt > 0 && $top >= 0.30 * $cnt) $bgMean = $topBin * 8 + 4;
        }

        // Cut thin links between neighbouring windows (groove strips, hairline gaps in a dashed border, bridges):
        // an opening with a small disk keeps the windows and drops anything narrower than ~2r. The edges and the tips
        // that the opening rounded off are restored later by growToHalfContrast().
        if (!$textured && empty($options['flat_no_split'])) {
            $r = max(2, (int) round(0.012 * max($w, $h)));
            $core = $this->openDisk($free, $w, $h, $r);
            [$labels, $comps] = $this->labelComponents($core, $w, $h, false);
            unset($core);
        }
        $hist = array_fill(0, 256, 0);
        foreach ($b as $q) $hist[$q > 255 ? 255 : ($q < 0 ? 0 : $q)]++;
        $otsu = 128;
        {
            $total = array_sum($hist); $sumAll = 0;
            for ($q = 0; $q < 256; $q++) $sumAll += $q * $hist[$q];
            $wB = 0; $sumB = 0; $bestVar = -1.0;
            for ($q = 0; $q < 256; $q++) {
                $wB += $hist[$q];
                if ($wB === 0) continue;
                $wF = $total - $wB;
                if ($wF === 0) break;
                $sumB += $q * $hist[$q];
                $mB = $sumB / $wB; $mF = ($sumAll - $sumB) / $wF;
                $between = $wB * $wF * ($mB - $mF) * ($mB - $mF);
                if ($between > $bestVar) { $bestVar = $between; $otsu = $q; }
            }
        }

        // per component: border contact + brightness statistics
        $contact = []; $sum = []; $sum2 = []; $sumC = [];
        for ($i = 0; $i < $n; $i++) {
            $l = $labels[$i];
            if ($l === 0) continue;
            $val = $b[$i];
            $sum[$l]  = ($sum[$l] ?? 0) + $val;
            $sum2[$l] = ($sum2[$l] ?? 0) + $val * $val;
            if ($chroma !== null) $sumC[$l] = ($sumC[$l] ?? 0) + ord($chroma[$i]);
        }
        for ($x = 0; $x < $w; $x++) {
            foreach ([$labels[$x], $labels[($h - 1) * $w + $x]] as $l) if ($l) $contact[$l] = ($contact[$l] ?? 0) + 1;
        }
        for ($y = 0; $y < $h; $y++) {
            foreach ([$labels[$y * $w], $labels[$y * $w + $w - 1]] as $l) if ($l) $contact[$l] = ($contact[$l] ?? 0) + 1;
        }

        $minArea = max(60, (int) round(0.0005 * $n));
        $maxArea = (int) round(0.60 * $n);


        $strict = []; $relaxed = []; $meanOf = []; $bgLike = [];
        foreach ($comps as $id => $c) {
            $area = $c['area'];
            if ($area < $minArea || $area > $maxArea) continue;
            $bw = $c['maxX'] - $c['minX'] + 1;
            $bh = $c['maxY'] - $c['minY'] + 1;
            if ($bw < 8 || $bh < 8) continue;
            if ($area / ($bw * $bh) < 0.28) continue;
            if ($area / ($bw + $bh) < 8) continue;                                      // thin strips / arcs / lines

            $mean = $sum[$id] / $area;
            $std = sqrt(max(0.0, $sum2[$id] / $area - $mean * $mean));
            if ($std > 38) continue;
            if ($mean < 110 && $area / ($bw * $bh) < 0.5) continue;                      // dark arcs / rim pieces are not solid placeholders
            if ($chroma !== null && $area < 0.02 * $n && ($sumC[$id] ?? 0) / $area > 90) continue;   // glow, decorations, coloured clip-art
            if ($textured) {
                // dark recesses only: clearly darker than the wall, and big (wall fragments are small)
                if ($mean > $otsu - 12) continue;
                if (min($bw, $bh) < 0.06 * max($w, $h) || $area < 0.004 * $n) continue;
            }

            $perim = 2 * ($bw + $bh);
            $frac = ($contact[$id] ?? 0) / $perim;
            if ($frac >= 0.60) continue;                       // background
            $bgLike[$id] = ($bgMean !== null && abs($mean - $bgMean) <= 16 && !$textured);
            if ($frac >= 0.10) { $relaxed[] = $id; continue; } // cut by the image edge
            // a pocket of background trapped between the lobes of a frame is not an opening
            $strict[] = $id;
            $meanOf[$id] = $mean;
        }

        // dark fragments of the frame itself (corner pieces of a rim) touch bright windows; a real dark placeholder
        // only touches the plain background
        if (!$textured && count($strict) > 1) {
            $candSet = array_flip($strict);
            $keep = [];
            foreach ($strict as $id) {
                $mean = $meanOf[$id];
                if ($mean >= 110) { $keep[] = $id; continue; }
                $c = $comps[$id];
                $pad = 9;
                $x0 = max(0, $c['minX'] - $pad); $y0 = max(0, $c['minY'] - $pad);
                $x1 = min($w - 1, $c['maxX'] + $pad); $y1 = min($h - 1, $c['maxY'] + $pad);
                $cw = $x1 - $x0 + 1; $ch = $y1 - $y0 + 1;
                $m = array_fill(0, $cw * $ch, 0);
                for ($y = 0; $y < $ch; $y++) {
                    $gi = ($y0 + $y) * $w + $x0; $ci = $y * $cw;
                    for ($x = 0; $x < $cw; $x++, $gi++, $ci++) if ($labels[$gi] === $id) $m[$ci] = 1;
                }
                $d = $m;
                for ($q = 0; $q < 9; $q++) $d = $this->morph3($d, $cw, $ch, true);
                $brightNeighbour = false;
                for ($y = 0; $y < $ch && !$brightNeighbour; $y++) {
                    $gi = ($y0 + $y) * $w + $x0; $ci = $y * $cw;
                    for ($x = 0; $x < $cw; $x++, $gi++, $ci++) {
                        if (!$d[$ci] || $m[$ci]) continue;
                        $l = $labels[$gi];
                        if ($l !== 0 && $l !== $id && isset($candSet[$l]) && ($meanOf[$l] ?? 0) > 170) { $brightNeighbour = true; break; }
                    }
                }
                if (!$brightNeighbour) $keep[] = $id;
            }
            $strict = $keep;
        }

        // big cells cut by the image edge (a page split into curved cells): small bits are decoration, not openings
        $bigRelaxed = [];
        foreach ($relaxed as $id) if ($comps[$id]['area'] >= 0.03 * $n) $bigRelaxed[] = $id;
        if (count($bigRelaxed) >= 2) {
            $minBig = min(array_map(fn ($id) => $comps[$id]['area'], $bigRelaxed));
            $strict  = array_values(array_filter($strict,  fn ($id) => $comps[$id]['area'] >= 0.08 * $minBig));
            $relaxed = array_values(array_filter($relaxed, fn ($id) => $comps[$id]['area'] >= 0.08 * $minBig));
        }

        $build = function (array $ids) use ($labels, $comps, $w, $h, $scale, $v, $textured, $bgLike, $n): array {
            $slots = [];
            foreach ($ids as $id) {
                $c = $comps[$id];
                $pad = 10;
                $x0 = max(0, $c['minX'] - $pad); $y0 = max(0, $c['minY'] - $pad);
                $x1 = min($w - 1, $c['maxX'] + $pad); $y1 = min($h - 1, $c['maxY'] + $pad);
                $cw = $x1 - $x0 + 1; $ch = $y1 - $y0 + 1;
                $m = array_fill(0, $cw * $ch, 0);
                $other = array_fill(0, $cw * $ch, 0);
                for ($y = 0; $y < $ch; $y++) {
                    $gi = ($y0 + $y) * $w + $x0; $ci = $y * $cw;
                    for ($x = 0; $x < $cw; $x++, $gi++, $ci++) {
                        $l = $labels[$gi];
                        if ($l === $id) $m[$ci] = 1; elseif ($l !== 0) $other[$ci] = 1;
                    }
                }
                $filled = $this->fillHoles($m, $cw, $ch);
                $area = 0; $farea = 0;
                for ($i = 0, $k = $cw * $ch; $i < $k; $i++) { $area += $m[$i]; $farea += $filled[$i]; }
                if ($farea > 1.25 * $area) continue;           // it encloses big holes: that is the frame, not an opening

                // put the border exactly where the colour changes: grow into the wall pixels that still look like
                // the inside (closer to the inside colour than to the frame colour = half-contrast edge)
                $val = array_fill(0, $cw * $ch, 0);
                for ($y = 0; $y < $ch; $y++) {
                    $gi = ($y0 + $y) * $w + $x0; $ci = $y * $cw;
                    for ($x = 0; $x < $cw; $x++, $gi++, $ci++) $val[$ci] = $v[$gi];
                }
                $g = $this->growToHalfContrast($filled, $other, $val, $cw, $ch);

                // smoothing: ragged bezels of textured walls need a strong clean-up; clean artwork only a light one
                // (a strong morphological opening would cut the tips of diamonds, stars and hearts)
                $rad = $textured
                    ? max(1, min(4, (int) round(0.03 * min($c['maxX'] - $c['minX'] + 1, $c['maxY'] - $c['minY'] + 1))))
                    : 1;
                for ($q = 0; $q < $rad; $q++) $g = $this->morph3($g, $cw, $ch, true);
                for ($q = 0; $q < $rad; $q++) $g = $this->morph3($g, $cw, $ch, false);
                if ($textured) {
                    for ($q = 0; $q < $rad; $q++) $g = $this->morph3($g, $cw, $ch, false);
                    for ($q = 0; $q < $rad; $q++) $g = $this->morph3($g, $cw, $ch, true);
                }

                $contour = $this->traceContour($g, $cw, $ch);
                $garea = 0;
                foreach ($g as $q) $garea += $q;
                if (count($contour) >= 3) {
                    $hull = $this->convexHullArea($contour);
                    if ($hull > 0 && $garea / $hull < 0.74) continue;
                    // a pocket of plain background trapped between frame parts: small or ragged, never a clean big window
                    if (!empty($bgLike[$id]) && $garea < 0.12 * $n && ($garea < 0.04 * $n || ($hull > 0 && $garea / $hull < 0.9))) continue;
                }
                $slot = $this->slotFromMask($g, $cw, $ch, $x0, $y0, $scale, 6);
                // Ragged bezels of hanging frames: a window that is close to an ellipse / rectangle IS that clean shape
                if ($textured && $slot) {
                    $gx0 = $cw; $gx1 = -1; $gy0 = $ch; $gy1 = -1; $ga = 0;
                    for ($y = 0; $y < $ch; $y++) for ($x = 0; $x < $cw; $x++) if ($g[$y * $cw + $x]) {
                        $ga++; if ($x < $gx0) $gx0 = $x; if ($x > $gx1) $gx1 = $x; if ($y < $gy0) $gy0 = $y; if ($y > $gy1) $gy1 = $y;
                    }
                    $bw2 = $gx1 - $gx0 + 1; $bh2 = $gy1 - $gy0 + 1;
                    if ($ga > 0 && $bw2 > 8 && $bh2 > 8) {
                        $ecx = $gx0 + $bw2 / 2; $ecy = $gy0 + $bh2 / 2; $erx = $bw2 / 2; $ery = $bh2 / 2;
                        $inter = 0; $unionE = 0;
                        for ($y = $gy0; $y <= $gy1; $y++) for ($x = $gx0; $x <= $gx1; $x++) {
                            $inE = ((($x + 0.5 - $ecx) / $erx) ** 2 + (($y + 0.5 - $ecy) / $ery) ** 2) <= 1.0;
                            $inG = $g[$y * $cw + $x] === 1;
                            if ($inE && $inG) $inter++;
                            if ($inE || $inG) $unionE++;
                        }
                        $iouE = $unionE ? $inter / $unionE : 0;
                        $fillR = $ga / ($bw2 * $bh2);
                        if ($iouE >= 0.78 && $iouE > $fillR + 0.05) {
                            $slot['shape'] = abs($bw2 - $bh2) <= 0.08 * max($bw2, $bh2) ? 'circle' : 'ellipse';
                            unset($slot['points']);
                        } elseif ($fillR >= 0.84) {
                            $slot['shape'] = 'rectangle';
                            unset($slot['points']);
                        }
                    }
                }
                // Round frames on a rough wall: the bezel is ragged, but most of its edge lies on one circle.
                // A robust (outlier-tolerant) circle fit gives the true round window.
                if ($textured && $slot && in_array($slot['shape'], ['polygon', 'ellipse', 'circle'], true)) {
                    $rc = $this->robustCircle($g, $cw, $ch);
                    if ($rc !== null) {
                        [$ccx, $ccy, $crr] = $rc;
                        $slot['shape'] = 'circle';
                        $slot['x'] = (int) round(($x0 + $ccx - $crr) / $scale);
                        $slot['y'] = (int) round(($y0 + $ccy - $crr) / $scale);
                        $slot['width'] = $slot['height'] = max(1, (int) round(2 * $crr / $scale));
                        unset($slot['points']);
                    }
                }
                // A window whose hull is a plain quadrilateral / pentagon (diamond, kite, house...) but whose outline is
                // bitten by overlapping decoration (leaves, ribbons) is that plain polygon. Only for a lone opening: in a set of
                // windows (puzzle pieces, collages) a notch is part of the piece's real shape.
                if ($slot && count($ids) <= 2 && isset($slot['points']) && count($contour) >= 3 && $hull > 0 && $garea / $hull < 0.95) {
                    $hp = $this->convexHullPoints($contour);
                    $hx0 = min(array_column($hp, 0)); $hx1 = max(array_column($hp, 0));
                    $hy0 = min(array_column($hp, 1)); $hy1 = max(array_column($hp, 1));
                    $simp = $this->simplifyClosed($hp, 0.03 * max($hx1 - $hx0, $hy1 - $hy0, 1));
                    if (count($simp) >= 3 && count($simp) <= 5) {
                        $dx = max(1, $hx1 - $hx0); $dy = max(1, $hy1 - $hy0);
                        $pts = [];
                        foreach ($simp as [$px, $py]) $pts[] = [round(($px - $hx0) / $dx, 4), round(($py - $hy0) / $dy, 4)];
                        $slot['points'] = $pts;
                        $slot['shape'] = 'polygon';
                        $slot['x'] = (int) round(($x0 + $hx0) / $scale);
                        $slot['y'] = (int) round(($y0 + $hy0) / $scale);
                        $slot['width'] = max(1, (int) round(($hx1 - $hx0 + 1) / $scale));
                        $slot['height'] = max(1, (int) round(($hy1 - $hy0 + 1) / $scale));
                    }
                }
                if ($slot && $slot['width'] >= 24 && $slot['height'] >= 24) $slots[] = $slot;
            }
            return $slots;
        };

        $slots = $build($strict);
        if (!empty($relaxed)) {
            $slots = array_merge($slots, $build($relaxed));
        }

        // In a template made of a grid of rectangular windows, irregular leftovers (glow, shadows, corner gaps)
        // are not photo openings.
        $rects = 0; $others = 0;
        foreach ($slots as $s) {
            if (in_array($s['shape'], ['rectangle', 'rounded_rectangle'], true)) $rects++;
            elseif ($s['shape'] !== 'heart') $others++;
        }
        if ($rects >= 8 && $rects >= 2 * $others) {
            $slots = array_values(array_filter($slots, static fn ($s) => in_array($s['shape'], ['rectangle', 'rounded_rectangle', 'heart'], true)));
        }
        return $slots;
    }

    /** Replaces rough slots by the exact flat-region slot that covers (almost) the same box. */
    private function snapToFlat(array $slots, array $flat): array
    {
        $used = [];
        foreach ($slots as $i => $s) {
            $best = -1; $bestIou = 0.0;
            foreach ($flat as $j => $f) {
                if (isset($used[$j])) continue;
                $ix = max(0, min($s['x'] + $s['width'], $f['x'] + $f['width']) - max($s['x'], $f['x']));
                $iy = max(0, min($s['y'] + $s['height'], $f['y'] + $f['height']) - max($s['y'], $f['y']));
                $inter = $ix * $iy;
                $union = $s['width'] * $s['height'] + $f['width'] * $f['height'] - $inter;
                $iou = $union > 0 ? $inter / $union : 0;
                if ($iou > $bestIou) { $bestIou = $iou; $best = $j; }
            }
            if ($best >= 0 && $bestIou >= 0.55) {
                $used[$best] = true;
                $slots[$i] = $flat[$best];
            }
        }
        return $slots;
    }

    /** Colourfulness (max-min of RGB) of every pixel; transparent pixels count as white (0). */
    private function chromaOnWhite($img, int $w, int $h): string
    {
        $out = str_repeat("\0", $w * $h);
        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                if ($a > 60) continue;
                $r = ($rgba >> 16) & 0xFF; $g = ($rgba >> 8) & 0xFF; $b = $rgba & 0xFF;
                $out[$row + $x] = chr(max($r, $g, $b) - min($r, $g, $b));
            }
        }
        return $out;
    }

    /** 3x3 mean filter on an int array. */
    private function boxBlur3(array $v, int $w, int $h): array
    {
        $tmp = $v;
        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $l = $x > 0 ? $v[$row + $x - 1] : $v[$row + $x];
                $r = $x < $w - 1 ? $v[$row + $x + 1] : $v[$row + $x];
                $tmp[$row + $x] = $l + $v[$row + $x] + $r;
            }
        }
        $out = $v;
        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $u = $y > 0 ? $tmp[$row - $w + $x] : $tmp[$row + $x];
                $d = $y < $h - 1 ? $tmp[$row + $w + $x] : $tmp[$row + $x];
                $out[$row + $x] = intdiv($u + $tmp[$row + $x] + $d, 9);
            }
        }
        return $out;
    }

    /** Separable grey-scale max (dilate) / min (erode) filter with a (2r+1) square window. */
    private function grayMorph(array $v, int $w, int $h, int $r, bool $max): array
    {
        $tmp = $v;
        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $best = $v[$row + $x];
                $a = max(0, $x - $r); $z = min($w - 1, $x + $r);
                for ($k = $a; $k <= $z; $k++) {
                    $q = $v[$row + $k];
                    if ($max ? $q > $best : $q < $best) $best = $q;
                }
                $tmp[$row + $x] = $best;
            }
        }
        $out = $tmp;
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $best = $tmp[$y * $w + $x];
                $a = max(0, $y - $r); $z = min($h - 1, $y + $r);
                for ($k = $a; $k <= $z; $k++) {
                    $q = $tmp[$k * $w + $x];
                    if ($max ? $q > $best : $q < $best) $best = $q;
                }
                $out[$y * $w + $x] = $best;
            }
        }
        return $out;
    }

    /**
     * Grows a window mask outwards (max 8 px) over the edge band, keeping only the pixels that are closer in brightness
     * to the inside of the window than to the frame around it. The result is the half-contrast outline, which keeps
     * sharp tips (diamonds, stars, hearts) that a plain "grow by N pixels" or a morphological opening would round off.
     *
     * @param array $m      0/1 window mask (holes already filled)
     * @param array $other  0/1 pixels owned by other windows (never entered)
     * @param array $val    unblurred brightness of the crop
     */
    private function growToHalfContrast(array $m, array $other, array $val, int $cw, int $ch): array
    {
        $n = $cw * $ch;

        // inside brightness from the eroded window (edges excluded)
        $er = $this->morph3($m, $cw, $ch, false);
        $s = 0; $k = 0;
        for ($i = 0; $i < $n; $i++) if ($er[$i]) { $s += $val[$i]; $k++; }
        if ($k < 10) {
            $s = 0; $k = 0;
            for ($i = 0; $i < $n; $i++) if ($m[$i]) { $s += $val[$i]; $k++; }
        }
        if ($k === 0) return $m;
        $in = $s / $k;

        // the edge band (up to 5 px outside) and how different it gets from the inside
        $band = $m;
        for ($q = 0; $q < 8; $q++) $band = $this->morph3($band, $cw, $ch, true);
        $devs = [];
        for ($i = 0; $i < $n; $i++) {
            if ($band[$i] && !$m[$i] && !$other[$i]) $devs[] = abs($val[$i] - $in);
        }
        $g = $m;
        if (count($devs) < 8) return $g;
        sort($devs);
        $dev = $devs[(int) (0.85 * (count($devs) - 1))];

        if ($dev < 12) {
            // no visible frame colour around the window: just close the 2 px eaten by the edge detector
            for ($pass = 0; $pass < 2; $pass++) {
                $d = $this->morph3($g, $cw, $ch, true);
                for ($i = 0; $i < $n; $i++) if ($d[$i] && !$g[$i] && !$other[$i]) $g[$i] = 1;
            }
            return $g;
        }

        // frame colour = the typical (median) colour 3-5 px outside the window, ignoring pixels that still look like the inside
        $d2 = $m; for ($q = 0; $q < 2; $q++) $d2 = $this->morph3($d2, $cw, $ch, true);
        $d5 = $d2; for ($q = 0; $q < 3; $q++) $d5 = $this->morph3($d5, $cw, $ch, true);
        $ring = [];
        for ($i = 0; $i < $n; $i++) {
            if ($d5[$i] && !$d2[$i] && !$other[$i] && abs($val[$i] - $in) >= 8) $ring[] = $val[$i];
        }
        if (count($ring) >= 8) {
            sort($ring);
            $out = $ring[intdiv(count($ring), 2)];
        } else {
            $os = 0; $on = 0;
            for ($i = 0; $i < $n; $i++) {
                if ($band[$i] && !$m[$i] && !$other[$i] && abs($val[$i] - $in) >= 0.7 * $dev) { $os += $val[$i]; $on++; }
            }
            $out = $on ? $os / $on : $in;
        }
        $mid = $in + ($out - $in) * 0.65;
        $sgn = $in >= $out ? 1 : -1;

        for ($pass = 0; $pass < 8; $pass++) {
            $add = [];
            for ($y = 0; $y < $ch; $y++) {
                $row = $y * $cw;
                for ($x = 0; $x < $cw; $x++) {
                    $i = $row + $x;
                    if ($g[$i] || $other[$i] || !$band[$i]) continue;
                    if ($sgn * ($val[$i] - $mid) <= 0) continue;
                    if (($x > 0 && $g[$i - 1]) || ($x < $cw - 1 && $g[$i + 1]) || ($y > 0 && $g[$i - $cw]) || ($y < $ch - 1 && $g[$i + $cw])) $add[] = $i;
                }
            }
            if (!$add) break;
            foreach ($add as $i) $g[$i] = 1;
        }
        return $g;
    }

    /** Fills holes of a 0/1 mask (everything not reachable from the crop border). */
    private function fillHoles(array $m, int $w, int $h): array
    {
        $n = $w * $h;
        $out = array_fill(0, $n, 1);
        $stack = [];
        for ($x = 0; $x < $w; $x++) {
            foreach ([$x, ($h - 1) * $w + $x] as $p) if (!$m[$p] && $out[$p]) { $out[$p] = 0; $stack[] = $p; }
        }
        for ($y = 0; $y < $h; $y++) {
            foreach ([$y * $w, $y * $w + $w - 1] as $p) if (!$m[$p] && $out[$p]) { $out[$p] = 0; $stack[] = $p; }
        }
        while ($stack) {
            $p = array_pop($stack);
            $x = $p % $w;
            if ($x > 0 && !$m[$p - 1] && $out[$p - 1]) { $out[$p - 1] = 0; $stack[] = $p - 1; }
            if ($x < $w - 1 && !$m[$p + 1] && $out[$p + 1]) { $out[$p + 1] = 0; $stack[] = $p + 1; }
            if ($p >= $w && !$m[$p - $w] && $out[$p - $w]) { $out[$p - $w] = 0; $stack[] = $p - $w; }
            if ($p < $n - $w && !$m[$p + $w] && $out[$p + $w]) { $out[$p + $w] = 0; $stack[] = $p + $w; }
        }
        return $out;
    }

    /* ======================================================================
     *  1 / 3 / 4 / 5.  ORIGINAL STRATEGIES (now also return outlines for non-rectangular shapes)
     * ==================================================================== */

    private function detectAlphaCutouts(string $alphaStr, int $w, int $h, float $scale): array
    {
        $visited = str_repeat("\0", $w * $h);
        $imgArea = $w * $h;
        $slots = [];

        for ($y = 0; $y < $h; $y++) {
            $rowOffset = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $pos = $rowOffset + $x;
                if ($visited[$pos] === "\1" || ord($alphaStr[$pos]) < 60) continue;

                $stack = [[$x, $y]];
                $visited[$pos] = "\1";
                $minX = $maxX = $x;
                $minY = $maxY = $y;
                $count = 0;
                $touchesBorder = false;
                $pixels = [];

                while ($stack) {
                    [$cx, $cy] = array_pop($stack);
                    $count++;
                    $pixels[] = $cy * $w + $cx;
                    if ($cx <= 1 || $cy <= 1 || $cx >= $w - 2 || $cy >= $h - 2) $touchesBorder = true;
                    if ($cx < $minX) $minX = $cx;
                    if ($cx > $maxX) $maxX = $cx;
                    if ($cy < $minY) $minY = $cy;
                    if ($cy > $maxY) $maxY = $cy;

                    foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                        $nx = $cx + $dx; $ny = $cy + $dy;
                        if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) continue;
                        $npos = $ny * $w + $nx;
                        if ($visited[$npos] === "\1" || ord($alphaStr[$npos]) < 60) continue;
                        $visited[$npos] = "\1";
                        $stack[] = [$nx, $ny];
                    }
                }

                if ($touchesBorder) continue;

                $bboxW = $maxX - $minX + 1;
                $bboxH = $maxY - $minY + 1;
                $bboxArea = $bboxW * $bboxH;
                if ($bboxArea <= 0) continue;

                $areaRatio = $bboxArea / $imgArea;
                if ($areaRatio < 0.0006 || $areaRatio > 0.70) continue;

                $fillRatio = $count / $bboxArea;
                $actualW = (int) round($bboxW / $scale);
                $actualH = (int) round($bboxH / $scale);
                if ($actualW < 24 || $actualH < 24) continue;

                $slots[] = $this->buildSlot($pixels, $w, $minX, $minY, $maxX, $maxY, $scale, $fillRatio, $bboxW, $bboxH);
            }
        }

        return $slots;
    }

    private function detectWhitePlaceholders(string $lumStr, int $w, int $h, float $scale): array
    {
        $visited = str_repeat("\0", $w * $h);
        $imgArea = $w * $h;
        $slots = [];

        for ($y = 0; $y < $h; $y++) {
            $rowOffset = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $pos = $rowOffset + $x;
                if ($visited[$pos] === "\1" || ord($lumStr[$pos]) < 225) continue;

                $stack = [[$x, $y]];
                $visited[$pos] = "\1";
                $minX = $maxX = $x;
                $minY = $maxY = $y;
                $count = 0;
                $touchesBorder = false;
                $pixels = [];

                while ($stack) {
                    [$cx, $cy] = array_pop($stack);
                    $count++;
                    $pixels[] = $cy * $w + $cx;
                    if ($cx <= 1 || $cy <= 1 || $cx >= $w - 2 || $cy >= $h - 2) $touchesBorder = true;
                    if ($cx < $minX) $minX = $cx;
                    if ($cx > $maxX) $maxX = $cx;
                    if ($cy < $minY) $minY = $cy;
                    if ($cy > $maxY) $maxY = $cy;

                    foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                        $nx = $cx + $dx; $ny = $cy + $dy;
                        if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) continue;
                        $npos = $ny * $w + $nx;
                        if ($visited[$npos] === "\1" || ord($lumStr[$npos]) < 225) continue;
                        $visited[$npos] = "\1";
                        $stack[] = [$nx, $ny];
                    }
                }

                if ($touchesBorder) continue;

                $bboxW = $maxX - $minX + 1;
                $bboxH = $maxY - $minY + 1;
                $bboxArea = $bboxW * $bboxH;
                if ($bboxArea <= 0) continue;

                $areaRatio = $bboxArea / $imgArea;
                if ($areaRatio < 0.0006 || $areaRatio > 0.70) continue;

                $fillRatio = $count / $bboxArea;
                if ($fillRatio < 0.65) continue;

                $actualW = (int) round($bboxW / $scale);
                $actualH = (int) round($bboxH / $scale);
                if ($actualW < 24 || $actualH < 24) continue;

                $slots[] = $this->buildSlot($pixels, $w, $minX, $minY, $maxX, $maxY, $scale, $fillRatio, $bboxW, $bboxH);
            }
        }

        return $slots;
    }

    private function detectDividerCompartments(string $lumStr, int $w, int $h, float $scale, int $wallThreshold = 45): array
    {
        $visited = str_repeat("\0", $w * $h);
        $imgArea = $w * $h;
        $slots = [];

        for ($y = 0; $y < $h; $y++) {
            $rowOffset = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $pos = $rowOffset + $x;
                if ($visited[$pos] === "\1" || ord($lumStr[$pos]) < $wallThreshold) continue;

                $stack = [[$x, $y]];
                $visited[$pos] = "\1";
                $minX = $maxX = $x;
                $minY = $maxY = $y;
                $count = 0;
                $touchesBorder = false;
                $pixels = [];

                while ($stack) {
                    [$cx, $cy] = array_pop($stack);
                    $count++;
                    $pixels[] = $cy * $w + $cx;
                    if ($cx <= 1 || $cy <= 1 || $cx >= $w - 2 || $cy >= $h - 2) $touchesBorder = true;
                    if ($cx < $minX) $minX = $cx;
                    if ($cx > $maxX) $maxX = $cx;
                    if ($cy < $minY) $minY = $cy;
                    if ($cy > $maxY) $maxY = $cy;

                    foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                        $nx = $cx + $dx; $ny = $cy + $dy;
                        if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) continue;
                        $npos = $ny * $w + $nx;
                        if ($visited[$npos] === "\1" || ord($lumStr[$npos]) < $wallThreshold) continue;
                        $visited[$npos] = "\1";
                        $stack[] = [$nx, $ny];
                    }
                }

                if ($touchesBorder) continue;

                $bboxW = $maxX - $minX + 1;
                $bboxH = $maxY - $minY + 1;
                $bboxArea = $bboxW * $bboxH;
                if ($bboxArea <= 0) continue;

                $areaRatio = $bboxArea / $imgArea;
                if ($areaRatio < 0.0006 || $areaRatio > 0.65) continue;

                $fillRatio = $count / $bboxArea;
                if ($fillRatio < 0.35) continue;

                $actualW = (int) round($bboxW / $scale);
                $actualH = (int) round($bboxH / $scale);
                if ($actualW < 24 || $actualH < 24) continue;

                $slots[] = $this->buildSlot($pixels, $w, $minX, $minY, $maxX, $maxY, $scale, $fillRatio, $bboxW, $bboxH);
            }
        }

        return count($slots) >= 2 ? $slots : [];
    }

    private function detectEdgeSlots(string $lumStr, int $w, int $h, float $scale, int $edgeThreshold): array
    {
        $edgeStr = str_repeat("\0", $w * $h);
        for ($y = 0; $y < $h; $y++) {
            $rowOffset = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $idx = $rowOffset + $x;
                $val = ord($lumStr[$idx]);
                $gx = $x < $w - 1 ? abs($val - ord($lumStr[$idx + 1])) : 0;
                $gy = $y < $h - 1 ? abs($val - ord($lumStr[$idx + $w])) : 0;
                if (max($gx, $gy) >= $edgeThreshold) {
                    $edgeStr[$idx] = "\1";
                }
            }
        }

        $dilated = $edgeStr;
        for ($y = 0; $y < $h; $y++) {
            $rowOffset = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $idx = $rowOffset + $x;
                if ($edgeStr[$idx] === "\1") continue;
                for ($dy = -1; $dy <= 1; $dy++) {
                    for ($dx = -1; $dx <= 1; $dx++) {
                        $nx = $x + $dx; $ny = $y + $dy;
                        if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) continue;
                        if ($edgeStr[$ny * $w + $nx] === "\1") {
                            $dilated[$idx] = "\1";
                            break 2;
                        }
                    }
                }
            }
        }

        $visited = str_repeat("\0", $w * $h);
        $slots = [];
        $imgArea = $w * $h;

        for ($y = 0; $y < $h; $y++) {
            $rowOffset = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $pos = $rowOffset + $x;
                if ($dilated[$pos] === "\1" || $visited[$pos] === "\1") continue;

                $stack = [[$x, $y]];
                $visited[$pos] = "\1";
                $minX = $maxX = $x;
                $minY = $maxY = $y;
                $count = 0;
                $touchesBorder = false;
                $pixels = [];

                while ($stack) {
                    [$cx, $cy] = array_pop($stack);
                    $count++;
                    $pixels[] = $cy * $w + $cx;
                    if ($cx <= 1 || $cy <= 1 || $cx >= $w - 2 || $cy >= $h - 2) $touchesBorder = true;
                    if ($cx < $minX) $minX = $cx;
                    if ($cx > $maxX) $maxX = $cx;
                    if ($cy < $minY) $minY = $cy;
                    if ($cy > $maxY) $maxY = $cy;

                    foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                        $nx = $cx + $dx; $ny = $cy + $dy;
                        if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) continue;
                        $npos = $ny * $w + $nx;
                        if ($visited[$npos] === "\1" || $dilated[$npos] === "\1") continue;
                        $visited[$npos] = "\1";
                        $stack[] = [$nx, $ny];
                    }
                }

                if ($touchesBorder) continue;

                $bboxW = $maxX - $minX + 1;
                $bboxH = $maxY - $minY + 1;
                $bboxArea = $bboxW * $bboxH;
                if ($bboxArea <= 0) continue;

                $areaRatio = $bboxArea / $imgArea;
                if ($areaRatio < 0.012 || $areaRatio > 0.65) continue;

                $fillRatio = $count / $bboxArea;
                if ($fillRatio < 0.40) continue;

                $actualW = (int) round($bboxW / $scale);
                $actualH = (int) round($bboxH / $scale);
                if ($actualW < 40 || $actualH < 40) continue;

                $aspect = $actualW / max(1, $actualH);
                if ($aspect < 0.22 || $aspect > 4.5) continue;

                $slots[] = $this->buildSlot($pixels, $w, $minX, $minY, $maxX, $maxY, $scale, $fillRatio, $bboxW, $bboxH);
            }
        }

        return $slots;
    }

    /**
     * Builds the slot for a pixel blob found by one of the older strategies.
     * Uses the accurate mask-based classifier (heart / polygon get an outline); falls back to the
     * original fill-ratio heuristic if tracing is not possible.
     */
    private function buildSlot(array $pixels, int $w, int $minX, int $minY, int $maxX, int $maxY, float $scale, float $fillRatio, int $bboxW, int $bboxH): array
    {
        $slot = null;
        if ($bboxW * $bboxH <= 4_000_000) {
            $slot = $this->slotFromPixels($pixels, $w, $minX, $minY, $maxX, $maxY, $scale);
        }
        if ($slot) return $slot;

        return [
            'x'        => (int) round($minX / $scale),
            'y'        => (int) round($minY / $scale),
            'width'    => (int) round($bboxW / $scale),
            'height'   => (int) round($bboxH / $scale),
            'shape'    => $this->classifyShape($fillRatio, $bboxW, $bboxH),
            'rotation' => 0,
        ];
    }

    private function classifyShape(float $fillRatio, int $bboxW, int $bboxH): string
    {
        $aspect = $bboxW / max(1, $bboxH);

        // Circle: fill ratio ~ pi/4 = 0.785, aspect ratio ~ 1.0
        if ($fillRatio >= 0.70 && $fillRatio <= 0.85 && abs($aspect - 1) <= 0.15) {
            return 'circle';
        }
        // Ellipse
        if ($fillRatio >= 0.70 && $fillRatio <= 0.85) {
            return 'ellipse';
        }
        // Heart: fill ratio ~ 0.48 - 0.68, roughly square/balanced aspect
        if ($fillRatio >= 0.45 && $fillRatio <= 0.68 && $aspect >= 0.72 && $aspect <= 1.38) {
            return 'heart';
        }
        // Rounded rectangle
        if ($fillRatio >= 0.86 && $fillRatio <= 0.95) {
            return 'rounded_rectangle';
        }

        return 'rectangle';
    }

    private function removeOverlapping(array $slots): array
    {
        usort($slots, fn ($a, $b) => ($b['width'] * $b['height']) <=> ($a['width'] * $a['height']));
        $kept = [];
        foreach ($slots as $s) {
            $overlaps = false;
            foreach ($kept as $k) {
                $ix = max(0, min($s['x'] + $s['width'], $k['x'] + $k['width']) - max($s['x'], $k['x']));
                $iy = max(0, min($s['y'] + $s['height'], $k['y'] + $k['height']) - max($s['y'], $k['y']));
                $interArea = $ix * $iy;
                $smallerArea = min($s['width'] * $s['height'], $k['width'] * $k['height']);
                if ($smallerArea > 0 && ($interArea / $smallerArea) > 0.5) { $overlaps = true; break; }
            }
            if (!$overlaps) $kept[] = $s;
        }
        return $kept;
    }

    private function sortReadingOrder(array $slots): array
    {
        if (empty($slots)) return [];
        usort($slots, fn ($a, $b) => $a['y'] <=> $b['y']);
        $avgH = array_sum(array_column($slots, 'height')) / count($slots);
        $rowTolerance = $avgH * 0.6;

        $rows = [];
        foreach ($slots as $s) {
            $placed = false;
            foreach ($rows as &$row) {
                if (abs($row['y'] - $s['y']) <= $rowTolerance) { $row['items'][] = $s; $placed = true; break; }
            }
            unset($row);
            if (!$placed) $rows[] = ['y' => $s['y'], 'items' => [$s]];
        }
        usort($rows, fn ($a, $b) => $a['y'] <=> $b['y']);

        $result = [];
        foreach ($rows as $row) {
            $items = $row['items'];
            usort($items, fn ($a, $b) => $a['x'] <=> $b['x']);
            foreach ($items as $it) $result[] = $it;
        }
        return $result;
    }

    private function callExternalDetector(string $url, string $filePath): ?array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(6)
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post($url);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['slots']) && is_array($data['slots'])) {
                    return $data['slots'];
                }
            }
        } catch (\Throwable $e) {
            // Silently fall back to built-in detection
        }
        return null;
    }
}