<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Frame extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'price', 'category', 'description', 'total_slots', 'template_path', 'template_source_path',
        'template_width', 'template_height', 'slot_positions', 'is_uniform_slots',
        'slot_width', 'slot_height', 'is_active',
    ];

    protected $casts = [
        'slot_positions'   => 'array',
        'is_uniform_slots' => 'boolean',
        'is_active'        => 'boolean',
        'price'            => 'decimal:2',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(FrameImage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(FrameOrder::class);
    }

    public function getEffectivePriceAttribute(): float
    {
        if (!is_null($this->price) && $this->price > 0) {
            return (float) $this->price;
        }
        $defaultPrice = Setting::get('price_per_image', 49.00);
        return (float) $defaultPrice;
    }

    public function slotSize(int $index): array
    {
        $slot = $this->slot_positions[$index] ?? null;
        if (!$slot) return ['width' => 0, 'height' => 0];

        if ($this->is_uniform_slots) {
            return ['width' => $this->slot_width, 'height' => $this->slot_height];
        }
        return [
            'width'  => $slot['width']  ?? $this->slot_width,
            'height' => $slot['height'] ?? $this->slot_height,
        ];
    }

    /**
     * Full slot rectangle for a given index: x, y, width, height,
     * shape ('rectangle'|'circle'|'ellipse') and rotation (degrees).
     */
    public function slotRect(int $index): ?array
    {
        $slot = $this->slot_positions[$index] ?? null;
        if (!$slot) return null;

        $size = $this->slotSize($index);

        return [
            'x'        => $slot['x'],
            'y'        => $slot['y'],
            'width'    => $size['width'],
            'height'   => $size['height'],
            'shape'    => $slot['shape'] ?? 'rectangle',
            'rotation' => (float) ($slot['rotation'] ?? 0),
        ];
    }
}