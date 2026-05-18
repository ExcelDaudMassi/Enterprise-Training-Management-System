<?php

namespace Database\Seeders;

use App\Models\Ruangan;
use Illuminate\Database\Seeder;

class RuanganSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: Insert semua ruangan tanpa pasangan_ruang_id dulu
        // karena self-referencing FK tidak bisa diisi sebelum baris lain ada
        $ruangan = [
            ['nama_ruang' => 'Ruang 1', 'lokasi_gedung' => 'Gedung A', 'kapasitas_max' => 30, 'bisa_digabung' => false],
            ['nama_ruang' => 'Ruang 2', 'lokasi_gedung' => 'Gedung A', 'kapasitas_max' => 30, 'bisa_digabung' => true],
            ['nama_ruang' => 'Ruang 3', 'lokasi_gedung' => 'Gedung A', 'kapasitas_max' => 30, 'bisa_digabung' => true],
            ['nama_ruang' => 'Ruang 4', 'lokasi_gedung' => 'Gedung A', 'kapasitas_max' => 30, 'bisa_digabung' => false],
            ['nama_ruang' => 'Ruang 5', 'lokasi_gedung' => 'Gedung B', 'kapasitas_max' => 25, 'bisa_digabung' => false],
            ['nama_ruang' => 'Ruang 6', 'lokasi_gedung' => 'Gedung B', 'kapasitas_max' => 25, 'bisa_digabung' => false],
            ['nama_ruang' => 'Ruang 7', 'lokasi_gedung' => 'Gedung A (sekat)', 'kapasitas_max' => 30, 'bisa_digabung' => false],
        ];

        foreach ($ruangan as $data) {
            Ruangan::create($data);
        }

        // Step 2: Update pasangan_ruang_id untuk Ruang 2 dan Ruang 3
        // (setelah semua baris ada, baru self-reference bisa diisi)
        $ruang2 = Ruangan::where('nama_ruang', 'Ruang 2')->first();
        $ruang3 = Ruangan::where('nama_ruang', 'Ruang 3')->first();

        $ruang2->update(['pasangan_ruang_id' => $ruang3->id]);
        $ruang3->update(['pasangan_ruang_id' => $ruang2->id]);
    }
}
