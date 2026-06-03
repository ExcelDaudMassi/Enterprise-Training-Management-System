<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Ruangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $activeWindow  = \App\Models\BookingWindow::active()->latest('id')->first();
        $defaultYear   = $activeWindow?->tahun ?? now()->year;
        $year          = (int) $request->get('year', $defaultYear);
        $ruanganFilter = $request->get('ruangan_id');

        // Warna per ruangan (statis, konsisten di FE & BE)
        $ruanganList = Ruangan::all(['id', 'nama_ruang', 'lokasi_gedung', 'kapasitas_max']);

        // Query bookings untuk tahun yang dipilih
        $bookingQuery = Booking::with(['ruangan:id,nama_ruang', 'user:id,name,divisi'])
            ->where(function ($query) use ($year) {
                $query->whereYear('tgl_mulai', $year)
                      ->orWhereYear('tgl_selesai', $year);
            })
            ->whereNotIn('status', ['cancelled']);

        if ($ruanganFilter) {
            $bookingQuery->where('ruangan_id', $ruanganFilter);
        }

        $bookings = $bookingQuery->get()->map(function ($booking) {
            $isOwner = $booking->user_id === Auth::id();
            return [
                'id'            => $booking->id,
                'ruangan_id'    => $booking->ruangan_id,
                'nama_ruang'    => $booking->displayRoomName(),
                // Sembunyikan detail sensitif booking milik departemen lain
                'nama_training' => $isOwner ? $booking->nama_training : '[Sudah Dipesan]',
                'divisi'        => $isOwner ? ($booking->user?->divisi) : null,
                'pemohon'       => $isOwner ? ($booking->user?->name) : null,
                'pic'           => $isOwner ? $booking->pic : null,
                'fase'          => $booking->fase,
                'gabung_ruang'  => (bool) $booking->gabung_ruang,
                'tgl_mulai'     => $booking->tgl_mulai->toDateString(),
                'tgl_selesai'   => $booking->tgl_selesai->toDateString(),
                'status'        => $booking->status,
                'is_owner'      => $isOwner,
            ];
        });

        // My Bookings: hanya booking milik akun yang sedang login (isolasi ketat per user_id)
        $myBookings = Booking::with('ruangan:id,nama_ruang')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($booking) {
                return [
                    'id'            => $booking->id,
                    'nama_training' => $booking->nama_training,
                    'nama_ruang'    => $booking->displayRoomName(),
                    'tgl_mulai'     => $booking->tgl_mulai->toDateString(),
                    'tgl_selesai'   => $booking->tgl_selesai->toDateString(),
                    'status'        => $booking->status,
                    'fase'          => $booking->fase,
                    'pic'           => $booking->pic,
                ];
            });

        return Inertia::render('User/Dashboard', [
            'ruanganList' => $ruanganList,
            'bookings'    => $bookings,
            'myBookings'  => $myBookings,
            'selectedYear'    => $year,
            'selectedRuangan' => $ruanganFilter ? (int) $ruanganFilter : null,
        ]);
    }
}
