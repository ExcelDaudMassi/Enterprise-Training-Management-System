<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jalankan auto-reject setiap hari
Schedule::command('app:auto-reject-bookings')->daily();

// Jalankan auto-process Preparation Alert setiap menit untuk testing
Schedule::command('app:scan-preparation-bookings')->hourly();


