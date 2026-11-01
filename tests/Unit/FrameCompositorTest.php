<?php

namespace Tests\Unit;

use App\Models\Frame;
use App\Services\FrameCompositor;
use Tests\TestCase;

class FrameCompositorTest extends TestCase
{
    public function test_it_composites_circle_slots_with_transparent_corners(): void
    {
        $directory = storage_path('app/public/compositor-tests-' . uniqid('render-', true));
        mkdir($directory, 0777, true);
        $templatePath = $directory . '/template.png';
        $photoPath = $directory . '/photo.png';

        $template = imagecreatetruecolor(600, 600);
        imagefill($template, 0, 0, imagecolorallocate($template, 255, 0, 0));
        imagepng($template, $templatePath);
        imagedestroy($template);

        $photo = imagecreatetruecolor(24, 24);
        imagefill($photo, 0, 0, imagecolorallocate($photo, 0, 255, 0));
        imagepng($photo, $photoPath);
        imagedestroy($photo);

        try {
            $slots = [];
            for ($index = 0; $index < 50; $index++) {
                $slots[] = [
                    'x' => 10 + ($index % 10) * 58,
                    'y' => 10 + intdiv($index, 10) * 110,
                    'width' => 46,
                    'height' => 46,
                    'shape' => 'circle',
                    'rotation' => 0,
                ];
            }

            $frame = new Frame([
                'template_path' => str_replace(storage_path('app/public/'), '', $templatePath),
                'slot_positions' => $slots,
            ]);

            $result = (new FrameCompositor())->renderFromImages($frame, array_fill(0, 50, $photoPath));

            $this->assertNotFalse($result);
            $center = imagecolorat($result, 33, 33);
            $corner = imagecolorat($result, 10, 10);
            $this->assertSame([0, 255, 0], [($center >> 16) & 0xff, ($center >> 8) & 0xff, $center & 0xff]);
            $this->assertSame([255, 0, 0], [($corner >> 16) & 0xff, ($corner >> 8) & 0xff, $corner & 0xff]);
            imagedestroy($result);
        } finally {
            unlink($templatePath);
            unlink($photoPath);
            rmdir($directory);
        }
    }

    public function test_it_fills_a_slot_to_the_edges_without_distorting_the_photo(): void
    {
        $directory = storage_path('app/public/compositor-tests-' . uniqid('fit-', true));
        mkdir($directory, 0777, true);
        $templatePath = $directory . '/template.png';
        $photoPath = $directory . '/photo.png';

        $template = imagecreatetruecolor(40, 40);
        imagefill($template, 0, 0, imagecolorallocate($template, 255, 0, 0));
        imagepng($template, $templatePath);
        imagedestroy($template);

        $photo = imagecreatetruecolor(40, 20);
        imagefilledrectangle($photo, 0, 0, 19, 19, imagecolorallocate($photo, 0, 0, 255));
        imagefilledrectangle($photo, 20, 0, 39, 19, imagecolorallocate($photo, 0, 255, 0));
        imagepng($photo, $photoPath);
        imagedestroy($photo);

        try {
            $frame = new Frame([
                'template_path' => str_replace(storage_path('app/public/'), '', $templatePath),
                'slot_positions' => [[
                    'x' => 10,
                    'y' => 10,
                    'width' => 20,
                    'height' => 20,
                    'shape' => 'rectangle',
                    'rotation' => 0,
                ]],
            ]);

            $result = (new FrameCompositor())->renderFromImages($frame, [$photoPath]);

            $this->assertNotFalse($result);
            $leftPixel = imagecolorat($result, 11, 20);
            $rightPixel = imagecolorat($result, 28, 20);
            $this->assertSame([0, 0, 255], [($leftPixel >> 16) & 0xff, ($leftPixel >> 8) & 0xff, $leftPixel & 0xff]);
            $this->assertSame([0, 255, 0], [($rightPixel >> 16) & 0xff, ($rightPixel >> 8) & 0xff, $rightPixel & 0xff]);
            imagedestroy($result);
        } finally {
            unlink($templatePath);
            unlink($photoPath);
            rmdir($directory);
        }
    }

    public function test_it_places_only_the_selected_photos_in_their_slots(): void
    {
        $directory = storage_path('app/public/compositor-tests-' . uniqid('partial-', true));
        mkdir($directory, 0777, true);
        $templatePath = $directory . '/template.png';
        $photoPaths = [];

        $template = imagecreatetruecolor(80, 20);
        imagefill($template, 0, 0, imagecolorallocate($template, 255, 0, 0));
        imagepng($template, $templatePath);
        imagedestroy($template);

        foreach ([[0, 0, 255], [0, 255, 0], [255, 255, 0]] as $index => $color) {
            $path = $directory . '/photo-' . $index . '.png';
            $photo = imagecreatetruecolor(20, 20);
            imagefill($photo, 0, 0, imagecolorallocate($photo, ...$color));
            imagepng($photo, $path);
            imagedestroy($photo);
            $photoPaths[] = $path;
        }

        try {
            $slots = [];
            for ($index = 0; $index < 4; $index++) {
                $slots[] = [
                    'x' => $index * 20,
                    'y' => 0,
                    'width' => 20,
                    'height' => 20,
                    'shape' => 'rectangle',
                    'rotation' => 0,
                ];
            }

            $frame = new Frame([
                'template_path' => str_replace(storage_path('app/public/'), '', $templatePath),
                'slot_positions' => $slots,
            ]);
            $result = (new FrameCompositor())->renderFromImages($frame, [
                0 => $photoPaths[0],
                2 => $photoPaths[1],
                3 => $photoPaths[2],
            ]);

            $this->assertNotFalse($result);
            $expectedColors = [[0, 0, 255], [255, 0, 0], [0, 255, 0], [255, 255, 0]];
            foreach ($expectedColors as $index => $expected) {
                $pixel = imagecolorat($result, $index * 20 + 10, 10);
                $this->assertSame($expected, [($pixel >> 16) & 0xff, ($pixel >> 8) & 0xff, $pixel & 0xff]);
            }
            imagedestroy($result);
        } finally {
            unlink($templatePath);
            foreach ($photoPaths as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }
}
