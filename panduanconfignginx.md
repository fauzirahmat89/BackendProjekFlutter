# Panduan Konfigurasi Nginx & SSL (Certbot)

Panduan ini menjelaskan cara mengkonfigurasi Nginx di server host sebagai reverse proxy untuk aplikasi Docker yang berjalan di port `8000`, serta mengamankannya dengan SSL menggunakan Certbot (Let's Encrypt).

## 1. Instalasi Nginx di Host

Jika Nginx belum terinstal di server (Ubuntu/Debian), jalankan:

```bash
sudo apt update
sudo apt install nginx -y
```

## 2. Konfigurasi Reverse Proxy

1. Buat file konfigurasi baru di `/etc/nginx/sites-available/`:
   ```bash
   sudo nano /etc/nginx/sites-available/makanmen
   ```

2. Masukkan konfigurasi berikut (sesuaikan `server_name` dengan domain Anda):
   ```nginx
   server {
       listen 80;
       server_name domain-anda.com; # GANTI DENGAN DOMAIN ANDA

       location / {
           proxy_pass http://127.0.0.1:8002;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```

3. Aktifkan konfigurasi dengan membuat symlink ke `sites-enabled`:
   ```bash
   sudo ln -s /etc/nginx/sites-available/makanmen /etc/nginx/sites-enabled/
   ```

4. Tes konfigurasi Nginx dan restart:
   ```bash
   sudo nginx -t
   sudo systemctl restart nginx
   ```

## 3. Instalasi SSL dengan Certbot (Robot SSL)

Certbot adalah alat otomatis (robot) untuk mengambil dan memasang sertifikat SSL dari Let's Encrypt.

1. Instal Certbot dan plugin Nginx:
   ```bash
   sudo apt install certbot python3-certbot-nginx -y
   ```

2. Jalankan Certbot untuk mendapatkan SSL:
   ```bash
   sudo certbot --nginx -d domain-anda.com
   ```
   *Ikuti petunjuk di layar (masukkan email, setujui terms, dan pilih opsi untuk redirect HTTP ke HTTPS).*

3. Certbot akan secara otomatis mengubah file konfigurasi Nginx Anda untuk menambahkan baris SSL dan redirect otomatis.

## 4. Verifikasi Pembaruan Otomatis

Sertifikat Let's Encrypt berlaku selama 90 hari. Certbot biasanya sudah menyertakan cronjob atau timer untuk perpanjangan otomatis. Anda bisa mengetesnya dengan:

```bash
sudo certbot renew --dry-run
```

Jika tidak ada error, maka SSL Anda akan diperpanjang secara otomatis sebelum kadaluarsa.
