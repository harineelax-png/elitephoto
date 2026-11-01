<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoshootPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoshootPlanController extends Controller
{
    public function index()
    {
        $plans = PhotoshootPlan::orderBy('sort_order')->orderBy('id')->get();
        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'price'        => 'required|numeric|min:0',
            'duration'     => 'required|string|max:100',
            'deliverables' => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'features'     => 'nullable|string', // newline-separated string
            'badge'        => 'nullable|string|max:50',
            'sort_order'   => 'nullable|integer',
            'is_active'    => 'nullable|boolean',
            'image'        => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
        ]);

        $featuresArray = [];
        if (!empty($validated['features'])) {
            $featuresArray = array_values(array_filter(array_map('trim', explode("\n", $validated['features']))));
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->storeImage($request->file('image'));
        }

        PhotoshootPlan::create([
            'name'         => $validated['name'],
            'price'        => $validated['price'],
            'duration'     => $validated['duration'],
            'deliverables' => $validated['deliverables'] ?? null,
            'description'  => $validated['description'] ?? null,
            'features'     => $featuresArray,
            'badge'        => $validated['badge'] ?? null,
            'image_url'    => $imagePath,
            'sort_order'   => $validated['sort_order'] ?? 0,
            'is_active'    => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.plans.index')->with('success', 'Photoshoot plan created successfully.');
    }

    public function edit(PhotoshootPlan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, PhotoshootPlan $plan)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'price'        => 'required|numeric|min:0',
            'duration'     => 'required|string|max:100',
            'deliverables' => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'features'     => 'nullable|string',
            'badge'        => 'nullable|string|max:50',
            'sort_order'   => 'nullable|integer',
            'is_active'    => 'nullable|boolean',
            'image'        => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
        ]);

        $featuresArray = [];
        if (!empty($validated['features'])) {
            $featuresArray = array_values(array_filter(array_map('trim', explode("\n", $validated['features']))));
        }

        // --- Image handling ---------------------------------------------------
        $imagePath = $plan->getRawOriginal('image_url'); // keep current by default

        // New image uploaded → delete old, save new
        if ($request->hasFile('image')) {
            $this->deleteStoredImage($plan);
            $imagePath = $this->storeImage($request->file('image'));
        }
        // Explicit remove request (checkbox in edit form)
        elseif ($request->boolean('remove_image')) {
            $this->deleteStoredImage($plan);
            $imagePath = null;
        }
        // --- ------------------------------------------------------------------

        $plan->update([
            'name'         => $validated['name'],
            'price'        => $validated['price'],
            'duration'     => $validated['duration'],
            'deliverables' => $validated['deliverables'] ?? null,
            'description'  => $validated['description'] ?? null,
            'features'     => $featuresArray,
            'badge'        => $validated['badge'] ?? null,
            'image_url'    => $imagePath,
            'sort_order'   => $validated['sort_order'] ?? 0,
            'is_active'    => $request->has('is_active'),
        ]);

        return redirect()->route('admin.plans.index')->with('success', 'Photoshoot plan updated successfully.');
    }

    public function toggleActive(PhotoshootPlan $plan)
    {
        $plan->update(['is_active' => !$plan->is_active]);
        return back()->with('success', 'Plan status updated.');
    }

    public function destroy(PhotoshootPlan $plan)
    {
        $this->deleteStoredImage($plan);
        $plan->delete();
        return redirect()->route('admin.plans.index')->with('success', 'Photoshoot plan deleted successfully.');
    }

    /* ---------------------------------------------------------------
     |  Private helpers — image storage
     * --------------------------------------------------------------- */

    /**
     * Save the uploaded image to storage/app/public/plans
     * and return the relative path (e.g. "plans/abc123.jpg").
     */
    private function storeImage($file): string
    {
        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('plans', $filename, 'public');
    }

    /**
     * Delete the plan's existing image file from disk (safe to call).
     */
    private function deleteStoredImage(PhotoshootPlan $plan): void
    {
        $raw = $plan->getRawOriginal('image_url');

        if (empty($raw) || str_starts_with($raw, 'http')) {
            return; // nothing to delete, or legacy full URL
        }

        if (Storage::disk('public')->exists($raw)) {
            Storage::disk('public')->delete($raw);
        }
    }
}