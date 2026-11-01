<?php

namespace App\Http\Controllers;

use App\Models\Frame;
use App\Models\FrameImage;
use App\Services\FrameCompositor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FrameImageController extends Controller
{
    public function __construct(private FrameCompositor $compositor) {}

    public function store(Request $request, Frame $frame)
    {
        $request->validate([
            'images'   => 'required|array|max:' . $frame->total_slots,
            'images.*' => 'required|image|mimes:jpeg,jpg,png,gif,webp,bmp|max:8192',
        ]);

        $occupied = $frame->images()->pluck('position')->all();
        $freePositions = array_diff(range(1, $frame->total_slots), $occupied);
        sort($freePositions);

        if (count($request->file('images')) > count($freePositions)) {
            return redirect()->route('frames.show', $frame)->with('error', 'Not enough free slots.');
        }

        foreach ($request->file('images') as $file) {
            $position = array_shift($freePositions);
            try {
                $path = $file->store('frame_images', 'public');
                $frame->images()->create(['image_path' => $path, 'position' => $position]);
            } catch (\Exception $e) {
                return redirect()->route('frames.show', $frame)->with('error', 'Error uploading image: ' . $e->getMessage());
            }
        }

        return redirect()->route('frames.show', $frame)->with('success', 'Images uploaded and placed automatically.');
    }

    public function destroy(Frame $frame, FrameImage $image)
    {
        if ($image->frame_id !== $frame->id) abort(404);

        Storage::disk('public')->delete($image->image_path);
        $image->delete();
        $this->reorderPositions($frame);

        return redirect()->route('frames.show', $frame)->with('success', 'Image deleted successfully.');
    }

    public function updatePosition(Request $request, Frame $frame, FrameImage $image)
    {
        if ($image->frame_id !== $frame->id) abort(404);

        $request->validate(['position' => 'required|integer|min:1|max:' . $frame->total_slots]);

        $newPos = $request->position;
        $oldPos = $image->position;

        if ($newPos == $oldPos) {
            return redirect()->route('frames.show', $frame)->with('info', 'Position unchanged.');
        }

        if ($newPos > $oldPos) {
            FrameImage::where('frame_id', $frame->id)->whereBetween('position', [$oldPos + 1, $newPos])->decrement('position');
        } else {
            FrameImage::where('frame_id', $frame->id)->whereBetween('position', [$newPos, $oldPos - 1])->increment('position');
        }

        $image->position = $newPos;
        $image->save();

        return redirect()->route('frames.show', $frame)->with('success', 'Image position updated.');
    }

    public function generate(Frame $frame)
    {
        $frame->load('images');
        if (!$frame->template_path) {
            return redirect()->route('frames.show', $frame)->with('error', 'No template uploaded for this frame.');
        }
        if ($frame->images->count() != $frame->total_slots) {
            return redirect()->route('frames.show', $frame)->with('error', 'All slots must be filled before generating.');
        }

        $image = $this->compositor->render($frame);
        if (!$image) {
            return redirect()->route('frames.show', $frame)->with('error', 'Could not read the template image.');
        }

        $outputPath = 'generated/frame_' . $frame->id . '_' . time() . '.jpg';
        $fullPath = storage_path('app/public/' . $outputPath);
        imagejpeg($image, $fullPath, 90);
        imagedestroy($image);

        return response()->download($fullPath)->deleteFileAfterSend(true);
    }

    private function reorderPositions(Frame $frame)
    {
        $images = $frame->images()->orderBy('position')->get();
        $position = 1;
        foreach ($images as $image) {
            $image->position = $position++;
            $image->save();
        }
    }
}