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
                'nama_ruang'              => $b->displayRoomName(),
                'layout_preferensi'       => $b->layout_preferensi,
                'is_hybrid'               => $b->is_hybrid,
                'is_flipchart'            => $b->is_flipchart,
                'catatan_admin'           => $b->catatan_admin,
                'catatan_user'            => $b->catatan_user,
                'catatan_acc_terlambat'   => $b->catatan_acc_terlambat,
                'jumlah_peserta'          => $b->participants->where('tipe', 'peserta')->count(),
                'jumlah_panitia'          => $b->participants->where('tipe', 'panitia')->count(),
                'participants'            => $b->participants->map(fn($p) => [
                    'id'      => $p->id,
                    'tipe'    => $p->tipe,
                    'nama'    => $p->nama,
                    'nrp'     => $p->nrp,
                    'jabatan' => $p->jabatan,
                    'site'    => $p->site,
                    'no_hp'   => $p->no_hp,
                    'gender'  => $p->gender,
                ])->values(),
                'created_at'              => $b->created_at->format('d M Y, H:i'),
                // Flag aksi yang tersedia — dikonsumsi langsung oleh frontend
                'can_cancel'              => $b->canBeCancelled(),
                'can_request_date_change' => $b->canRequestDateChange(),
                'can_update_participants' => $b->canUpdateParticipants(),
            ]);

        return Inertia::render('User/BookingHistory', [
            'bookings' => $bookings,
        ]);
    }

    public function active()
    {
        $bookings = Booking::with(['ruangan', 'participants'])
            ->where('user_id', Auth::id())
            ->whereIn('status', [
                Booking::STATUS_WAITING_CONFIRMATION,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_FINAL,
            ])
            ->orderBy('tgl_mulai', 'asc')
            ->get()
            ->map(fn(Booking $b) => [
                'id'                      => $b->id,
                'nama_training'           => $b->nama_training,
                'pic'                     => $b->pic,
                'tgl_mulai'               => $b->tgl_mulai?->toDateString(),
                'tgl_selesai'             => $b->tgl_selesai?->toDateString(),
                'status'                  => $b->status,
                'gabung_ruang'            => $b->gabung_ruang,
                'nama_ruang'              => $b->displayRoomName(),
                'layout_preferensi'       => $b->layout_preferensi,
                'is_hybrid'               => $b->is_hybrid,
                'is_flipchart'            => $b->is_flipchart,
                'catatan_admin'           => $b->catatan_admin,
                'catatan_user'            => $b->catatan_user,
                'jumlah_peserta'          => $b->participants->where('tipe', 'peserta')->count(),
                'jumlah_panitia'          => $b->participants->where('tipe', 'panitia')->count(),
                'participants'            => $b->participants->map(fn($p) => [
                    'id'      => $p->id,
                    'tipe'    => $p->tipe,
                    'nama'    => $p->nama,
                    'nrp'     => $p->nrp,
                    'jabatan' => $p->jabatan,
                    'site'    => $p->site,
                    'no_hp'   => $p->no_hp,
                    'gender'  => $p->gender,
                ])->values(),
                'can_cancel'              => $b->canBeCancelled(),
                'created_at'              => $b->created_at->format('d M Y, H:i'),
            ]);

        return Inertia::render('User/BookingActive', [
            'bookings' => $bookings,
        ]);
    }

    public function show(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }

        $booking->load(['ruangan', 'participants']);

        $bookingData = [
            'id'                      => $booking->id,
            'nama_training'           => $booking->nama_training,
            'pic'                     => $booking->pic,
            'tgl_mulai'               => $booking->tgl_mulai?->toDateString(),
            'tgl_selesai'             => $booking->tgl_selesai?->toDateString(),
            'status'                  => $booking->status,
            'gabung_ruang'            => $booking->gabung_ruang,
            'ruangan'                 => $booking->ruangan,
            'nama_ruang'              => $booking->displayRoomName(),
            'layout_preferensi'       => $booking->layout_preferensi,
            'is_hybrid'               => $booking->is_hybrid,
            'is_flipchart'            => $booking->is_flipchart,
            'catatan_admin'           => $booking->catatan_admin,
            'catatan_user'            => $booking->catatan_user,
            'participants'            => $booking->participants,
            'can_cancel'              => $booking->canBeCancelled(),
            'can_update_participants' => $booking->canUpdateParticipants(),
            // no can_request_date_change to enforce No-Edit rule
        ];

        return Inertia::render('User/BookingDetail', [
            'booking' => $bookingData,
        ]);
    }

    public function downloadPdf(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }

        $booking->load(['ruangan', 'participants']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.booking-ticket', compact('booking'));
        
        return $pdf->download('Bukti_Booking_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $booking->nama_training) . '.pdf');
    }

    public function exportDetail(Booking $booking)
    {
        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }

        $path = (new \App\Services\BookingExcelExportService())->generate($booking);

        return response()->download($path, basename($path))->deleteFileAfterSend(true);
    }
}
