# Panduan Instalasi IK WorkDesk di PC atau Laptop Lain (Windows)

Panduan ini untuk menjalankan IK WorkDesk sebagai **development/local environment** di Windows 10 atau Windows 11. Hasilnya dapat diakses dari browser pada komputer tersebut, tanpa mengubah aplikasi production.

> Jangan menyalin file `.env` dari server production dan jangan membagikan password database, email, SSH, atau `APP_KEY`. Buat `.env` baru dari `.env.example` pada setiap komputer.

## 1. Yang perlu disiapkan

Pastikan akun Windows dapat memasang aplikasi dan menggunakan Command Prompt atau PowerShell.

| Aplikasi | Versi minimum | Kegunaan |
| --- | --- | --- |
| Git for Windows | Versi terbaru stabil | Mengunduh dan memperbarui source code. |
| PHP | 8.3 atau lebih baru | Menjalankan Laravel dan Artisan. |
| Composer | 2.x | Mengunduh package PHP. |
| Node.js | 22 LTS | Membuat asset JavaScript dan CSS. Instalasi Node sudah mencakup npm. |
| Browser | Chrome, Edge, atau Firefox terbaru | Membuka aplikasi. |
| Visual Studio Code (opsional) | Versi terbaru stabil | Mengedit source code. |
| PostgreSQL (opsional) | 15 atau lebih baru | Hanya diperlukan jika tidak memakai database SQLite bawaan. |

Untuk instalasi local paling sederhana, gunakan **SQLite**. SQLite tidak memerlukan aplikasi server database terpisah, tetapi ekstensi `pdo_sqlite` dan `sqlite3` harus aktif di PHP.

## 2. Instal aplikasi pendukung

Instal Git, PHP, Composer, dan Node.js terlebih dahulu. Setelah selesai, tutup lalu buka kembali PowerShell agar PATH terbaru terbaca.

Periksa versi dari PowerShell:

```powershell
git --version
php -v
composer --version
node -v
npm -v
```

Versi PHP harus 8.3 atau lebih baru, dan Node harus 22 atau lebih baru. Jika salah satu perintah tidak dikenali, periksa kembali instalasi atau PATH aplikasi tersebut.

Periksa ekstensi PHP untuk SQLite:

```powershell
php -m | Select-String "pdo_sqlite|sqlite3"
```

Kedua nama ekstensi harus tampil. Jika belum ada, buka `php.ini`, aktifkan baris berikut dengan menghapus tanda titik koma di depannya, lalu buka ulang PowerShell:

```ini
extension=pdo_sqlite
extension=sqlite3
```

## 3. Unduh aplikasi

Pilih folder kerja, misalnya `D:\Projects`, lalu jalankan:

```powershell
cd D:\Projects
git clone https://github.com/khafafi92/ik-workdesk.git
cd ik-workdesk
git status --short --branch
```

Perintah terakhir seharusnya menampilkan branch `main` tanpa perubahan lokal.

Jika source code sudah dikirim sebagai ZIP, ekstrak ZIP ke folder kerja, buka PowerShell di folder tersebut, lalu lanjutkan ke langkah 4. Untuk mendapatkan update berikutnya, instal Git dan gunakan clone repository lebih disarankan.

## 4. Instal package aplikasi

Jalankan dari root folder proyek (`ik-workdesk`):

```powershell
composer install
npm ci
```

Jika `npm ci` gagal karena file lock tidak tersedia, gunakan:

```powershell
npm install
```

Jangan gunakan `composer update` saat instalasi biasa. Perintah itu dapat mengganti versi dependency yang sudah dikunci oleh proyek.

## 5. Buat konfigurasi local dan database SQLite

Salin template konfigurasi:

```powershell
Copy-Item .env.example .env
```

Buat file database SQLite:

```powershell
New-Item database\database.sqlite -ItemType File -Force
```

