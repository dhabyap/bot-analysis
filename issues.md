# Digital Marketing Monitoring Assistant - Project Milestones

## Issue 1: Infrastructure & Data Ingestion Setup
- **Deskripsi:** 
  Menyiapkan database migration, environment untuk Bot Telegram dan Gemini API, serta endpoint penerima payload dari Meta Pixel.
- **Tugas:**
  - [x] Install framework dasar Laravel 11 (tanpa fitur asinkronus agar kompatibel dengan Shared Hosting).
  - [x] Buat file migration untuk tabel `meta_events` (menyimpan id, event_name, source, value, payload json).
  - [x] Buat *Webhook Endpoint* (POST) `/api/webhook/meta` dan pastikan URL tersebut *bypass* dari proteksi CSRF.
  - [x] Implementasi `MetaWebhookController` untuk injeksi data webhook ke database, serta mengirim HTTP trigger notifikasi (apabila `event_name` adalah event penting misal 'Purchase') ke chat Telegram Admin.

---

## Issue 2: AI Analytical Assistant (Telegram Bot Integration)
- **Deskripsi:**
  Mengintegrasikan Telegram API (*webhook mode*) dengan backend menggunakan facade `Http` (tanpa SDK pihak ketiga) serta menyambungkannya dengan Google Gemini (gemini-1.5-flash).
- **Tugas:**
  - [x] Buat *Webhook Endpoint* `/api/webhook/telegram` untuk menerima aksi (chat) dari Admin.
  - [x] Bypass endpoint `/api/webhook/telegram` dari perlindungan CSRF.
  - [x] Implementasi `TelegramWebhookController` untuk memproses *command/chat*.
  - [x] Buat *Data Flow*: Agregasi data (misal total traffic & value hari ini) -> *Inject Context* -> Prompting ke Gemini -> Proses Response Gemini -> HTTP post `/sendMessage` kembali ke user Telegram.

---

## Issue 3: Automated Daily Reporting System
- **Deskripsi:**
  Membuat sistem peringkasan harian (Daily Report) yang akan dipanggil secara otomatis melalui Cron Job dari server (cPanel).
- **Tugas:**
  - [x] Buat Laravel Console Command `app:daily-report`.
  - [x] Susun query untuk menghitung total metriks (misal Total Purchase, Total Nilai Transaksi, Total Visit) dari `H-1` (satu hari sebelumnya).
  - [x] Integrasikan `app:daily-report` untuk mengirim output hasil langsung ke chat Admin Telegram via API.
  - [x] Jadwalkan task ini di `routes/console.php` (misal pada jam 07:00 pagi setiap harinya) – instruksi setup di cpanel akan dilampirkan setelah siap.

---
*Catatan:* Semua interaksi eksternal (API call ke Telegram & Gemini) wajib dilakukan via Laravel HTTP Client secara *Synchronous*. Batasi *timeout* request agar sesuai dengan alokasi maksimum yang umumnya diizinkan Shared Hosting.
