# Daftar Akun Perusahaan — Master Data User

> Diperbarui pada: 3 Juni 2026  
> Total akun: **18 akun** (16 departemen JIEP + 2 akun default/BBSO)  

---

## 1. Akun Default & Testing (BBSO)

| Email               | Password    | Role  | Keterangan          |
|---------------------|-------------|-------|---------------------|
| `admin@bbso.com`    | `password`  | admin | Admin utama sistem  |
| `user@bbso.com`     | `password`  | user  | Akun testing user   |

---

## 2. Akun Departemen Site JIEP (16 Akun)

*   **Format Nama:** `[DEPARTEMEN] - JIEP` (Contoh: `HCLD - JIEP`)
*   **Format Email:** `namadepartemen@jiep.com`
*   **Password Login:** `JIEP.123`
*   **Role:** `user`

### Daftar Rinci Akun JIEP:

| No | Departemen (Divisi) | Email Login                | Password   | Role |
|----|---------------------|----------------------------|------------|------|
| 1  | HCLD                | `hcld@jiep.com`            | `JIEP.123` | user |
| 2  | SRGS                | `srgs@jiep.com`            | `JIEP.123` | user |
| 3  | CCKM                | `cckm@jiep.com`            | `JIEP.123` | user |
| 4  | CPMD                | `cpmd@jiep.com`            | `JIEP.123` | user |
| 5  | FAT                 | `fat@jiep.com`             | `JIEP.123` | user |
| 6  | SM                  | `sm@jiep.com`              | `JIEP.123` | user |
| 7  | ENG                 | `eng@jiep.com`             | `JIEP.123` | user |
| 8  | PLANT               | `plant@jiep.com`           | `JIEP.123` | user |
| 9  | OPR                 | `opr@jiep.com`             | `JIEP.123` | user |
| 10 | CIS                 | `cis@jiep.com`             | `JIEP.123` | user |
| 11 | TYRE                | `tyre@jiep.com`            | `JIEP.123` | user |
| 12 | Affco               | `affco@jiep.com`           | `JIEP.123` | user |
| 13 | SHE                 | `she@jiep.com`             | `JIEP.123` | user |
| 14 | LSP                 | `lsp@jiep.com`             | `JIEP.123` | user |
| 15 | OTD                 | `otd@jiep.com`             | `JIEP.123` | user |
| 16 | Serikat Pekerja     | `serikatpekerja@jiep.com`  | `JIEP.123` | user |

---

> **Catatan**: Seeder bersifat idempotent — jika dijalankan ulang, akun yang sudah ada akan dilewati secara otomatis (tidak akan duplikat).
