# 📋 Catatan Proyek BBSO Booking System

---

## 🧠 Gap Analysis — Sistem H-14 vs Skenario Operasional Nyata

**Kesimpulan:** Fondasi alur H-14 sudah ada, namun terdapat beberapa *gap* fungsional yang perlu diperhatikan.

### Fase 1 — Pengajuan Awal (H-30)
- ✅ Upload manifest Excel + validasi NRP ganda/format → berjalan baik
- ✅ ACC 1 Admin → status DB berubah menjadi `confirmed`
- ✅ Tombol "Batalkan Booking" aktif selama status `confirmed` dan belum H-1

### Fase 2 — Deteksi Otomatis (H-14 Pagi)
- ✅ **SUDAH DIPERBAIKI:** Bug notifikasi global di `HandleInertiaRequests.php` — query urgent H-14 kini mencari `confirmed`, bukan `waiting_confirmation`
- ✅ **SUDAH DIBANGUN:** Cron job `app:scan-h14-bookings` sekarang berjalan otomatis (lihat bagian Scheduler di bawah)
- ⚠️ Perhitungan H-14 di dashboard masih bersifat *on-the-fly*, bukan disimpan oleh cron job

### Fase 3 — Eksekusi Final (H-14 Siang)
- ⚠️ Nama status di skenario (`final_acc`) vs sistem (`final`) — sudah diputuskan **memakai `final`** agar tidak merusak fitur lain
- ⚠️ Inkonsistensi: `acc2()` mengubah ke `final_confirmed`, `approveFinal()` mengubah ke `final` → perlu distandarisasi kelak

### Fase 4 — Dampak di Layar User (setelah Final)
- ❌ Booking berstatus `final` hilang dari halaman "Booking Aktif" user (hanya muncul di Riwayat)
  - **TODO:** Masukkan status `final` ke query `BookingHistoryController::active()`
- ❌ Tidak ada banner peringatan "⚠️ Housekeeping sedang mempersiapkan ruangan"
  - **TODO:** Tambahkan di `BookingDetail.vue` jika status `final`
- ❌ Label status masih "Final" bukan "Final ACC / Persiapan Lapangan"
- ✅ Tombol Batalkan otomatis tersembunyi saat `final` (sudah benar)

---

## ⚙️ Fitur Pengaturan Mode H-14 (Admin Settings)

**Lokasi UI:** Admin Panel → Sidebar → ⚙️ Pengaturan → `/admin/settings`

### 3 Mode yang Tersedia
| Mode | Deskripsi |
|---|---|
| `manual` | Admin harus klik ACC Final secara manual. Default. |
| `auto_acc` | Otomatis ubah status ke `final` saat H-14 |
| `auto_cancel` | Otomatis batalkan booking saat H-14 (mencegah booking fiktif) |

### Cara Kerja Teknis
- Setting disimpan di tabel `settings` (key: `h14_mode`, value: `manual/auto_acc/auto_cancel`)
- Command: `php artisan app:scan-h14-bookings`
- Controller: `app/Http/Controllers/Admin/SettingController.php`
- Model: `app/Models/Setting.php`

---

## 🕐 Scheduler (Penjadwal Otomatis)

### Daftar Command Terjadwal (`routes/console.php`)
| Command | Jadwal | Fungsi |
|---|---|---|
| `app:auto-reject-bookings` | Setiap hari | Batalkan otomatis booking `waiting_confirmation` yang melewati H-3 |
| `app:scan-h14-bookings` | Setiap 1 menit *(lihat catatan!)* | Proses booking H-14 sesuai mode pengaturan |

### ⚠️ CATATAN PENTING — Frekuensi Scheduler
> **Saat ini:** `app:scan-h14-bookings` diatur `->everyMinute()` untuk keperluan **testing/development**.
>
> **Saat deploy ke production**, ubah ke frekuensi yang lebih ringan agar tidak membebani database:
> ```php
> // routes/console.php
> Schedule::command('app:scan-h14-bookings')->everyThirtyMinutes();
> // atau
> Schedule::command('app:scan-h14-bookings')->hourly();
> ```

### Cara Menjalankan Scheduler di Lokal (Development)
```bash
# Jalankan di terminal terpisah, biarkan tetap berjalan
php artisan schedule:work
```
> Scheduler **tidak berjalan otomatis** di lokal. Perintah di atas harus dijalankan setiap kali membuka project.

### Cara Menjalankan di Production (Linux Server)
Tambahkan baris ini ke **crontab** server:
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🐛 Bug yang Sudah Diperbaiki

| Bug | File | Status |
|---|---|---|
| Notifikasi badge H-14 membaca `waiting_confirmation` (harusnya `confirmed`) | `HandleInertiaRequests.php` | ✅ Fixed |
| API settings salah URL (`/api/` vs `/admin/api/`) di `Settings.vue` | `Settings.vue` | ✅ Fixed |

---

## 📌 TODO — Yang Perlu Dikerjakan Berikutnya

- [ ] **Booking Aktif User:** Masukkan status `final` ke query booking aktif di `BookingHistoryController::active()` agar acara tidak hilang dari dashboard user setelah di-ACC Final
- [ ] **Banner Peringatan Final:** Tambahkan pesan `⚠️ Ruangan sedang dipersiapkan Tim Lapangan` di `BookingDetail.vue` saat status `final`
- [ ] **Standarisasi Status:** Satukan penggunaan `final` vs `final_confirmed` di seluruh codebase
- [ ] **Integrasi WhatsApp:** Kirim notifikasi otomatis ke Grup WhatsApp Tim Housekeeping & Security saat booking di-ACC Final (saat ini masih `// TODO` di controller)
- [ ] **Ganti frekuensi scheduler** dari `everyMinute()` → `hourly()` sebelum deploy ke production

---

## 🌿 Branching & Git Strategy

- Branch aktif: `feature/admin-settings` → merge ke `develop` → merge ke `main`
- Selalu stay di `main` untuk production
