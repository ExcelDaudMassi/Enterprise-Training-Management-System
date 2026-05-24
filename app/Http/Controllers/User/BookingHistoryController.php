<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingParticipant;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BookingHistoryController extends Controller
{
    public function index()
    {
        $bookings = Booking::with(['ruangan', 'participants'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn(Booking $b) => [
                'id'                      => $b->id,
                'nama_training'           => $b->nama_training,
                'pic'                     => $b->pic,
                'tgl_mulai'               => $b->tgl_mulai?->toDateString(),
                'tgl_selesai'             => $b->tgl_selesai?->toDateString(),
                'proposed_tgl_mulai'      => $b->proposed_tgl_mulai?->toDateString(),
                'proposed_tgl_selesai'    => $b->proposed_tgl_selesai?->toDateString(),
                'status_perubahan'        => $b->status_perubahan,
                'status'                  => $b->status,
                'fase'                    => $b->fase,
                'gabung_ruang'            => $b->gabung_ruang,
                'ruangan'                 => $b->ruangan,
                'nama_ruang'              => $b->ruangan?->nama_ruang ?? 'Ruang Gabungan',
                'layout_preferensi'       => $b->layout_preferensi,
                'is_hybrid'               => $b->is_hybrid,
                'is_flipchart'            => $b->is_flipchart,
                'catatan_admin'           => $b->catatan_admin,
                'catatan_user'            => $b->catatan_user,
                'catatan_acc_terlambat'   => $b->catatan_acc_terlambat,
                'jumlah_peserta'          => $b->participants->where('tipe', 'peserta')->count(),
                'jumlah_panitia'          => $b->participants->where('tipe', 'panitia')->count(),
                'created_at'              => $b->created_at->format('d M Y, H:i'),
                // Flag aksi yang tersedia — dikonsumsi langsung oleh frontend
                'can_cancel'              => $b->canBeCancelled(),
                'can_request_date_change' => $b->canRequestDateChange(),
                'can_update_participants' => $b->canUpdateParticipants(),
            ]);

        return Inertia::render('User/BookingHistory', [
            'auth'     => ['user' => ['name' => Auth::user()->name, 'role' => Auth::user()->role]],
            'bookings' => $bookings,
        ]);
    }
}
