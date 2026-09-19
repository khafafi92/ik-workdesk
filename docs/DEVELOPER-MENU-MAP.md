# Peta Kode Menu IK WorkDesk

Dokumen ini adalah titik awal ketika ingin mengubah menu di Filament Panel. Cari nama menu pada tabel di bawah, lalu buka file sesuai jenis perubahan.

Jika Anda masih baru mengenal Laravel atau Filament, baca dahulu [Panduan Developer Pemula](BEGINNER-DEVELOPER-GUIDE.md). Panduan tersebut menjelaskan istilah, contoh perubahan, test, Git, dan deployment langkah demi langkah.

Untuk daftar field, kolom, action, permission, aturan sensitif, dan test pada masing-masing menu, lihat [Panduan Detail Per Menu](menus/README.md).

## Aturan cepat

Setiap menu Filament mengikuti struktur berikut:

```text
app/Filament/Resources/NamaMenu/
├── NamaMenuResource.php       # registrasi menu, akses, query scope, label, dan routing
├── Schemas/NamaMenuForm.php   # field form, label, deskripsi, validasi, disabled, dan visibility
├── Tables/NamaMenusTable.php  # kolom list, filter, sorting, badge, dan action tabel
├── Pages/                     # proses create, edit, view, dan custom action halaman
├── RelationManagers/          # tabel/detail data anak di halaman record
└── Widgets/                   # widget khusus resource bila tersedia
```

Gunakan pedoman ini:

| Ingin mengubah | Lokasi utama |
| --- | --- |
| Tambah/edit field, label, helper text, atau deskripsi form | `Schemas/*Form.php` |
| Tambah/edit kolom, filter, badge, atau tombol pada daftar | `Tables/*Table.php` |
| Ubah nama menu, icon, group sidebar, scope data, atau permission | `*Resource.php` |
| Ubah proses sebelum/sesudah create atau edit | `Pages/Create*.php` atau `Pages/Edit*.php` |
| Ubah tombol/action pada halaman detail | `Pages/View*.php` |
| Ubah aturan bisnis, relasi, cast, atau otomatisasi status | `app/Models/*.php` atau `app/Services/*.php` |
| Tambah kolom database | buat migration baru di `database/migrations` |
| Ubah notifikasi otomatis | `app/Observers` dan `app/Notifications` |
| Ubah tampilan custom Livewire | `app/Livewire` dan `resources/views/livewire` |
| Tambah regression test | `tests/Feature` |

> Jangan mengedit migration lama yang sudah pernah dijalankan di live. Selalu buat migration baru agar deployment dapat dilacak dan diulang dengan aman.

## Contoh lengkap: Service Desk

Untuk mengubah Service Desk, mulai dari folder:

```text
app/Filament/Resources/Tickets/
```

| Perubahan Service Desk | File |
| --- | --- |
| Field, label, deskripsi, upload, Priority, Status, Reported At, Due At | `Schemas/TicketForm.php` |
| Kolom daftar Service Desk, filter, badge, dan row action | `Tables/TicketsTable.php` |
| Akses menu, data yang boleh dilihat, label sidebar, dan badge jumlah | `TicketResource.php` |
| Proses pembuatan request dan Work Log otomatis | `Pages/CreateTicket.php` |
| Validasi/perubahan saat edit | `Pages/EditTicket.php` |
| Tombol dan susunan halaman detail | `Pages/ViewTicket.php` |
| Comments dan Findings | `RelationManagers/CommentsRelationManager.php` dan `RelationManagers/FindingsRelationManager.php` |
| Aturan status collaborative, resolved, dan relasi data | `app/Models/Ticket.php` |
| Collaboration Room | `app/Livewire/TicketCollaborationRoom.php` |
| Tampilan Post to group dan timeline | `resources/views/livewire/ticket-collaboration-room.blade.php` |
| Struktur tabel database | migration bernama `*tickets*`, `*ticket_assignments*`, dan `*ticket_comments*` |
| Test utama | `tests/Feature/LegalTaskApprovalTest.php`, `PendingLegalCollaborationLockTest.php`, dan test Ticket lainnya |

## Peta seluruh menu panel

Semua path resource di bawah relatif terhadap `app/Filament/Resources`.

### Menu utama

