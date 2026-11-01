<?php

namespace App\Http\Controllers;

use App\Models\Frame;
use App\Services\SlotDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FrameController extends Controller
{
    public function index()
    {
        $frames = Frame::latest()->paginate(10);
        return view('frames.index', compact('frames'));
    }

    public function create()
    {
        return view('frames.create');
    }

    public function store(Request $request)
    {
        // The template is uploaded first; if the browser did not send any slots,
        // they are detected automatically from the uploaded template (any shape).
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'template'       => 'required|image|mimes:jpeg,jpg,png,gif,webp,bmp|max:8192',
            'slot_positions' => 'nullable|json',
        ]);

        $path = $request->file('template')->store('frame_templates', 'public');
        $fullPath = storage_path('app/public/' . $path);
        [$width, $height] = getimagesize($fullPath);

        $slots = $this->resolveSlots($validated['slot_positions'] ?? null, $fullPath);
        if (empty($slots)) {
            Storage::disk('public')->delete($path);
            $message = $request->filled('slot_positions')
                ? 'You must have at least one slot defined.'
                : 'No openings could be detected automatically in this template. Please draw the slots manually.';
            return back()->withInput()->with('error', $message);
        }

        $isUniform = $this->detectUniform($slots);

        $data = [
            'name'             => $validated['name'],
            'total_slots'      => count($slots),
            'template_path'    => $path,
            'template_width'   => $width,
            'template_height'  => $height,
            'is_uniform_slots' => $isUniform,
        ];

        $this->applySlotData($data, $slots, $isUniform);

        Frame::create($data);

        return redirect()->route('frames.index')->with('success', 'Frame created successfully.');
    }

    public function show(Frame $frame)
    {
        $frame->load('images');
        return view('frames.show', compact('frame'));
    }

    public function edit(Frame $frame)
    {
        return view('frames.edit', compact('frame'));
    }

    public function update(Request $request, Frame $frame)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'template'       => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,bmp|max:8192',
            'slot_positions' => 'nullable|json',
        ]);

        $data = [
            'name' => $validated['name'],
        ];

        $newTemplatePath = null;
        $detectPath = $frame->template_path ? storage_path('app/public/' . $frame->template_path) : null;

        if ($request->hasFile('template')) {
            $newTemplatePath = $request->file('template')->store('frame_templates', 'public');
            $detectPath = storage_path('app/public/' . $newTemplatePath);
            [$width, $height] = getimagesize($detectPath);
            $data['template_path']   = $newTemplatePath;
            $data['template_width']  = $width;
            $data['template_height'] = $height;
        }

        // Slots: use what the browser sent; if a new template was uploaded without usable slots, detect them
        $slots = null;
        $rawSlots = $validated['slot_positions'] ?? null;
        if ($rawSlots !== null && $rawSlots !== '') {
            $slots = $this->resolveSlots($rawSlots, $detectPath, false);
            if (empty($slots)) {
                if ($newTemplatePath) {
                    $slots = $this->resolveSlots(null, $detectPath);
                }
                if (empty($slots)) {
                    if ($newTemplatePath) Storage::disk('public')->delete($newTemplatePath);
                    return back()->withInput()->with('error', 'You must have at least one slot defined.');
                }
            }
        } elseif ($newTemplatePath) {
            $slots = $this->resolveSlots(null, $detectPath);
            if (empty($slots)) {
                Storage::disk('public')->delete($newTemplatePath);
                return back()->withInput()->with('error', 'No openings could be detected automatically in the new template. Please draw the slots manually.');
            }
        }

        if ($slots !== null) {
            $data['total_slots'] = count($slots);
            $isUniform = $this->detectUniform($slots);
            $data['is_uniform_slots'] = $isUniform;
            $this->applySlotData($data, $slots, $isUniform);
        }

        if ($newTemplatePath && $frame->template_path) {
            Storage::disk('public')->delete($frame->template_path);
        }

        $frame->update($data);

        return redirect()->route('frames.index')->with('success', 'Frame updated successfully.');
    }

    public function destroy(Frame $frame)
    {
        if ($frame->template_path) {
            Storage::disk('public')->delete($frame->template_path);
        }
        $frame->delete();
        return redirect()->route('frames.index')->with('success', 'Frame deleted successfully.');
    }

    /**
     * AJAX endpoint: auto-detect slot openings (rectangles, circles, hearts, any shape)
     * from either a freshly uploaded template file, or an already-saved frame's template.
     */
    public function detectSlots(Request $request)
    {
        if ($request->hasFile('template')) {
            $request->validate([
                'template' => 'required|image|mimes:jpeg,jpg,png,gif,webp,bmp|max:8192',
            ]);
            $path = $request->file('template')->getRealPath();
        } elseif ($request->filled('frame_id')) {
            $frame = Frame::findOrFail($request->frame_id);
            if (!$frame->template_path) {
                return response()->json(['width' => 0, 'height' => 0, 'slots' => [], 'message' => 'This frame has no template image yet.']);
            }
            $path = storage_path('app/public/' . $frame->template_path);
        } else {
            return response()->json(['width' => 0, 'height' => 0, 'slots' => [], 'message' => 'No image provided.'], 422);
        }

        $options = [];
        if ($request->filled('edge_threshold')) {
            $options['edge_threshold'] = (int) $request->edge_threshold;
        }

        $result = (new SlotDetector())->detect($path, $options);

        if (empty($result['slots'])) {
            $result['message'] = 'No openings detected automatically. Please draw boxes manually.';
        }

        return response()->json($result);
    }

    /**
     * Re-runs slot detection on an already saved frame's template and replaces its slots.
     * Handy for old frames whose slots were detected as plain rectangles.
     */
    public function redetectSlots(Frame $frame)
    {
        if (!$frame->template_path) {
            return redirect()->route('frames.index')->with('error', 'This frame has no template image yet.');
        }

        $slots = $this->resolveSlots(null, storage_path('app/public/' . $frame->template_path));
        if (empty($slots)) {
            return redirect()->route('frames.index')->with('error', 'No openings could be detected automatically. Please draw the slots manually.');
        }

        $isUniform = $this->detectUniform($slots);
        $data = [
            'total_slots'      => count($slots),
            'is_uniform_slots' => $isUniform,
        ];
        $this->applySlotData($data, $slots, $isUniform);
        $frame->update($data);

        return redirect()->route('frames.index')->with('success', 'Slots re-detected: ' . count($slots) . ' openings found.');
    }

    /**
     * Returns the cleaned slot list.
     *  - If the browser sent a slot list, it is cleaned (and heart/polygon outlines are restored if missing).
     *  - Otherwise (or when $detectIfEmpty is true and nothing usable was sent) the slots are detected from the template.
     */
    private function resolveSlots(?string $json, ?string $templatePath, bool $detectIfEmpty = true): array
    {
        $detector = new SlotDetector();

        $slots = ($json !== null && $json !== '') ? json_decode($json, true) : null;
        if (is_array($slots) && count($slots) >= 1) {
            return $detector->normalizeSlots($slots, $templatePath);
        }

        if (!$detectIfEmpty || !$templatePath || !is_file($templatePath)) {
            return [];
        }

        $detected = $detector->detect($templatePath)['slots'] ?? [];
        return $detector->normalizeSlots($detected);
    }

    /**
     * Apply slot data to the frame data array.
     * If slots are uniform, store width/height globally; otherwise store per-slot dimensions.
     * Always store shape and rotation per slot, plus the outline ("points") of heart / polygon slots.
     */
    private function applySlotData(array &$data, array $slots, bool $isUniform): void
    {
        if ($isUniform) {
            $data['slot_width']  = (int) round($slots[0]['width']);
            $data['slot_height'] = (int) round($slots[0]['height']);
            $data['slot_positions'] = array_map(function ($s) {
                $slot = [
                    'x'        => (int) round($s['x']),
                    'y'        => (int) round($s['y']),
                    'shape'    => $s['shape'] ?? 'rectangle',
                    'rotation' => (float) ($s['rotation'] ?? 0),
                ];
                if (!empty($s['points'])) $slot['points'] = $s['points'];
                return $slot;
            }, $slots);
        } else {
            $data['slot_width']  = null;
            $data['slot_height'] = null;
            $data['slot_positions'] = array_map(function ($s) {
                $slot = [
                    'x'        => (int) round($s['x']),
                    'y'        => (int) round($s['y']),
                    'width'    => (int) round($s['width']),
                    'height'   => (int) round($s['height']),
                    'shape'    => $s['shape'] ?? 'rectangle',
                    'rotation' => (float) ($s['rotation'] ?? 0),
                ];
                if (!empty($s['points'])) $slot['points'] = $s['points'];
                return $slot;
            }, $slots);
        }
    }

    /**
     * Detect if all slots have the same width, height, and shape.
     * Width and height are compared with a tolerance of 1 pixel.
     * Slots with a custom outline (heart / polygon) are never treated as uniform,
     * because every outline is different.
     */
    private function detectUniform(array $slots): bool
    {
        $firstW = round($slots[0]['width']);
        $firstH = round($slots[0]['height']);
        $firstShape = $slots[0]['shape'] ?? 'rectangle';

        foreach ($slots as $s) {
            if (!empty($s['points'])) {
                return false;
            }
            if (abs(round($s['width']) - $firstW) > 1 || abs(round($s['height']) - $firstH) > 1) {
                return false;
            }
            if (($s['shape'] ?? 'rectangle') !== $firstShape) {
                return false;
            }
        }
        return true;
    }
}
