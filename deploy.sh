#!/bin/bash
set -e

echo "Deploying application..."

# 1. Masuk ke mode maintenance (agar user tidak mengakses aplikasi saat update)
php artisan down || true

# 2. Tarik kode terbaru dari repositori git (sesuaikan dengan branch production)
# git pull origin main

# 3. Install/Update dependensi PHP (hanya package production)
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# 4. Install/Update dependensi Node (Frontend) & Build aset production
npm ci
npm run build

# 5. Eksekusi Migrasi Database (Wajib --force agar tidak butuh interaksi)
php artisan migrate --force

# 6. Bersihkan dan buat ulang seluruh Cache (Sangat penting untuk performa production!)
php artisan optimize:clear
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

# 7. Pastikan storage link terhubung ke public
php artisan storage:link || true

# 8. Keluar dari mode maintenance
php artisan up

echo "✅ Application deployed successfully!"