| Menu | Resource / halaman | Form | Tabel | Model / logic khusus |
| --- | --- | --- | --- | --- |
| Dashboard | `app/Filament/Pages/Dashboard.php` | — | — | View: `resources/views/filament/pages/dashboard.blade.php` |
| Reminders | `Reminders/ReminderResource.php` | `Reminders/Schemas/ReminderForm.php` | `Reminders/Tables/RemindersTable.php` | `app/Models/Reminder.php`; kalender di `Reminders/Widgets/ReminderCalendarWidget.php` |
| Service Desk | `Tickets/TicketResource.php` | `Tickets/Schemas/TicketForm.php` | `Tickets/Tables/TicketsTable.php` | `app/Models/Ticket.php`; Collaboration Room dijelaskan di atas |
| Work Logs | `WorkTasks/WorkTaskResource.php` | `WorkTasks/Schemas/WorkTaskForm.php` | `WorkTasks/Tables/WorkTasksTable.php` | `app/Models/WorkTask.php`; guard mutation di `app/Services/WorkTaskMutationGuard.php` |
| Aktivitas Harian | `DailyActivities/DailyActivityResource.php` | `DailyActivities/Schemas/DailyActivityForm.php` | `DailyActivities/Tables/DailyActivitiesTable.php` | `app/Models/DailyActivity.php` |
| Laporan Aktivitas | `ActivityReports/ActivityReportResource.php` | read-only | `ActivityReports/Tables/ActivityReportsTable.php` | memakai `app/Models/DailyActivity.php` |

### Master Data dan administrasi

| Menu | Resource | Form | Tabel | Model / catatan |
| --- | --- | --- | --- | --- |
| Departments | `Departments/DepartmentResource.php` | `Departments/Schemas/DepartmentForm.php` | `Departments/Tables/DepartmentsTable.php` | `app/Models/Department.php` |
| Employees | `Employees/EmployeeResource.php` | `Employees/Schemas/EmployeeForm.php` | `Employees/Tables/EmployeesTable.php` | `app/Models/Employee.php` |
| Work Locations | `WorkLocations/WorkLocationResource.php` | `WorkLocations/Schemas/WorkLocationForm.php` | `WorkLocations/Tables/WorkLocationsTable.php` | `app/Models/WorkLocation.php` |
| Ticket Categories | `TicketCategories/TicketCategoryResource.php` | `TicketCategories/Schemas/TicketCategoryForm.php` | `TicketCategories/Tables/TicketCategoriesTable.php` | `app/Models/TicketCategory.php` |
| Task Categories | `TaskCategories/TaskCategoryResource.php` | `TaskCategories/Schemas/TaskCategoryForm.php` | `TaskCategories/Tables/TaskCategoriesTable.php` | `app/Models/TaskCategory.php` |
| Permit Companies | `PermitCompanies/PermitCompanyResource.php` | `PermitCompanies/Schemas/PermitCompanyForm.php` | `PermitCompanies/Tables/PermitCompaniesTable.php` | `app/Models/PermitCompany.php`; KBLI di `RelationManagers/KblisRelationManager.php` |
| Projects | `WorkProjects/WorkProjectResource.php` | `WorkProjects/Schemas/WorkProjectForm.php` | `WorkProjects/Tables/WorkProjectsTable.php` | `app/Models/WorkProject.php` |
| Activity Categories | `ActivityCategories/ActivityCategoryResource.php` | `ActivityCategories/Schemas/ActivityCategoryForm.php` | `ActivityCategories/Tables/ActivityCategoriesTable.php` | `app/Models/ActivityCategory.php` |
| User Management | `Users/UserResource.php` | `Users/Schemas/UserForm.php` | `Users/Tables/UsersTable.php` | `app/Models/User.php`; akses tambahan di `app/Services/UserAdditionalAccessService.php` |
| Role Management | `Roles/RoleResource.php` | `Roles/Schemas/RoleForm.php` | `Roles/Tables/RolesTable.php` | `app/Models/Role.php`; saat ini disembunyikan dari navigation |

### Attendance dan laporan lokasi

