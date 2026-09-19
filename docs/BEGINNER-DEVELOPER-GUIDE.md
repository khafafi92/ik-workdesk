# Panduan Developer Pemula IK WorkDesk

Panduan ini berlaku untuk seluruh menu. Setelah memahami istilah dan contoh di dokumen ini, gunakan [Dari Layar Menu ke File Kode](UI-TO-CODE-BY-MENU.md) untuk menemukan file pada setiap menu. Untuk praktik membuat modul lengkap, ikuti [Membuat Menu Baru dari Nol](BUILD-A-MENU-FROM-ZERO.md).

Panduan ini ditulis untuk orang yang baru mengenal Laravel, Filament, atau struktur IK WorkDesk. Ikuti dari atas ke bawah saat pertama kali melakukan perubahan.

Jika sudah memahami struktur proyek dan hanya perlu mencari lokasi file, gunakan [Peta Kode Menu](DEVELOPER-MENU-MAP.md).

Untuk penjelasan khusus setiap menu, buka [Panduan Detail Per Menu](menus/README.md).

Untuk memahami alur dari layar browser ke file kode, baca [Dari Layar Menu ke File Kode](UI-TO-CODE-BY-MENU.md). Contoh paling lengkap tersedia pada [Walkthrough Service Desk](menus/SERVICE-DESK-WALKTHROUGH.md).

## 1. Gambaran sederhana cara kerja aplikasi

Saat user membuka sebuah menu, data melewati beberapa lapisan:

```text
Browser user
    |
    v
Filament Resource / Page
    |
    +--> Schema Form  : field yang dilihat dan diisi user
    |
    +--> Table        : kolom daftar, filter, badge, dan tombol
    |
    +--> Page         : proses create, edit, view, dan custom action
    |
    v
Model / Service      : aturan bisnis dan hubungan antar-data
    |
    v
Database             : penyimpanan data permanen
```

Contoh pada Service Desk:

```text
Menu Service Desk
    |
    +--> TicketResource.php       : akses dan registrasi menu
    +--> TicketForm.php           : Subject, Priority, Status, Due At, dll.
    +--> TicketsTable.php         : daftar request
    +--> CreateTicket.php         : proses membuat request
    +--> EditTicket.php           : proses menyimpan perubahan
    +--> ViewTicket.php           : halaman detail dan action
    +--> Ticket.php               : aturan status dan relasi data
    +--> tabel tickets             : data di database
```

## 2. Istilah yang perlu dikenal

### Resource

Resource adalah pintu masuk sebuah menu Filament.

Biasanya berisi:

- model yang digunakan;
- nama dan icon menu;
- group sidebar;
- siapa yang boleh melihat, membuat, mengedit, atau menghapus;
- query pembatasan data;
- hubungan ke Form, Table, dan Pages.

Contoh:

```text
app/Filament/Resources/Tickets/TicketResource.php
```

Jangan menaruh seluruh field dan kolom tabel di Resource. Resource sebaiknya hanya menjadi penghubung.

### Schema Form

Schema Form menentukan field yang muncul pada halaman create, edit, atau view.

Di sinilah developer mengubah:

- label field;
- helper text atau deskripsi;
- pilihan Select;
- validasi `required`, `maxLength`, dan lain-lain;
- kondisi `disabled`, `visible`, atau `hidden`;
- susunan Section dan jumlah kolom layout.

Contoh:

```text
app/Filament/Resources/Tickets/Schemas/TicketForm.php
```

### Table

Table menentukan tampilan daftar record.

Di sinilah developer mengubah:

- kolom yang tampil;
- label kolom;
- pencarian dan sorting;
- badge atau warna status;
- filter;
- tombol View, Edit, Delete, atau action khusus.

Contoh:

```text
app/Filament/Resources/Tickets/Tables/TicketsTable.php
```

### Page

Page menangani satu halaman dan siklus penyimpanannya.

Contoh kegunaan:

- mengubah data sebelum disimpan;
- membuat data turunan setelah request dibuat;
- menambah tombol pada header halaman;
- melakukan approval, reject, claim, atau action lain.

Contoh:

