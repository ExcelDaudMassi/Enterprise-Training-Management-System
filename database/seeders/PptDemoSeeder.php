<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Booking;
use App\Models\BookingParticipant;
use App\Models\BookingLog;
use App\Models\User;
use App\Models\Ruangan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PptDemoSeeder
 *
 * Membuat data booking yang realistis dan rapi untuk keperluan demo PPT.
 * Setiap booking memiliki:
 *  - Rentang tanggal 3–5 hari
 *  - Peserta & panitia yang lengkap
 *  - Status yang beragam (pending, confirmed, finalized, rejected)
 *  - Data yang menarik secara visual
 */
class PptDemoSeeder extends Seeder
{
    public function run(): void
    {
        // ── Hapus data demo lama (Juli 2026) jika ada ─────────────
        $oldIds = Booking::whereBetween('tgl_mulai', ['2026-07-01', '2026-07-31'])
            ->pluck('id');
        if ($oldIds->isNotEmpty()) {
            DB::table('booking_logs')->whereIn('booking_id', $oldIds)->delete();
            DB::table('booking_participants')->whereIn('booking_id', $oldIds)->delete();
            Booking::whereIn('id', $oldIds)->delete();
            $this->command->warn("🗑️  Menghapus {$oldIds->count()} booking lama (Juli 2026)...");
        }

        // ── Ambil data ruangan & user ──────────────────────────────
        $ruangan = Ruangan::all()->keyBy('nama_ruang');
        $adminUser = User::where('role', 'admin')->first();
        $users = User::where('role', 'user')->take(5)->get();

        if ($ruangan->isEmpty() || $users->isEmpty()) {
            $this->command->warn('⚠️  Tidak ada ruangan atau user. Jalankan RuanganSeeder & CompanyUsersSeeder dulu.');
            return;
        }

        // ── Definisi 5 booking demo yang keren untuk PPT ──────────
        $bookings = [

            // 1. JANUARI 2027 — Booking CONFIRMED — Leadership & Management (4 hari)
            [
                'user'          => $users->get(0),
                'ruangan'       => $ruangan->get('Ruang 1') ?? $ruangan->first(),
                'nama_training' => 'Leadership & Management Development Program',
                'tgl_mulai'     => Carbon::create(2027, 1, 13),
                'tgl_selesai'   => Carbon::create(2027, 1, 16), // 4 hari
                'fase'          => 'reguler',
                'status'        => 'confirmed',
                'pic'           => 'Budi Santoso',
                'no_hp_pic'     => '081234567890',
                'layout'        => 'u-shape',
                'is_hybrid'     => true,
                'is_flipchart'  => true,
                'is_pena'       => true,
                'catatan_user'  => 'Mohon disiapkan proyektor cadangan dan mic wireless untuk sesi panel.',
                'acc1_done'     => true,
                'acc2_done'     => false,
                'admin'         => $adminUser,
                'peserta'       => [
                    ['nama' => 'Ahmad Fauzi',       'jabatan' => 'Manager HRD',        'site' => 'JIEP', 'nrp' => 'EMP-0021', 'no_hp' => '081111111101', 'gender' => 'L'],
                    ['nama' => 'Siti Rahayu',        'jabatan' => 'Senior Supervisor',  'site' => 'JIEP', 'nrp' => 'EMP-0022', 'no_hp' => '081111111102', 'gender' => 'P'],
                    ['nama' => 'Rudi Hermawan',      'jabatan' => 'Team Leader',        'site' => 'JIEP', 'nrp' => 'EMP-0023', 'no_hp' => '081111111103', 'gender' => 'L'],
                    ['nama' => 'Dewi Anggraini',     'jabatan' => 'Senior Staff',       'site' => 'JIEP', 'nrp' => 'EMP-0024', 'no_hp' => '081111111104', 'gender' => 'P'],
                    ['nama' => 'Hendra Gunawan',     'jabatan' => 'Kepala Seksi',       'site' => 'JIEP', 'nrp' => 'EMP-0025', 'no_hp' => '081111111105', 'gender' => 'L'],
                    ['nama' => 'Maya Kusuma',        'jabatan' => 'Koordinator Unit',   'site' => 'JIEP', 'nrp' => 'EMP-0026', 'no_hp' => '081111111106', 'gender' => 'P'],
                    ['nama' => 'Tono Subroto',       'jabatan' => 'Kepala Bagian',      'site' => 'JIEP', 'nrp' => 'EMP-0027', 'no_hp' => '081111111107', 'gender' => 'L'],
                    ['nama' => 'Anita Wijayanti',    'jabatan' => 'Manager Produksi',   'site' => 'JIEP', 'nrp' => 'EMP-0028', 'no_hp' => '081111111108', 'gender' => 'P'],
                ],
                'panitia' => [
                    ['nama' => 'Farhan Maulana',    'jabatan' => 'Training Coordinator', 'site' => 'JIEP', 'nrp' => 'EMP-0099', 'no_hp' => '081199990001', 'gender' => 'L'],
                    ['nama' => 'Lestari Putri',     'jabatan' => 'Admin Training',       'site' => 'JIEP', 'nrp' => 'EMP-0100', 'no_hp' => '081199990002', 'gender' => 'P'],
                ],
            ],

            // 2. FEBRUARI 2027 — Booking FINALIZED — K3 Safety Training (3 hari)
            [
                'user'          => $users->get(1),
                'ruangan'       => $ruangan->get('Ruang 4') ?? $ruangan->skip(1)->first(),
                'nama_training' => 'Pelatihan Keselamatan & Kesehatan Kerja (K3) 2027',
                'tgl_mulai'     => Carbon::create(2027, 2, 3),
                'tgl_selesai'   => Carbon::create(2027, 2, 5), // 3 hari
                'fase'          => 'reguler',
                'status'        => 'finalized',
                'pic'           => 'Wahyu Prasetyo',
                'no_hp_pic'     => '082233445566',
                'layout'        => 'classroom',
                'is_hybrid'     => false,
                'is_flipchart'  => true,
                'is_pena'       => true,
                'catatan_user'  => 'Peserta wajib membawa APD masing-masing. Tersedia sesi simulasi praktik.',
                'acc1_done'     => true,
                'acc2_done'     => true,
                'admin'         => $adminUser,
                'peserta'       => [
                    ['nama' => 'Bambang Sugiarto',  'jabatan' => 'Operator Mesin',     'site' => 'JIEP', 'nrp' => 'EMP-0031', 'no_hp' => '081222222201', 'gender' => 'L'],
                    ['nama' => 'Rina Fitriani',     'jabatan' => 'Teknisi Senior',     'site' => 'JIEP', 'nrp' => 'EMP-0032', 'no_hp' => '081222222202', 'gender' => 'P'],
                    ['nama' => 'Dedi Kurniawan',    'jabatan' => 'Foreman',            'site' => 'JIEP', 'nrp' => 'EMP-0033', 'no_hp' => '081222222203', 'gender' => 'L'],
                    ['nama' => 'Eni Sulistyowati',  'jabatan' => 'Operator',           'site' => 'JIEP', 'nrp' => 'EMP-0034', 'no_hp' => '081222222204', 'gender' => 'P'],
                    ['nama' => 'Agus Salim',        'jabatan' => 'Teknisi Listrik',    'site' => 'JIEP', 'nrp' => 'EMP-0035', 'no_hp' => '081222222205', 'gender' => 'L'],
                    ['nama' => 'Fitri Handayani',   'jabatan' => 'Quality Control',    'site' => 'JIEP', 'nrp' => 'EMP-0036', 'no_hp' => '081222222206', 'gender' => 'P'],
                ],
                'panitia' => [
                    ['nama' => 'Surya Adiatma',    'jabatan' => 'Safety Officer',      'site' => 'JIEP', 'nrp' => 'EMP-0101', 'no_hp' => '081199990003', 'gender' => 'L'],
                    ['nama' => 'Dina Oktaviani',   'jabatan' => 'K3 Coordinator',      'site' => 'JIEP', 'nrp' => 'EMP-0102', 'no_hp' => '081199990004', 'gender' => 'P'],
                ],
            ],

            // 3. MARET 2027 — Booking PENDING — Digital Transformation Workshop (5 hari)
            [
                'user'          => $users->get(2),
                'ruangan'       => $ruangan->get('Ruang 5') ?? $ruangan->skip(2)->first(),
                'nama_training' => 'Digital Transformation & Industry 4.0 Workshop',
                'tgl_mulai'     => Carbon::create(2027, 3, 10),
                'tgl_selesai'   => Carbon::create(2027, 3, 14), // 5 hari
                'fase'          => 'reguler',
                'status'        => 'pending',
                'pic'           => 'Indra Wijaya',
                'no_hp_pic'     => '087788991122',
                'layout'        => 'theater',
                'is_hybrid'     => true,
                'is_flipchart'  => false,
                'is_pena'       => false,
                'catatan_user'  => 'Workshop 5 hari dengan narasumber dari luar perusahaan. Butuh koneksi internet stabil dan layar besar.',
                'acc1_done'     => false,
                'acc2_done'     => false,
                'admin'         => $adminUser,
                'peserta'       => [
                    ['nama' => 'Tri Wulandari',     'jabatan' => 'IT Developer',       'site' => 'JIEP', 'nrp' => 'EMP-0041', 'no_hp' => '081333333301', 'gender' => 'P'],
                    ['nama' => 'Eko Prasetyo',      'jabatan' => 'System Analyst',     'site' => 'JIEP', 'nrp' => 'EMP-0042', 'no_hp' => '081333333302', 'gender' => 'L'],
                    ['nama' => 'Nita Anggraini',    'jabatan' => 'Data Analyst',       'site' => 'JIEP', 'nrp' => 'EMP-0043', 'no_hp' => '081333333303', 'gender' => 'P'],
                    ['nama' => 'Fajar Nugroho',     'jabatan' => 'IT Support',         'site' => 'JIEP', 'nrp' => 'EMP-0044', 'no_hp' => '081333333304', 'gender' => 'L'],
                    ['nama' => 'Shinta Dewi',       'jabatan' => 'Network Engineer',   'site' => 'JIEP', 'nrp' => 'EMP-0045', 'no_hp' => '081333333305', 'gender' => 'P'],
                    ['nama' => 'Rizal Firmansyah',  'jabatan' => 'ERP Specialist',     'site' => 'JIEP', 'nrp' => 'EMP-0046', 'no_hp' => '081333333306', 'gender' => 'L'],
                    ['nama' => 'Hani Nurhayati',    'jabatan' => 'Business Analyst',   'site' => 'JIEP', 'nrp' => 'EMP-0047', 'no_hp' => '081333333307', 'gender' => 'P'],
                    ['nama' => 'Dani Setiawan',     'jabatan' => 'Cloud Architect',    'site' => 'JIEP', 'nrp' => 'EMP-0048', 'no_hp' => '081333333308', 'gender' => 'L'],
                    ['nama' => 'Laras Susanti',     'jabatan' => 'UI/UX Designer',     'site' => 'JIEP', 'nrp' => 'EMP-0049', 'no_hp' => '081333333309', 'gender' => 'P'],
                    ['nama' => 'Bagus Pramono',     'jabatan' => 'DevOps Engineer',    'site' => 'JIEP', 'nrp' => 'EMP-0050', 'no_hp' => '081333333310', 'gender' => 'L'],
                ],
                'panitia' => [
                    ['nama' => 'Yusuf Rahman',     'jabatan' => 'IT Training Lead',    'site' => 'JIEP', 'nrp' => 'EMP-0103', 'no_hp' => '081199990005', 'gender' => 'L'],
                    ['nama' => 'Mia Santika',      'jabatan' => 'Training Support',    'site' => 'JIEP', 'nrp' => 'EMP-0104', 'no_hp' => '081199990006', 'gender' => 'P'],
                ],
            ],

            // 4. APRIL 2027 — Booking CONFIRMED + GABUNG RUANG — Finance & Accounting (4 hari)
            [
                'user'          => $users->get(3),
                'ruangan'       => $ruangan->get('Ruang 2') ?? $ruangan->skip(3)->first(),
                'nama_training' => 'Finance & Accounting Management Training 2027',
                'tgl_mulai'     => Carbon::create(2027, 4, 7),
                'tgl_selesai'   => Carbon::create(2027, 4, 10), // 4 hari
                'fase'          => 'reguler',
                'status'        => 'confirmed',
                'gabung_ruang'  => true,
                'pic'           => 'Nurul Hidayah',
                'no_hp_pic'     => '089966554433',
                'layout'        => 'u-shape',
                'is_hybrid'     => false,
                'is_flipchart'  => true,
                'is_pena'       => true,
                'catatan_user'  => 'Peserta 50 orang, mohon ruangan digabung (Ruang 2+3). Sediakan whiteboard besar.',
                'acc1_done'     => true,
                'acc2_done'     => false,
                'admin'         => $adminUser,
                'peserta'       => [
                    ['nama' => 'Endah Suryani',     'jabatan' => 'Accounting Staff',   'site' => 'JIEP', 'nrp' => 'EMP-0051', 'no_hp' => '081444444401', 'gender' => 'P'],
                    ['nama' => 'Yanto Susilo',      'jabatan' => 'Finance Analyst',    'site' => 'JIEP', 'nrp' => 'EMP-0052', 'no_hp' => '081444444402', 'gender' => 'L'],
                    ['nama' => 'Lina Marlina',      'jabatan' => 'Tax Specialist',     'site' => 'JIEP', 'nrp' => 'EMP-0053', 'no_hp' => '081444444403', 'gender' => 'P'],
                    ['nama' => 'Wahid Maulana',     'jabatan' => 'Budget Controller',  'site' => 'JIEP', 'nrp' => 'EMP-0054', 'no_hp' => '081444444404', 'gender' => 'L'],
                    ['nama' => 'Citra Puspitasari', 'jabatan' => 'Cost Accountant',    'site' => 'JIEP', 'nrp' => 'EMP-0055', 'no_hp' => '081444444405', 'gender' => 'P'],
                    ['nama' => 'Joko Widodo',       'jabatan' => 'Finance Manager',    'site' => 'JIEP', 'nrp' => 'EMP-0056', 'no_hp' => '081444444406', 'gender' => 'L'],
                ],
                'panitia' => [
                    ['nama' => 'Bayu Setiawan',    'jabatan' => 'Finance Training PIC', 'site' => 'JIEP', 'nrp' => 'EMP-0105', 'no_hp' => '081199990007', 'gender' => 'L'],
                    ['nama' => 'Putri Andriani',   'jabatan' => 'Admin Finance',        'site' => 'JIEP', 'nrp' => 'EMP-0106', 'no_hp' => '081199990008', 'gender' => 'P'],
                ],
            ],

            // 5. JANUARI 2027 — Booking REJECTED — HR Recruitment (3 hari, bentrok jadwal)
            [
                'user'          => $users->get(4),
                'ruangan'       => $ruangan->get('Ruang 1') ?? $ruangan->first(),
                'nama_training' => 'Rekrutmen & Seleksi Karyawan — Teknik Wawancara',
                'tgl_mulai'     => Carbon::create(2027, 1, 13),
                'tgl_selesai'   => Carbon::create(2027, 1, 15), // 3 hari (sengaja bentrok Ruang 1)
                'fase'          => 'reguler',
                'status'        => 'rejected',
                'pic'           => 'Angga Putra',
                'no_hp_pic'     => '081298765432',
                'layout'        => 'classroom',
                'is_hybrid'     => false,
                'is_flipchart'  => false,
                'is_pena'       => false,
                'catatan_user'  => 'Pelatihan untuk tim rekrutmen terkait teknik wawancara berbasis kompetensi.',
                'acc1_done'     => false,
                'acc2_done'     => false,
                'catatan_admin' => 'Jadwal bertabrakan dengan Leadership Training di Ruang 1. Mohon pilih tanggal atau ruangan lain.',
                'admin'         => $adminUser,
                'peserta'       => [
                    ['nama' => 'Ratna Dewi',        'jabatan' => 'Recruiter',          'site' => 'JIEP', 'nrp' => 'EMP-0061', 'no_hp' => '081555555501', 'gender' => 'P'],
                    ['nama' => 'Haris Santoso',     'jabatan' => 'HR Generalist',      'site' => 'JIEP', 'nrp' => 'EMP-0062', 'no_hp' => '081555555502', 'gender' => 'L'],
                    ['nama' => 'Wulan Sari',        'jabatan' => 'Talent Acquisition', 'site' => 'JIEP', 'nrp' => 'EMP-0063', 'no_hp' => '081555555503', 'gender' => 'P'],
                    ['nama' => 'Gilang Ramadhan',   'jabatan' => 'HR Staff',           'site' => 'JIEP', 'nrp' => 'EMP-0064', 'no_hp' => '081555555504', 'gender' => 'L'],
                ],
                'panitia' => [
                    ['nama' => 'Septi Lestari',    'jabatan' => 'HR Training Admin',   'site' => 'JIEP', 'nrp' => 'EMP-0107', 'no_hp' => '081199990009', 'gender' => 'P'],
                ],
            ],
        ];

        // ── Insert ke database ─────────────────────────────────────
        $count = 0;
        foreach ($bookings as $data) {
            $booking = Booking::create([
                'user_id'           => $data['user']->id,
                'ruangan_id'        => $data['ruangan']->id,
                'nama_training'     => $data['nama_training'],
                'tgl_mulai'         => $data['tgl_mulai']->format('Y-m-d'),
                'tgl_selesai'       => $data['tgl_selesai']->format('Y-m-d'),
                'fase'              => $data['fase'],
                'status'            => $data['status'],
                'pic'               => $data['pic'],
                'no_hp_pic'         => $data['no_hp_pic'],
                'gabung_ruang'      => $data['gabung_ruang'] ?? false,
                'layout_preferensi' => $data['layout'],
                'is_hybrid'         => $data['is_hybrid'],
                'is_flipchart'      => $data['is_flipchart'],
                'is_pena_mini_note' => $data['is_pena'],
                'catatan_user'      => $data['catatan_user'],
                'catatan_admin'     => $data['catatan_admin'] ?? null,
                // ACC Tahap 1
                'acc1_at'           => $data['acc1_done'] ? Carbon::now()->subDays(3) : null,
                'acc1_by'           => ($data['acc1_done'] && $data['admin']) ? $data['admin']->id : null,
                // ACC Tahap 2
                'acc2_at'           => $data['acc2_done'] ? Carbon::now()->subDays(1) : null,
                'acc2_by'           => ($data['acc2_done'] && $data['admin']) ? $data['admin']->id : null,
            ]);

            // Insert peserta
            foreach ($data['peserta'] as $p) {
                $booking->participants()->create([
                    'tipe'    => 'peserta',
                    'nama'    => $p['nama'],
                    'nrp'     => $p['nrp'],
                    'jabatan' => $p['jabatan'],
                    'site'    => $p['site'],
                    'no_hp'   => $p['no_hp'],
                    'gender'  => $p['gender'],
                ]);
            }

            // Insert panitia
            foreach ($data['panitia'] as $p) {
                $booking->participants()->create([
                    'tipe'    => 'panitia',
                    'nama'    => $p['nama'],
                    'nrp'     => $p['nrp'],
                    'jabatan' => $p['jabatan'],
                    'site'    => $p['site'],
                    'no_hp'   => $p['no_hp'],
                    'gender'  => $p['gender'],
                ]);
            }

            // Insert log aktivitas agar riwayat terisi
            if ($data['acc1_done'] && $data['admin']) {
                DB::table('booking_logs')->insert([
                    'booking_id' => $booking->id,
                    'user_id'    => $data['admin']->id,
                    'action'     => 'confirmed',
                    'message'    => 'Booking disetujui (ACC Tahap 1) oleh admin.',
                    'created_at' => Carbon::now()->subDays(3),
                    'updated_at' => Carbon::now()->subDays(3),
                ]);
            }
            if ($data['acc2_done'] && $data['admin']) {
                DB::table('booking_logs')->insert([
                    'booking_id' => $booking->id,
                    'user_id'    => $data['admin']->id,
                    'action'     => 'finalized',
                    'message'    => 'Booking difinalisasi (ACC Tahap 2) oleh admin.',
                    'created_at' => Carbon::now()->subDays(1),
                    'updated_at' => Carbon::now()->subDays(1),
                ]);
            }
            if ($data['status'] === 'rejected') {
                DB::table('booking_logs')->insert([
                    'booking_id' => $booking->id,
                    'user_id'    => $data['admin'] ? $data['admin']->id : $data['user']->id,
                    'action'     => 'rejected',
                    'message'    => $data['catatan_admin'] ?? 'Booking ditolak karena jadwal bentrok.',
                    'created_at' => Carbon::now()->subDays(2),
                    'updated_at' => Carbon::now()->subDays(2),
                ]);
            }

            $count++;
            $this->command->info("✅ [{$count}] Booking dibuat: \"{$data['nama_training']}\" [{$data['status']}]");
        }

        $this->command->newLine();
        $this->command->info("🎉 PptDemoSeeder selesai: {$count} booking demo berhasil dibuat!");
        $this->command->line("   → Login sebagai user/admin untuk melihat hasilnya di web.");
    }
}
