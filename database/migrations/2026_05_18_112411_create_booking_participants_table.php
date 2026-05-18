<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->enum('tipe', ['peserta', 'panitia'])->comment('Tipe: peserta atau panitia');
            $table->string('nama', 255);
            $table->string('jabatan', 255)->nullable();
            $table->string('site', 255)->nullable();
            $table->char('gender', 1)->nullable()->comment('L atau P');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_participants');
    }
};
