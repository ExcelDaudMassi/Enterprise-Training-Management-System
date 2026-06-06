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
                    'status' => \App\Models\Booking::STATUS_FINALIZED,
                    'acc2_at' => now(),
                    // Sistem yang melakukan ACC otomatis (bukan admin spesifik)
                ]);

                \App\Models\BookingLog::create([
                    'booking_id' => $booking->id,
                    'user_id'    => $booking->user_id, // Menggunakan user_id pemilik booking agar tidak error NOT NULL
                    'action'     => 'auto_approve_final',
                    'message'    => 'Sistem secara otomatis menyetujui booking ini (H-14 Auto ACC).',
                ]);

                \App\Models\BookingNotification::create([
                    'user_id'    => $booking->user_id,
                    'booking_id' => $booking->id,
                    'tipe'       => 'approval',
                    'title'      => 'Booking Final ACC (Otomatis H-14)',
                    'message'    => "Booking Anda untuk '{$booking->nama_training}' telah otomatis di-ACC Final oleh sistem karena telah memasuki H-14. Persiapan lapangan segera dilakukan.",
                ]);

                // Kirim notifikasi Real-Time ke UI
                broadcast(new \App\Events\BookingStatusUpdated($booking));
                \Illuminate\Support\Facades\Log::info("Broadcast H-14 Auto ACC sent for booking #{$booking->id}");

                $this->info("Booking #{$booking->id} otomatis di-ACC Final (H-14).");
                $count++;
            } elseif ($mode === 'auto_cancel') {
                $alasan = 'Dibatalkan secara otomatis oleh sistem karena melewati batas H-14 tanpa konfirmasi.';
                
                $booking->update([
                    'status' => \App\Models\Booking::STATUS_CANCELLED,
                    'catatan_admin' => $alasan
                ]);

                \App\Models\BookingLog::create([
                    'booking_id' => $booking->id,
                    'user_id'    => $booking->user_id, // Gunakan user pemilik booking
                    'action'     => 'auto_cancel',
                    'message'    => 'Sistem otomatis membatalkan booking (H-14 Auto Cancel).',
                ]);

                \App\Models\BookingNotification::create([
                    'user_id'    => $booking->user_id,
                    'booking_id' => $booking->id,
                    'tipe'       => 'rejection',
                    'title'      => 'Booking Dibatalkan (Otomatis H-14)',
                    'message'    => "Booking Anda untuk '{$booking->nama_training}' telah dibatalkan secara otomatis karena {$alasan}",
                ]);

                // Kirim notifikasi Real-Time ke UI
                broadcast(new \App\Events\BookingStatusUpdated($booking));

                $this->info("Booking #{$booking->id} otomatis dibatalkan (H-14).");
                $count++;
            }
        }

        $this->info("Berhasil memproses {$count} booking dengan mode {$mode}.");
    }
}
