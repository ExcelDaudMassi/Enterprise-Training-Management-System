<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom tracking ACC Tahap 1 ke tabel bookings.
     *
     * ACC-1 (Konfirmasi Awal): Admin mengkonfirmasi booking → status: confirmed
     *   - acc1_at : waktu ACC-1 dilakukan
     *   - acc1_by : admin yang melakukan ACC-1
     *
     * ACC-2 (Final H-14) sudah ada di migration sebelumnya (acc2_at, acc2_by).
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('acc1_at')->nullable()
                  ->after('catatan_admin')
                  ->comment('Waktu ACC Tahap 1 (konfirmasi awal) dilakukan oleh admin');

            $table->foreignId('acc1_by')->nullable()
                  ->after('acc1_at')
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('User (admin) yang melakukan ACC Tahap 1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['acc1_by']);
            $table->dropColumn(['acc1_at', 'acc1_by']);
        });
    }
};
