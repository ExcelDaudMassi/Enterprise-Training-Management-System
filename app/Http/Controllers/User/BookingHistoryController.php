<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Inertia\Inertia;

class BookingHistoryController extends Controller
{
    public function index()
    {
        $bookings = Booking::with('ruangan:id,nama_ruang')
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Booking $b) {
                return [
                    'id'             => $b->id,
                    'nama_training'  => $b->nama_training,
                    'nama_ruang'     => $b->ruangan?->nama_ruang ?? 'Ruang Gabungan',
                    'tgl_mulai'      => $b->tgl_mulai?->toDateString(),
                    'tgl_selesai'    => $b->tgl_selesai?->toDateString(),
                    'status'         => $b->status,
                    'fase'           => $b->fase,
                    'pic'            => $b->pic,
                    'gabung_ruang'   => (bool) $b->gabung_ruang,
                    'layout_preferensi' => $b->layout_preferensi,
                    'is_hybrid'      => (bool) $b->is_hybrid,
                    'is_flipchart'   => (bool) $b->is_flipchart,
                    'catatan_admin'  => $b->catatan_admin,
                    'created_at'     => $b->created_at->format('d M Y, H:i'),
                ];
            });

        return Inertia::render('User/BookingHistory', [
            'bookings' => $bookings,
        ]);
    }
}
