# Release Notes v1.0-company

**Tanggal Rilis:** 6 Juni 2026
**Cabang (Branch):** `release/v1.0-company`

## Deskripsi Rilis
Rilis ini merupakan versi siap *deploy* (Production Ready) untuk sistem Enterprise Training Management System (Booking System). 

## Fitur & Persiapan Utama
1. **Frontend Optimization**: Seluruh aset frontend (Vue.js + TailwindCSS) telah dikompilasi menggunakan Vite untuk mode *production*.
2. **Scheduler Optimization**: Skema sinkronisasi cronjob telah diubah menjadi setiap jam (`hourly()`) pada file `routes/console.php` untuk menyeimbangkan performa server.
3. **Konfigurasi WhatsApp Fonnte**: Variabel *environment* `.env.example` sudah disesuaikan dan memiliki ruang untuk token resmi Fonnte (`FONNTE_TOKEN`).
4. **Keamanan Database**: SQLite disiapkan dengan kapabilitas transaksi atomik (`lockForUpdate`) untuk memastikan tidak terjadi tumpang tindih (*race condition*) dalam pemesanan ruangan.
5. **Sanity Check Git**: Kredensial penting (terutama file `.env` dan `database.sqlite`) dipastikan bersih dari riwayat pelacakan `git`.

## Instruksi Tambahan Saat Deployment
- Harap menyalin konfigurasi environment: `cp .env.example .env`
- Konfigurasikan file `.env` sesuai kredensial server produksi.
- Nyalakan sistem antrean (Queue) melalui Laravel Horizon atau Supervisor: `php artisan queue:work`
- Jalankan *Scheduler*: Atur Cronjob di server Nginx/Ubuntu yang mengarah ke `php artisan schedule:run`.
