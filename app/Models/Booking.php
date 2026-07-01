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
    const STATUS_PENDING               = 'pending';
    const STATUS_CONFIRMED             = 'confirmed';
    const STATUS_FINALIZED             = 'finalized';
    const STATUS_REJECTED              = 'rejected';
    const STATUS_CANCELLED             = 'cancelled';
    const STATUS_COMPLETED             = 'completed';

    // Status-perubahan constants
    const CHANGE_NONE     = 'none';
    const CHANGE_PENDING  = 'pending';
    const CHANGE_APPROVED = 'approved';
    const CHANGE_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'ruangan_id',
        'tipe_booking',
        'estimasi_peserta',
        'estimasi_panitia',
        'nama_training',
        'tgl_mulai',
        'tgl_selesai',
        'fase',
        'status',
        'pic',
        'no_hp_pic',
        'gabung_ruang',
        'layout_preferensi',
        'layout_custom_path',
        'is_hybrid',
        'is_flipchart',
        'is_pena_mini_note',
        'catatan_admin',
        'catatan_user',
        // Workflow Tahap 3 — Perubahan Tanggal
        'proposed_tgl_mulai',
        'proposed_tgl_selesai',
        'status_perubahan',
        // Workflow Tahap 4 — ACC Final
        'acc1_at',
        'acc1_by',
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
            'acc1_at'              => 'datetime',
            'acc2_at'              => 'datetime',
            'gabung_ruang'         => 'boolean',
            'is_hybrid'            => 'boolean',
            'is_flipchart'         => 'boolean',
            'is_pena_mini_note'    => 'boolean',
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

    /**
     * Log aktivitas administratif pada booking ini.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(BookingLog::class);
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

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeWaitingConfirmation($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFinalized($query)
    {
        return $query->where('status', self::STATUS_FINALIZED);
    }

    public function scopeFinal($query)
    {
        return $query->where('status', self::STATUS_FINALIZED);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Booking yang mendekati batas persiapan (confirmed, tgl_mulai dalam 0-X hari).
     */
    public function scopeUrgentPreparation($query)
    {
        $today = Carbon::today();
        $days = \App\Models\Setting::where('key', 'preparation_alert_days')->value('value') ?? 14;
        
        return $query->where('status', self::STATUS_CONFIRMED)
                     ->where('tgl_mulai', '>=', $today)
                     ->where('tgl_mulai', '<=', $today->copy()->addDays((int) $days));
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isWaitingConfirmation(): bool
    {
        return $this->isPending();
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function isFinalConfirmed(): bool
    {
        return $this->isFinalized();
    }

    public function canBeAcc2(): bool
    {
        return $this->isConfirmed() && !$this->isFinalized();
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFinal(): bool
    {
        return $this->isFinalized();
    }

    public function hasPendingDateChange(): bool
    {
        return $this->status_perubahan === self::CHANGE_PENDING;
    }

    // =========================================================
    // Business Rule Checks
    // =========================================================

    public function canBeCancelled(): bool
    {
        $belumMelewatiH1 = \Illuminate\Support\Carbon::today()->lessThan($this->tgl_mulai);
        
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
        ]) && !$this->isFinalized() && $belumMelewatiH1;
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
            && !$this->isFinalized()
            && !$this->hasPendingDateChange();
    }

    /**
     * User bisa update peserta jika booking belum final, belum cancelled, dan belum rejected.
     */
    public function canUpdateParticipants(): bool
    {
        return !$this->isFinalized() && !$this->isCancelled() && !$this->isRejected();
    }

    /**
     * Admin bisa ACC Final (Tahap 2) jika booking berstatus confirmed.
     */
    public function canBeFinalized(): bool
    {
        return $this->isConfirmed();
    }

    // =========================================================
    // Display Helpers
    // =========================================================

    /**
     * Nama ruangan yang ditampilkan ke UI.
     * Jika booking menggunakan gabungan ruang, tampilkan "Ruang Gabungan 2 + 3"
     * agar tidak membingungkan user/admin.
     */
    public function displayRoomName(): string
    {
        if ($this->gabung_ruang) {
            return 'Ruang Gabungan 2 + 3';
        }

        return $this->ruangan?->nama_ruang ?? 'Ruangan';
    }
}
