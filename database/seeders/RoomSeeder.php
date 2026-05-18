<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            [
                'name'        => 'Ruang Rapat Utama',
                'capacity'    => 30,
                'description' => 'Ruang rapat besar dengan proyektor, whiteboard, dan AC. Cocok untuk rapat divisi atau presentasi.',
                'location'    => 'Lantai 2',
                'is_active'   => true,
            ],
            [
                'name'        => 'Ruang Meeting A',
                'capacity'    => 10,
                'description' => 'Ruang meeting kecil dengan TV 55 inci dan video conference. Cocok untuk meeting tim kecil.',
                'location'    => 'Lantai 1',
                'is_active'   => true,
            ],
            [
                'name'        => 'Ruang Meeting B',
                'capacity'    => 10,
                'description' => 'Ruang meeting kecil dengan whiteboard dan AC. Tersedia colokan di setiap sudut meja.',
                'location'    => 'Lantai 1',
                'is_active'   => true,
            ],
            [
                'name'        => 'Aula Serbaguna',
                'capacity'    => 100,
                'description' => 'Aula besar untuk acara pelatihan, seminar, atau company gathering. Dilengkapi sound system.',
                'location'    => 'Lantai 3',
                'is_active'   => true,
            ],
            [
                'name'        => 'Ruang Training',
                'capacity'    => 20,
                'description' => 'Ruang khusus training dengan laptop dan komputer di setiap meja peserta.',
                'location'    => 'Lantai 2',
                'is_active'   => true,
            ],
            [
                'name'        => 'Ruang Presentasi',
                'capacity'    => 15,
                'description' => 'Ruang khusus presentasi dengan layar besar dan sistem audio yang baik. Cocok untuk demo produk atau pitching.',
                'location'    => 'Lantai 3',
                'is_active'   => true,
            ],
            [
                'name'        => 'Ruang Diskusi',
                'capacity'    => 6,
                'description' => 'Ruang kecil untuk diskusi informal atau brainstorming antar tim. Dilengkapi whiteboard dan sofa.',
                'location'    => 'Lantai 1',
                'is_active'   => true,
            ],
        ];

        foreach ($rooms as $room) {
            Room::create($room);
        }
    }
}
