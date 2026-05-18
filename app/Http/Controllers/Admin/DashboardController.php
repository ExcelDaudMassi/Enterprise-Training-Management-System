<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingWindow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
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
        // Gunakan collect() sebagai base untuk menghindari Eloquent Collection
        // yang akan memanggil getKey() pada array items hasil map()
        $notifications = collect()
            ->merge($overdueBookings)
            ->merge($urgentBookings)
            ->merge($newBookings)
            ->unique('booking_id')
            ->values();

        return Inertia::render('Admin/Dashboard', [
            'auth'           => ['user' => ['name' => Auth::user()->name, 'email' => Auth::user()->email, 'role' => Auth::user()->role]],
            'stats'          => $stats,
            'bookingWindow'  => $bookingWindow,
            'notifications'  => $notifications,
        ]);
    }
}
