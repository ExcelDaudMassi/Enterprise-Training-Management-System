<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Tabel ruangan dengan fitur self-referencing untuk pasangan ruangan
     * yang bisa digabungkan (contoh: Ruang 2 & Ruang 3).
     */
    public function up(): void
    {
        Schema::create('ruangan', function (Blueprint $table) {
            $table->id();
            $table->string('nama_ruang', 50)->comment('Nama ruangan, contoh: Ruang 1');
            $table->string('lokasi_gedung', 100)->nullable()->comment('Nama gedung lokasi ruangan');
            $table->integer('kapasitas_max')->comment('Kapasitas maksimal peserta (tidak termasuk pengajar)');

            // Fitur penggabungan ruangan
            $table->boolean('bisa_digabung')->default(false)
                ->comment('Apakah ruangan ini bisa digabung dengan ruangan pasangannya');
            $table->foreignId('pasangan_ruang_id')->nullable()
                ->constrained('ruangan')
                ->nullOnDelete()
                ->comment('ID ruangan pasangan yang bisa digabung (self-referencing)');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Perlu nullify dulu self-referencing FK sebelum drop
        Schema::table('ruangan', function (Blueprint $table) {
            $table->dropForeign(['pasangan_ruang_id']);
        });
        Schema::dropIfExists('ruangan');
    }
};
