<?php

namespace App\Services;

use App\Models\Frame;
use ZipArchive;

class FrameCompositor
{
    /**
     * Renders the frame's template with all currently-placed images
     * composited into their slots, respecting each slot's shape
     * (rectangle/circle/ellipse/heart/rounded_rectangle) and rotation.
     *
     * @return \GdImage|false
     */
    public function render(Frame $frame)
    {
        $templatePath = storage_path('app/public/' . $frame->template_path);
        if (!file_exists($templatePath)) return false;

        $base = @imagecreatefromstring(file_get_contents($templatePath));
        if (!$base) return false;

        foreach ($frame->slot_positions as $index => $slotPos) {
            $slotImage = $frame->images->where('position', $index + 1)->first();
            if (!$slotImage) continue;

            $rect = $this->rectWithOutline($frame->slotRect($index), $slotPos);
            if (!$rect || $rect['width'] <= 0 || $rect['height'] <= 0) continue;

            $userImgPath = storage_path('app/public/' . $slotImage->image_path);
            if (!file_exists($userImgPath)) continue;

            $userImg = @imagecreatefromstring(file_get_contents($userImgPath));
            if (!$userImg) continue;

            $this->pasteIntoSlot($base, $userImg, $rect);
        }

        return $base;
    }

    /**
     * Renders a frame using an array of image file paths mapped to slots.
     * $imagePaths can be keyed by slot index (0, 1, 2...) or sequential.
     *
     * @param Frame $frame
     * @param array $imagePaths Absolute file paths or storage-relative paths
     * @param array $options Options like watermark, banner_text, calendar_day, scale, format
     * @return \GdImage|false
     */
    public function renderFromImages(Frame $frame, array $imagePaths, array $options = [])
    {
        $templatePath = storage_path('app/public/' . $frame->template_path);
        if (!file_exists($templatePath)) return false;

        $base = @imagecreatefromstring(file_get_contents($templatePath));
        if (!$base) return false;

        $scale = (float) ($options['scale'] ?? ($options['hd'] ?? false ? 2.0 : 1.0));
        if ($scale <= 0) $scale = 1.0;

        $origW = imagesx($base);
        $origH = imagesy($base);

        if (abs($scale - 1.0) > 0.001) {
            $scaledW = (int) round($origW * $scale);
            $scaledH = (int) round($origH * $scale);
            $scaledBase = imagecreatetruecolor($scaledW, $scaledH);
            imagealphablending($scaledBase, false);
            imagesavealpha($scaledBase, true);
            $trans = imagecolorallocatealpha($scaledBase, 0, 0, 0, 127);
            imagefill($scaledBase, 0, 0, $trans);
            imagecopyresampled($scaledBase, $base, 0, 0, 0, 0, $scaledW, $scaledH, $origW, $origH);
            imagedestroy($base);
            $base = $scaledBase;
        }

        $transforms = $this->resolveTransforms($frame, $options);

        $slotPositions = $frame->slot_positions ?? [];
        foreach ($slotPositions as $index => $slotPos) {
            $path = $imagePaths[$index] ?? null;
            if (!$path) continue;

            $fullPath = file_exists($path) ? $path : storage_path('app/public/' . $path);
            if (!file_exists($fullPath)) continue;

            $userImg = @imagecreatefromstring(file_get_contents($fullPath));
            if (!$userImg) continue;

            $rect = $this->rectWithOutline($frame->slotRect($index), $slotPos);
            if (!$rect || $rect['width'] <= 0 || $rect['height'] <= 0) continue;

            $rect['transform'] = $transforms[$index] ?? null;
            $this->pasteIntoSlot($base, $userImg, $rect, $scale);
        }

        // Apply custom text banner if provided
        if (!empty($options['banner_text'])) {
            $this->applyBannerText($base, $options['banner_text'], $options['banner_subtext'] ?? null, $scale, $frame);
        }

        // Apply calendar highlight & days grid if requested (DOB / Anniversary DD-MM-YYYY)
        $calDate = $options['calendar_date'] ?? $options['calendar_day'] ?? null;
        if (!empty($calDate)) {
            $this->applyCalendarGrid($base, $frame, $calDate, $scale);
        }

        // Apply watermark if requested
        if (!empty($options['watermark'])) {
            $this->applyWatermark($base, $scale);
        }

        return $base;
    }

