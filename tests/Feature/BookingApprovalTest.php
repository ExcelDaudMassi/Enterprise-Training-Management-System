<?php

use App\Models\User;
use App\Models\Ruangan;
use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\BookingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can successfully approve a booking, creating logs and notifications', function () {
    // 1. Create a user, an admin, and a room
    $user = User::create([
        'name' => 'Regular User',
        'email' => 'user@example.com',
        'password' => bcrypt('password'),
        'role' => 'user',
        'divisi' => 'IT'
    ]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'divisi' => 'Management'
    ]);

    $ruang = Ruangan::create([
        'nama_ruang' => 'Ruang 1',
        'lokasi_gedung' => 'Gedung A',
        'kapasitas_max' => 30,
        'bisa_digabung' => false
    ]);

    // 2. Create a pending booking
    $booking = Booking::create([
        'user_id' => $user->id,
        'ruangan_id' => $ruang->id,
        'nama_training' => 'PHP Advanced',
        'tgl_mulai' => '2026-06-01',
        'tgl_selesai' => '2026-06-03',
        'fase' => 'reguler',
        'status' => 'pending',
        'pic' => 'John Doe',
        'gabung_ruang' => false
    ]);

    // 3. Act as admin and approve
    $response = $this->actingAs($admin)
        ->post(route('admin.bookings.approve', $booking));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // 4. Assert DB changes
    $booking->refresh();
    expect($booking->status)->toBe('confirmed');

    // Assert Log
    $log = BookingLog::where('booking_id', $booking->id)->first();
    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($admin->id);
    expect($log->action)->toBe('approve');

    // Assert Notification
    $notification = BookingNotification::where('booking_id', $booking->id)->first();
    expect($notification)->not->toBeNull();
    expect($notification->user_id)->toBe($user->id);
    expect($notification->is_read)->toBeFalse();
});

test('admin cannot approve overlapping booking, transaction rolls back successfully', function () {
    $user = User::create([
        'name' => 'Regular User',
        'email' => 'user@example.com',
        'password' => bcrypt('password'),
        'role' => 'user',
        'divisi' => 'IT'
    ]);

    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'divisi' => 'Management'
    ]);

    $ruang = Ruangan::create([
        'nama_ruang' => 'Ruang 1',
        'lokasi_gedung' => 'Gedung A',
        'kapasitas_max' => 30,
        'bisa_digabung' => false
    ]);

    // 1. Create a confirmed booking
    $confirmedBooking = Booking::create([
        'user_id' => $user->id,
        'ruangan_id' => $ruang->id,
        'nama_training' => 'First Confirmed Event',
        'tgl_mulai' => '2026-06-01',
        'tgl_selesai' => '2026-06-03',
        'fase' => 'reguler',
        'status' => 'confirmed',
        'pic' => 'John',
        'gabung_ruang' => false
    ]);

    // 2. Create another pending booking that overlaps
    $pendingBooking = Booking::create([
        'user_id' => $user->id,
        'ruangan_id' => $ruang->id,
        'nama_training' => 'Overlapping Event',
        'tgl_mulai' => '2026-06-02',
        'tgl_selesai' => '2026-06-04',
        'fase' => 'reguler',
        'status' => 'pending',
        'pic' => 'Jane',
        'gabung_ruang' => false
    ]);

    // 3. Act as admin and try to approve overlapping
    $response = $this->actingAs($admin)
        ->post(route('admin.bookings.approve', $pendingBooking));

    $response->assertRedirect();
    $response->assertSessionHas('error'); // Error message should be in session flash

    // 4. Assert that the pending booking status is NOT confirmed
    $pendingBooking->refresh();
    expect($pendingBooking->status)->toBe('pending');

    // Assert no log was created for the failed booking
    $logCount = BookingLog::where('booking_id', $pendingBooking->id)->count();
    expect($logCount)->toBe(0);

    // Assert no notification was created for the failed booking
    $notifCount = BookingNotification::where('booking_id', $pendingBooking->id)->count();
    expect($notifCount)->toBe(0);
});