Buka `.env` dengan VS Code atau Notepad, kemudian pastikan nilai berikut digunakan untuk local environment:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=log
```

`MAIL_MAILER=log` membuat email notifikasi hanya dicatat ke log local, sehingga tidak ada email sungguhan yang dikirim ketika sedang mencoba aplikasi.

### Buat akun administrator pertama

Sebelum menjalankan seeder, isi tiga nilai berikut di `.env`. Ganti seluruh contoh dengan data local Anda sendiri.

```dotenv
WORKDESK_BOOTSTRAP_ADMIN_NAME="Administrator Local"
WORKDESK_BOOTSTRAP_ADMIN_EMAIL="admin.local@example.test"
WORKDESK_BOOTSTRAP_ADMIN_PASSWORD="GantiDenganPasswordKuat!2026"
```

Password harus minimal 12 karakter dan mengandung huruf besar, huruf kecil, angka, serta simbol. Jangan memakai email atau password production.

## 6. Buat application key, tabel database, dan asset

Masih dari root proyek, jalankan perintah satu per satu:

```powershell
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan optimize:clear
```

Arti tiap perintah:

- `key:generate` membuat kunci enkripsi khusus komputer ini.
- `migrate --seed` membuat tabel, permission, data master awal, dan akun administrator yang dibuat pada langkah 5.
- `npm run build` membuat file CSS/JavaScript yang dipakai browser.
- `optimize:clear` memastikan tidak ada cache konfigurasi lama.

Jika proses migration meminta konfirmasi, pilih `yes` hanya jika database local yang dipakai memang baru atau boleh diperbarui.

## 7. Jalankan aplikasi

Buka **dua** jendela PowerShell di folder proyek.

Pada jendela pertama, jalankan web server:

```powershell
php artisan serve
```

Pada jendela kedua, jalankan queue worker untuk notifikasi dan pekerjaan background:

```powershell
php artisan queue:work --tries=3
```

Buka browser pada alamat berikut:

```text
http://127.0.0.1:8000
```

Masuk dengan email dan password yang dibuat pada langkah 5. Jangan tutup kedua jendela PowerShell selama aplikasi digunakan.

### Saat mengubah tampilan frontend

Jika sedang mengubah CSS, JavaScript, atau Blade yang memakai Vite, buka jendela PowerShell ketiga:

```powershell
npm run dev
```

Untuk penggunaan normal tanpa perubahan frontend, `npm run build` pada langkah 6 sudah cukup.

## 8. Pemeriksaan setelah instalasi

Jalankan pengecekan berikut sebelum mulai memakai atau mengembangkan aplikasi:

```powershell
php artisan about
php artisan test
php vendor\bin\pint --test
git status --short
```

Hasil yang diharapkan:

- `php artisan about` menampilkan environment `local` dan koneksi database SQLite.
- seluruh test selesai tanpa kegagalan;
- Pint tidak menemukan masalah format;
- `git status --short` tidak berisi perubahan source code. File `.env` dan `database/database.sqlite` tidak akan masuk Git karena sudah diabaikan.

## 9. Memperbarui aplikasi dari GitHub

Sebelum update, hentikan `php artisan serve` dan `php artisan queue:work` dengan `Ctrl+C`. Dari root proyek jalankan:

```powershell
git status --short
git pull --ff-only origin main
composer install
npm ci
php artisan migrate
npm run build
php artisan optimize:clear
php artisan optimize
```

Jika `git status --short` menampilkan file source code yang berubah, jangan langsung menjalankan `git pull`. Simpan atau commit pekerjaan lokal terlebih dahulu agar tidak terjadi konflik.

Setelah update selesai, jalankan kembali web server dan queue worker seperti pada langkah 7.

## 10. Opsi database PostgreSQL

Gunakan ini hanya bila komputer tersebut harus memakai PostgreSQL. Buat database kosong, misalnya `ik_workdesk_local`, lalu ubah bagian database pada `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ik_workdesk_local
DB_USERNAME=postgres
DB_PASSWORD=GantiPasswordDatabaseLocal
```

Pastikan ekstensi `pdo_pgsql` dan `pgsql` aktif di PHP:

```powershell
php -m | Select-String "pdo_pgsql|pgsql"
```

Kemudian jalankan ulang:

```powershell
php artisan migrate --seed
```

Jangan arahkan konfigurasi local ke database production.

## 11. Solusi masalah umum

| Masalah | Penyebab umum | Solusi |
| --- | --- | --- |
| `php` atau `composer` tidak dikenali | PATH belum terbaca | Tutup/buka PowerShell, lalu periksa PATH dan instalasi. |
| `could not find driver` | Ekstensi SQLite atau PostgreSQL belum aktif | Aktifkan ekstensi pada `php.ini`, lalu buka ulang PowerShell. |
| `no such table` | Migration belum berjalan | Jalankan `php artisan migrate --seed`. |
| Halaman tanpa style | Asset belum dibangun | Jalankan `npm run build`, lalu refresh browser dengan `Ctrl+F5`. |
| Error cache atau permission | Cache local lama | Jalankan `php artisan optimize:clear`. |
| Queue tidak memproses pekerjaan | Worker belum berjalan | Jalankan `php artisan queue:work --tries=3`. |
| Tidak bisa login | Admin bootstrap belum dibuat | Periksa tiga `WORKDESK_BOOTSTRAP_ADMIN_*` di `.env`, lalu jalankan `php artisan db:seed --class=AdminUserSeeder`. |
| Port 8000 dipakai aplikasi lain | Ada web server lain di port tersebut | Jalankan `php artisan serve --port=8001`, lalu buka `http://127.0.0.1:8001`. |

Untuk melihat error aplikasi local, periksa file log:

```powershell
Get-Content storage\logs\laravel.log -Tail 100
```

## 12. Hal yang tidak boleh dilakukan

- Jangan commit `.env`, database local, token, password, atau file log.
- Jangan gunakan `git add .` tanpa mengecek `git status --short`.
- Jangan menjalankan `php artisan migrate:fresh` jika database local berisi data yang masih dibutuhkan; perintah itu menghapus seluruh tabel.
- Jangan menggunakan konfigurasi database, SMTP, atau SSH production pada PC/laptop baru.
- Jangan menjalankan deployment server production dari panduan local ini.
