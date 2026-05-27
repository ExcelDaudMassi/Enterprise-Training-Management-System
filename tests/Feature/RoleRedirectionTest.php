<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('regular user cannot access admin routes and gets redirected to user dashboard with error', function () {
    $user = User::create([
        'name' => 'Regular User',
        'email' => 'user@example.com',
        'password' => bcrypt('password'),
        'role' => 'user',
        'divisi' => 'IT'
    ]);

    $response = $this->actingAs($user)
        ->get(route('admin.dashboard'));

    $response->assertRedirect(route('user.dashboard'));
    $response->assertSessionHas('error', 'Akses ditolak. Halaman tersebut hanya untuk Admin.');
});

test('admin cannot access user routes and gets redirected to admin dashboard with error', function () {
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'divisi' => 'Management'
    ]);

    $response = $this->actingAs($admin)
        ->get(route('user.dashboard'));

    $response->assertRedirect(route('admin.dashboard'));
    $response->assertSessionHas('error', 'Akses ditolak. Halaman tersebut hanya untuk User.');
});

test('unauthenticated user is redirected to login page when accessing protected routes', function () {
    $response = $this->get(route('user.dashboard'));
    $response->assertRedirect(route('login'));

    $response2 = $this->get(route('admin.dashboard'));
    $response2->assertRedirect(route('login'));
});