```text
app/Filament/Resources/Tickets/Pages/CreateTicket.php
app/Filament/Resources/Tickets/Pages/EditTicket.php
app/Filament/Resources/Tickets/Pages/ViewTicket.php
```

### Model

Model mewakili tabel database dan aturan bisnis data.

Model biasanya berisi:

- daftar field yang boleh disimpan;
- tipe data tanggal, boolean, atau array;
- relasi ke model lain;
- aturan otomatis saat create, save, update, atau delete;
- fungsi pengecekan permission yang terkait record;
- fungsi perubahan status.

Contoh:

```text
app/Models/Ticket.php
app/Models/WorkTask.php
```

### Service

Service digunakan untuk proses bisnis yang cukup besar atau dipakai lebih dari satu tempat.

Contoh:

```text
app/Services/WorkTaskMutationGuard.php
app/Services/AttendanceReportProcessor.php
app/Services/MeetingBookingService.php
```

### Migration

Migration adalah catatan perubahan struktur database.

Migration digunakan untuk:

- menambah kolom;
- mengubah tipe kolom;
- membuat tabel;
- menambah index atau foreign key.

Lokasi:

```text
database/migrations
```

Migration lama yang sudah berjalan di live tidak boleh diedit. Buat migration baru.

### Seeder

Seeder mengisi atau memperbarui data awal, misalnya permission dan role.

Contoh:

```text
database/seeders/AccessControlSeeder.php
```

### Test

Test memastikan perubahan tidak merusak fungsi lama.

Lokasi utama:

```text
tests/Feature
```

## 3. Cara menentukan file yang harus dibuka

Gunakan tabel keputusan berikut.

| Permintaan | File pertama yang dibuka |
| --- | --- |
| Ubah tulisan atau deskripsi field | `Schemas/*Form.php` |
| Tambah field pada form | migration, model, lalu `Schemas/*Form.php` |
| Tambah kolom pada daftar | `Tables/*Table.php` |
| Ubah warna badge status | `Tables/*Table.php` |
| Ubah nama/icon/group menu | `*Resource.php` |
| Ubah siapa yang boleh melihat menu | `*Resource.php` |
| Ubah data mana yang boleh dilihat user | `getEloquentQuery()` di `*Resource.php` |
| Ubah proses ketika Save ditekan | `Pages/Create*.php` atau `Pages/Edit*.php` |
| Ubah status otomatis | model atau service |
| Ubah tombol halaman detail | `Pages/View*.php` |
| Ubah tampilan custom di bawah form | `app/Livewire` dan `resources/views/livewire` |
| Ubah struktur database | migration baru |
| Ubah role/permission bawaan | `database/seeders/AccessControlSeeder.php` |
| Ubah email/notifikasi | `app/Notifications` dan `app/Observers` |

## 4. Cara mencari file dengan cepat

Jalankan perintah dari root proyek:

```powershell
cd D:\Internal\ik-workdesk
```

Mencari semua file yang menyebut `Due At`:

```powershell
rg -n "Due At|due_at" app tests database
```

Mencari field Status:

```powershell
rg -n "Select::make\('status'\)|status" app\Filament\Resources
```

Mencari permission tertentu:

```powershell
rg -n "worklogs.manage" app database tests
```

Mencari nama menu:

```powershell
rg -n "Service Desk|Work Logs|Aktivitas Harian" app\Filament
```

`rg` hanya membaca file. Perintah ini aman digunakan untuk pencarian.

## 5. Contoh paling sederhana: mengubah label atau deskripsi

Contoh permintaan:

> Ubah label `Due At` menjadi `Target Selesai` dan berikan keterangan.

Langkah:

1. Buka:

   ```text
   app/Filament/Resources/Tickets/Schemas/TicketForm.php
   ```

2. Cari:

   ```php
   DateTimePicker::make('due_at')
       ->label('Due At');
   ```

3. Ubah menjadi:

   ```php
   DateTimePicker::make('due_at')
       ->label('Target Selesai')
       ->helperText('Tanggal target penyelesaian request. Bukan tanggal aktual selesai.');
   ```

4. Jalankan formatter dan test.

Perubahan ini hanya mengubah tampilan. Database tidak perlu diubah karena field `due_at` sudah tersedia.

## 6. Contoh menambah kolom pada daftar

