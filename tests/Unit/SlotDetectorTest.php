<?php

namespace Tests\Unit;

use App\Services\SlotDetector;
use Tests\TestCase;

class SlotDetectorTest extends TestCase
{
    public function test_it_detects_small_solid_template_openings(): void
    {
        $image = imagecreatetruecolor(316, 274);
        $white = imagecolorallocate($image, 255, 255, 255);
        $dark = imagecolorallocate($image, 45, 45, 45);
        imagefill($image, 0, 0, $white);

        for ($index = 0; $index < 41; $index++) {
            $x = 15 + ($index % 7) * 42;
            $y = 15 + intdiv($index, 7) * 42;
            imagefilledrectangle($image, $x, $y, $x + 33, $y + 33, $dark);
        }

        $path = tempnam(sys_get_temp_dir(), 'slot-detector-');
        imagejpeg($image, $path);
        imagedestroy($image);

        try {
            $result = (new SlotDetector())->detect($path);
            $this->assertCount(41, $result['slots']);
        } finally {
            unlink($path);
        }
    }

    public function test_it_preserves_circular_opening_shapes(): void
    {
        $image = imagecreatetruecolor(400, 180);
        $white = imagecolorallocate($image, 255, 255, 255);
        $dark = imagecolorallocate($image, 40, 40, 40);
        imagefill($image, 0, 0, $white);
        imagefilledellipse($image, 70, 70, 90, 90, $dark);
        imagefilledrectangle($image, 160, 25, 240, 115, $dark);
        imagefilledellipse($image, 330, 80, 120, 60, $dark);

        $path = tempnam(sys_get_temp_dir(), 'slot-detector-shapes-');
        imagepng($image, $path);
        imagedestroy($image);

        try {
            $result = (new SlotDetector())->detect($path);

            $this->assertCount(3, $result['slots']);
            $this->assertSame(['circle', 'rectangle', 'ellipse'], array_column($result['slots'], 'shape'));
        } finally {
            unlink($path);
        }
    }
}
