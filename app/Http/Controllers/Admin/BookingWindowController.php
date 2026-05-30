<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingWindowController extends Controller
{
    /**
     * Tampilkan riwayat booking window.
     */
    public function index()
    {
        $windows = BookingWindow::orderBy('created_at', 'desc')->get()->map(function ($w) {
            // Hitung total booking yang masuk selama window ini dibuka (berdasarkan rentang waktu)
            // Karena window bisa saja belum ditutup, kita asumsikan end_date sebagai batas.
            // Jika ingin ketat, hitung booking yang created_at nya berada di antara start_date dan end_date.
            $totalBooking = \App\Models\Booking::whereDate('created_at', '>=', $w->start_date)
                ->whereDate('created_at', '<=', $w->end_date ?? now())
                ->count();

            return [
                'id'           => $w->id,
                'nama_periode' => $w->nama_periode,
                'tahun'        => $w->tahun,
                'start_date'   => $w->start_date?->toDateString(),
                'end_date'     => $w->end_date?->toDateString(),
                'is_active'    => $w->is_active,
                'total_booking'=> $totalBooking,
            ];
        });

        return \Inertia\Inertia::render('Admin/BookingWindowHistory', [
            'windows' => $windows,
        ]);
    }

    /**
     * Buka / buat booking window baru.
     */
    public function open(Request $request)
    {
        $request->validate([
            'tahun'      => ['required', 'integer', 'min:2024'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        // Nonaktifkan semua window lama
        BookingWindow::where('is_active', true)->update(['is_active' => false]);

        // Buat window baru
        BookingWindow::create([
            'nama_periode' => 'Periode ' . $request->tahun,
            'tahun'        => $request->tahun,
            'is_active'    => true,
            'start_date'   => Carbon::parse($request->start_date),
            'end_date'     => Carbon::parse($request->end_date),
        ]);

        return back()->with('success', 'Window booking berhasil dibuka.');
    }

    /**
     * Tutup booking window aktif.
     */
    public function close()
    {
        // Validasi: Cek apakah masih ada booking yang berstatus waiting_confirmation
        $pendingCount = \App\Models\Booking::where('status', 'waiting_confirmation')->count();

        if ($pendingCount > 0) {
            return back()->with('error', "Tidak dapat menutup window. Terdapat {$pendingCount} pengajuan yang belum diproses (Setuju/Tolak). Harap selesaikan seluruh antrean sebelum menutup periode pemesanan.");
        }

        BookingWindow::where('is_active', true)->update(['is_active' => false]);

        return back()->with('success', 'Window booking berhasil ditutup.');
    }
}
