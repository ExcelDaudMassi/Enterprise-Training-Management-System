<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ruangan_id',
        'nama_training',
        'tgl_mulai',
        'tgl_selesai',
        'fase',
        'status',
        'pic',
        'gabung_ruang',
        'layout_preferensi',
        'layout_custom_path',
        'is_hybrid',
        'is_flipchart',
        'catatan_admin',
    ];

    protected function casts(): array
    {
        return [
            'tgl_mulai'    => 'date',
            'tgl_selesai'  => 'date',
            'gabung_ruang' => 'boolean',
            'is_hybrid'    => 'boolean',
            'is_flipchart' => 'boolean',
        ];
    }

    // =========================================================
    // Relationships
    // =========================================================

    /**
     * User yang membuat booking ini.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ruangan yang dipesan.
     */
    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    // =========================================================
    // Scopes
    // =========================================================

    public function scopePlotting($query)
    {
        return $query->where('status', 'plotting');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeWaitingConfirmation($query)
    {
        return $query->where('status', 'waiting_confirmation');
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // =========================================================
    // Helper Methods
    // =========================================================

    public function isPlotting(): bool
    {
        return $this->status === 'plotting';
    }

    public function isWaitingConfirmation(): bool
    {
        return $this->status === 'waiting_confirmation';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Booking bisa dibatalkan jika belum confirmed dan tanggal mulai belum lewat.
     */
    public function canBeCancelled(): bool
    {
        return !$this->isCancelled()
            && !$this->isConfirmed()
            && $this->tgl_mulai->isFuture();
    }
}
