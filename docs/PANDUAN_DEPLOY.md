# 🚀 Panduan Deployment: Enterprise Training Management System

Dokumen ini menjelaskan langkah-langkah lengkap untuk melakukan deployment aplikasi **Enterprise Training Management System** ke server produksi (VPS/Dedicated Server dengan Linux Ubuntu/Debian, Nginx, dan PHP 8.3).

---

## 📋 1. Persyaratan Server (Prerequisites)

Sebelum melakukan deployment, pastikan server Anda telah terinstall perangkat lunak berikut:

* **PHP 8.3** atau lebih tinggi dengan ekstensi wajib:
  * `php8.3-cli`, `php8.3-fpm`, `php8.3-sqlite3` (atau driver DB pilihan Anda), `php8.3-mbstring`, `php8.3-xml`, `php8.3-curl`, `php8.3-zip`, `php8.3-gd`
* **Composer v2.x** (Dependency Manager PHP)
* **Node.js (v18.x+) & NPM** (untuk build aset frontend)
* **Web Server:** Nginx (Sangat direkomendasikan) atau Apache
* **Supervisor:** Pengelola daemon proses Linux (untuk menjalankan queue worker secara persisten)

---

## 🛠️ 2. Langkah-Langkah Deployment (Step-by-Step)

### Langkah 2.1: Persiapan Direktori & Clone Kode
Tempatkan kode sumber aplikasi ke direktori web server Anda (misalnya `/var/www/booking`):
```bash
cd /var/www
# Unggah atau clone repository ke folder ini
git clone <repository_url> booking
cd booking
```

### Langkah 2.2: Instalasi Dependensi PHP (Backend)
Jalankan Composer untuk menginstal semua library PHP yang diperlukan tanpa menyertakan library development (agar performa lebih cepat dan aman):
```bash
composer install --no-dev --optimize-autoloader
```

### Langkah 2.3: Konfigurasi File Environment (`.env`)
Salin file `.env.example` menjadi `.env` produksi:
```bash
cp .env.example .env
```
Buka file `.env` menggunakan editor teks (seperti `nano .env`) dan sesuaikan parameter berikut:
```env
APP_NAME="Enterprise Training Management System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://booking.nama-domain-anda.com

# Database (Menggunakan SQLite sebagai default bawaan)
DB_CONNECTION=sqlite
# Database SQLite otomatis mengarah ke database/database.sqlite

# Queue Connection (Wajib diatur ke 'database' untuk WhatsApp asinkronus)
QUEUE_CONNECTION=database

# API Integrasi WhatsApp Fonnte
FONNTE_TOKEN=token_fonnte_resmi_dari_dashboard_fonnte
FONNTE_FRONTDESK_TARGET=nomor_wa_frontdesk_tujuan (contoh: 081234567890)
```

### Langkah 2.4: Generate Application Key
Buat key enkripsi Laravel untuk keamanan session dan signed URL:
```bash
php artisan key:generate
```

### Langkah 2.5: Setup Database & Migrasi
1. Jika menggunakan SQLite, pastikan file database fisik sudah dibuat:
   ```bash
   touch database/database.sqlite
   ```
2. Jalankan migrasi database beserta data awal (*seeder*) secara paksa:
   ```bash
   php artisan migrate --force --seed
   ```

### Langkah 2.6: Kompilasi Aset Frontend (Vue 3 + Tailwind CSS v4)
Instal dependensi JavaScript, lalu jalankan build script produksi agar semua chunk frontend Vue dan Tailwind terkompilasi ke dalam folder `public/build`:
```bash
npm install
npm run build
```

### Langkah 2.7: Konfigurasi Hak Akses Folder (Permissions)
Web server (misalnya user `www-data` pada Nginx) harus memiliki hak akses untuk menulis pada folder penyimpanan sementara, cache, serta folder database (wajib untuk SQLite agar dapat membuat file lock sementara):
```bash
# Ganti ownership ke group web server
sudo chown -R www-data:www-data /var/www/booking

# Berikan izin tulis untuk storage & cache
sudo chmod -R 775 /var/www/booking/storage
sudo chmod -R 775 /var/www/booking/bootstrap/cache

# Berikan izin tulis untuk folder database dan berkas SQLite di dalamnya
sudo chmod 775 /var/www/booking/database
sudo chmod 664 /var/www/booking/database/database.sqlite
```

### Langkah 2.8: Konfigurasi Web Server (Nginx)
Buat file konfigurasi server block Nginx baru:
```bash
sudo nano /etc/nginx/sites-available/booking
```
Isi dengan konfigurasi berikut (sesuaikan domain dan path socket PHP-FPM):
```nginx
server {
    listen 80;
    server_name booking.nama-domain-anda.com;
    root /var/www/booking/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
Aktifkan konfigurasi dan muat ulang Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/booking /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 🕐 3. Konfigurasi Scheduler (Cron Job)

Sistem mengandalkan scheduler otomatis untuk mendeteksi mode H-14 dan membatalkan booking kadaluarsa. Tambahkan baris cron job ke sistem server.

1. Buka konfigurasi crontab server:
   ```bash
   crontab -e
   ```
2. Tambahkan baris berikut di bagian paling bawah (sesuaikan dengan path direktori aplikasi Anda):
   ```bash
   * * * * * cd /var/www/booking && php artisan schedule:run >> /dev/null 2>&1
   ```
3. Simpan dan keluar. Baris ini akan memerintahkan server memicu scheduler Laravel setiap 1 menit. Laravel sendiri yang akan mengatur bahwa pengecekan H-14 hanya dieksekusi **setiap 1 jam** (`hourly()`) sesuai kode di `console.php`.

---

## ⚙️ 4. Konfigurasi Queue Worker (Supervisor)

Karena pengiriman WhatsApp melalui Fonnte diproses asinkronus via antrean database agar browser admin tidak memuat lambat saat menekan "ACC Final", Anda harus mengaktifkan *queue worker* secara terus-menerus menggunakan Supervisor.

1. Buat file konfigurasi Supervisor baru:
   ```bash
   sudo nano /etc/supervisor/conf.d/booking-worker.conf
   ```
2. Rekatkan konfigurasi berikut:
   ```ini
   [program:booking-worker]
   process_name=%(program_name)s_%(process_num)02d
   command=php /var/www/booking/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   stopasgroup=true
   killasgroup=true
   user=www-data
   numprocs=2
   redirect_stderr=true
   stdout_logfile=/var/www/booking/storage/logs/worker.log
   stopwaitsecs=3600
   ```
3. Simpan file tersebut, kemudian muat ulang konfigurasi Supervisor:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start booking-worker:*
   ```

---

## ⚡ 5. Optimasi Performa Produksi (Caching)

Setelah semua instalasi selesai, jalankan perintah caching berikut di server produksi untuk mempercepat eksekusi PHP:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```
> ⚠️ **Catatan Penting:** Jika Anda melakukan perubahan konfigurasi pada file `.env` di masa mendatang, pastikan Anda menjalankan perintah `php artisan config:clear` terlebih dahulu, baru kemudian `php artisan config:cache`.

---

## 🧪 6. Pengujian Integrasi WhatsApp (Fonnte)

Untuk memastikan integrasi WhatsApp dengan Fonnte berjalan dengan baik di server Anda, Anda dapat memicu tes pengiriman manual melalui Artisan Command bawaan:
```bash
php artisan app:test-fonnte {nomor_hp_tujuan} "Pesan uji coba dari server produksi."
```
Periksa log antrean jika pesan tidak sampai:
```bash
tail -n 50 storage/logs/laravel.log
```
# ─── SELESAI ───
