# IK WorkDesk

IK WorkDesk adalah platform operasional internal berbasis Laravel dan Filament untuk Service Desk, Work Logs, Attendance, Reminder, Meeting Room, Vehicle Booking, serta workflow kolaboratif antar-department.

## Requirements

- PHP 8.3+
- Composer 2
- Node.js 22+
- Database yang didukung Laravel (SQLite dapat digunakan untuk development)
- Queue worker untuk notification dan email asynchronous

## Instalasi development

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Untuk menjalankan server, queue, log viewer, dan Vite secara bersamaan:

```bash
composer dev
```

## Pengujian

```bash
composer test
vendor/bin/pint --test
```

GitHub Actions menjalankan instalasi dependency, build frontend, migration, Pint, dan seluruh PHPUnit test pada setiap push dan pull request.

## Storage dan keamanan file

File Attendance, Service Desk, Collaboration Room, dan Findings disimpan di `storage/app/private`. File tidak boleh disajikan langsung melalui `/storage`; download harus melalui route yang melakukan authorization terhadap record induknya.

Saat memperbarui instalasi lama, lakukan simulasi lalu migrasikan upload sensitif dengan perintah berikut. Perintah memverifikasi checksum sebelum menghapus salinan public.

```bash
php artisan storage:migrate-private-uploads --dry-run
php artisan storage:migrate-private-uploads
```

## Queue dan notification

Notification email menggunakan queued notification. Production wajib menjalankan worker:

```bash
php artisan queue:work --tries=3
```

Konfigurasi mailer berada di `.env` dan `config/mail.php`. Jangan menyimpan credential SMTP di repository.

## Authorization

Akses menggunakan role dan permission. Beberapa permission utama:

- `tickets.view`, `tickets.create`, `tickets.manage`
- `attendance.view`, `attendance.upload`, `attendance.manage`
- permission Meeting Room dan Vehicle Booking

Route legacy `/admin/departments` hanya tersedia untuk superadmin. Resource Filament dan endpoint download tetap wajib melakukan authorization masing-masing.

## Deployment

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
```

Setelah deployment, restart queue worker dan pastikan `storage` serta `bootstrap/cache` dapat ditulis oleh user web server.
