<?php

namespace Tests\Unit;

use App\Services\ImageUploadNormalizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadNormalizerTest extends TestCase
{
    public function test_it_converts_uploaded_jpeg_files_to_png(): void
    {
        Storage::fake('public');
        $temporaryPath = tempnam(sys_get_temp_dir(), 'studio-jpeg-');
        $image = imagecreatetruecolor(40, 20);
        imagefill($image, 0, 0, imagecolorallocate($image, 0, 0, 255));
        imagejpeg($image, $temporaryPath);
        imagedestroy($image);
        $upload = new UploadedFile($temporaryPath, 'photo.jpg', 'image/jpeg', null, true);

        try {
            $path = (new ImageUploadNormalizer())->storeAsPng($upload, 'temp_previews/test');

            $this->assertNotNull($path);
            $this->assertStringEndsWith('.png', $path);
            $storedImage = getimagesizefromstring(Storage::disk('public')->get($path));
            $this->assertSame(IMAGETYPE_PNG, $storedImage[2]);
            $this->assertSame([40, 20], [$storedImage[0], $storedImage[1]]);
        } finally {
            unlink($temporaryPath);
        }
    }
}
