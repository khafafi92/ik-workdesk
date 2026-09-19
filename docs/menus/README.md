# Panduan Detail Per Menu

Folder ini menjelaskan seluruh menu IK WorkDesk. Setiap menu dibahas dari fungsi bisnis, file Page, Form, Table, Model/Service, field, kolom, permission, aturan penting, hingga test. Dengan demikian, pola yang dijelaskan tidak hanya berlaku untuk Service Desk.

## Daftar panduan

| Kelompok | Menu yang dibahas | Panduan |
| --- | --- | --- |
| Menu operasional utama | Dashboard, Reminders, Service Desk, Work Logs, Aktivitas Harian, Laporan Aktivitas | [CORE-MENUS.md](CORE-MENUS.md) |
| Master Data | Departments, Employees, Work Locations, Ticket Categories, Task Categories, Permit Companies, Projects, Activity Categories | [MASTER-DATA-MENUS.md](MASTER-DATA-MENUS.md) |
| Attendance | Report Center, Attendance Imports, Work Hour Imports, Work Hour Records, Attendance Results, Location Reports | [ATTENDANCE-MENUS.md](ATTENDANCE-MENUS.md) |
| Fasilitas | Meeting Room Calendar, Meeting Bookings, Meeting Rooms, Vehicle Calendar, Vehicle Bookings, Vehicles | [FACILITY-MENUS.md](FACILITY-MENUS.md) |
| Administrasi | User Management dan Role Management | [ADMIN-MENUS.md](ADMIN-MENUS.md) |

Walkthrough paling lengkap tersedia untuk [Service Desk](SERVICE-DESK-WALKTHROUGH.md). Dokumen tersebut menunjukkan alur dari URL dan Page hingga Form, Table, Model, CSS, database, dan test.

Untuk membuat sistem atau menu baru dengan pola yang sama, ikuti [Membuat Menu Baru dari Nol](../BUILD-A-MENU-FROM-ZERO.md). Panduan itu dimulai dari rancangan field, Migration, Model, Resource, Page, Form, Table, permission, style, test, sampai checklist deployment.

## Pola yang berlaku untuk seluruh menu

```text
Menu pada sidebar
    -> Resource atau Page
    -> Page List/Create/View/Edit
    -> Schema Form atau Blade
    -> Table atau komponen tampilan
    -> Model dan database
    -> Service untuk aturan bisnis kompleks
    -> Permission untuk keamanan
    -> Test untuk membuktikan perilaku
```

Untuk Resource standar, file Page setiap menu dapat dilihat di [Dari Layar Menu ke File Kode](../UI-TO-CODE-BY-MENU.md#file-page-yang-dibuka-untuk-setiap-menu). Untuk Dashboard dan Calendar yang tidak memakai Resource standar, gunakan bagian [Menu yang tidak memakai Resource standar](../UI-TO-CODE-BY-MENU.md#menu-yang-tidak-memakai-resource-standar).

## Cara memakai panduan

Contoh: Anda ingin mengubah pilihan Status di Service Desk.

1. Buka [CORE-MENUS.md](CORE-MENUS.md).
2. Cari bagian `Service Desk`.
3. Lihat tabel `Lokasi kode`.
4. Untuk field form, buka `Tickets/Schemas/TicketForm.php`.
5. Cari `Select::make('status')`.
6. Periksa aturan status pada `app/Models/Ticket.php` sebelum mengubah pilihan.
7. Jalankan test Ticket dan seluruh test suite.

Jika perubahan membutuhkan field database baru, ikuti juga bagian penambahan field pada [Panduan Developer Pemula](../BEGINNER-DEVELOPER-GUIDE.md).
