<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom-kolom untuk mendukung alur kerja booking 5-tahap:
     *
     *  - Tahap 3 (Perubahan Tanggal)  : proposed_tgl_mulai, proposed_tgl_selesai, status_perubahan
     *  - Tahap 4 (ACC Final)          : acc2_at, acc2_by
     *  - Tahap 5 (ACC Terlambat)      : catatan_acc_terlambat
     *  - Umum                         : catatan_user (membedakan dari catatan_admin)
     *  - Status 'final'               : Karena menggunakan SQLite, nilai 'final' pada kolom status
     *                                   dikelola di sisi aplikasi (model/controller).
     *                                   SQLite tidak mengenal tipe ENUM, menyimpan sebagai TEXT.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // --- Tahap 3: Usulan Perubahan Tanggal ---
            $table->date('proposed_tgl_mulai')->nullable()
                  ->comment('Tanggal mulai baru yang diusulkan user');
            $table->date('proposed_tgl_selesai')->nullable()
                  ->comment('Tanggal selesai baru yang diusulkan user');
            $table->string('status_perubahan', 20)->default('none')
                  ->comment('Status usulan perubahan tanggal: none | pending | approved | rejected');

            // --- Tahap 4: Info ACC Final ---
            $table->timestamp('acc2_at')->nullable()
                  ->comment('Waktu ACC Tahap 2 dilakukan oleh admin');
            $table->foreignId('acc2_by')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('User (admin) yang melakukan ACC Tahap 2');

            // --- Tahap 5: Catatan ACC Terlambat ---
            $table->text('catatan_acc_terlambat')->nullable()
                  ->comment('Alasan ACC terlambat yang wajib diisi admin jika melewati H-14');

            // --- Umum: Catatan dari User ---
            $table->text('catatan_user')->nullable()
                  ->comment('Catatan tambahan dari user saat booking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['acc2_by']);
            $table->dropColumn([
                'proposed_tgl_mulai',
                'proposed_tgl_selesai',
                'status_perubahan',
                'acc2_at',
                'acc2_by',
                'catatan_acc_terlambat',
                'catatan_user',
            ]);
        });
    }
};
