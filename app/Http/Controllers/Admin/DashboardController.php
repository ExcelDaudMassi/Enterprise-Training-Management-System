<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingWindow;
use App\Models\Ruangan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today     = Carbon::today();
        $now       = Carbon::now();
        $h14Cutoff = $today->copy()->addDays(14);

        // ── 4 Stats Cards ──────────────────────────────────────────────
        $stats = [
            // Kartu 1 – Menunggu approval
            'pending_approval' => Booking::where('status', 'waiting_confirmation')->count(),

            // Kartu 2 – Confirmed bulan berjalan
            'confirmed_this_month' => Booking::where('status', 'confirmed')
                ->whereMonth('tgl_mulai', $today->month)
                ->whereYear('tgl_mulai', $today->year)
                ->count(),

            // Kartu 3 – Urgent: waiting + mulai dalam ≤ 14 hari
            'urgent_h14' => Booking::where('status', 'waiting_confirmation')
                ->where('tgl_mulai', '<=', $h14Cutoff)
                ->where('tgl_mulai', '>=', $today)
                ->count(),

            // Kartu 4 – Ruangan terpakai hari ini (unique ruangan_id)
            'rooms_today' => Booking::where('status', 'confirmed')
                ->where('tgl_mulai', '<=', $today)
                ->where('tgl_selesai', '>=', $today)
                ->whereNotNull('ruangan_id')
                ->distinct('ruangan_id')
                ->count('ruangan_id'),
        ];

        // ── Booking Window ─────────────────────────────────────────────
        $activeWindow = BookingWindow::active()->latest('id')->first();
        $bookingWindow = $activeWindow ? [
            'is_active'  => true,
            'end_date'   => $activeWindow->end_date?->toDateString(),
            'nama'       => $activeWindow->nama_periode,
            'id'         => $activeWindow->id,
        ] : [
            'is_active'  => false,
            'end_date'   => null,
            'nama'       => null,
            'id'         => null,
        ];

        // ── Notifications ──────────────────────────────────────────────
        // a) Booking baru (waiting_confirmation, urut dari terbaru, max 5)
        $newBookings = Booking::with('user')
            ->where('status', 'waiting_confirmation')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn($b) => [
                'type'          => 'new',
                'booking_id'    => $b->id,
                'label'         => "Booking baru: {$b->nama_training}",
                'sub'           => $b->user?->name ?? '-',
                'created_at'    => $b->created_at->diffForHumans(),
                'filter'        => 'waiting_confirmation',
            ]);

        // b) Urgent H-14 (waiting, tgl_mulai dalam 14 hari)
        $urgentBookings = Booking::with('user')
            ->where('status', 'waiting_confirmation')
            ->where('tgl_mulai', '<=', $h14Cutoff)
            ->where('tgl_mulai', '>=', $today)
            ->orderBy('tgl_mulai', 'asc')
            ->take(5)
            ->get()
            ->map(fn($b) => [
                'type'          => 'urgent',
                'booking_id'    => $b->id,
                'label'         => "Urgent H-14: {$b->nama_training}",
                'sub'           => "Mulai " . $b->tgl_mulai?->format('d M Y'),
                'created_at'    => $b->created_at->diffForHumans(),
                'filter'        => 'urgent',
            ]);

        // c) Melewati deadline: waiting + tgl_mulai sudah lewat (overdue)
        $overdueBookings = Booking::with('user')
            ->where('status', 'waiting_confirmation')
            ->where('tgl_mulai', '<', $today)
            ->orderBy('tgl_mulai', 'asc')
            ->take(5)
            ->get()
            ->map(fn($b) => [
                'type'          => 'overdue',
                'booking_id'    => $b->id,
                'label'         => "Lewat tenggat: {$b->nama_training}",
                'sub'           => "Seharusnya mulai " . $b->tgl_mulai?->format('d M Y'),
                'created_at'    => $b->created_at->diffForHumans(),
                'filter'        => 'overdue',
            ]);

        // Gabung semua notifikasi, urutkan: overdue → urgent → new
        $notifications = collect()
            ->merge($overdueBookings)
            ->merge($urgentBookings)
            ->merge($newBookings)
            ->unique('booking_id')
            ->values();

        // ── DATA KALENDER (Sama seperti User Dashboard) ─────────────────
        $year = (int) $request->get('year', $activeWindow?->tahun ?? 2027);
        $ruanganFilter = $request->get('ruangan_id');

        $ruanganList = Ruangan::all(['id', 'nama_ruang', 'lokasi_gedung', 'kapasitas_max']);

        $bookingQuery = Booking::with(['ruangan:id,nama_ruang', 'user:id,name,divisi', 'participants'])
            ->where(function ($query) use ($year) {
                $query->whereYear('tgl_mulai', $year)
                      ->orWhereYear('tgl_selesai', $year);
            })
            ->whereNotIn('status', ['cancelled']);

        if ($ruanganFilter) {
            $bookingQuery->where('ruangan_id', $ruanganFilter);
        }

        $bookings = $bookingQuery->get()->map(function ($booking) {
            return [
                'id'            => $booking->id,
                'ruangan_id'    => $booking->ruangan_id,
                'nama_ruang'    => $booking->ruangan?->nama_ruang,
                'nama_training' => $booking->nama_training,
                'divisi'        => $booking->user?->divisi,
                'pemohon'       => $booking->user?->name,
                'pic'           => $booking->pic,
                'fase'          => $booking->fase,
                'gabung_ruang'  => (bool) $booking->gabung_ruang,
                'tgl_mulai'     => $booking->tgl_mulai->toDateString(),
                'tgl_selesai'   => $booking->tgl_selesai->toDateString(),
                'status'        => $booking->status,
                'created_at'    => $booking->created_at->toIso8601String(),
                'participants'  => $booking->participants->map(function ($p) {
                    return [
                        'nama'    => $p->nama,
                        'jabatan' => $p->jabatan,
                        'site'    => $p->site,
                        'gender'  => $p->gender,
                        'tipe'    => $p->tipe,
                    ];
                }),
            ];
        });

        return Inertia::render('Admin/Dashboard', [
            'auth'           => ['user' => ['name' => Auth::user()->name, 'email' => Auth::user()->email, 'role' => Auth::user()->role]],
            'stats'          => $stats,
            'bookingWindow'  => $bookingWindow,
            'notifications'  => $notifications,
            'ruanganList'    => $ruanganList,
            'bookings'       => $bookings,
            'selectedYear'   => $year,
            'selectedRuangan'=> $ruanganFilter ? (int) $ruanganFilter : null,
        ]);
    }
}
