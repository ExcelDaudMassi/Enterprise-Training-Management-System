<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ubah kunci pengaturan lama menjadi baru jika ada
        DB::table('settings')->where('key', 'h14_mode')->update(['key' => 'preparation_alert_mode']);

        // Tambahkan pengaturan default untuk durasi hari
        DB::table('settings')->insertOrIgnore([
            'key' => 'preparation_alert_days',
            'value' => '14',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan ke pengaturan lama
        DB::table('settings')->where('key', 'preparation_alert_mode')->update(['key' => 'h14_mode']);

        // Hapus pengaturan hari
        DB::table('settings')->where('key', 'preparation_alert_days')->delete();
    }
};
