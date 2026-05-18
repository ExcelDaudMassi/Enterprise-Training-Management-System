<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Inertia\Inertia;

class BookingHistoryController extends Controller
{
    public function index()
    {
        $bookings = Booking::with('ruangan')
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('User/BookingHistory', [
            'bookings' => $bookings
        ]);
    }
}
