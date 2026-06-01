<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingExcelExportService;
use Illuminate\Http\Request;

class BookingShareController extends Controller
{
    /**
     * Tampilkan halaman publik detail booking (tanpa login).
     * Dilindungi oleh signed URL dari Laravel agar tidak bisa diakses sembarangan.
     */
    public function show(Request $request, Booking $booking)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Link ini tidak valid atau sudah kadaluarsa.');
        }

        $booking->load(['ruangan', 'user', 'participants']);

        $peserta  = $booking->participants->where('tipe', 'peserta')->values();
        $panitia  = $booking->participants->where('tipe', 'panitia')->values();

        // Buat URL download Excel yang juga pakai signed URL (berlaku sama)
        $downloadUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'booking.share.download',
            \Carbon\Carbon::createFromTimestamp($request->query('expires')),
            ['booking' => $booking->id]
        );

        return view('booking.share', compact('booking', 'peserta', 'panitia', 'downloadUrl'));
    }

    /**
     * Download file Excel detail booking (tanpa login, via signed URL).
     */
    public function download(Request $request, Booking $booking)
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Link ini tidak valid atau sudah kadaluarsa.');
        }

        $service  = new BookingExcelExportService();
        $filePath = $service->generate($booking);
        $filename = basename(preg_replace('/^temp_excel_[a-z0-9]+_/', '', $filePath));

        return response()->download($filePath, $filename)->deleteFileAfterSend(true);
    }
}
