# Daftar Akun Perusahaan — Master Data User

> Dibuat pada: 27 Mei 2026  
> Total akun: **496 akun user** (16 departemen × 31 site)  
> Akun admin/testing yang sudah ada **tetap dipertahankan**.

---

## Format Akun

| Field    | Format                           | Contoh            |
|----------|----------------------------------|-------------------|
| Email    | `namadepartemen@site.com`        | `hcld@jiep.com`   |
| Password | `NAMASITE.123` (huruf kapital)   | `JIEP.123`        |
| Role     | `user`                           | —                 |
| Divisi   | Nama departemen                  | `HCLD`            |
| Nama     | `DEPARTEMEN - SITE`              | `HCLD - JIEP`     |

---

## Daftar Departemen (16)

| No | Slug Email      | Nama Tampil      |
|----|-----------------|------------------|
| 1  | `hcld`          | HCLD             |
| 2  | `srgs`          | SRGS             |
| 3  | `cckm`          | CCKM             |
| 4  | `cpmd`          | CPMD             |
| 5  | `fat`           | FAT              |
| 6  | `sm`            | SM               |
| 7  | `eng`           | ENG              |
| 8  | `plant`         | PLANT            |
| 9  | `opr`           | OPR              |
| 10 | `cis`           | CIS              |
| 11 | `tyre`          | TYRE             |
| 12 | `affco`         | Affco            |
| 13 | `she`           | SHE              |
| 14 | `lsp`           | LSP              |
| 15 | `otd`           | OTD              |
| 16 | `serikatpekerja`| Serikat Pekerja  |

---

## Daftar Site (31)

| No  | Site   | Password Login  |
|-----|--------|-----------------|
| 1   | JIEP   | `JIEP.123`      |
| 2   | ABKL   | `ABKL.123`      |
| 3   | ARIA   | `ARIA.123`      |
| 4   | ASMI   | `ASMI.123`      |
| 5   | BAYA   | `BAYA.123`      |
| 6   | BEKB   | `BEKB.123`      |
| 7   | BRCB   | `BRCB.123`      |
| 8   | BRCG   | `BRCG.123`      |
| 9   | BTSJ   | `BTSJ.123`      |
| 10  | INDO   | `INDO.123`      |
| 11  | KIDE   | `KIDE.123`      |
| 12  | KPCB   | `KPCB.123`      |
| 13  | KPCS   | `KPCS.123`      |
| 14  | KPCT   | `KPCT.123`      |
| 15  | MTBU   | `MTBU.123`      |
| 16  | SMMS   | `SMMS.123`      |
| 17  | TCMM   | `TCMM.123`      |
| 18  | TOPS   | `TOPS.123`      |
| 19  | ERKA   | `ERKA.123`      |
| 20  | CUTB   | `CUTB.123`      |
| 21  | PIKO   | `PIKO.123`      |
| 22  | ADRO   | `ADRO.123`      |
| 23  | SJRP   | `SJRP.123`      |
| 24  | KPRT   | `KPRT.123`      |
| 25  | NPRL   | `NPRL.123`      |
| 26  | BRCS   | `BRCS.123`      |
| 27  | HMNT   | `HMNT.123`      |
| 28  | BBSO   | `BBSO.123`      |
| 29  | BPOP   | `BPOP.123`      |
| 30  | CCOS   | `CCOS.123`      |
| 31  | PPSO   | `PPSO.123`      |

---

## Contoh Akun Lengkap (Per Site: BBSO)

| Email                       | Password    |
|-----------------------------|-------------|
| `hcld@bbso.com`             | `BBSO.123`  |
| `srgs@bbso.com`             | `BBSO.123`  |
| `cckm@bbso.com`             | `BBSO.123`  |
| `cpmd@bbso.com`             | `BBSO.123`  |
| `fat@bbso.com`              | `BBSO.123`  |
| `sm@bbso.com`               | `BBSO.123`  |
| `eng@bbso.com`              | `BBSO.123`  |
| `plant@bbso.com`            | `BBSO.123`  |
| `opr@bbso.com`              | `BBSO.123`  |
| `cis@bbso.com`              | `BBSO.123`  |
| `tyre@bbso.com`             | `BBSO.123`  |
| `affco@bbso.com`            | `BBSO.123`  |
| `she@bbso.com`              | `BBSO.123`  |
| `lsp@bbso.com`              | `BBSO.123`  |
| `otd@bbso.com`              | `BBSO.123`  |
| `serikatpekerja@bbso.com`   | `BBSO.123`  |

---

## Akun Tetap (Admin & Testing)

| Email               | Password    | Role  | Keterangan          |
|---------------------|-------------|-------|---------------------|
| `admin@bbso.com`    | `password`  | admin | Admin utama sistem  |
| `user@bbso.com`     | `password`  | user  | Akun testing user   |
| `siti@bbso.com`     | `password`  | user  | Akun testing user   |

---

> **Catatan**: Seeder bersifat idempotent — jika dijalankan ulang, akun yang sudah ada akan dilewati secara otomatis (tidak akan duplikat).
