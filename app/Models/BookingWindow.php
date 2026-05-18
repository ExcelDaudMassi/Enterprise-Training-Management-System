<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingWindow extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_periode',
        'tahun',
        'is_active',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Scope untuk mengambil periode yang aktif.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
