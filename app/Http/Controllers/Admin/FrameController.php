<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Frame;
use App\Services\FrameTemplateService;
use App\Services\SlotDetector;
use Illuminate\Http\Request;

class FrameController extends Controller
{
    public function __construct(private FrameTemplateService $templates) {}

    public function index(Request $request)
    {
        $query = Frame::latest();

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $frames = $query->paginate(12)->withQueryString();
        $categories = Frame::distinct()->pluck('category')->filter()->values();

        return view('admin.frames.index', compact('frames', 'categories'));
    }

    public function create()
    {
        return view('admin.frames.create');
    }

    public function store(Request $request)
    {
        // The template is uploaded first; if the browser did not send any slots,
        // they are detected automatically from the uploaded template (any shape).
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'category'       => 'required|string|max:100',
            'price'          => 'nullable|numeric|min:0',
            'description'    => 'nullable|string',
            'template'       => 'required|file|mimes:jpeg,jpg,png,gif,webp,bmp,psd|max:12288',
            'slot_positions' => 'nullable|json',
        ]);

        $template = $this->templates->store($request->file('template'));
        $templateFile = storage_path('app/public/' . $template['template_path']);

        $slots = $this->resolveSlots($validated['slot_positions'] ?? null, $templateFile);
        if (empty($slots)) {
            $this->templates->delete($template['template_path'], $template['template_source_path']);
            $message = $request->filled('slot_positions')
                ? 'You must have at least one slot defined.'
                : 'No openings could be detected automatically in this template. Please draw the slots manually.';
            return back()->withInput()->with('error', $message);
        }

        $data = [
            'name'             => $validated['name'],
            'category'         => $validated['category'],
            'price'            => $validated['price'] ?: null,
            'description'      => $validated['description'] ?? null,
            'total_slots'      => count($slots),
            ...$template,
            'slot_positions'   => $slots,
            'is_active'        => true,
        ];

        Frame::create($data);

        return redirect()->route('admin.frames.index')->with('success', 'Frame template created successfully.');
    }

    public function edit(Frame $frame)
    {
        return view('admin.frames.edit', compact('frame'));
    }

    public function update(Request $request, Frame $frame)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'category'       => 'required|string|max:100',
            'price'          => 'nullable|numeric|min:0',
            'description'    => 'nullable|string',
            'template'       => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,bmp,psd|max:12288',
            'slot_positions' => 'nullable|json',
            'is_active'      => 'nullable|boolean',
        ]);

        $newTemplate = $request->hasFile('template') ? $this->templates->store($request->file('template')) : null;
        $templateFile = $newTemplate
            ? storage_path('app/public/' . $newTemplate['template_path'])
            : ($frame->template_path ? storage_path('app/public/' . $frame->template_path) : null);

        // Slots: use what the browser sent; if a new template was uploaded without usable slots, detect them
        $slots = null;
        $rawSlots = $validated['slot_positions'] ?? null;
        if ($rawSlots !== null && $rawSlots !== '') {
            $slots = $this->resolveSlots($rawSlots, $templateFile, false);
            if (empty($slots) && $newTemplate) {
                $slots = $this->resolveSlots(null, $templateFile);
            }
            if (empty($slots)) {
                if ($newTemplate) $this->templates->delete($newTemplate['template_path'], $newTemplate['template_source_path']);
                return back()->withInput()->with('error', 'You must have at least one slot defined.');
            }
        } elseif ($newTemplate) {
            $slots = $this->resolveSlots(null, $templateFile);
            if (empty($slots)) {
                $this->templates->delete($newTemplate['template_path'], $newTemplate['template_source_path']);
                return back()->withInput()->with('error', 'No openings could be detected automatically in the new template. Please draw the slots manually.');
            }
        }

        $data = [
            'name'        => $validated['name'],
            'category'    => $validated['category'],
            'price'       => $validated['price'] ?: null,
            'description' => $validated['description'] ?? null,
            'is_active'   => $request->has('is_active'),
        ];

        if ($slots !== null) {
            $data['total_slots']    = count($slots);
            $data['slot_positions'] = $slots;
        }

        if ($newTemplate) {
            $oldTemplatePath = $frame->template_path;
            $oldSourcePath = $frame->template_source_path;
            $data = array_merge($data, $newTemplate);
            $frame->update($data);
            $this->templates->delete($oldTemplatePath, $oldSourcePath);

            return redirect()->route('admin.frames.index')->with('success', 'Frame template updated successfully.');
        }

        $frame->update($data);

        return redirect()->route('admin.frames.index')->with('success', 'Frame template updated successfully.');
    }

    public function toggleActive(Frame $frame)
    {
        $frame->update(['is_active' => !$frame->is_active]);
        return back()->with('success', 'Frame status updated.');
    }

    public function destroy(Frame $frame)
    {
        $this->templates->delete($frame->template_path, $frame->template_source_path);
        $frame->delete();
        return redirect()->route('admin.frames.index')->with('success', 'Frame template deleted successfully.');
    }

    public function detectSlots(Request $request)
    {
        if ($request->hasFile('template')) {
            $request->validate([
                'template' => 'required|file|mimes:jpeg,jpg,png,gif,webp,bmp,psd|max:12288',
            ]);
            $options = [];
            if ($request->filled('edge_threshold')) {
                $options['edge_threshold'] = (int) $request->edge_threshold;
            }

            $result = $this->templates->detectUploaded($request->file('template'), $options);

            if (empty($result['slots'])) {
                $result['message'] = 'No openings detected automatically. Please draw boxes manually.';
            }

            return response()->json($result);
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
     * Handy for old frames whose slots were detected as plain rectangles
     * (for example the "heart" frame that got 41 small rectangles).
     */
    public function redetectSlots(Frame $frame)
    {
        if (!$frame->template_path) {
            return back()->with('error', 'This frame has no template image yet.');
        }

        $slots = $this->resolveSlots(null, storage_path('app/public/' . $frame->template_path));
        if (empty($slots)) {
            return back()->with('error', 'No openings could be detected automatically. Please draw the slots manually.');
        }

        $frame->update([
            'total_slots'    => count($slots),
            'slot_positions' => $slots,
        ]);

        return back()->with('success', 'Slots re-detected: ' . count($slots) . ' openings found.');
    }

    /**
     * Returns the cleaned slot list.
     *  - If the browser sent a slot list, it is cleaned (and heart/polygon outlines are restored if missing).
     *  - Otherwise (or when nothing usable was sent and $detectIfEmpty is true) the slots are detected from the template.
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
}
