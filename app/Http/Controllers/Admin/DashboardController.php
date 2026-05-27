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
            // Kartu 1 – Menunggu ACC Tahap 1
            'pending_approval' => Booking::where('status', Booking::STATUS_WAITING_CONFIRMATION)->count(),

            // Kartu 2 – Confirmed bulan berjalan
            'confirmed_this_month' => Booking::whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_FINAL])
                ->whereMonth('tgl_mulai', $today->month)
                ->whereYear('tgl_mulai', $today->year)
                ->count(),

            // Kartu 3 – H-14: confirmed & mulai dalam <= 14 hari (butuh ACC Final)
            'urgent_h14' => Booking::where('status', Booking::STATUS_CONFIRMED)
                ->where('tgl_mulai', '<=', $h14Cutoff)
                ->where('tgl_mulai', '>=', $today)
                ->count(),

            // Kartu 4 – Ruangan terpakai hari ini (confirmed, final, ATAU final_confirmed)
            'rooms_today' => Booking::whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_FINAL])
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

        // b) Urgent H-14: confirmed, mulai dalam 14 hari (butuh ACC Final)
        $urgentBookings = Booking::with('user')
            ->where('status', Booking::STATUS_CONFIRMED)
            ->where('tgl_mulai', '<=', $h14Cutoff)
            ->where('tgl_mulai', '>=', $today)
            ->orderBy('tgl_mulai', 'asc')
            ->take(5)
            ->get()
            ->map(fn($b) => [
                'type'          => 'urgent',
                'booking_id'    => $b->id,
                'label'         => "H-14 Perlu ACC Final: {$b->nama_training}",
                'sub'           => "Mulai " . $b->tgl_mulai?->format('d M Y'),
                'created_at'    => $b->created_at->diffForHumans(),
                'filter'        => 'h14',
            ]);

        // c) Overdue ACC Tahap 2: confirmed, tgl_mulai sudah lewat, belum final
        // d) Perlu Final Confirmation (ACC-2) (confirmed, tgl_mulai dalam 14 hari)
        $needAcc2Bookings = Booking::with('user')
            ->where('status', Booking::STATUS_CONFIRMED)
            ->where('tgl_mulai', '<=', $h14Cutoff)
            ->where('tgl_mulai', '>=', $today)
            ->orderBy('tgl_mulai', 'asc')
            ->take(5)
            ->get()
            ->map(fn($b) => [
                'type'          => 'overdue', // Menjadikan alert merah
                'booking_id'    => $b->id,
                'label'         => "Lewat Batas ACC-2: {$b->nama_training}",
                'sub'           => "Mulai " . $b->tgl_mulai?->format('d M Y'),
                'created_at'    => $b->created_at->diffForHumans(),
                'filter'        => 'confirmed', // Arahkan ke tab confirmed
            ]);
        $overdueBookings = Booking::with('user')
            ->where('status', Booking::STATUS_CONFIRMED)
            ->where('tgl_mulai', '<', $today)
            ->orderBy('tgl_mulai', 'asc')
            ->take(5)
            ->get()
            ->map(fn($b) => [
                'type'          => 'overdue',
                'booking_id'    => $b->id,
                'label'         => "Lewat tenggat ACC: {$b->nama_training}",
                'sub'           => "Seharusnya mulai " . $b->tgl_mulai?->format('d M Y'),
                'created_at'    => $b->created_at->diffForHumans(),
                'filter'        => 'overdue',
            ]);

        // Gabung semua notifikasi, urutkan: overdue → urgent → needAcc2 → new
        $notifications = collect()
            ->merge($overdueBookings)
            ->merge($urgentBookings)
            ->merge($needAcc2Bookings)
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
                'no_hp_pic'     => $booking->no_hp_pic,
                'fase'          => $booking->fase,
                'gabung_ruang'  => (bool) $booking->gabung_ruang,
                'layout_preferensi' => $booking->layout_preferensi,
                'layout_url'    => $booking->layout_custom_path ? asset('storage/' . $booking->layout_custom_path) : null,
                'is_hybrid'     => (bool) $booking->is_hybrid,
                'is_flipchart'  => (bool) $booking->is_flipchart,
                'catatan_admin' => $booking->catatan_admin,
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
