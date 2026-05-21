<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BookingApprovalController extends Controller
{
    // ============================================================
    // INDEX — Tampilkan daftar booking dengan filter tab
    // ============================================================

    /**
     * Filter yang tersedia via query param ?filter=...:
     *   waiting_confirmation  — Tahap 1: menunggu ACC awal
     *   confirmed             — sudah di-ACC Tahap 1
     *   cancelled             — ditolak/dibatalkan
     *   final                 — sudah ACC Tahap 2
     *   h14                   — Tahap 4: confirmed & tgl_mulai dalam 14 hari
     *   overdue               — Tahap 5: confirmed & tgl_mulai sudah lewat
     *   date_changes          — Memiliki usulan perubahan tanggal (pending)
     *   all                   — semua
     */
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $today  = Carbon::today();

        $query = Booking::with(['user', 'ruangan']);

        match ($filter) {
            'waiting_confirmation' => $query->where('status', Booking::STATUS_WAITING_CONFIRMATION),
            'confirmed'            => $query->where('status', Booking::STATUS_CONFIRMED),
            'cancelled'            => $query->where('status', Booking::STATUS_CANCELLED),
            'final'                => $query->where('status', Booking::STATUS_FINAL),

            // H-14: confirmed, mulai dalam 0-14 hari ke depan
            'h14'     => $query->where('status', Booking::STATUS_CONFIRMED)
                               ->where('tgl_mulai', '>=', $today)
                               ->where('tgl_mulai', '<=', $today->copy()->addDays(14)),

            // Overdue ACC Tahap 2: confirmed tapi tanggal mulai sudah lewat
            'overdue' => $query->where('status', Booking::STATUS_CONFIRMED)
                               ->where('tgl_mulai', '<', $today),

            // Usulan perubahan tanggal yang belum diproses
            'date_changes' => $query->where('status_perubahan', Booking::CHANGE_PENDING),

            default => null, // 'all' — tanpa filter tambahan
        };

        // Pengurutan: antrian tunggu → diurutkan dari TERLAMA; yang lain → TERBARU
        if (in_array($filter, ['waiting_confirmation', 'h14', 'overdue', 'date_changes'])) {
            $query->orderBy('tgl_mulai', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $bookings = $query->get()->map(fn(Booking $b) => $this->formatBooking($b));

        return Inertia::render('Admin/BookingApproval', [
            'auth'         => ['user' => ['name' => Auth::user()->name, 'role' => Auth::user()->role]],
            'bookings'     => $bookings,
            'activeFilter' => $filter,
        ]);
    }

    // ============================================================
    // TAHAP 1 — Setujui booking (waiting_confirmation → confirmed)
    // ============================================================
    public function approve(Booking $booking)
    {
        if (!$booking->isWaitingConfirmation()) {
            return back()->with('error', 'Hanya booking berstatus "Menunggu" yang dapat disetujui.');
        }

        $booking->update(['status' => Booking::STATUS_CONFIRMED]);

        return back()->with('success', "Booking #{$booking->id} berhasil disetujui.");
    }

    // ============================================================
    // TAHAP 1 — Tolak booking (waiting_confirmation → cancelled)
    // ============================================================
    public function reject(Request $request, Booking $booking)
    {
        if (!$booking->isWaitingConfirmation()) {
            return back()->with('error', 'Hanya booking berstatus "Menunggu" yang dapat ditolak.');
        }

        $request->validate([
            'catatan_admin' => 'required|string|max:500',
        ], [
            'catatan_admin.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $booking->update([
            'status'        => Booking::STATUS_CANCELLED,
            'catatan_admin' => $request->catatan_admin,
        ]);

        return back()->with('success', "Booking #{$booking->id} telah ditolak.");
    }

    // ============================================================
    // TAHAP 4 — ACC Final (confirmed → final)
    // ============================================================
    public function approveFinal(Booking $booking)
    {
        if (!$booking->canBeFinalized()) {
            return back()->with('error', 'Booking tidak dapat di-finalisasi. Status saat ini: ' . $booking->status);
        }

        $booking->update([
            'status'  => Booking::STATUS_FINAL,
            'acc2_at' => now(),
            'acc2_by' => Auth::id(),
        ]);

        // TODO: Kirim notifikasi WhatsApp ke HK & Frontdesk di sini (fase berikutnya)

        return back()->with('success', "Booking #{$booking->id} berhasil di-ACC Final. Status: FINAL.");
    }

    // ============================================================
    // TAHAP 5 — ACC Terlambat (confirmed → final, dengan catatan alasan)
    // ============================================================
    public function approveFinalLate(Request $request, Booking $booking)
    {
        if (!$booking->canBeFinalized()) {
            return back()->with('error', 'Booking tidak dapat di-finalisasi. Status saat ini: ' . $booking->status);
        }

        $request->validate([
            'catatan_acc_terlambat' => 'required|string|max:1000',
        ], [
            'catatan_acc_terlambat.required' => 'Alasan ACC terlambat wajib diisi.',
        ]);

        $booking->update([
            'status'                => Booking::STATUS_FINAL,
            'acc2_at'               => now(),
            'acc2_by'               => Auth::id(),
            'catatan_acc_terlambat' => $request->catatan_acc_terlambat,
        ]);

        return back()->with('success', "Booking #{$booking->id} di-ACC Terlambat dan kini berstatus FINAL.");
    }

    // ============================================================
    // TAHAP 3 — Setujui Perubahan Tanggal dari User
    // ============================================================
    public function approveDateChange(Booking $booking)
    {
        if (!$booking->hasPendingDateChange()) {
            return back()->with('error', 'Tidak ada usulan perubahan tanggal yang aktif untuk booking ini.');
        }

        $booking->update([
            'tgl_mulai'            => $booking->proposed_tgl_mulai,
            'tgl_selesai'          => $booking->proposed_tgl_selesai,
            'proposed_tgl_mulai'   => null,
            'proposed_tgl_selesai' => null,
            'status_perubahan'     => Booking::CHANGE_APPROVED,
        ]);

        return back()->with('success', "Perubahan tanggal untuk Booking #{$booking->id} telah disetujui.");
    }

    // ============================================================
    // TAHAP 3 — Tolak Perubahan Tanggal dari User
    // ============================================================
    public function rejectDateChange(Request $request, Booking $booking)
    {
        if (!$booking->hasPendingDateChange()) {
            return back()->with('error', 'Tidak ada usulan perubahan tanggal yang aktif untuk booking ini.');
        }

        $request->validate([
            'catatan_admin' => 'required|string|max:500',
        ], [
            'catatan_admin.required' => 'Alasan penolakan perubahan tanggal wajib diisi.',
        ]);

        // Tanggal lama TETAP berlaku, hanya hapus proposal dan ubah status_perubahan
        $booking->update([
            'proposed_tgl_mulai'   => null,
            'proposed_tgl_selesai' => null,
            'status_perubahan'     => Booking::CHANGE_REJECTED,
            'catatan_admin'        => $request->catatan_admin,
        ]);

        return back()->with('success', "Usulan perubahan tanggal Booking #{$booking->id} ditolak. Tanggal lama tetap berlaku.");
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    /**
     * Format satu objek Booking menjadi array yang siap dikonsumsi frontend.
     */
    private function formatBooking(Booking $b): array
    {
        $today       = Carbon::today();
        $daysToStart = $b->tgl_mulai ? $today->diffInDays($b->tgl_mulai, false) : null;

        return [
            'id'                      => $b->id,
            'nama_training'           => $b->nama_training,
            'pic'                     => $b->pic,
            'tgl_mulai'               => $b->tgl_mulai?->toDateString(),
            'tgl_selesai'             => $b->tgl_selesai?->toDateString(),
            'proposed_tgl_mulai'      => $b->proposed_tgl_mulai?->toDateString(),
            'proposed_tgl_selesai'    => $b->proposed_tgl_selesai?->toDateString(),
            'status_perubahan'        => $b->status_perubahan,
            'ruangan'                 => $b->ruangan?->nama_ruang ?? 'Ruang Gabungan',
            'gabung_ruang'            => $b->gabung_ruang,
            'layout'                  => $b->layout_preferensi,
            'is_hybrid'               => $b->is_hybrid,
            'is_flipchart'            => $b->is_flipchart,
            'catatan_admin'           => $b->catatan_admin,
            'catatan_user'            => $b->catatan_user,
            'catatan_acc_terlambat'   => $b->catatan_acc_terlambat,
            'status'                  => $b->status,
            'pemohon'                 => $b->user?->name ?? '-',
            'divisi'                  => $b->user?->divisi ?? '-',
            'jumlah_peserta'          => BookingParticipant::where('booking_id', $b->id)->where('tipe', 'peserta')->count(),
            'jumlah_panitia'          => BookingParticipant::where('booking_id', $b->id)->where('tipe', 'panitia')->count(),
            'created_at'              => $b->created_at->format('d M Y, H:i'),
            'acc2_at'                 => $b->acc2_at?->format('d M Y, H:i'),
            // Helpers UI
            'days_to_start'           => $daysToStart,
            'is_overdue_acc2'         => $b->isConfirmed() && $b->tgl_mulai?->isPast(),
            'can_be_finalized'        => $b->canBeFinalized(),
            'has_pending_date_change' => $b->hasPendingDateChange(),
        ];
    }
}
