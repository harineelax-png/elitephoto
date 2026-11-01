<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrameOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'frame_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'amount',
        'currency',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'payment_status',
        'generated_image_path',
        'preview_image_path',
        'download_token',
        'download_count',
        'paid_at',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'paid_at'    => 'datetime',
    ];

    public function frame(): BelongsTo
    {
        return $this->belongsTo(Frame::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
