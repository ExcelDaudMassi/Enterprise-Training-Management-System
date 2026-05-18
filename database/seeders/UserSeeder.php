<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun Admin
        User::create([
            'name'     => 'Administrator',
            'email'    => 'admin@bbso.com',
            'password' => 'password',
            'role'     => 'admin',
            'divisi'   => null,
        ]);

        // Akun User biasa
        User::create([
            'name'     => 'Budi Santoso',
            'email'    => 'user@bbso.com',
            'password' => 'password',
            'role'     => 'user',
            'divisi'   => 'Human Resources',
        ]);

        User::create([
            'name'     => 'Siti Rahma',
            'email'    => 'siti@bbso.com',
            'password' => 'password',
            'role'     => 'user',
            'divisi'   => 'Finance',
        ]);
    }
}
