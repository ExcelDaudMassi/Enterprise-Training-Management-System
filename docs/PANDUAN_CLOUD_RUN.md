# Panduan Deployment: Google Cloud Run

Deploy aplikasi Laravel monolithic (dengan Inertia + Vue) ke Google Cloud Run membutuhkan pembungkusan aplikasi ke dalam **Docker Container** dan penggunaan layanan database eksternal karena sifat Cloud Run yang *stateless* (ephemeral).

---

## 1. Persiapan Arsitektur (Wajib)

### A. Migrasi Database ke Cloud SQL (MySQL / PostgreSQL)
Karena container Cloud Run bersifat sementara, file `database.sqlite` akan terhapus setiap kali instance mati.
- **Langkah:** Buat instance **Google Cloud SQL** (MySQL atau PostgreSQL).
- **Konfigurasi Laravel:** Ubah `.env` di Cloud Run agar mengarah ke koneksi Cloud SQL menggunakan Unix Socket.

### B. Penyimpanan File (Storage)
File hasil *upload* (misalnya bukti pembayaran, foto profil) yang disimpan di `storage/app/public` juga akan hilang jika container mati.
- **Langkah:** Gunakan **Google Cloud Storage (GCS)**.
- **Konfigurasi Laravel:** Integrasikan *filesystem* Laravel dengan driver GCS.

---

## 2. File yang Dibutuhkan untuk Cloud Run

Untuk menjalankan Laravel di Cloud Run, Anda harus menyiapkan beberapa file di *root* repositori:

### A. `Dockerfile`
File ini berisi instruksi untuk merakit OS, PHP, Apache/Nginx, dan meng-copy kode sumber Anda.
```dockerfile
# Contoh Base Image
FROM php:8.2-apache

# 1. Install ekstensi PHP yang dibutuhkan (pdo_mysql, gd, zip, dll)
# 2. Install Composer
# 3. Install Node.js (opsional, jika build frontend dilakukan di dalam Docker)
# 4. Copy seluruh kode aplikasi ke /var/www/html
# 5. Set DocumentRoot Apache ke /var/www/html/public
# 6. Jalankan php artisan optimize & config:cache
```

### B. `entrypoint.sh` (Opsional tapi disarankan)
Script ini akan dijalankan pertama kali saat container hidup. Gunanya untuk menjalankan migrasi database secara otomatis.

---

## 3. Menangani Scheduler dan Queue di Cloud Run

Di server VPS, kita menggunakan Supervisor dan Cron. Di Cloud Run, pendekatannya berbeda:
- **Queue Worker:** Cloud Run tidak bisa menjalankan *background process* (`php artisan queue:work`) secara berkelanjutan di container web. Anda harus menggunakan **Google Cloud Tasks** untuk men-trigger endpoint API Queue, ATAU membuat service Cloud Run terpisah khusus untuk *worker*.
- **Scheduler:** Gunakan **Google Cloud Scheduler** untuk melakukan HTTP/GET request ke endpoint khusus (misal: `/api/cron`) setiap jam, yang di dalam kodenya menjalankan `Artisan::call('schedule:run')`.

---

## 4. Langkah-Langkah Eksekusi (gcloud CLI)

Jika Dockerfile dan Database (Cloud SQL) sudah siap, proses deploy adalah sebagai berikut:

1. **Autentikasi ke Google Cloud:**
   ```bash
   gcloud auth login
   gcloud config set project [ID_PROJECT_ANDA]
   ```

2. **Deploy via Cloud Build (Otomatis membangun dan deploy):**
   ```bash
   gcloud run deploy booking-app \
     --source . \
     --region=asia-southeast2 \
     --allow-unauthenticated \
     --add-cloudsql-instances=[PROJECT_ID]:[REGION]:[NAMA_INSTANCE_SQL]
   ```

3. **Set Environment Variables (.env):**
   Gunakan **Google Secret Manager** atau atur langsung lewat antarmuka Cloud Run (Tab *Variables & Secrets*) untuk memasukkan kredensial database, App Key, dan Token Fonnte.

---

## Kesimpulan
Deploy ke Google Cloud Run akan membuat aplikasi Anda sangat stabil, **kebal terhadap lonjakan trafik**, dan bebas *maintenance* server OS. Namun, ini membutuhkan **perubahan infrastruktur** dari SQLite menjadi Cloud SQL dan setup Docker.
