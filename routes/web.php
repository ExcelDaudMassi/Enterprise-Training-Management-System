<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\User\DashboardController as UserDashboard;
use App\Http\Controllers\User\BookingWizardController;
use App\Http\Controllers\User\BookingHistoryController;
use App\Http\Controllers\User\BookingManageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\BookingApprovalController;
use Illuminate\Support\Facades\Route;

// ============================================================
// Auth routes
// ============================================================
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/dev/switch-role', [AuthController::class, 'switchRole'])->middleware('auth')->name('dev.switch-role');

// ============================================================
// User routes (auth required + user role)
// ============================================================
Route::middleware(['auth', 'user'])->group(function () {
    Route::get('/user/dashboard', [UserDashboard::class, 'index'])->name('user.dashboard');
    Route::get('/user/booking/create', [BookingWizardController::class, 'create'])->name('user.booking.create');
    Route::get('/user/booking/download-template', [BookingWizardController::class, 'downloadTemplate'])->name('user.booking.download-template');
    Route::get('/user/booking/history', [BookingHistoryController::class, 'index'])->name('user.booking.history');

    // Booking Wizard APIs
    Route::post('/api/booking/validate-participants', [BookingWizardController::class, 'validateParticipants'])->name('api.booking.validate-participants');
    Route::post('/api/booking/check-eligibility', [BookingWizardController::class, 'checkEligibility'])->name('api.booking.check-eligibility');
    Route::post('/api/booking/get-availability', [BookingWizardController::class, 'getAvailability'])->name('api.booking.get-availability');
    Route::post('/api/booking/get-available-rooms', [BookingWizardController::class, 'getAvailableRooms'])->name('api.booking.get-available-rooms');
    Route::post('/api/booking/save-stage4', [BookingWizardController::class, 'saveStage4'])->name('api.booking.save-stage4');
    Route::post('/api/booking/submit', [BookingWizardController::class, 'submitBooking'])->name('api.booking.submit');

    // Polling API untuk realtime check window status
    Route::get('/api/booking-window/status', function () {
        return response()->json([
            'is_active' => \App\Models\BookingWindow::active()->exists(),
        ]);
    })->name('api.booking-window.status');

    // ─── Booking Self-Management (Tahap 3) ───────────────────
    Route::post('/api/booking/{booking}/cancel', [BookingManageController::class, 'cancel'])
         ->name('api.booking.cancel');
    Route::post('/api/booking/{booking}/request-date-change', [BookingManageController::class, 'requestDateChange'])
         ->name('api.booking.request-date-change');
    Route::post('/api/booking/{booking}/update-participants', [BookingManageController::class, 'updateParticipants'])
         ->name('api.booking.update-participants');
});

// ============================================================
// Admin routes (auth + admin role required)
// ============================================================
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

    // ─── Booking Approval ─────────────────────────────────────
    Route::get('/bookings', [BookingApprovalController::class, 'index'])->name('bookings.index');

    // Tahap 1 — ACC Awal
    Route::post('/bookings/{booking}/approve', [BookingApprovalController::class, 'approve'])->name('bookings.approve');
    Route::post('/bookings/{booking}/reject', [BookingApprovalController::class, 'reject'])->name('bookings.reject');

    // Tahap 4 — ACC Final (confirmed → final)
    Route::post('/bookings/{booking}/final', [BookingApprovalController::class, 'approveFinal'])->name('bookings.final');

    // Tahap 5 — ACC Terlambat (confirmed → final + alasan wajib)
    Route::post('/bookings/{booking}/final-late', [BookingApprovalController::class, 'approveFinalLate'])->name('bookings.final-late');

    // Tahap 3 — Persetujuan/Penolakan Usulan Perubahan Tanggal dari User
    Route::post('/bookings/{booking}/approve-date-change', [BookingApprovalController::class, 'approveDateChange'])->name('bookings.approve-date-change');
    Route::post('/bookings/{booking}/reject-date-change', [BookingApprovalController::class, 'rejectDateChange'])->name('bookings.reject-date-change');

    // ─── Booking Window management ────────────────────────────
    Route::get('/booking-windows', [\App\Http\Controllers\Admin\BookingWindowController::class, 'index'])->name('booking-windows.index');
    Route::post('/booking-window/open', [\App\Http\Controllers\Admin\BookingWindowController::class, 'open'])->name('booking-window.open');
    Route::post('/booking-window/close', [\App\Http\Controllers\Admin\BookingWindowController::class, 'close'])->name('booking-window.close');
});

// Default: redirect root ke halaman login
Route::get('/', fn() => redirect()->route('login'));
