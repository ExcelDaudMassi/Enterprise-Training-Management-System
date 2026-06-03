<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\Booking;
use Illuminate\Support\Carbon;

#[Signature('app:auto-reject-bookings')]
#[Description('Otomatis membatalkan booking yang berstatus menunggu persetujuan pada H-3 sebelum tanggal mulai.')]
class AutoRejectBookings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cutoffDate = Carbon::today()->addDays(3);

        $bookingsToReject = Booking::where('status', Booking::STATUS_PENDING)
            ->where('tgl_mulai', '<=', $cutoffDate)
            ->get();

        $count = 0;
        foreach ($bookingsToReject as $booking) {
            $booking->update([
                'status' => Booking::STATUS_REJECTED,
                'catatan_admin' => 'Dibatalkan otomatis karena melewati batas waktu konfirmasi Admin (H-3 acara).'
            ]);
            $count++;
        }

        $this->info("Berhasil membatalkan otomatis {$count} booking yang melewati batas H-3.");
    }
}
