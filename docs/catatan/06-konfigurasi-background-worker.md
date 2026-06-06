# Panduan Sistem: WebSockets, Cron Job, dan Background Queue

Berikut adalah catatan tahap-tahap dan konfigurasi yang dibutuhkan agar seluruh fitur (Real-Time WebSockets, Auto ACC H-14, dan Pengiriman WhatsApp yang cepat) dapat berjalan lancar, terutama jika suatu saat sistem ini dipindahkan ke server production (VPS / Hosting) atau saat laptop di-restart.

> **PENTING:**
> Sistem ini sekarang memiliki 3 "mesin" (worker) yang harus berjalan di latar belakang agar fitur-fitur canggihnya berfungsi.

---

## 1. Mesin Real-Time WebSockets (Laravel Reverb)
Fitur ini membuat layar Admin dan User otomatis berkedip/update tanpa perlu di-refresh saat ada perubahan status booking.

**Konfigurasi di `.env`:**
Pastikan variabel ini terisi dengan benar (terutama jika IP lokal Anda berubah):
```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=777777
REVERB_APP_KEY=testing_reverb_key
REVERB_APP_SECRET=testing_reverb_secret
REVERB_SERVER_HOST="0.0.0.0"
REVERB_HOST="127.0.0.1"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="192.168.100.7" # Sesuaikan dengan IP lokal jika diakses via HP/Laptop lain
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**Cara Menjalankan (Local / Server):**
Buka terminal baru di folder project, lalu jalankan:
```bash
php artisan reverb:start
```
*(Mesin ini harus terus menyala agar fitur real-time tidak mati).*

---

## 2. Mesin Penjadwalan / Cron Job (H-14 Auto ACC)
Fitur ini bertugas mengecek setiap 1 menit apakah ada *booking* yang sudah masuk H-14 untuk di-ACC atau di-Cancel secara otomatis oleh sistem.

**Cara Menjalankan di Lokal (Laptop / Windows):**
Buka terminal baru, lalu jalankan:
```bash
php artisan schedule:work
```
*(Ini akan membuat terminal mengeksekusi pengecekan setiap pergantian menit).*

**Cara Menjalankan di Server Production (Linux / VPS):**
Anda tidak perlu menjalankan perintah di atas secara manual. Cukup tambahkan baris berikut ke pengaturan *crontab* di server (`crontab -e`):
```bash
* * * * * cd /path/ke/folder/booking && php artisan schedule:run >> /dev/null 2>&1
```

---

## 3. Mesin Antrean / Background Queue (WhatsApp & TinyURL)
Fitur ini memastikan tombol ACC Final bisa ditekan dan langsung sukses dalam **1 detik**, tanpa membuat Admin menunggu *loading* pengiriman pesan WhatsApp (pesan WA akan dikirim diam-diam di belakang layar).

**Konfigurasi di `.env`:**
```env
QUEUE_CONNECTION=database
```

**Cara Menjalankan (Local / Server):**
Buka terminal baru, lalu jalankan:
```bash
php artisan queue:work
```
*(Jika mesin ini mati, tombol ACC Final tetap akan cepat, tetapi pesan WhatsApp akan tertunda/nyangkut di antrean database sampai mesin ini dinyalakan kembali).*

---

> **Ringkasan untuk Developer / Server Admin:**
> Saat *deploy* ke production, gunakan aplikasi seperti **Supervisor** (di Linux) atau **PM2** untuk memastikan ketiga perintah ini berjalan otomatis di latar belakang dan auto-restart jika server dihidupkan ulang:
> 1. `php artisan reverb:start`
> 2. `php artisan queue:work`
> 3. `php artisan schedule:run` (via Cron OS)
