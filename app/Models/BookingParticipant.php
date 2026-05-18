<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingParticipant extends Model
{
    protected $fillable = [
        'booking_id',
        'tipe',
        'nama',
        'jabatan',
        'site',
        'gender',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
