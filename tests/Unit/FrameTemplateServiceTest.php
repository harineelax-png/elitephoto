<?php

namespace Tests\Unit;

use App\Services\FrameTemplateService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrameTemplateServiceTest extends TestCase
{
    public function test_image_template_is_stored_with_its_dimensions(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('template.png', 24, 36);

        $template = (new FrameTemplateService())->store($file);

        $this->assertSame(24, $template['template_width']);
        $this->assertSame(36, $template['template_height']);
        $this->assertNull($template['template_source_path']);
        Storage::disk('public')->assertExists($template['template_path']);
    }
}