    /**
     * Renders and saves directly to disk in PNG (lossless) or JPEG.
     */
    public function renderAndSave(Frame $frame, array $imagePaths, string $outputPath, array $options = []): bool
    {
        $image = $this->renderFromImages($frame, $imagePaths, $options);
        if (!$image) return false;

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $ext = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));
        if ($ext === 'png' || ($options['format'] ?? '') === 'png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $saved = imagepng($image, $outputPath, 9);
        } else {
            $saved = imagejpeg($image, $outputPath, 98);
        }

        imagedestroy($image);
        return $saved;
    }

    /**
     * Adds the detected outline ("points", normalised 0..1 inside the slot's bounding box) of a heart / polygon
     * slot to the rectangle returned by Frame::slotRect(), so the photo can be masked to the exact opening.
     */
    private function rectWithOutline(?array $rect, mixed $slotPos): ?array
    {
        if (!$rect || !is_array($slotPos) || empty($slotPos['points']) || !is_array($slotPos['points'])) {
            return $rect;
        }

        $shape = strtolower((string) ($rect['shape'] ?? $slotPos['shape'] ?? 'rectangle'));
        if (in_array($shape, ['heart', 'polygon'], true)) {
            $rect['shape']  = $shape;
            $rect['points'] = $slotPos['points'];
        }

        return $rect;
    }

    /**
     * Crops and resizes the source image to fill the slot's width/height
     * without distortion, masks it with silky antialiasing to the
     * slot's shape (including detected heart / polygon outlines), rotates
     * if angled, and pastes into the base image.
     */
    public function pasteIntoSlot($base, $userImg, array $rect, float $scale = 1.0): void
    {
        $w = (int) round(($rect['width'] ?? 0) * $scale);
        $h = (int) round(($rect['height'] ?? 0) * $scale);
        $shape = strtolower($rect['shape'] ?? 'rectangle');
        $rotation = (float) ($rect['rotation'] ?? 0);
        $points = $rect['points'] ?? null;

        if ($w <= 0 || $h <= 0) return;

        // A detected outline always wins over the parametric heart
        $hasOutline = is_array($points) && count($points) >= 3 && in_array($shape, ['heart', 'polygon'], true);
        if ($hasOutline) {
            $shape = 'polygon';
        }

        $cropped = $this->cropToFit($userImg, $w, $h, $rect['transform'] ?? null);

        if (in_array($shape, ['circle', 'ellipse', 'rounded_rectangle', 'heart', 'diamond', 'polygon'])) {
            $cropped = $this->applyAntialiasedMask($cropped, $w, $h, $shape, $hasOutline ? ['points' => $points] : []);
        }

        if (abs($rotation) > 0.05) {
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);
            $transparent = imagecolorallocatealpha($cropped, 0, 0, 0, 127);
            $rotated = imagerotate($cropped, -$rotation, $transparent);
            imagealphablending($rotated, true);
            imagesavealpha($rotated, true);
            imagedestroy($cropped);
            $cropped = $rotated;
        }

        $rw = imagesx($cropped);
        $rh = imagesy($cropped);
        $centerX = ($rect['x'] * $scale) + $w / 2;
        $centerY = ($rect['y'] * $scale) + $h / 2;
        $destX = (int) round($centerX - $rw / 2);
        $destY = (int) round($centerY - $rh / 2);

        imagealphablending($base, true);
        imagecopy($base, $cropped, $destX, $destY, 0, 0, $rw, $rh);

        imagedestroy($cropped);
    }

    /**
     * High-quality supersampled antialiased mask for circles, ellipses,
     * rounded rectangles, hearts, diamonds and free-form polygons
     * ($params['points'] = list of [x, y] pairs normalised 0..1 inside the slot).
     */
    public function applyAntialiasedMask($srcImg, int $w, int $h, string $shape, array $params = [])
    {
        $mw = $w * 2;
        $mh = $h * 2;
        $mask = imagecreatetruecolor($mw, $mh);
        imagealphablending($mask, false);
        imagesavealpha($mask, true);
        $black = imagecolorallocate($mask, 0, 0, 0);
        $white = imagecolorallocate($mask, 255, 255, 255);
        imagefill($mask, 0, 0, $black);

        if ($shape === 'circle' || $shape === 'ellipse') {
            imagefilledellipse($mask, (int)($mw / 2), (int)($mh / 2), $mw, $mh, $white);
        } elseif ($shape === 'rounded_rectangle') {
            $radius = (int) ($params['radius'] ?? max(8, min($mw, $mh) * 0.12));
            imagefilledrectangle($mask, $radius, 0, $mw - $radius, $mh, $white);
            imagefilledrectangle($mask, 0, $radius, $mw, $mh - $radius, $white);
            imagefilledellipse($mask, $radius, $radius, $radius * 2, $radius * 2, $white);
            imagefilledellipse($mask, $mw - $radius, $radius, $radius * 2, $radius * 2, $white);
            imagefilledellipse($mask, $radius, $mh - $radius, $radius * 2, $radius * 2, $white);
            imagefilledellipse($mask, $mw - $radius, $mh - $radius, $radius * 2, $radius * 2, $white);
        } elseif ($shape === 'heart') {
            $cx = $mw / 2;
            $cy = $mh * 0.52;
            $scaleX = ($mw * 0.46) / 16;
            $scaleY = ($mh * 0.46) / 16;
            $points = [];
            for ($t = 0; $t <= 360; $t += 2) {
                $rad = deg2rad($t);
                $x = 16 * pow(sin($rad), 3);
                $y = -(13 * cos($rad) - 5 * cos(2 * $rad) - 2 * cos(3 * $rad) - cos(4 * $rad));
                $points[] = (int) round($cx + $x * $scaleX);
                $points[] = (int) round($cy + $y * $scaleY);
            }
            imagefilledpolygon($mask, $points, $white);
        } elseif ($shape === 'diamond') {
            $points = [
                (int)($mw / 2), 0,
                $mw, (int)($mh / 2),
                (int)($mw / 2), $mh,
                0, (int)($mh / 2)
            ];
            imagefilledpolygon($mask, $points, $white);
        } elseif ($shape === 'polygon') {
            $poly = [];
            foreach (($params['points'] ?? []) as $p) {
                if (!is_array($p) || count($p) < 2) continue;
                $poly[] = (int) round(max(0.0, min(1.0, (float) $p[0])) * ($mw - 1));
                $poly[] = (int) round(max(0.0, min(1.0, (float) $p[1])) * ($mh - 1));
            }
            if (count($poly) < 6) {
                imagedestroy($mask);
                return $srcImg;
            }
            imagefilledpolygon($mask, $poly, $white);
        } else {
            imagedestroy($mask);
            return $srcImg;
        }

        $smoothMask = imagecreatetruecolor($w, $h);
        imagealphablending($smoothMask, false);
        imagesavealpha($smoothMask, true);
        imagecopyresampled($smoothMask, $mask, 0, 0, 0, 0, $w, $h, $mw, $mh);
        imagedestroy($mask);

        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefill($out, 0, 0, $transparent);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $maskColor = imagecolorat($smoothMask, $x, $y);
                $maskLum = $maskColor & 0xFF;
                if ($maskLum === 0) continue;

                $srcColor = imagecolorat($srcImg, $x, $y);
                $r = ($srcColor >> 16) & 0xFF;
                $g = ($srcColor >> 8) & 0xFF;
                $b = $srcColor & 0xFF;
                $srcAlpha = ($srcColor >> 24) & 0x7F;

                $srcOpacity = (127 - $srcAlpha) / 127.0;
                $maskOpacity = $maskLum / 255.0;
                $finalOpacity = $srcOpacity * $maskOpacity;
                $finalAlpha = (int) round(127 - ($finalOpacity * 127));

                $c = imagecolorallocatealpha($out, $r, $g, $b, $finalAlpha);
                imagesetpixel($out, $x, $y, $c);
            }
        }

        imagedestroy($smoothMask);
        imagedestroy($srcImg);
        return $out;
    }

    /**
     * Fits the photo into the slot. With no transform the photo simply covers the slot (centred).
     * With a transform ['zoom' => 1, 'x' => 0, 'y' => 0] the customer's zoom and position are applied:
     * zoom 1 = cover, <1 = zoomed out, >1 = zoomed in; x / y = photo centre offset in slot widths / heights.
     * Any slot area the photo does not reach is filled with a soft, blurred version of the same photo.
     */
    public function cropToFit($srcImg, int $destWidth, int $destHeight, ?array $transform = null)
    {
        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);

        $zoom = 1.0; $offX = 0.0; $offY = 0.0;
        if (is_array($transform)) {
            $zoom = max(0.2, min(6.0, (float) ($transform['zoom'] ?? 1)));
            $offX = max(-3.0, min(3.0, (float) ($transform['x'] ?? 0)));
            $offY = max(-3.0, min(3.0, (float) ($transform['y'] ?? 0)));
        }

        $newImg = imagecreatetruecolor($destWidth, $destHeight);
        imagealphablending($newImg, false);
        imagesavealpha($newImg, true);
        $transparent = imagecolorallocatealpha($newImg, 0, 0, 0, 127);
        imagefill($newImg, 0, 0, $transparent);

        // size / position of the photo on the slot canvas
        $cover = max($destWidth / $srcW, $destHeight / $srcH);
        $drawW = $srcW * $cover * $zoom;
        $drawH = $srcH * $cover * $zoom;
        $drawX = $destWidth / 2 + $offX * $destWidth - $drawW / 2;
        $drawY = $destHeight / 2 + $offY * $destHeight - $drawH / 2;

        $eps = 0.75;
        $coversSlot = $drawX <= $eps && $drawY <= $eps
            && $drawX + $drawW >= $destWidth - $eps && $drawY + $drawH >= $destHeight - $eps;

        if (!$coversSlot) {
            $this->paintSoftBackground($newImg, $srcImg, $destWidth, $destHeight);
        }

        // visible part of the photo (intersection of the photo and the slot)
        $dx0 = max(0.0, $drawX);               $dy0 = max(0.0, $drawY);
        $dx1 = min((float) $destWidth,  $drawX + $drawW);
        $dy1 = min((float) $destHeight, $drawY + $drawH);
        if ($dx1 - $dx0 >= 1 && $dy1 - $dy0 >= 1) {
            $sx = ($dx0 - $drawX) / $drawW * $srcW;
            $sy = ($dy0 - $drawY) / $drawH * $srcH;
            $sw = ($dx1 - $dx0) / $drawW * $srcW;
            $sh = ($dy1 - $dy0) / $drawH * $srcH;
            imagealphablending($newImg, !$coversSlot ? true : false);
            imagecopyresampled(
                $newImg, $srcImg,
                (int) round($dx0), (int) round($dy0),
                (int) floor($sx), (int) floor($sy),
                max(1, (int) round($dx1 - $dx0)), max(1, (int) round($dy1 - $dy0)),
                max(1, (int) round($sw)), max(1, (int) round($sh))
            );
            imagealphablending($newImg, false);
        }

        imagedestroy($srcImg);
        return $newImg;
    }

    /**
     * Soft fill for slot areas the photo does not cover: the photo's own colours, heavily blurred and lightened a little.
     */
    private function paintSoftBackground($canvas, $srcImg, int $w, int $h): void
    {
        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);
        $cover = max($w / $srcW, $h / $srcH);
        $cropW = max(1, (int) round($w / $cover));
        $cropH = max(1, (int) round($h / $cover));
        $cropX = (int) max(0, floor(($srcW - $cropW) / 2));
        $cropY = (int) max(0, floor(($srcH - $cropH) / 2));

        // shrink to a tiny image (averages the colours) ...
        $tw = max(2, (int) round($w / 28));
        $th = max(2, (int) round($h / 28));
        $tiny = imagecreatetruecolor($tw, $th);
        imagealphablending($tiny, false);
        imagecopyresampled($tiny, $srcImg, 0, 0, $cropX, $cropY, $tw, $th, min($cropW, $srcW), min($cropH, $srcH));

        // ... and stretch it back up smoothly (bicubic, in two steps) = a blurred colour wash
        $midW = max($tw + 1, (int) round($w / 7));
        $midH = max($th + 1, (int) round($h / 7));
        $mid = @imagescale($tiny, $midW, $midH, IMG_BICUBIC);
        $wash = $mid ? @imagescale($mid, $w, $h, IMG_BICUBIC) : false;
        if ($mid) imagedestroy($mid);
        if (!$wash) {
            $wash = imagecreatetruecolor($w, $h);
            imagecopyresampled($wash, $tiny, 0, 0, 0, 0, $w, $h, $tw, $th);
        }
        imagedestroy($tiny);
        if (function_exists('imagefilter')) {
            for ($i = 0; $i < 2; $i++) @imagefilter($wash, IMG_FILTER_GAUSSIAN_BLUR);
        }
        imagealphablending($wash, false);

        // lighten slightly so the photo itself stays the hero
        imagealphablending($wash, true);
        $veil = imagecolorallocatealpha($wash, 255, 255, 255, 105);
        imagefilledrectangle($wash, 0, 0, $w, $h, $veil);

        imagealphablending($canvas, false);
        imagecopy($canvas, $wash, 0, 0, 0, 0, $w, $h);
        imagedestroy($wash);
    }

    /**
     * Zoom / position the customer chose for each slot: ['slotIndex' => ['zoom' => 1.2, 'x' => 0.05, 'y' => -0.1]].
     * Taken from $options['transforms'], otherwise from the current studio request (field "transforms",
     * JSON) which is also remembered in the session so the paid HD render uses the same framing.
     */
    private function resolveTransforms(Frame $frame, array $options): array
    {
        $raw = $options['transforms'] ?? null;
        $key = 'studio_transforms.' . $frame->id;

        if ($raw === null && function_exists('request') && request()->has('transforms')) {
            $raw = request()->input('transforms');
            $remember = true;
        }
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (is_array($raw)) {
            $clean = [];
            foreach ($raw as $slot => $t) {
                if (!is_array($t) || !is_numeric($slot)) continue;
                $clean[(int) $slot] = [
                    'zoom' => max(0.2, min(6.0, (float) ($t['zoom'] ?? 1))),
                    'x'    => max(-3.0, min(3.0, (float) ($t['x'] ?? 0))),
                    'y'    => max(-3.0, min(3.0, (float) ($t['y'] ?? 0))),
                ];
            }
            if (!empty($remember) && function_exists('session')) {
                try { session()->put($key, $clean); } catch (\Throwable $e) {}
            }
            return $clean;
        }
        if ($raw === null && function_exists('session')) {
            try { return (array) session()->get($key, []); } catch (\Throwable $e) {}
        }
        return [];
    }

    /**
     * Overlay professional watermark on preview images.
     */
    public function applyWatermark($base, float $scale = 1.0): void
    {
        $w = imagesx($base);
        $h = imagesy($base);

        $fontPath = 'C:\\Windows\\Fonts\\arialbd.ttf';
        if (!file_exists($fontPath)) {
            $fontPath = 'C:\\Windows\\Fonts\\arial.ttf';
        }

        $bannerHeight = (int) round(54 * $scale);
        $bannerY = $h - $bannerHeight;

        $overlay = imagecreatetruecolor($w, $bannerHeight);
        $dark = imagecolorallocate($overlay, 20, 20, 25);
        imagefilledrectangle($overlay, 0, 0, $w, $bannerHeight, $dark);
        imagecopymerge($base, $overlay, 0, $bannerY, 0, 0, $w, $bannerHeight, 82);
        imagedestroy($overlay);

        $gold = imagecolorallocate($base, 255, 204, 0);

        if (file_exists($fontPath)) {
            $text = "★ PREVIEW ONLY  •  PAY TO DOWNLOAD HIGH-RESOLUTION HD ★";
            $fSize = (int) round(14 * $scale);
            $bbox = imagettfbbox($fSize, 0, $fontPath, $text);
            $tw = abs($bbox[4] - $bbox[0]);
            $tx = max(10, (int) round(($w - $tw) / 2));
            $ty = $bannerY + (int) round(34 * $scale);
            imagettftext($base, $fSize, 0, $tx, $ty, $gold, $fontPath, $text);

            $wmText = "PREVIEW ONLY";
            $wmColor = imagecolorallocatealpha($base, 255, 255, 255, 88);
            $diagSize = (int) round(22 * $scale);
            $step = (int) round(240 * $scale);
            for ($x = (int) round(-100 * $scale); $x < $w + (int) round(200 * $scale); $x += $step) {
                for ($y = (int) round(150 * $scale); $y < $h - (int) round(80 * $scale); $y += $step) {
                    imagettftext($base, $diagSize, -35, $x, $y, $wmColor, $fontPath, $wmText);
                }
            }
        } else {
            $msg = "PREVIEW ONLY - COMPLETE PAYMENT TO DOWNLOAD IN HD";
            $tx = max(20, (int) round(($w - strlen($msg) * 9 * $scale) / 2));
            imagestring($base, 5, $tx, $bannerY + (int) round(18 * $scale), $msg, $gold);
        }
    }

    /**
     * Overlay custom text on ribbon banner area
     */
    public function applyBannerText($base, string $text, ?string $subtext = null, float $scale = 1.0, ?Frame $frame = null): void
    {
        $w = imagesx($base);
        $fontPath = 'C:\\Windows\\Fonts\\georgiab.ttf';
        if (!file_exists($fontPath)) {
            $fontPath = 'C:\\Windows\\Fonts\\arialbd.ttf';
        }

        // Detect ribbon style: rose wedding has red ribbon, yellow birthday has gold ribbon
        $isRedRibbon = $frame && (str_contains(strtolower($frame->template_path ?? ''), 'rose') || str_contains(strtolower($frame->name ?? ''), 'rose'));

        if ($isRedRibbon) {
            $yPos = (int) round(472 * $scale);
            $color = imagecolorallocate($base, 255, 255, 255); // White on red ribbon
            $shadow = imagecolorallocatealpha($base, 40, 10, 10, 50); // Deep red shadow
        } else {
            $yPos = (int) round(500 * $scale);
            $color = imagecolorallocate($base, 255, 255, 255); // Crisp white
            $shadow = imagecolorallocatealpha($base, 80, 20, 20, 40);
        }

        if (file_exists($fontPath)) {
            $fontSize = (int) round(18 * $scale);
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $tw = abs($bbox[4] - $bbox[0]);
            if ($tw > $w * 0.75) {
                $fontSize = (int) round(14 * $scale);
                $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
                $tw = abs($bbox[4] - $bbox[0]);
            }
            $xPos = max(20, (int) round(($w - $tw) / 2));
            $sOff = max(1, (int) round(1.5 * $scale));

            imagettftext($base, $fontSize, 0, $xPos + $sOff, $yPos + $sOff, $shadow, $fontPath, $text);
            imagettftext($base, $fontSize, 0, $xPos, $yPos, $color, $fontPath, $text);

            if ($subtext) {
                $subSize = (int) round(14 * $scale);
                $subBbox = imagettfbbox($subSize, 0, $fontPath, $subtext);
                $subTw = abs($subBbox[4] - $subBbox[0]);
                $subX = max(20, (int) round(($w - $subTw) / 2));
                $subY = $yPos + (int) round(26 * $scale);
                imagettftext($base, $subSize, 0, $subX + $sOff, $subY + $sOff, $shadow, $fontPath, $subtext);
                imagettftext($base, $subSize, 0, $subX, $subY, $color, $fontPath, $subtext);
            }
        } else {
            $xPos = max(20, (int) round(($w - strlen($text) * 9 * $scale) / 2));
            imagestring($base, 5, $xPos, $yPos, $text, $color);
        }
    }

    /**
     * Renders complete calendar month, year, days grid, and DOB/anniversary heart highlight.
     */
    public function applyCalendarGrid($base, Frame $frame, mixed $dateInput, float $scale = 1.0): void
    {
        $selectedDay = null;
        $monthName = null;
        $year = null;
        $daysInMonth = 31;
        $firstWeekday = 0;

        if (is_numeric($dateInput)) {
            $selectedDay = (int) $dateInput;
            $monthName = strtoupper(date('F'));
            $year = date('Y');
            $time = time();
            $daysInMonth = (int) date('t', $time);
            $firstWeekday = (int) date('w', strtotime("$year-" . date('n') . "-01"));
        } else {
            $time = strtotime($dateInput);
            if (!$time) {
                $d = \DateTime::createFromFormat('d-m-Y', $dateInput);
                if ($d) $time = $d->getTimestamp();
            }
            if (!$time) {
                $d = \DateTime::createFromFormat('d/m/Y', $dateInput);
                if ($d) $time = $d->getTimestamp();
            }

            if ($time) {
                $selectedDay = (int) date('j', $time);
                $monthName = strtoupper(date('F', $time));
                $year = date('Y', $time);
                $monthNum = date('n', $time);
                $daysInMonth = (int) date('t', $time);
                $firstWeekday = (int) date('w', strtotime("$year-$monthNum-01"));
            } else {
                return;
            }
        }

        $fontPath = 'C:\\Windows\\Fonts\\arialbd.ttf';
        $serifFont = 'C:\\Windows\\Fonts\\georgiab.ttf';
        if (!file_exists($serifFont)) $serifFont = $fontPath;

        $dark = imagecolorallocate($base, 25, 25, 25);
        $red = imagecolorallocate($base, 220, 20, 60);
        $white = imagecolorallocate($base, 255, 255, 255);
        $gold = imagecolorallocate($base, 218, 165, 32);

        $templatePath = strtolower($frame->template_path ?? '');

        if (str_contains($templatePath, 'rose_wedding') || str_contains(strtolower($frame->name), 'rose')) {
            $title = "$monthName $year";
            if (file_exists($serifFont)) {
                $fSize = (int) round(16 * $scale);
                $tBbox = imagettfbbox($fSize, 0, $serifFont, $title);
                $tW = abs($tBbox[4] - $tBbox[0]);
                $tX = max(40, (int) round((250 * $scale) - $tW / 2));
                imagettftext($base, $fSize, 0, $tX + 1, (int) round(596 * $scale), $gold, $serifFont, $title);
                imagettftext($base, $fSize, 0, $tX, (int) round(595 * $scale), $red, $serifFont, $title);
            }

            $startX = 45 * $scale;
            $startY = 650 * $scale;
            $colW = 59.0 * $scale;
            $rowH = 47.5 * $scale;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $idx = ($firstWeekday + $d - 1);
                $c = $idx % 7;
                $r = (int) floor($idx / 7);
                if ($r > 4) continue;

                $cx = (int) round($startX + $c * $colW + $colW / 2);
                $cy = (int) round($startY + $r * $rowH + $rowH / 2);
                $ellR = (int) round(36 * $scale);

                if ($d == $selectedDay) {
                    imagefilledellipse($base, $cx, $cy, $ellR, $ellR, $red);
                    if (file_exists($fontPath)) {
                        $fSize = (int) round(13 * $scale);
                        $bbox = imagettfbbox($fSize, 0, $fontPath, (string)$d);
                        $tw = abs($bbox[4] - $bbox[0]);
                        imagettftext($base, $fSize, 0, (int)($cx - $tw / 2), (int)($cy + 5 * $scale), $white, $fontPath, (string)$d);
                    }
                } else {
                    if (file_exists($fontPath)) {
                        $fSize = (int) round(13 * $scale);
                        $bbox = imagettfbbox($fSize, 0, $fontPath, (string)$d);
                        $tw = abs($bbox[4] - $bbox[0]);
                        imagettftext($base, $fSize, 0, (int)($cx - $tw / 2), (int)($cy + 5 * $scale), $dark, $fontPath, (string)$d);
                    }
                }
            }
        } elseif (str_contains($templatePath, 'spiral_heart') || str_contains(strtolower($frame->name), 'spiral')) {
            $col = ($selectedDay - 1) % 7;
            $row = (int) floor(($selectedDay - 1) / 7);
            $cx = (int) round((610 + $col * 30) * $scale);
            $cy = (int) round((110 + $row * 24) * $scale);
            $d1 = (int) round(28 * $scale);
            $d2 = (int) round(26 * $scale);
            imageellipse($base, $cx, $cy, $d1, $d1, $red);
            imageellipse($base, $cx, $cy, $d2, $d2, $red);
        } else {
            $title = "$monthName $year";
            if (file_exists($serifFont)) {
                $fSize = (int) round(16 * $scale);
                $tBbox = imagettfbbox($fSize, 0, $serifFont, $title);
                $tW = abs($tBbox[4] - $tBbox[0]);
                $tX = max(50, (int) round((259 * $scale) - $tW / 2));
                imagettftext($base, $fSize, 0, $tX + 1, (int) round(626 * $scale), $gold, $serifFont, $title);
                imagettftext($base, $fSize, 0, $tX, (int) round(625 * $scale), $dark, $serifFont, $title);
            }

            $startX = 50 * $scale;
            $startY = 685 * $scale;
            $colW = 59.5 * $scale;
            $rowH = 53.5 * $scale;

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $idx = ($firstWeekday + $d - 1);
                $c = $idx % 7;
                $r = (int) floor($idx / 7);
                if ($r > 4) continue;

                $cx = (int) round($startX + $c * $colW + $colW / 2);
                $cy = (int) round($startY + $r * $rowH + $rowH / 2);
                $ellR = (int) round(38 * $scale);

                if ($d == $selectedDay) {
                    imagefilledellipse($base, $cx, $cy, $ellR, $ellR, $red);
                    if (file_exists($fontPath)) {
                        $fSize = (int) round(14 * $scale);
                        $bbox = imagettfbbox($fSize, 0, $fontPath, (string)$d);
                        $tw = abs($bbox[4] - $bbox[0]);
                        imagettftext($base, $fSize, 0, (int)($cx - $tw / 2), (int)($cy + 6 * $scale), $white, $fontPath, (string)$d);
                    }
                } else {
                    if (file_exists($fontPath)) {
                        $fSize = (int) round(14 * $scale);
                        $bbox = imagettfbbox($fSize, 0, $fontPath, (string)$d);
                        $tw = abs($bbox[4] - $bbox[0]);
                        imagettftext($base, $fSize, 0, (int)($cx - $tw / 2), (int)($cy + 6 * $scale), $dark, $fontPath, (string)$d);
                    }
                }
            }
        }
    }
}