<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Frame;
use App\Services\FrameCompositor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BatchGeneratorController extends Controller
{
    public function __construct(private FrameCompositor $compositor) {}

    public function index()
    {
        $frames = Frame::where('is_active', true)
            ->whereNotNull('template_path')
            ->latest()
            ->get();

        return view('admin.batch.index', compact('frames'));
    }

    /**
     * Accepts a chunk of photos (up to 20 per request to stay within PHP limits),
     * applies them to the chosen theme, and saves into a batch folder.
     */
    public function processChunk(Request $request)
    {
        $request->validate([
            'frame_id'    => 'required|exists:frames,id',
            'batch_id'    => 'required|string',
            'mode'        => 'required|in:pack,solo',
            'banner_text' => 'nullable|string|max:100',
            'calendar_day'=> 'nullable|integer|between:1,31',
            'photos'      => 'required|array',
            'photos.*'    => 'required|image|mimes:jpeg,jpg,png,webp,bmp|max:12288',
        ]);

        $frame = Frame::findOrFail($request->frame_id);
        $batchId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $request->batch_id);
        $batchDir = storage_path('app/public/batch_generated/' . $batchId);
        $tempUploadsDir = $batchDir . '/raw_uploads';

        if (!is_dir($batchDir)) mkdir($batchDir, 0777, true);
        if (!is_dir($tempUploadsDir)) mkdir($tempUploadsDir, 0777, true);

        // Save uploaded photos
        $uploadedPaths = [];
        foreach ($request->file('photos') as $file) {
            $name = uniqid('raw_') . '.' . $file->getClientOriginalExtension();
            $file->move($tempUploadsDir, $name);
            $uploadedPaths[] = $tempUploadsDir . '/' . $name;
        }

        $options = [
            'watermark'    => false, // ADMIN GENERATION HAS NO WATERMARK & NO PAYMENT
            'banner_text'  => $request->banner_text,
            'calendar_day' => $request->calendar_day,
        ];

        $generatedResults = [];
        $totalSlots = max(1, (int) $frame->total_slots);

        if ($request->mode === 'pack') {
            // Group photos into chunks equal to total_slots
            $chunks = array_chunk($uploadedPaths, $totalSlots);
            foreach ($chunks as $chunk) {
                // If chunk has fewer photos than slots, repeat photos to fill remaining slots
                $slotImages = [];
                for ($i = 0; $i < $totalSlots; $i++) {
                    $slotImages[$i] = $chunk[$i % count($chunk)];
                }

                $genName = 'framed_' . uniqid() . '.jpg';
                $outPath = $batchDir . '/' . $genName;

                if ($this->compositor->renderAndSave($frame, $slotImages, $outPath, $options)) {
                    $generatedResults[] = [
                        'file_name' => $genName,
                        'url'       => asset('storage/batch_generated/' . $batchId . '/' . $genName),
                    ];
                }
            }
        } else {
            // Solo mode: 1 composite per photo
            foreach ($uploadedPaths as $photoPath) {
                $slotImages = [];
                for ($i = 0; $i < $totalSlots; $i++) {
                    $slotImages[$i] = $photoPath;
                }

                $genName = 'framed_' . uniqid() . '.jpg';
                $outPath = $batchDir . '/' . $genName;

                if ($this->compositor->renderAndSave($frame, $slotImages, $outPath, $options)) {
                    $generatedResults[] = [
                        'file_name' => $genName,
                        'url'       => asset('storage/batch_generated/' . $batchId . '/' . $genName),
                    ];
                }
            }
        }

        return response()->json([
            'status'    => 'success',
            'generated' => $generatedResults,
            'count'     => count($generatedResults),
            'batch_id'  => $batchId,
        ]);
    }

    /**
     * Bundles all generated images from a batch into a ZIP archive and triggers download.
     */
    public function downloadZip(string $batchId)
    {
        $batchId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $batchId);
        $batchDir = storage_path('app/public/batch_generated/' . $batchId);

        if (!is_dir($batchDir)) {
            return back()->with('error', 'Batch directory not found.');
        }

        $zipFile = $batchDir . '/batch_' . $batchId . '_framed_photos.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = glob($batchDir . '/framed_*.jpg');
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        } else {
            return back()->with('error', 'Unable to create zip file.');
        }

        if (file_exists($zipFile)) {
            return response()->download($zipFile);
        }

        return back()->with('error', 'Zip file not found.');
    }
}
