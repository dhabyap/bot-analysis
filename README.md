# Digital Marketing Monitoring Assistant

Sistem backend berbasis Laravel 10 untuk menganalisa, mencatat, dan melaporkan event pemasaran digital (seperti dari Meta Pixel) dengan menggunakan integrasi Telegram Bot dan AI Google Gemini.

## Fitur Utama

1. **Meta Pixel Webhook Ingestion**: Endpoint untuk menerima `payload` event dari Meta Pixel, menyimpannya ke database, dan memberikan alert Telegram *real-time* khusus untuk event bervalue tinggi (misal: "Purchase").
2. **AI Analytical Assistant**: Chatbot Telegram khusus Admin yang dapat ditanyai mengenai ringkasan data trafik atau transaksi hari ini, diproses cerdas menggunakan Google Gemini (gemini-1.5-flash).
3. **Daily Automated Reporting**: Cron Job harian yang otomatis merangkum event dan total transaksi hari sebelumnya, lalu mengirimkannya ke Telegram admin.

---

## Prasyarat (Prerequisites)

- PHP >= 8.1
- Composer
- Database MySQL/MariaDB
- Akun Telegram (untuk mendapatkan `TELEGRAM_BOT_TOKEN` via BotFather dan `TELEGRAM_ADMIN_ID`)
- Akun Google AI Studio (untuk mendapatkan `GEMINI_API_KEY`)

---

## 💻 Cara Menjalankan di Lokal (Local Development)

Proses pengembangan di lokal memerlukan ekstensi seperti Ngrok karena Telegram memerlukan URL publik yang mendukung HTTPS untuk webhook.

### 1. Instalasi dan Setup Dasar
1. **Clone repositori ini:**
   ```bash
   git clone https://github.com/dhabyap/bot-analysis.git
   cd bot-analysis
   ```
2. **Install dependency Composer:**
   ```bash
   composer install
   ```
3. **Setup environment:**
   ```bash
   cp .env.example .env
   ```
4. **Generate Application Key:**
   ```bash
   php artisan key:generate
   ```

### 2. Konfigurasi Database & API Keys
Buka file `.env` dan atur parameter berikut:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nama_database_lokal_anda
DB_USERNAME=root
DB_PASSWORD=

TELEGRAM_BOT_TOKEN=token_bot_dari_botfather
TELEGRAM_ADMIN_ID=id_telegram_anda
GEMINI_API_KEY=api_key_dari_google_ai_studio
```

### 3. Jalankan Migrasi
```bash
php artisan migrate
```

### 4. Setup Webhook (Wajib untuk Telegram)
Telegram webhook hanya bisa ditembak ke URL publik (HTTPS).
1. Jalankan server Laravel:
   ```bash
   php artisan serve
   ```
   *(Server akan berjalan di `http://127.0.0.1:8000`)*
2. Buka *tunnel* menggunakan [Ngrok](https://ngrok.com/):
   ```bash
   ngrok http 8000
   ```
3. Copy URL HTTPS dari Ngrok (misal `https://1234-abcd.ngrok-free.app`), lalu daftarkan webhook ke Telegram. Buka browser dan paste URL ini (ganti token dan url):
   ```
   https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook?url=https://<NGROK_URL>/api/webhook/telegram
   ```

*(Opsional)* Anda juga bisa menguji Cron Job harian dengan:
```bash
php artisan app:daily-report
```

---

## 🌍 Cara Menjalankan di Server Production (cPanel / Shared Hosting)

Laravel 10 pada proyek ini telah dikonfigurasi secara *synchronous* agar sepenuhnya kompatibel dengan shared hosting.

### 1. Upload dan Instalasi
1. Clone repositori ke server via Terminal/SSH.
   ```bash
   git clone https://github.com/dhabyap/bot-analysis.git
   ```
   *Atau, upload file .zip dari lokal ke File Manager cPanel lalu extract.*
2. Jalankan `composer install --no-dev --optimize-autoloader` via SSH.
3. Ubah `.env.example` menjadi `.env` dan isi dengan detail Database Production serta API Keys (Telegram & Gemini).

### 2. Setup Webhook Server
Karena server production sudah memiliki nama domain (misal `https://domainanda.com`), daftarkan Webhook Telegram dengan URL tersebut:
1. Buka browser dan arahkan ke:
   ```
   https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook?url=https://domainanda.com/api/webhook/telegram
   ```
   Jika muncul respon `{"ok":true,"result":true,"description":"Webhook was set"}`, berarti bot sudah aktif.

### 3. Setup Cron Job (Daily Report)
Agar fungsi *Daily Automated Reporting* berjalan otomatis setiap pagi (diatur jam `07:00` pada `routes/console.php`):
1. Masuk ke cPanel -> Menu **Cron Jobs**.
2. Tambahkan Cron Job baru untuk berjalan **setiap menit** (`* * * * *`).
3. Isi command dengan path ke PHP dan artisan, contoh:
   ```bash
   /usr/local/bin/php /home/username_cpanel/public_html/bot-analysis/artisan schedule:run >> /dev/null 2>&1
   ```
   *(Pastikan absolute path ke `php` dan `artisan` disesuaikan dengan environment cPanel anda).*

---

## 🤖 Cara Penggunaan Bot & Sistem

### 1. Menerima Data Pemasaran (Webhook Meta Pixel)
Sistem menerima data payload HTTP POST pada endpoint:
`POST https://domainanda.com/api/webhook/meta`

- Meta Pixel atau API harus mengirimkan data dalam format JSON berisi minimal parameter `event_name`. 
- Parameter `custom_data.value` akan dibaca jika ada.
- Jika `event_name` adalah `Purchase` atau event bernilai tinggi, bot akan otomatis mengirimkan notifikasi instan ke Telegram Admin.

### 2. Berinteraksi dengan AI Assistant di Telegram
1. Buka chat Telegram Anda dengan Bot yang telah didaftarkan.
2. Bot akan memverifikasi apakah pengirim pesan adalah **Admin** (berdasarkan pengecekan `TELEGRAM_ADMIN_ID`).
3. Jika admin, ajukan pertanyaan ringan terkait trafik hari ini:
   - *"Halo, ada berapa total transaksi hari ini?"*
   - *"Tolong berikan ringkasan trafik Meta Ads kita"*
4. Sistem akan menarik data dari tabel `meta_events` pada hari ini (`today()`), menyusunnya menjadi konteks, lalu mengirimkannya ke Google Gemini.
5. Gemini akan merespon pertanyaan Anda dengan laporan berdasarkan data tersebut.

### 3. Menerima Laporan Harian
Jika Cron Job aktif, sistem secara otomatis akan menarik data agregasi trafik `H-1` dan mengirimkannya pada pukul `07:00` pagi waktu server ke chat Telegram admin.
