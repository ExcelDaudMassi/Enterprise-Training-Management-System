<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ruangan extends Model
{
    use HasFactory;

    protected $table = 'ruangan';

    protected $fillable = [
        'nama_ruang',
        'lokasi_gedung',
        'kapasitas_max',
        'bisa_digabung',
        'pasangan_ruang_id',
    ];

    protected function casts(): array
    {
        return [
            'bisa_digabung' => 'boolean',
        ];
    }

    // =========================================================
    // Relationships
    // =========================================================

    /**
     * Semua booking untuk ruangan ini.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'ruangan_id');
    }

    /**
     * Ruangan pasangan yang bisa digabungkan (self-referencing).
     */
    public function pasanganRuang(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'pasangan_ruang_id');
    }

    /**
     * Ruangan lain yang menjadikan ruangan ini sebagai pasangannya.
     */
    public function dipasangkanOleh(): HasMany
    {
        return $this->hasMany(Ruangan::class, 'pasangan_ruang_id');
    }

    // =========================================================
    // Helpers
    // =========================================================

    /**
     * Cek apakah ruangan ini bisa digabung dengan pasangannya.
     */
    public function hasPasangan(): bool
    {
        return $this->bisa_digabung && !is_null($this->pasangan_ruang_id);
    }
}
