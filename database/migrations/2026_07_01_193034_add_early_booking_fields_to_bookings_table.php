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
        Schema::table('bookings', function (Blueprint $table) {
            $table->enum('tipe_booking', ['reguler', 'early'])->default('reguler')->after('id');
            $table->integer('estimasi_peserta')->nullable()->after('catatan_user');
            $table->integer('estimasi_panitia')->nullable()->after('estimasi_peserta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['tipe_booking', 'estimasi_peserta', 'estimasi_panitia']);
        });
    }
};
