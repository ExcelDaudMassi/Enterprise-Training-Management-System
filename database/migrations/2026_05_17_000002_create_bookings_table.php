<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel booking utama — mencatat header pemesanan ruangan.
     * Detail per-tanggal disimpan di tabel detail_booking (fase selanjutnya).
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            // Relasi ke users dan ruangan
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ruangan_id')->constrained('ruangan')->cascadeOnDelete();

            // Informasi training
            $table->string('nama_training', 255)->comment('Nama training/kegiatan yang dipesan');
            $table->date('tgl_mulai')->comment('Tanggal mulai kegiatan');
            $table->date('tgl_selesai')->comment('Tanggal selesai kegiatan');

            // Fase dan status sesuai alur bisnis
            $table->enum('fase', ['plotting', 'reguler'])->comment('Jenis pemesanan: plotting=jauh hari, reguler=normal');
            $table->enum('status', ['plotting', 'waiting_confirmation', 'confirmed', 'cancelled'])
                ->default('plotting')
                ->comment('Status booking: plotting → waiting_confirmation → confirmed/cancelled');

            // Informasi tambahan
            $table->string('pic', 100)->nullable()->comment('Person In Charge dari pihak user');

            // Fitur gabung ruangan
            $table->boolean('gabung_ruang')->default(false)
                ->comment('Apakah booking ini menggunakan gabungan dua ruangan');

            $table->timestamps();

            // Index untuk query yang sering digunakan
            $table->index(['ruangan_id', 'tgl_mulai', 'tgl_selesai'], 'idx_bookings_ruangan_tgl');
            $table->index(['user_id', 'status'], 'idx_bookings_user_status');
            $table->index('status', 'idx_bookings_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
