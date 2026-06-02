# Panduan Deployment Backend Laravel (VPS)

Dokumentasi ini berisi langkah-langkah untuk melakukan *deployment* backend aplikasi MakanMen ke dalam Virtual Private Server (VPS) produksi (seperti DigitalOcean, Niagahoster, AWS, dsb) menggunakan Docker.

## Prasyarat (Prerequisites)
Pastikan VPS Anda sudah terinstall perangkat lunak berikut:
1. **Git** (Untuk mengambil *source code*)
2. **Docker** (Versi terbaru)
3. **Docker Compose** (Versi V2)

*Catatan: Anda tidak perlu menginstall PHP, Composer, atau MySQL secara langsung di VPS Anda, karena semuanya sudah di-handle oleh Docker.*

---

## Langkah-langkah Deployment

### 1. Clone Repositori
Masuk ke terminal VPS Anda via SSH, kemudian *clone* project ini ke dalam direktori yang Anda inginkan (misal: `/var/www/makanmen`).

```bash
git clone <URL_REPO_ANDA> /var/www/makanmen
cd /var/www/makanmen/backend
```

### 2. Atur Environment Variables
Salin file `.env.example` menjadi `.env`.

```bash
cp .env.example .env
```

Buka file `.env` (misal menggunakan `nano .env` atau `vim .env`) dan sesuaikan variabel berikut untuk lingkungan produksi:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.domainanda.com # Sesuaikan dengan domain atau IP VPS Anda

# Pengaturan Database (Akan otomatis digunakan oleh Docker MySQL)
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=makanmen
DB_USERNAME=makanmen_user
DB_PASSWORD=password_rahasia_anda # Ganti dengan password yang kuat
```

### 3. Build & Jalankan Container
Jalankan Docker Compose untuk mem-build image dan menjalankan container Nginx, PHP (App), dan MySQL di latar belakang (*background*).

```bash
docker compose up -d --build
```

### 4. Install Dependencies (Composer)
Karena folder proyek di-mount langsung ke container, Anda perlu menjalankan instalasi package Composer melalui container aplikasi:

```bash
docker compose exec app composer install --optimize-autoloader --no-dev
```

### 5. Generate Application Key
Buat *key* unik untuk aplikasi Laravel Anda:

```bash
docker compose exec app php artisan key:generate
```

### 6. Atur Hak Akses Folder (Permissions)
Agar Laravel dapat menulis ke dalam folder `storage` dan `bootstrap/cache` (mencegah error `tempnam()` atau 500 Server Error):

```bash
docker compose exec app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
```

### 7. Jalankan Migrasi Database
Migrasikan struktur tabel (toko, menu, order, dll) ke dalam database MySQL:

```bash
docker compose exec app php artisan migrate --force
```

### 8. Link Storage
Agar file gambar/media yang di-upload bisa diakses oleh publik:

```bash
docker compose exec app php artisan storage:link
```

### 9. Optimasi Cache (Khusus Production)
Untuk meningkatkan performa di VPS, jalankan perintah optimasi *cache* berikut:

```bash
docker compose exec app php artisan config:cache
docker compose exec app php artisan event:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

---

## Cek Status & Logs

- **Melihat status container yang berjalan:**
  ```bash
  docker compose ps
  ```

- **Melihat log (jika terjadi error):**
  ```bash
  docker compose logs -f app
  docker compose logs -f web
  docker compose logs -f db
  ```

## Konfigurasi Port / Reverse Proxy (Opsional)
Saat ini Nginx dalam Docker berjalan di *port* `8000` (berdasarkan `docker-compose.yml` -> `"8000:80"`). 
Jika Anda ingin aplikasi dapat diakses langsung dari IP VPS (Port 80) atau Domain, Anda memiliki dua pilihan:

1. **Ganti Port di `docker-compose.yml`**: Ubah port `8000:80` menjadi `80:80`, lalu *restart* container (`docker compose up -d`).
2. **Gunakan Reverse Proxy (Disarankan)**: Biarkan port `8000` dan install Nginx di VPS *host*. Buat block server (virtual host) di Nginx host untuk mengarahkan trafik domain (misal `api.domainanda.com`) ke `http://localhost:8000`. Ini memudahkan Anda jika nantinya perlu menginstall SSL/HTTPS (menggunakan Certbot/Let's Encrypt).