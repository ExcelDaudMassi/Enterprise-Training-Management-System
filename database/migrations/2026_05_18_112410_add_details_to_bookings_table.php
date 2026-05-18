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
            $table->string('layout_preferensi')->nullable();
            $table->string('layout_custom_path')->nullable();
            $table->boolean('is_hybrid')->default(false);
            $table->boolean('is_flipchart')->default(false);
            $table->text('catatan_admin')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'layout_preferensi',
                'layout_custom_path',
                'is_hybrid',
                'is_flipchart',
                'catatan_admin'
            ]);
        });
    }
};
