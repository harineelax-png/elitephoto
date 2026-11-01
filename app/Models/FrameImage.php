<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrameImage extends Model
{
    use HasFactory;

    protected $fillable = ['frame_id', 'image_path', 'position'];

    public function frame(): BelongsTo
    {
        return $this->belongsTo(Frame::class);
    }
}