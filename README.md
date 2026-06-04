# Smart Gatensi — Backend

Layanan backend untuk aplikasi Smart Gatensi (LSP Gatensi Karya Konstruksi).

## Tech Stack

- **Framework:** Laravel 12 (PHP ^8.2)
- **Database:** MySQL (multiple connections)
- **Cache & Queue:** Redis
- **PDF:** DomPDF, imagick

## Docker Setup (Laravel Sail)

Proyek ini menggunakan [Laravel Sail](https://laravel.com/docs/sail) untuk lingkungan Docker.

### Prasyarat

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) terinstal dan running
- PHP 8.2+ dan Composer (untuk install dependencies sebelum Docker pertama kali)

### Instalasi & Menjalankan

> **Catatan:** `.env` berisi token/key dengan karakter `$`. Docker Compose membaca `.env` secara default dan akan mencoba menginterpolasi karakter `$` sebagai variable reference, menyebabkan warning. Solusinya menggunakan file `.env.compose` terpisah.

```bash
# 1. Install PHP dependencies (vendor)
composer install

# 2. Copy environment file jika belum ada
copy .env.example .env

# 3. Generate app key
php artisan key:generate

# 4. Jalankan Docker containers (gunakan --env-file untuk hindari warning $)
docker compose --env-file .env.compose up -d

# 5. Masuk ke container app
docker compose exec laravel.test bash

# 6. Akses aplikasi
#    http://localhost
```

> Aplikasi menggunakan **database eksternal** (bukan MySQL di Docker). Pastikan koneksi database di `.env` sudah benar sebelum menjalankan container. Migrasi tidak perlu dijalankan karena menggunakan database yang sudah ada.

### Services

| Service     | Container Name      | Port (Host)        |
|-------------|---------------------|--------------------|
| PHP App     | gatensi-backend-laravel.test-1 | 80           |
| Redis       | gatensi-backend-redis-1        | 6380 → 6379  |
| Mailpit     | gatensi-backend-mailpit-1      | 8025         |

### Koneksi Database

Aplikasi ini menggunakan **database eksternal** — tidak ada MySQL di Docker. Semua koneksi database (DB_MYGATENSI_2, DB_REGULER, DB_BALAI, DB_FG, DB_SMART, dsb.) menggunakan host IP langsung yang sudah dikonfigurasi di `.env` dan tetap bisa diakses dari dalam container.

### Environment Variables untuk Docker

Saat menjalankan di Docker, environment variables berikut akan otomatis menggunakan service name Docker:

- `REDIS_HOST=redis` → mengarah ke service Redis container
- `MAIL_HOST=mailpit` → mengarah ke service Mailpit container

Override di `.env` jika perlu:

```env
DOCKER_REDIS_HOST=redis
DOCKER_REDIS_PORT=6379
```

### PHP Extensions (sudah included)

| Extension              | Untuk                         |
|------------------------|-------------------------------|
| `php8.4-imagick`       | Image processing (QR code, dsb) |
| `php8.4-gd`            | DomPDF image rendering        |
| `php8.4-xml`           | DomPDF, DOM parsing           |
| `php8.4-mbstring`      | String encoding               |
| `php8.4-redis`         | Redis cache & queue           |
| `php8.4-zip`           | File compression              |
| `php8.4-bcmath`        | Presisi angka desimal         |

### Queue Worker

Queue worker otomatis berjalan di dalam container via Supervisor. Konfigurasi ada di `docker/8.4/supervisord.conf`.

Jika perlu restart worker:

```bash
docker compose exec laravel.test supervisorctl restart queue-worker
```

### Common Commands

```bash
# Melihat logs
docker compose --env-file .env.compose logs -f laravel.test

# Masuk ke container
docker compose --env-file .env.compose exec laravel.test bash

# Artisan di dalam container
docker compose --env-file .env.compose exec laravel.test php artisan cache:clear

# Stop containers
docker compose --env-file .env.compose down

# Rebuild container (setelah perubahan Dockerfile)
docker compose --env-file .env.compose build laravel.test

# Restart semua
docker compose --env-file .env.compose restart
```

### Troubleshooting

**Imagick tidak ditemukan?**
```bash
docker compose exec laravel.test php -m | grep imagick
```
Seharusnya output: `imagick`. Jika tidak, pastikan Dockerfile di `docker/8.4/Dockerfile` mengandung `php8.4-imagick`.

**Permission denied di storage/logs?**
```bash
docker compose exec laravel.test chmod -R 777 storage bootstrap/cache
```

---

## Pengembangan (tanpa Docker)

```bash
composer install
php artisan serve
```

Akses di `http://localhost:8000`.
