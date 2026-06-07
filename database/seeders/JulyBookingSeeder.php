<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Booking;
use App\Models\User;
use App\Models\Ruangan;
use Carbon\Carbon;

class JulyBookingSeeder extends Seeder
{
    public function run()
    {
        $users = User::whereIn('id', range(3, 18))->get();
        $ruanganIds = Ruangan::pluck('id')->toArray();
        $statuses = ['waiting_confirmation', 'confirmed', 'finalized', 'rejected', 'completed'];
        
        $faker = \Faker\Factory::create('id_ID');

        foreach ($users as $index => $user) {
            $day = rand(1, 28);
            $startDate = Carbon::create(2026, 7, $day, 8, 0, 0);
            $endDate = (clone $startDate)->addDays(rand(0, 2))->setTime(16, 0, 0);

            $ruanganId = $ruanganIds[array_rand($ruanganIds)];
            $status = $statuses[array_rand($statuses)];
            
            $booking = Booking::create([
                'user_id' => $user->id,
                'ruangan_id' => $ruanganId,
                'nama_training' => 'Training ' . $user->divisi,
                'tgl_mulai' => $startDate,
                'tgl_selesai' => $endDate,
                'fase' => 'reguler',
                'status' => $status,
                'pic' => $user->name,
                'gabung_ruang' => false,
                'layout_preferensi' => 'u-shape',
                'is_hybrid' => rand(0, 1) == 1,
                'is_flipchart' => rand(0, 1) == 1,
                'is_pena_mini_note' => rand(0, 1) == 1,
                'no_hp_pic' => $faker->phoneNumber,
            ]);

            // Add dummy participants
            for ($i = 0; $i < 5; $i++) {
                $booking->participants()->create([
                    'tipe' => 'peserta',
                    'nama' => $faker->name,
                    'jabatan' => 'Staff',
                    'site' => 'JIEP',
                    'no_hp' => $faker->phoneNumber,
                    'gender' => rand(0, 1) ? 'L' : 'P'
                ]);
            }
            
            for ($i = 0; $i < 2; $i++) {
                $booking->participants()->create([
                    'tipe' => 'panitia',
                    'nama' => $faker->name,
                    'jabatan' => 'Panitia',
                    'site' => 'JIEP',
                    'no_hp' => $faker->phoneNumber,
                    'gender' => rand(0, 1) ? 'L' : 'P'
                ]);
            }
        }
    }
}
