<?php

namespace App\Http\Controllers;

use App\Models\Frame;
use App\Services\FrameCompositor;
use Illuminate\Http\Request;

class ComposeController extends Controller
{
    public function __construct(private FrameCompositor $compositor) {}

    public function index()
    {
        $frames = Frame::withCount('images')->get();
        return view('compose.index', compact('frames'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'frame_id' => 'required|exists:frames,id',
            'images'   => 'required|array|max:10',
            'images.*' => 'required|image|mimes:jpeg,jpg,png,gif,webp,bmp|max:8192',
        ]);

        $frame = Frame::findOrFail($request->frame_id);

        if (!$frame->template_path) {
            return redirect()->route('compose.index')
                ->with('error', 'This frame has no template image. Please upload one first.');
        }

        $occupied = $frame->images()->pluck('position')->all();
        $freePositions = array_diff(range(1, $frame->total_slots), $occupied);
        sort($freePositions);

        if (count($request->file('images')) > count($freePositions)) {
            return redirect()->route('compose.index')
                ->with('error', 'Not enough free slots. Only ' . count($freePositions) . ' slots available.');
        }

        foreach ($request->file('images') as $file) {
            $position = array_shift($freePositions);
            try {
                $path = $file->store('frame_images', 'public');
                $frame->images()->create(['image_path' => $path, 'position' => $position]);
            } catch (\Exception $e) {
                return redirect()->route('compose.index')->with('error', 'Failed to upload image.');
            }
        }

        $frame->load('images');
        $image = $this->compositor->render($frame);
        if (!$image) {
            return redirect()->route('compose.index')
                ->with('error', 'Unable to generate composite. Unsupported image format?');
        }

        ob_start();
        imagejpeg($image, null, 90);
        $output = ob_get_clean();
        imagedestroy($image);

        return response($output)
            ->header('Content-Type', 'image/jpeg')
            ->header('Content-Disposition', 'inline; filename="frame_' . $frame->id . '.jpg"');
    }
}