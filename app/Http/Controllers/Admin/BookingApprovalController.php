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

        // Pengurutan: jika melihat tab antrean (menunggu, urgent, overdue), urutkan dari yang TERLAMA (asc).
        // Jika melihat tab lain (semua, disetujui, ditolak), urutkan dari yang TERBARU (desc).
        if (in_array($filter, ['waiting_confirmation', 'urgent', 'overdue'])) {
            $query->orderBy('created_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $bookings = $query
            ->orderByRaw("CASE status 
                WHEN 'waiting_confirmation' THEN 1 
                WHEN 'confirmed' THEN 2 
                WHEN 'cancelled' THEN 3 
                WHEN 'plotting' THEN 4 
                ELSE 5 END")
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
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($booking) {
                // Tentukan daftar ID ruangan yang dipesan
                $roomIds = [$booking->ruangan_id];
                $booking->loadMissing('ruangan');
                if ($booking->gabung_ruang && $booking->ruangan && $booking->ruangan->pasangan_ruang_id) {
                    $roomIds[] = $booking->ruangan->pasangan_ruang_id;
                }

                // Pessimistic locking pada baris ruangan di database
                \App\Models\Ruangan::whereIn('id', $roomIds)->lockForUpdate()->get();

                // Cek konflik: apakah ada booking CONFIRMED lain yang bertabrakan tanggal dan ruangan
                $overlappingConfirmed = Booking::where('status', 'confirmed')
                    ->where('id', '!=', $booking->id)
                    ->where('tgl_mulai', '<=', $booking->tgl_selesai->toDateString())
                    ->where('tgl_selesai', '>=', $booking->tgl_mulai->toDateString())
                    ->with('ruangan')
                    ->get();

                $hasConflict = false;
                foreach ($overlappingConfirmed as $cb) {
                    $cbRoomIds = [$cb->ruangan_id];
                    if ($cb->gabung_ruang && $cb->ruangan && $cb->ruangan->pasangan_ruang_id) {
                        $cbRoomIds[] = $cb->ruangan->pasangan_ruang_id;
                    }
                    if (!empty(array_intersect($roomIds, $cbRoomIds))) {
                        $hasConflict = true;
                        break;
                    }
                }

                if ($hasConflict) {
                    throw new \Exception("Gagal menyetujui: Ruangan telah terisi (disetujui oleh admin lain) pada rentang tanggal tersebut.");
                }

                // Ubah status menjadi confirmed (ruangan resmi terkunci)
                $booking->update(['status' => 'confirmed']);

                // Catat log administratif
                \App\Models\BookingLog::create([
                    'booking_id' => $booking->id,
                    'user_id'    => auth()->id(),
                    'action'     => 'approve',
                    'message'    => "Admin " . auth()->user()->name . " menyetujui booking ini.",
                ]);

                // Kirim/Catat notifikasi untuk user pemohon
                \App\Models\BookingNotification::create([
                    'user_id'    => $booking->user_id,
                    'booking_id' => $booking->id,
                    'tipe'       => 'approval',
                    'title'      => 'Booking Disetujui',
                    'message'      => "Booking Anda untuk '{$booking->nama_training}' pada tanggal " . $booking->tgl_mulai->format('d M Y') . " telah disetujui.",
                ]);

                \Illuminate\Support\Facades\Log::info("Booking #{$booking->id} disetujui oleh admin ID #" . auth()->id());
            });

            return back()->with('success', "Booking #{$booking->id} berhasil disetujui.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Tolak booking.
     */
    public function reject(Request $request, Booking $booking)
    {
        $request->validate([
            'catatan_admin' => 'required|string|max:500',
        ], [
            'catatan_admin.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $booking->update([
            'status' => 'cancelled',
            'catatan_admin' => $request->catatan_admin,
        ]);

        return back()->with('success', "Booking #{$booking->id} telah ditolak.");
    }
}
