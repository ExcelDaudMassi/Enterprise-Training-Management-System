<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

class ScanH14Bookings extends Command
{
    protected $signature = 'app:scan-h14-bookings';

    protected $description = 'Otomatis memproses booking (ACC atau Batal) yang masuk H-14 sesuai pengaturan sistem.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $setting = \App\Models\Setting::where('key', 'h14_mode')->first();
        $mode = $setting ? $setting->value : 'manual';

        if ($mode === 'manual') {
            $this->info('Mode H-14 adalah manual. Tidak ada tindakan yang diambil.');
            return;
        }

        $cutoffDate = \Illuminate\Support\Carbon::today()->addDays(14);

        $bookings = \App\Models\Booking::where('status', \App\Models\Booking::STATUS_CONFIRMED)
            ->where('tgl_mulai', '<=', $cutoffDate)
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('Tidak ada booking terkonfirmasi pada H-14 yang perlu diproses.');
            return;
        }

        $count = 0;
        foreach ($bookings as $booking) {
            if ($mode === 'auto_acc') {
                $booking->update([
                    'status' => \App\Models\Booking::STATUS_FINAL,
                ]);
                $this->info("Booking #{$booking->id} otomatis di-ACC Final (H-14).");
                $count++;
            } elseif ($mode === 'auto_cancel') {
                $booking->update([
                    'status' => \App\Models\Booking::STATUS_CANCELLED,
                    'catatan_admin' => 'Dibatalkan secara otomatis oleh sistem karena melewati batas H-14 tanpa konfirmasi.'
                ]);
                $this->info("Booking #{$booking->id} otomatis dibatalkan (H-14).");
                $count++;
            }
        }

        $this->info("Berhasil memproses {$count} booking dengan mode {$mode}.");
    }
}
