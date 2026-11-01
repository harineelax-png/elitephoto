<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PhotoshootPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'duration',
        'deliverables',
        'description',
        'features',
        'badge',
        'image_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features'   => 'array',
        'is_active'  => 'boolean',
        'price'      => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(PhotoshootBooking::class);
    }

    /**
     * Accessor: return the full public URL for the stored image.
     * Handles both legacy full URLs (http...) and relative paths.
     */
    public function getImageUrlAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('public')->url($value);
    }

    /**
     * Helper: delete the existing image file from disk (safe to call).
     */
    public function deleteStoredImage(): void
    {
        $raw = $this->getRawOriginal('image_url');
        if (empty($raw) || str_starts_with($raw, 'http')) {
            return;
        }

        if (Storage::disk('public')->exists($raw)) {
            Storage::disk('public')->delete($raw);
        }
    }
}