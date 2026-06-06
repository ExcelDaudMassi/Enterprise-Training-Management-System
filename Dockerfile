# ==============================================================================
# Stage 1: Build Frontend Assets (Node.js)
# ==============================================================================
FROM node:20-alpine AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ==============================================================================
# Stage 2: Build Backend & Final Image (PHP 8.2 Apache)
# ==============================================================================
FROM php:8.2-apache

# Set environment variables untuk production
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV PORT=8080
# Apache mendengarkan port yang ada di ENV PORT
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf

# Install dependencies sistem operasi yang dibutuhkan oleh ekstensi PHP
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Konfigurasi dan instal ekstensi PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd zip bcmath

# Enable Apache mod_rewrite
RUN a2enmod rewrite headers

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Salin file konfigurasi Apache khusus untuk Cloud Run
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# Salin seluruh kode aplikasi (tanpa file yang ada di .dockerignore)
COPY . /var/www/html

# Salin hasil build frontend dari Stage 1
COPY --from=frontend-builder /app/public/build /var/www/html/public/build

# Install dependensi PHP (tanpa dev dependencies)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set perizinan yang tepat untuk folder storage dan bootstrap/cache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache \
    && chmod -R 777 /var/www/html/database

# Salin script entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Jalankan skrip entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
