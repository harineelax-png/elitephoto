<?php

namespace App\Services;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadNormalizer
{
    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    public function storeAsPng(UploadedFile $file, string $directory): ?string
    {
        $image = $this->imageManager
            ->decodePath($file->getRealPath())
            ->orient()
            ->removeAnimation();
        $png = $image->encodeUsingFormat(Format::PNG)->toString();
        $path = trim($directory, '/') . '/' . Str::uuid() . '.png';

        return Storage::disk('public')->put($path, $png) ? $path : null;
    }
}