Contoh permintaan:

> Tampilkan `Resolved At` pada daftar Service Desk.

Langkah:

1. Pastikan field sudah ada di model dan database. Cari:

   ```powershell
   rg -n "resolved_at" app\Models database\migrations
   ```

2. Buka:

   ```text
   app/Filament/Resources/Tickets/Tables/TicketsTable.php
   ```

3. Tambahkan kolom di dalam `->columns([...])`:

   ```php
   TextColumn::make('resolved_at')
       ->label('Resolved At')
       ->dateTime('d M Y H:i')
       ->placeholder('-')
       ->sortable();
   ```

4. Tambahkan test bila kolom memiliki aturan visibility khusus.

Karena `resolved_at` sudah ada, tidak perlu migration baru.

## 7. Contoh menambah field database baru dari awal

Contoh permintaan:

> Tambahkan field `Customer Reference` pada Service Desk.

Perubahan ini menyentuh beberapa lapisan.

### Langkah 1: buat migration

```powershell
php artisan make:migration add_customer_reference_to_tickets_table
```

Isi method `up()` pada migration baru:

```php
Schema::table('tickets', function (Blueprint $table): void {
    $table->string('customer_reference')->nullable()->after('subject');
});
```

Isi method `down()`:

```php
Schema::table('tickets', function (Blueprint $table): void {
    $table->dropColumn('customer_reference');
});
```

### Langkah 2: daftarkan pada model

Buka:

```text
app/Models/Ticket.php
```

Tambahkan `customer_reference` ke daftar field yang boleh disimpan apabila model memakai `$fillable` atau atribut Fillable.

### Langkah 3: tambahkan ke form

Buka:

```text
app/Filament/Resources/Tickets/Schemas/TicketForm.php
```

Tambahkan:

```php
TextInput::make('customer_reference')
    ->label('Customer Reference')
    ->maxLength(255)
    ->helperText('Nomor referensi dari pihak pemohon jika tersedia.');
```

### Langkah 4: tambahkan ke tabel bila diperlukan

Buka:

```text
app/Filament/Resources/Tickets/Tables/TicketsTable.php
```

Tambahkan:

```php
TextColumn::make('customer_reference')
    ->label('Customer Reference')
    ->placeholder('-')
    ->searchable()
    ->toggleable();
```

### Langkah 5: tambahkan test

Buat atau perbarui test di `tests/Feature` untuk memastikan nilai dapat disimpan dan ditampilkan sesuai permission.

### Langkah 6: jalankan migration lokal dan test

```powershell
php artisan migrate
php artisan test
```

Migration live hanya dijalankan pada saat deployment dengan:

```bash
php artisan migrate --force
```

## 8. Contoh mengubah aturan permission

Contoh permintaan:

> User boleh melihat Work Log, tetapi hanya PIC yang boleh menjalankan pekerjaan.

Jangan hanya membuat field abu-abu di form. UI dapat dilewati melalui request langsung.

Perubahan yang aman memiliki dua lapisan:

1. Tampilan:

   ```text
   app/Filament/Resources/WorkTasks/Schemas/WorkTaskForm.php
   ```

   Gunakan `->disabled(...)` agar user mendapat petunjuk visual.

2. Backend:

   ```text
   app/Models/WorkTask.php
   app/Services/WorkTaskMutationGuard.php
   ```

   Tolak perubahan jika actor bukan user yang berwenang.

3. Test:

   ```text
   tests/Feature/AssignedPicExecutionTest.php
   tests/Feature/WorkLogAuthorizationTest.php
   ```

Prinsip penting:

> `disabled()` memperbaiki pengalaman user, tetapi model/service/test yang menjaga keamanan data.

## 9. Contoh mengubah status otomatis

Contoh permintaan:

> Ketika seluruh Work Log selesai, Service Desk menjadi Resolved.

Lokasi utama:

```text
app/Models/Ticket.php
app/Models/WorkTask.php
```

Jangan mengubah label `Resolved` di form lalu menganggap proses bisnis sudah berubah. Cari method yang melakukan update status, misalnya:

```powershell
rg -n "syncCollaborativeStatus|resolved_at|status.*resolved" app\Models tests
```

Setelah mengubah aturan status:

