<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

class ScanPreparationBookings extends Command
{
    protected $signature = 'app:scan-preparation-bookings';

    protected $description = 'Otomatis memproses booking (ACC atau Batal) yang masuk masa persiapan (Preparation Alert) sesuai pengaturan sistem.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $settingMode = \App\Models\Setting::where('key', 'preparation_alert_mode')->first();
        $mode = $settingMode ? $settingMode->value : 'manual';
        
        $settingDays = \App\Models\Setting::where('key', 'preparation_alert_days')->first();
        $days = $settingDays ? (int) $settingDays->value : 14;

        if ($mode === 'manual') {
            $this->info('Mode Preparation Alert adalah manual. Tidak ada tindakan yang diambil.');
            return;
        }

        $cutoffDate = \Illuminate\Support\Carbon::today()->addDays($days);

        $bookings = \App\Models\Booking::where('status', \App\Models\Booking::STATUS_CONFIRMED)
            ->where('tgl_mulai', '<=', $cutoffDate)
            ->where('tgl_mulai', '>=', \Illuminate\Support\Carbon::today())
            ->get();

        if ($bookings->isEmpty()) {
            $this->info("Tidak ada booking terkonfirmasi pada H-{$days} yang perlu diproses.");
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
                    'message'    => 'Sistem secara otomatis menyetujui booking ini (Preparation Auto ACC).',
                ]);

                \App\Models\BookingNotification::create([
                    'user_id'    => $booking->user_id,
                    'booking_id' => $booking->id,
                    'tipe'       => 'approval',
                    'title'      => 'Booking Final ACC (Otomatis)',
                    'message'    => "Booking Anda untuk '{$booking->nama_training}' telah otomatis di-ACC Final oleh sistem karena telah memasuki masa persiapan. Persiapan lapangan segera dilakukan.",
                ]);

                // Kirim notifikasi Real-Time ke UI
                broadcast(new \App\Events\BookingStatusUpdated($booking));
                \Illuminate\Support\Facades\Log::info("Broadcast Preparation Auto ACC sent for booking #{$booking->id}");

                $this->info("Booking #{$booking->id} otomatis di-ACC Final (H-{$days}).");
                $count++;
            } elseif ($mode === 'auto_cancel') {
                $alasan = "Dibatalkan secara otomatis oleh sistem karena melewati batas persiapan H-{$days} tanpa konfirmasi.";
                
                $booking->update([
                    'status' => \App\Models\Booking::STATUS_CANCELLED,
                    'catatan_admin' => $alasan
                ]);

                \App\Models\BookingLog::create([
                    'booking_id' => $booking->id,
                    'user_id'    => $booking->user_id, // Gunakan user pemilik booking
                    'action'     => 'auto_cancel',
                    'message'    => 'Sistem otomatis membatalkan booking (Preparation Auto Cancel).',
                ]);

                \App\Models\BookingNotification::create([
                    'user_id'    => $booking->user_id,
                    'booking_id' => $booking->id,
                    'tipe'       => 'rejection',
                    'title'      => 'Booking Dibatalkan (Otomatis)',
                    'message'    => "Booking Anda untuk '{$booking->nama_training}' telah dibatalkan secara otomatis karena {$alasan}",
                ]);

                // Kirim notifikasi Real-Time ke UI
                broadcast(new \App\Events\BookingStatusUpdated($booking));

                $this->info("Booking #{$booking->id} otomatis dibatalkan (H-{$days}).");
                $count++;
            }
        }

        $this->info("Berhasil memproses {$count} booking dengan mode {$mode}.");
    }
}
