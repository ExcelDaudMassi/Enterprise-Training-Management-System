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
    /**
     * Tampilkan daftar semua booking yang perlu diproses admin.
     * Menerima query param: ?filter=waiting_confirmation|confirmed|cancelled|urgent|overdue|all
     */
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $today  = Carbon::today();

        $query = Booking::with(['user', 'ruangan']);

        // Terapkan filter dari query param (dikirim dari dashboard cards)
        match ($filter) {
            'waiting_confirmation' => $query->where('status', 'waiting_confirmation'),
            'confirmed'            => $query->where('status', 'confirmed'),
            'cancelled'            => $query->where('status', 'cancelled'),
            'urgent'               => $query->where('status', 'waiting_confirmation')
                                            ->where('tgl_mulai', '<=', $today->copy()->addDays(14))
                                            ->where('tgl_mulai', '>=', $today),
            'overdue'              => $query->where('status', 'waiting_confirmation')
                                            ->where('tgl_mulai', '<', $today),
            default                => null, // 'all' — tanpa filter
        };

        $bookings = $query
            ->orderByRaw("FIELD(status, 'waiting_confirmation', 'confirmed', 'cancelled', 'plotting')")
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Booking $b) {
                return [
                    'id'              => $b->id,
                    'nama_training'   => $b->nama_training,
                    'pic'             => $b->pic,
                    'tgl_mulai'       => $b->tgl_mulai?->toDateString(),
                    'tgl_selesai'     => $b->tgl_selesai?->toDateString(),
                    'ruangan'         => $b->ruangan?->nama_ruang ?? 'Ruang Gabungan',
                    'layout'          => $b->layout_preferensi,
                    'is_hybrid'       => $b->is_hybrid,
                    'is_flipchart'    => $b->is_flipchart,
                    'catatan_admin'   => $b->catatan_admin,
                    'status'          => $b->status,
                    'gabung_ruang'    => $b->gabung_ruang,
                    'pemohon'         => $b->user?->name ?? '-',
                    'divisi'          => $b->user?->divisi ?? '-',
                    'jumlah_peserta'  => BookingParticipant::where('booking_id', $b->id)->where('tipe', 'peserta')->count(),
                    'jumlah_panitia'  => BookingParticipant::where('booking_id', $b->id)->where('tipe', 'panitia')->count(),
                    'created_at'      => $b->created_at->format('d M Y, H:i'),
                ];
            });

        return Inertia::render('Admin/BookingApproval', [
            'auth'         => ['user' => ['name' => Auth::user()->name, 'role' => Auth::user()->role]],
            'bookings'     => $bookings,
            'activeFilter' => $filter,
        ]);
    }

    /**
     * Setujui booking.
     */
    public function approve(Booking $booking)
    {
        $booking->update(['status' => 'confirmed']);

        return back()->with('success', "Booking #{$booking->id} berhasil disetujui.");
    }

    /**
     * Tolak booking.
     */
    public function reject(Request $request, Booking $booking)
    {
        $booking->update(['status' => 'cancelled']);

        return back()->with('success', "Booking #{$booking->id} telah ditolak.");
    }
}
