<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'site' column to users table.
     * Stores the location/plant site code (e.g. BBSO, JIEP, ABKL).
     * Used for auto-fill on booking form and display purposes.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('site', 20)
                  ->nullable()
                  ->after('divisi')
                  ->comment('Kode site/lokasi perusahaan (e.g. BBSO, JIEP). Nullable untuk akun admin.');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('site');
        });
    }
};
