<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Booking extends Model
{
    use HasFactory;

    // =========================================================
    // Status Constants — nilai resmi untuk kolom `status`
    // =========================================================
    const STATUS_PLOTTING              = 'plotting';
    const STATUS_WAITING_CONFIRMATION  = 'waiting_confirmation';
    const STATUS_CONFIRMED             = 'confirmed';
    const STATUS_CANCELLED             = 'cancelled';
    const STATUS_FINAL                 = 'final';

    // Status-perubahan constants
    const CHANGE_NONE     = 'none';
    const CHANGE_PENDING  = 'pending';
    const CHANGE_APPROVED = 'approved';
    const CHANGE_REJECTED = 'rejected';

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
        'catatan_user',
        // Workflow Tahap 3 — Perubahan Tanggal
        'proposed_tgl_mulai',
        'proposed_tgl_selesai',
        'status_perubahan',
        // Workflow Tahap 4 — ACC Final
        'acc2_at',
        'acc2_by',
        // Workflow Tahap 5 — ACC Terlambat
        'catatan_acc_terlambat',
    ];

    protected function casts(): array
    {
        return [
            'tgl_mulai'            => 'date',
            'tgl_selesai'          => 'date',
            'proposed_tgl_mulai'   => 'date',
            'proposed_tgl_selesai' => 'date',
            'acc2_at'              => 'datetime',
            'gabung_ruang'         => 'boolean',
            'is_hybrid'            => 'boolean',
            'is_flipchart'         => 'boolean',
        ];
    }

    // =========================================================
    // Relationships
    // =========================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(BookingParticipant::class);
    }

    /** Admin yang melakukan ACC Tahap 2 */
    public function acc2Admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acc2_by');
    }

    // =========================================================
    // Scopes
    // =========================================================

    public function scopePlotting($query)
    {
        return $query->where('status', self::STATUS_PLOTTING);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeWaitingConfirmation($query)
    {
        return $query->where('status', self::STATUS_WAITING_CONFIRMATION);
    }

    public function scopeFinal($query)
    {
        return $query->where('status', self::STATUS_FINAL);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Booking yang mendekati H-14 (confirmed, tgl_mulai dalam 0-14 hari).
     */
    public function scopeUrgentH14($query)
    {
        $today = Carbon::today();
        return $query->where('status', self::STATUS_CONFIRMED)
                     ->where('tgl_mulai', '>=', $today)
                     ->where('tgl_mulai', '<=', $today->copy()->addDays(14));
    }

    /**
     * Scope: Booking overdue untuk ACC Tahap 2
     * (confirmed, tgl_mulai sudah lewat tapi belum final).
     */
    public function scopeOverdueAcc2($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED)
                     ->where('tgl_mulai', '<', Carbon::today());
    }

    /**
     * Scope: Booking dengan usulan perubahan tanggal yang pending.
     */
    public function scopePendingDateChange($query)
    {
        return $query->where('status_perubahan', self::CHANGE_PENDING);
    }

    // =========================================================
    // Helper Methods (Status Checks)
    // =========================================================

    public function isPlotting(): bool
    {
        return $this->status === self::STATUS_PLOTTING;
    }

    public function isWaitingConfirmation(): bool
    {
        return $this->status === self::STATUS_WAITING_CONFIRMATION;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    public function hasPendingDateChange(): bool
    {
        return $this->status_perubahan === self::CHANGE_PENDING;
    }

    // =========================================================
    // Business Rule Checks
    // =========================================================

    /**
     * User bisa membatalkan booking jika:
     *  - Status adalah waiting_confirmation atau confirmed
     *  - Belum final
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_WAITING_CONFIRMATION,
            self::STATUS_CONFIRMED,
        ]) && !$this->isFinal();
    }

    /**
     * User bisa mengajukan perubahan tanggal jika:
     *  - Status confirmed (sudah di-ACC Tahap 1)
     *  - Belum final
     *  - Tidak sedang ada pending date change lain
     */
    public function canRequestDateChange(): bool
    {
        return $this->isConfirmed()
            && !$this->isFinal()
            && !$this->hasPendingDateChange();
    }

    /**
     * User bisa update peserta jika booking belum final dan belum cancelled.
     */
    public function canUpdateParticipants(): bool
    {
        return !$this->isFinal() && !$this->isCancelled();
    }

    /**
     * Admin bisa ACC Final (Tahap 2) jika booking berstatus confirmed.
     */
    public function canBeFinalized(): bool
    {
        return $this->isConfirmed();
    }
}
