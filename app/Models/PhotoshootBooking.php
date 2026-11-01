<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoshootBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_number',
        'photoshoot_plan_id',
        'plan_name',
        'customer_name',
        'customer_email',
        'customer_phone',
        'booking_date',
        'booking_time',
        'location',
        'notes',
        'status',
    ];

    protected $casts = [
        'booking_date' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PhotoshootPlan::class, 'photoshoot_plan_id');
    }
}
