<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompanyUsersSeeder extends Seeder
{
    /**
     * Daftar site perusahaan.
     */
    protected array $sites = [
        'JIEP', 'ABKL', 'ARIA', 'ASMI', 'BAYA', 'BEKB', 'BRCB', 'BRCG',
        'BTSJ', 'INDO', 'KIDE', 'KPCB', 'KPCS', 'KPCT', 'MTBU', 'SMMS',
        'TCMM', 'TOPS', 'ERKA', 'CUTB', 'PIKO', 'ADRO', 'SJRP', 'KPRT',
        'NPRL', 'BRCS', 'HMNT', 'BBSO', 'BPOP', 'CCOS', 'PPSO',
    ];

    /**
     * Daftar departemen perusahaan.
     * Key = slug yang dipakai untuk email, Value = nama tampil.
     */
    protected array $departments = [
        'hcld'           => 'HCLD',
        'srgs'           => 'SRGS',
        'cckm'           => 'CCKM',
        'cpmd'           => 'CPMD',
        'fat'            => 'FAT',
        'sm'             => 'SM',
        'eng'            => 'ENG',
        'plant'          => 'PLANT',
        'opr'            => 'OPR',
        'cis'            => 'CIS',
        'tyre'           => 'TYRE',
        'affco'          => 'Affco',
        'she'            => 'SHE',
        'lsp'            => 'LSP',
        'otd'            => 'OTD',
        'serikatpekerja' => 'Serikat Pekerja',
    ];

    /**
     * Seed the application's database with company user accounts.
     * Total: 16 departemen × 31 site = 496 akun.
     * Format email  : namadepartemen@site.com (e.g. hcld@jiep.com)
     * Format password: NAMASITE.123 (e.g. JIEP.123)
     * Role          : user
     */
    public function run(): void
    {
        $count = 0;

        foreach ($this->sites as $site) {
            $siteLower    = strtolower($site);
            $siteUpper    = strtoupper($site);
            $password     = Hash::make("{$siteUpper}.123");

            foreach ($this->departments as $deptSlug => $deptLabel) {
                $email = "{$deptSlug}@{$siteLower}.com";

                // Lewati jika email sudah ada agar tidak duplicate
                if (User::where('email', $email)->exists()) {
                    continue;
                }

                User::create([
                    'name'     => "{$deptLabel} - {$siteUpper}",
                    'email'    => $email,
                    'password' => $password,
                    'role'     => 'user',
                    'divisi'   => $deptLabel,
                    'site'     => $siteUpper,
                ]);

                $count++;
            }
        }

        $this->command->info("✅ CompanyUsersSeeder selesai: {$count} akun berhasil dibuat.");
    }
}
