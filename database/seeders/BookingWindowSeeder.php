<?php

namespace Database\Seeders;

use App\Models\BookingWindow;
use Illuminate\Database\Seeder;

class BookingWindowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BookingWindow::create([
            'nama_periode' => 'Pemesanan Ruang Training 2027',
            'tahun' => 2027,
            'is_active' => true, // Aktifkan secara default untuk testing
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ]);
    }
}
