#!/bin/bash
set -e

# Clear and cache configuration
php artisan optimize:clear
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

# Force migration (pastikan koneksi database benar-benar ke Cloud SQL MySQL/Postgres)
php artisan migrate --force

# Start Apache di foreground
apache2-foreground