| Menu | Resource / halaman | Form | Tabel | Model / service |
| --- | --- | --- | --- | --- |
| Attendance Imports | `AttendanceImports/AttendanceImportResource.php` | `AttendanceImports/Schemas/AttendanceImportForm.php` | `AttendanceImports/Tables/AttendanceImportsTable.php` | `app/Models/AttendanceImport.php`; processor di `app/Services/AttendanceReportProcessor.php` |
| Work Hour Imports | `WorkHourImports/WorkHourImportResource.php` | `WorkHourImports/Schemas/WorkHourImportForm.php` | `WorkHourImports/Tables/WorkHourImportsTable.php` | memakai `app/Models/AttendanceImport.php` |
| Work Hour Records | `WorkHourRecords/WorkHourRecordResource.php` | read-only | `WorkHourRecords/Tables/WorkHourRecordsTable.php` | `app/Models/WorkHourRecord.php`; support resource, tidak tampil sebagai menu utama |
| Attendance Results | `AttendanceResults/AttendanceResultResource.php` | `AttendanceResults/Schemas/AttendanceResultForm.php` | `AttendanceResults/Tables/AttendanceResultsTable.php` | `app/Models/AttendanceResult.php`; support resource |
| Attendance Report Center | `app/Filament/Pages/AttendanceReportCenter.php` | selector periode ada di page | link report ada di page | view di `resources/views/filament/pages/attendance-report-center.blade.php` |
| Location Reports | `LocationReports/LocationReportResource.php` | `LocationReports/Schemas/LocationReportForm.php` | `LocationReports/Tables/LocationReportsTable.php` | memakai `app/Models/AttendanceImport.php` |

### Meeting Room dan Vehicle

| Menu | Resource / halaman | Form | Tabel | Model |
| --- | --- | --- | --- | --- |
| Meeting Room Calendar | `app/Filament/Pages/MeetingRoomCalendar.php` | logic kalender di page | — | `app/Models/MeetingBooking.php` |
| Meeting Room Bookings | `MeetingBookings/MeetingBookingResource.php` | `MeetingBookings/Schemas/MeetingBookingForm.php` | `MeetingBookings/Tables/MeetingBookingsTable.php` | `app/Models/MeetingBooking.php` |
| Meeting Rooms | `MeetingRooms/MeetingRoomResource.php` | `MeetingRooms/Schemas/MeetingRoomForm.php` | `MeetingRooms/Tables/MeetingRoomsTable.php` | `app/Models/MeetingRoom.php` |
| Vehicle Calendar | `app/Filament/Pages/VehicleBookingCalendar.php` | logic kalender di page | — | `app/Models/VehicleBooking.php` |
| Vehicle Bookings | `VehicleBookings/VehicleBookingResource.php` | `VehicleBookings/Schemas/VehicleBookingForm.php` | `VehicleBookings/Tables/VehicleBookingsTable.php` | `app/Models/VehicleBooking.php` |
| Vehicles | `Vehicles/VehicleResource.php` | `Vehicles/Schemas/VehicleForm.php` | `Vehicles/Tables/VehiclesTable.php` | `app/Models/Vehicle.php` |

## File lintas menu

| Area | Lokasi |
| --- | --- |
| Registrasi seluruh resource/page Filament | `app/Providers/Filament/AdminPanelProvider.php` |
| Warna, layout, dan style panel | `public/css/filament/admin/workdesk-theme.css` dan `workdesk-ui-polish.css` |
| Global search | `app/Filament/GlobalSearch/ContextualGlobalSearchProvider.php` |
| Role dan permission awal | `database/seeders/AccessControlSeeder.php` |
| Middleware login panel | `app/Http/Middleware/AuthenticateFilament.php` |
| Route aplikasi non-Filament | `routes/web.php` |
| Observer otomatis | `app/Observers` |
| Notification | `app/Notifications` |
| Test perilaku sistem | `tests/Feature` |

## Checklist aman saat mengubah menu

1. Ubah tampilan di `Schemas` atau `Tables`; jangan menaruh field/kolom baru langsung di Resource.
2. Jika field membutuhkan penyimpanan baru, buat migration dan tambahkan field ke model.
3. Letakkan aturan bisnis penting di model/service, bukan hanya `disabled()` pada UI.
4. Tambahkan test untuk permission dan perubahan status yang sensitif.
5. Jalankan:

   ```bash
   vendor/bin/pint --test
   php artisan test
   npm run build
   ```

6. Saat deployment, pastikan `storage` dan `bootstrap/cache` dapat ditulis oleh user web server. Direktori compiled view harus mempertahankan group `www-data` agar Filament tidak menghasilkan error 500.

## Template menu baru

Gunakan struktur ini agar menu baru tetap konsisten:

```text
app/Filament/Resources/Examples/
├── ExampleResource.php
├── Schemas/ExampleForm.php
├── Tables/ExamplesTable.php
└── Pages/
    ├── CreateExample.php
    ├── EditExample.php
    └── ListExamples.php
```

`ExampleResource.php` hanya menghubungkan komponen:

```php
public static function form(Schema $schema): Schema
{
    return ExampleForm::configure($schema);
}

public static function table(Table $table): Table
{
    return ExamplesTable::configure($table);
}
```

Dengan pola ini, developer dapat langsung mengetahui lokasi perubahan tanpa membaca satu file Resource yang terlalu panjang.
