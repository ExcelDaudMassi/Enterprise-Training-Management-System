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
        $days      = \App\Models\Setting::where('key', 'preparation_alert_days')->value('value') ?? 14;
        $prepCutoff = $today->copy()->addDays((int) $days);

        // ── Tahun filter (dipakai untuk stats cards & donut chart) ─────
        $activeWindowForYear = \App\Models\BookingWindow::active()->latest('id')->first();
        $year = (int) $request->get('year', $activeWindowForYear?->tahun ?? 2027);
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd   = Carbon::create($year, 12, 31)->endOfDay();

        // Preparation Alert cutoff kontekstual:
        // – Untuk tahun berjalan: X hari dari hari ini
        // – Untuk tahun lain (masa lalu / depan): seluruh tahun tersebut
        $currentYear = $today->year;
        if ($year === $currentYear) {
            $prepYearStart = $today->copy();
            $prepYearEnd   = $prepCutoff->copy();
        } else {
            $prepYearStart = $yearStart->copy();
            $prepYearEnd   = $yearEnd->copy();
        }

        // ── 4 Stats Cards (berdasarkan tahun yang dipilih) ─────────────
        $stats = [
            // Kartu 1 – Pending (menunggu ACC) untuk tahun yang dipilih
            'pending_approval' => Booking::where('status', Booking::STATUS_PENDING)
                ->where(function ($q) use ($yearStart, $yearEnd) {
                    $q->whereBetween('tgl_mulai', [$yearStart, $yearEnd])
                      ->orWhereBetween('tgl_selesai', [$yearStart, $yearEnd]);
                })
                ->count(),

            // Kartu 2 – Confirmed untuk tahun yang dipilih
            'confirmed_count' => Booking::where('status', Booking::STATUS_CONFIRMED)
                ->where(function ($q) use ($yearStart, $yearEnd) {
                    $q->whereBetween('tgl_mulai', [$yearStart, $yearEnd])
                      ->orWhereBetween('tgl_selesai', [$yearStart, $yearEnd]);
                })
                ->count(),

            // Kartu 3 – Finalized untuk tahun yang dipilih
            'finalized_count' => Booking::where('status', Booking::STATUS_FINALIZED)
                ->where(function ($q) use ($yearStart, $yearEnd) {
                    $q->whereBetween('tgl_mulai', [$yearStart, $yearEnd])
                      ->orWhereBetween('tgl_selesai', [$yearStart, $yearEnd]);
                })
                ->count(),

            // Kartu 4 – Ruangan terpakai hari ini (selalu hari ini, tidak difilter tahun)
            'rooms_today' => Booking::whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_FINALIZED])
                ->where('tgl_mulai', '<=', $today)
                ->where('tgl_selesai', '>=', $today)
                ->whereNotNull('ruangan_id')
                ->distinct('ruangan_id')
                ->count('ruangan_id'),

            // Extra – Jumlah dibatalkan/ditolak untuk tahun dipilih (dipakai oleh donut chart)
            'cancelled_count' => Booking::whereIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED])
                ->where(function ($q) use ($yearStart, $yearEnd) {
                    $q->whereBetween('tgl_mulai', [$yearStart, $yearEnd])
                      ->orWhereBetween('tgl_selesai', [$yearStart, $yearEnd]);
                })
                ->count(),
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
            ->where('status', Booking::STATUS_PENDING)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn($b) => [
                'type'          => 'new',
                'booking_id'    => $b->id,
                'label'         => "Booking baru: {$b->nama_training}",
                'sub'           => $b->user?->name ?? '-',
                'created_at'    => $b->created_at->diffForHumans(),
                'filter'        => 'pending',
            ]);

        // b) Urgent Preparation Alert: confirmed, mulai dalam X hari (butuh ACC Final)
        $urgentBookings = Booking::with('user')
            ->where('status', Booking::STATUS_CONFIRMED)
            ->where('tgl_mulai', '<=', $prepCutoff)
            ->where('tgl_mulai', '>=', $today)
            ->orderBy('tgl_mulai', 'asc')
            ->take(5)
            ->get()
            ->map(fn($b) => [
                'type'          => 'urgent',
                'booking_id'    => $b->id,
                'label'         => "H-{$days} Perlu ACC Final: {$b->nama_training}",
                'sub'           => "Mulai " . $b->tgl_mulai?->format('d M Y'),
                'created_at'    => $b->created_at->diffForHumans(),
                'filter'        => 'preparation_alert',
            ]);

        // c) Overdue ACC Tahap 2: confirmed, tgl_mulai sudah lewat, belum final
        // d) Perlu Final Confirmation (ACC-2) (confirmed, tgl_mulai dalam X hari)
        $needAcc2Bookings = Booking::with('user')
            ->where('status', Booking::STATUS_CONFIRMED)
            ->where('tgl_mulai', '<=', $prepCutoff)
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
        $ruanganFilter = $request->get('ruangan_id');

        $ruanganList = Ruangan::all(['id', 'nama_ruang', 'lokasi_gedung', 'kapasitas_max', 'pasangan_ruang_id']);

        // Exclude cancelled — booking batal tidak ditampilkan di kalender & Gantt chart
        // Angka cancelled tetap tersedia via stats['cancelled_count'] untuk donut chart
        $bookingQuery = Booking::with(['ruangan:id,nama_ruang', 'user:id,name,divisi', 'participants'])
            ->where(function ($query) use ($year) {
                $query->whereYear('tgl_mulai', $year)
                      ->orWhereYear('tgl_selesai', $year);
            })
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED]);

        if ($ruanganFilter) {
            $bookingQuery->where('ruangan_id', $ruanganFilter);
        }

        $bookings = $bookingQuery->get()->map(function ($booking) {
            return [
                'id'            => $booking->id,
                'ruangan_id'    => $booking->ruangan_id,
                'nama_ruang'    => $booking->displayRoomName(),
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
                'is_pena_mini_note' => (bool) $booking->is_pena_mini_note,
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
