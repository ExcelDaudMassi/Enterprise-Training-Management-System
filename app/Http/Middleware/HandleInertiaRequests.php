<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     * Data di sini tersedia di semua komponen Vue via usePage().props
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id'     => $request->user()->id,
                    'name'   => $request->user()->name,
                    'email'  => $request->user()->email,
                    'role'   => $request->user()->role,
                    'divisi' => $request->user()->divisi,
                ] : null,
            ],
            // Share global Booking Window status
            'bookingWindow' => function () {
                $activeWindow = \App\Models\BookingWindow::active()->latest('id')->first();
                return $activeWindow ? [
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
            },
            // Share global notifications for Admin Layout
            'notifications' => function () {
                if (!auth()->check() || auth()->user()->role !== 'admin') {
                    return [];
                }
                
                $today     = \Illuminate\Support\Carbon::today();
                $h14Cutoff = $today->copy()->addDays(14);

                // a) Booking baru
                $newBookings = \App\Models\Booking::with('user')
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

                // b) Urgent H-14
                $urgentBookings = \App\Models\Booking::with('user')
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

                // c) Overdue
                $overdueBookings = \App\Models\Booking::with('user')
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

                return collect()
                    ->merge($overdueBookings)
                    ->merge($urgentBookings)
                    ->merge($newBookings)
                    ->unique('booking_id')
                    ->values()
                    ->toArray();
            },
            // Share user notifications
            'userNotifications' => function () {
                if (!auth()->check()) {
                    return [];
                }
                return \App\Models\BookingNotification::where('user_id', auth()->id())
                    ->where('is_read', false)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(fn($n) => [
                        'id'         => $n->id,
                        'booking_id' => $n->booking_id,
                        'tipe'       => $n->tipe,
                        'title'      => $n->title,
                        'message'    => $n->message,
                        'created_at' => $n->created_at->diffForHumans(),
                    ]);
            },
            // Share flash messages
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ];
    }
}
