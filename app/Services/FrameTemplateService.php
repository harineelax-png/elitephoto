<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FrameTemplateService
{
    public function store(UploadedFile $file): array
    {
        if (!$this->isPsd($file)) {
            $path = $file->store('frame_templates', 'public');
            $dimensions = getimagesize(Storage::disk('public')->path($path));

            if ($dimensions === false) {
                Storage::disk('public')->delete($path);
                throw ValidationException::withMessages([
                    'template' => 'The selected template could not be read as an image.',
                ]);
            }

            return [
                'template_path' => $path,
                'template_source_path' => null,
                'template_width' => $dimensions[0],
                'template_height' => $dimensions[1],
            ];
        }

        [$png, $width, $height] = $this->rasterizePsd($file->getRealPath());
        $sourcePath = $file->store('frame_templates', 'public');
        $previewPath = 'frame_templates/' . pathinfo($sourcePath, PATHINFO_FILENAME) . '.png';

        if (!Storage::disk('public')->put($previewPath, $png)) {
            Storage::disk('public')->delete($sourcePath);
            throw new RuntimeException('Could not save the PSD template preview.');
        }

        return [
            'template_path' => $previewPath,
            'template_source_path' => $sourcePath,
            'template_width' => $width,
            'template_height' => $height,
        ];
    }

    public function detectUploaded(UploadedFile $file, array $options = []): array
    {
        if (!$this->isPsd($file)) {
            return (new SlotDetector())->detect($file->getRealPath(), $options);
        }

        [$png] = $this->rasterizePsd($file->getRealPath());
        $temporaryPath = tempnam(sys_get_temp_dir(), 'frame-template-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Could not create a temporary PSD preview for slot detection.');
        }

        try {
            if (file_put_contents($temporaryPath, $png) === false) {
                throw new RuntimeException('Could not prepare the PSD preview for slot detection.');
            }

            $result = (new SlotDetector())->detect($temporaryPath, $options);
            $result['preview'] = 'data:image/png;base64,' . base64_encode($png);

            return $result;
        } finally {
            unlink($temporaryPath);
        }
    }

    public function delete(?string ...$paths): void
    {
        Storage::disk('public')->delete(array_values(array_filter($paths)));
    }

    private function isPsd(UploadedFile $file): bool
    {
        return strtolower($file->guessExtension() ?? '') === 'psd';
    }

    private function rasterizePsd(string $path): array
    {
        if (!class_exists(\Imagick::class)) {
            throw ValidationException::withMessages([
                'template' => 'PSD templates require the PHP Imagick extension. Install or enable Imagick, then try again.',
            ]);
        }

        $psd = null;
        $merged = null;

        try {
            $psd = new \Imagick();
            $psd->readImage($path);
            $merged = $psd->mergeImageLayers(\Imagick::LAYERMETHOD_MERGE);
            $merged->setImageFormat('png');
            $png = $merged->getImageBlob();

            return [$png, $merged->getImageWidth(), $merged->getImageHeight()];
        } catch (\ImagickException $exception) {
            throw ValidationException::withMessages([
                'template' => 'The PSD template could not be converted. Please check the file or upload an image instead.',
            ]);
        } finally {
            if ($merged instanceof \Imagick) {
                $merged->clear();
            }
            if ($psd instanceof \Imagick) {
                $psd->clear();
            }
        }
    }
}