- uji single department;
- uji collaborative;
- uji task pending/rejected;
- uji user yang tidak berwenang;
- pastikan timestamp seperti `resolved_at` atau `completed_at` benar.

## 10. Contoh mengubah Collaboration Room

Collaboration Room tidak hanya menggunakan Filament Schema. Komponennya terpisah:

```text
app/Livewire/TicketCollaborationRoom.php
resources/views/livewire/ticket-collaboration-room.blade.php
```

Pembagian tanggung jawab:

| Perubahan | File |
| --- | --- |
| Aturan siapa yang boleh posting | `TicketCollaborationRoom.php` |
| Validasi pesan dan attachment | `TicketCollaborationRoom.php` |
| Tampilan textarea, tombol, timeline, dan kondisi abu-abu | Blade view |
| Style Collaboration Room | `public/css/filament/admin/workdesk-ui-polish.css` |
| Test approval dan PIC | `tests/Feature/PendingLegalCollaborationLockTest.php` |

Aturan backend dan tampilan harus selalu diperbarui bersama.

## 11. Peta cepat Service Desk dan Work Logs

### Service Desk

```text
app/Filament/Resources/Tickets/
|- TicketResource.php
|- Schemas/TicketForm.php
|- Tables/TicketsTable.php
|- Pages/CreateTicket.php
|- Pages/EditTicket.php
|- Pages/ViewTicket.php
|- RelationManagers/CommentsRelationManager.php
`- RelationManagers/FindingsRelationManager.php

app/Models/Ticket.php
app/Models/TicketAssignment.php
app/Models/TicketComment.php
app/Livewire/TicketCollaborationRoom.php
resources/views/livewire/ticket-collaboration-room.blade.php
```

### Work Logs

```text
app/Filament/Resources/WorkTasks/
|- WorkTaskResource.php
|- Schemas/WorkTaskForm.php
|- Tables/WorkTasksTable.php
|- Pages/CreateWorkTask.php
|- Pages/EditWorkTask.php
|- Pages/ViewWorkTask.php
|- RelationManagers/ActivityHistoryRelationManager.php
`- RelationManagers/FindingsRelationManager.php

app/Models/WorkTask.php
app/Models/WorkTaskFinding.php
app/Services/WorkTaskMutationGuard.php
```

Untuk menu lain, lihat tabel lengkap di [Peta Kode Menu](DEVELOPER-MENU-MAP.md).

## 12. Urutan kerja yang aman

Ikuti urutan ini untuk setiap task:

1. Pastikan berada di branch yang benar.
2. Jalankan `git status --short`.
3. Cari menu dan field menggunakan `rg`.
4. Baca Resource, Form/Table, Model, dan test yang terkait.
5. Buat perubahan sekecil mungkin.
6. Jangan mengubah file lain yang tidak berhubungan.
7. Jalankan formatter.
8. Jalankan test terkait.
9. Jalankan seluruh test.
10. Periksa diff.
11. Commit hanya file yang terkait.
12. Push dan deploy setelah mendapat persetujuan.

Perintah yang umum:

```powershell
git status --short
git diff --check
php vendor\bin\pint --test
php artisan test
git diff
```

Jika ada perubahan frontend yang dibundel melalui Vite:

```powershell
npm run build
```

## 13. Cara membaca hasil Git

Melihat file yang berubah:

```powershell
git status --short
```

Arti tanda umum:

| Tanda | Arti |
| --- | --- |
| `M` | file sudah dimodifikasi |
| `??` | file baru dan belum dilacak Git |
| `A` | file baru sudah masuk staging |
| `D` | file dihapus |

Melihat isi perubahan:

```powershell
git diff
```

Menambahkan hanya file yang memang terkait:

```powershell
git add -- path\ke\file1.php path\ke\file2.php
```

Jangan menggunakan `git add .` jika workspace memiliki file pribadi, screenshot, log, atau perubahan user lain.

Commit:

```powershell
git commit -m "feat: add customer reference to service desk"
```

Push:

```powershell
git push origin main
```

## 14. Test minimum berdasarkan jenis perubahan

