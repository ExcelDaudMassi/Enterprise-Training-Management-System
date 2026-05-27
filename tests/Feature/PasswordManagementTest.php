<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('unauthenticated user cannot access password settings page', function () {
    $response = $this->get(route('user.settings.password'));
    $response->assertRedirect(route('login'));
});

test('regular user can access password settings page', function () {
    $user = User::create([
        'name' => 'Regular User',
        'email' => 'user@example.com',
        'password' => bcrypt('password123'),
        'role' => 'user',
        'divisi' => 'IT'
    ]);

    $response = $this->actingAs($user)->get(route('user.settings.password'));
    $response->assertStatus(200);
});

test('admin cannot access user password settings page', function () {
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password123'),
        'role' => 'admin',
        'divisi' => 'Management'
    ]);

    $response = $this->actingAs($admin)->get(route('user.settings.password'));
    $response->assertRedirect(route('admin.dashboard'));
    $response->assertSessionHas('error');
});

test('user cannot update password with incorrect current password', function () {
    $user = User::create([
        'name' => 'Regular User',
        'email' => 'user@example.com',
        'password' => bcrypt('password123'),
        'role' => 'user',
        'divisi' => 'IT'
    ]);

    $response = $this->actingAs($user)->post(route('user.settings.password.update'), [
        'current_password' => 'wrongpassword',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors(['current_password']);
    expect(Hash::check('password123', $user->fresh()->password))->toBeTrue();
});

test('user cannot update password if confirmation does not match', function () {
    $user = User::create([
        'name' => 'Regular User',
        'email' => 'user@example.com',
        'password' => bcrypt('password123'),
        'role' => 'user',
        'divisi' => 'IT'
    ]);

    $response = $this->actingAs($user)->post(route('user.settings.password.update'), [
        'current_password' => 'password123',
        'password' => 'newpassword123',
        'password_confirmation' => 'differentpassword',
    ]);

    $response->assertSessionHasErrors(['password']);
    expect(Hash::check('password123', $user->fresh()->password))->toBeTrue();
});

test('user cannot update password to less than 8 characters', function () {
    $user = User::create([
        'name' => 'Regular User',
        'email' => 'user@example.com',
        'password' => bcrypt('password123'),
        'role' => 'user',
        'divisi' => 'IT'
    ]);

    $response = $this->actingAs($user)->post(route('user.settings.password.update'), [
        'current_password' => 'password123',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors(['password']);
    expect(Hash::check('password123', $user->fresh()->password))->toBeTrue();
});

test('user can update password successfully and login with new password', function () {
    $user = User::create([
        'name' => 'Regular User',
        'email' => 'user@example.com',
        'password' => bcrypt('password123'),
        'role' => 'user',
        'divisi' => 'IT'
    ]);

    $response = $this->actingAs($user)->post(route('user.settings.password.update'), [
        'current_password' => 'password123',
        'password' => 'newsecurepass123',
        'password_confirmation' => 'newsecurepass123',
    ]);

    $response->assertRedirect(route('user.dashboard'));
    $response->assertSessionHas('success');
    expect(Hash::check('newsecurepass123', $user->fresh()->password))->toBeTrue();
});
