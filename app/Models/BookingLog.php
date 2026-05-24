<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'action',
        'message',
    ];

    /**
     * Relasi ke Booking.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Relasi ke User (admin/aktor).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