| Jenis perubahan | Test minimum |
| --- | --- |
| Hanya dokumentasi | periksa link/path dan `git diff --check` |
| Label/helper text/form | test resource terkait dan seluruh PHPUnit |
| Kolom/filter tabel | render resource dan seluruh PHPUnit |
| Permission | test actor yang boleh dan tidak boleh |
| Status workflow | test seluruh cabang status |
| Migration | migrate fresh pada database test dan seluruh PHPUnit |
| Upload/attachment | test validasi tipe file, storage, download, dan authorization |
| Notification | test penerima, kondisi terkirim, dan kondisi tidak terkirim |
| Frontend asset | `npm run build` dan visual check |

Command standar sebelum handoff:

```powershell
php vendor\bin\pint --test
php artisan test
git diff --check
```

## 15. Hal yang tidak boleh dilakukan

- Jangan menyimpan password, token, atau credential ke Git.
- Jangan mengedit `.env` untuk kebutuhan permanen aplikasi.
- Jangan mengedit migration lama yang sudah berjalan di live.
- Jangan mengandalkan field `disabled()` sebagai satu-satunya keamanan.
- Jangan menghapus atau menimpa perubahan user yang tidak terkait.
- Jangan menggunakan `git reset --hard` untuk merapikan workspace.
- Jangan mengubah data live hanya untuk menguji tampilan.
- Jangan deploy sebelum test lulus.
- Jangan menjalankan cache production tanpa memastikan permission `storage` dan `bootstrap/cache` benar.

## 16. Checklist penambahan menu baru

Jika membuat menu baru, siapkan:

```text
app/Models/Example.php
app/Filament/Resources/Examples/ExampleResource.php
app/Filament/Resources/Examples/Schemas/ExampleForm.php
app/Filament/Resources/Examples/Tables/ExamplesTable.php
app/Filament/Resources/Examples/Pages/ListExamples.php
app/Filament/Resources/Examples/Pages/CreateExample.php
app/Filament/Resources/Examples/Pages/EditExample.php
database/migrations/..._create_examples_table.php
tests/Feature/ExampleAuthorizationTest.php
```

Kemudian periksa:

- nama dan icon menu;
- navigation group dan sort;
- permission view/create/edit/delete;
- query scope department;
- validation form;
- kolom dan filter tabel;
- model fillable/casts/relationships;
- migration dan index;
- test user berwenang dan tidak berwenang;
- dokumentasi menu pada Peta Kode Menu.

## 17. Deployment aman

Urutan umum deployment:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
```

Sebelum cache dibangun, pastikan user deployment dan user web server dapat menulis cache:

```bash
chgrp -R www-data storage bootstrap/cache
chmod -R g+rwX storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod g+s {} +
```

Sesudah deployment, periksa:

- halaman login HTTP 200;
- panel user terautentikasi HTTP 200;
- halaman menu yang diubah;
- log production;
- queue;
- maintenance mode OFF.

## 18. Jika terjadi error 500

Jangan menebak dari layar. Periksa log:

```bash
tail -n 200 storage/logs/laravel.log
```

Periksa environment:

```bash
php artisan about --only=environment
```

Periksa permission:

```bash
stat -c '%U:%G %a %n' storage storage/logs storage/framework/views bootstrap/cache
```

Periksa syntax file PHP yang baru diubah:

```bash
php -l app/Path/To/ChangedFile.php
```

Pulihkan dari backup bila perubahan membuat panel tidak dapat digunakan. Setelah live pulih, diagnosis dilakukan pada versi lokal sebelum deploy ulang.

## 19. Kapan harus meminta bantuan

Minta review developer lain jika perubahan menyentuh:

- permission atau data lintas department;
- approval Legal;
- penyelesaian collaborative request;
- upload/download file private;
- migrasi atau penghapusan data;
- email/notification ke banyak user;
- autentikasi dan session;
- deployment production.

Saat meminta bantuan, sertakan:

- menu dan URL;
- user/role yang mengalami masalah;
- hasil yang diharapkan;
- hasil aktual;
- screenshot;
- nomor ticket atau Work Log;
- potongan log tanpa credential.

Dengan langkah ini, developer pemula dapat melakukan perubahan kecil dengan aman dan mengetahui kapan perubahan membutuhkan pemeriksaan lebih lanjut.
