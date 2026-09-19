# Dari Layar Menu ke File Kode

Panduan ini menjawab pertanyaan: “Saya sedang melihat bagian ini di layar, file mana yang harus dibuka?”

## Pola halaman Filament

Untuk menu standar, alurnya selalu seperti ini:

```text
Halaman daftar
    -> Pages/List*.php
    -> Resource::table()
    -> Tables/*Table.php

Halaman create
    -> Pages/Create*.php
    -> Resource::form()
    -> Schemas/*Form.php

Halaman edit
    -> Pages/Edit*.php
    -> Resource::form()
    -> Schemas/*Form.php

Function/aturan
    -> Page hook
    -> Model
    -> Service

Style custom
    -> Blade khusus bila ada
    -> public/css/filament/admin/workdesk-ui-polish.css
```

`List*.php` biasanya hanya mengatur tombol halaman. Kolom hampir selalu berada di `Tables`.

`Create*.php` dan `Edit*.php` mengatur proses Save. Field hampir selalu berada di `Schemas`.

## Peta semua menu

Semua path Resource di bawah berada di `app/Filament/Resources`.

| Menu di layar | Tampilan form/deskripsi | Tampilan daftar | Function/aturan | Style khusus |
| --- | --- | --- | --- | --- |
| Service Desk | `Tickets/Schemas/TicketForm.php` | `Tickets/Tables/TicketsTable.php` | `Tickets/Pages`, `Ticket.php`, `WorkTask.php` | Collaboration Blade + `.ik-collab-*` CSS |
| Work Logs | `WorkTasks/Schemas/WorkTaskForm.php` | `WorkTasks/Tables/WorkTasksTable.php` | `WorkTasks/Pages`, `WorkTask.php`, `WorkTaskMutationGuard.php` | CSS panel |
| Reminders | `Reminders/Schemas/ReminderForm.php` | `Reminders/Tables/RemindersTable.php` | `Reminders/Pages`, `Reminder.php`, Notifications | widget + CSS panel |
| Aktivitas Harian | `DailyActivities/Schemas/DailyActivityForm.php` | `DailyActivities/Tables/DailyActivitiesTable.php` | Pages, `DailyActivity.php`, `DailyActivityDataService.php` | CSS panel |
| Laporan Aktivitas | tidak ada form | `ActivityReports/Tables/ActivityReportsTable.php` | `ActivityReportResource.php` query scope | CSS panel |
| Departments | `Departments/Schemas/DepartmentForm.php` | `Departments/Tables/DepartmentsTable.php` | `Department.php` | CSS panel |
| Employees | `Employees/Schemas/EmployeeForm.php` | `Employees/Tables/EmployeesTable.php` | `Employee.php`, `User.php` | CSS panel |
| Work Locations | `WorkLocations/Schemas/WorkLocationForm.php` | `WorkLocations/Tables/WorkLocationsTable.php` | `WorkLocation.php`, Attendance processor | CSS panel |
| Ticket Categories | `TicketCategories/Schemas/TicketCategoryForm.php` | `TicketCategories/Tables/TicketCategoriesTable.php` | `TicketCategory.php`, CreateTicket | CSS panel |
| Task Categories | `TaskCategories/Schemas/TaskCategoryForm.php` | `TaskCategories/Tables/TaskCategoriesTable.php` | `TaskCategory.php` | CSS panel |
| Permit Companies | `PermitCompanies/Schemas/PermitCompanyForm.php` | `PermitCompanies/Tables/PermitCompaniesTable.php` | Company/KBLI models + RelationManager | CSS panel |
| Projects | `WorkProjects/Schemas/WorkProjectForm.php` | `WorkProjects/Tables/WorkProjectsTable.php` | `WorkProject.php` | CSS panel |
| Activity Categories | `ActivityCategories/Schemas/ActivityCategoryForm.php` | `ActivityCategories/Tables/ActivityCategoriesTable.php` | `ActivityCategory.php` | CSS panel |
| Attendance Imports | `AttendanceImports/Schemas/AttendanceImportForm.php` | `AttendanceImports/Tables/AttendanceImportsTable.php` | Pages + `AttendanceReportProcessor.php` | result Page/Blade |
| Work Hour Imports | `WorkHourImports/Schemas/WorkHourImportForm.php` | `WorkHourImports/Tables/WorkHourImportsTable.php` | Pages + Attendance processor | CSS panel |
| Work Hour Records | read-only | `WorkHourRecords/Tables/WorkHourRecordsTable.php` | Resource query + model | CSS panel |
| Attendance Results | `AttendanceResults/Schemas/AttendanceResultForm.php` | `AttendanceResults/Tables/AttendanceResultsTable.php` | model + Attendance processor | result Page/Blade |
| Location Reports | `LocationReports/Schemas/LocationReportForm.php` | `LocationReports/Tables/LocationReportsTable.php` | Pages + Attendance processor | CSS panel |
| Meeting Calendar | Page + `meeting-room-calendar.blade.php` | kalender | `MeetingRoomCalendar.php`, BookingService | calendar Blade/CSS |
| Meeting Bookings | `MeetingBookings/Schemas/MeetingBookingForm.php` | `MeetingBookings/Tables/MeetingBookingsTable.php` | Pages + `MeetingBookingService.php` | CSS panel |
| Meeting Rooms | `MeetingRooms/Schemas/MeetingRoomForm.php` | `MeetingRooms/Tables/MeetingRoomsTable.php` | `MeetingRoom.php` | CSS panel |
| Vehicle Calendar | Page + `vehicle-booking-calendar.blade.php` | kalender | `VehicleBookingCalendar.php`, BookingService | calendar Blade/CSS |
| Vehicle Bookings | `VehicleBookings/Schemas/VehicleBookingForm.php` | `VehicleBookings/Tables/VehicleBookingsTable.php` | Pages + `VehicleBookingService.php` | CSS panel |
| Vehicles | `Vehicles/Schemas/VehicleForm.php` | `Vehicles/Tables/VehiclesTable.php` | `Vehicle.php` | CSS panel |
| User Management | `Users/Schemas/UserForm.php` | `Users/Tables/UsersTable.php` | User Pages, Model, Role/Access services | CSS panel |
| Role Management | `Roles/Schemas/RoleForm.php` | `Roles/Tables/RolesTable.php` | `RoleResource.php`, Role model, Seeder | CSS panel |

## File Page yang dibuka untuk setiap menu

Bagian ini adalah petunjuk langkah demi langkah berdasarkan tombol yang sedang dipakai. Semua path pada tabel berada di dalam folder:

```text
app/Filament/Resources/
```

Cara membacanya:

1. Jika ingin mengubah halaman daftar atau tombol di atas tabel, buka file pada kolom **Daftar**.
2. Jika ingin mengubah proses setelah tombol **Create/Save** ditekan, buka file pada kolom **Create**.
3. Jika ingin mengubah proses setelah tombol **Save changes** ditekan, buka file pada kolom **Edit**.
4. Jika ingin mengubah halaman detail, buka file pada kolom **Detail**.
5. Untuk menambah field, label, bantuan, pilihan dropdown, dan validasi input, tetap buka file `Schemas/*Form.php` pada tabel sebelumnya.
6. Untuk menambah kolom, filter, pencarian, badge, dan action pada tabel, tetap buka file `Tables/*Table.php` pada tabel sebelumnya.

Tanda `-` berarti menu tersebut memang tidak mempunyai halaman itu.

| Menu | Daftar | Create | Edit | Detail/hasil |
| --- | --- | --- | --- | --- |
| Service Desk | `Tickets/Pages/ListTickets.php` | `Tickets/Pages/CreateTicket.php` | `Tickets/Pages/EditTicket.php` | `Tickets/Pages/ViewTicket.php` |
| Work Logs | `WorkTasks/Pages/ListWorkTasks.php` | `WorkTasks/Pages/CreateWorkTask.php` | `WorkTasks/Pages/EditWorkTask.php` | `WorkTasks/Pages/ViewWorkTask.php` |
| Reminders | `Reminders/Pages/ListReminders.php` | `Reminders/Pages/CreateReminder.php` | `Reminders/Pages/EditReminder.php` | `Reminders/Pages/ViewReminder.php` |
| Aktivitas Harian | `DailyActivities/Pages/ListDailyActivities.php` | `DailyActivities/Pages/CreateDailyActivity.php` | `DailyActivities/Pages/EditDailyActivity.php` | - |
| Laporan Aktivitas | `ActivityReports/Pages/ListActivityReports.php` | - | - | - |
| Departments | `Departments/Pages/ListDepartments.php` | `Departments/Pages/CreateDepartment.php` | `Departments/Pages/EditDepartment.php` | - |
| Employees | `Employees/Pages/ListEmployees.php` | `Employees/Pages/CreateEmployee.php` | `Employees/Pages/EditEmployee.php` | - |
| Work Locations | `WorkLocations/Pages/ListWorkLocations.php` | `WorkLocations/Pages/CreateWorkLocation.php` | `WorkLocations/Pages/EditWorkLocation.php` | - |
| Ticket Categories | `TicketCategories/Pages/ListTicketCategories.php` | `TicketCategories/Pages/CreateTicketCategory.php` | `TicketCategories/Pages/EditTicketCategory.php` | - |
| Task Categories | `TaskCategories/Pages/ListTaskCategories.php` | `TaskCategories/Pages/CreateTaskCategory.php` | `TaskCategories/Pages/EditTaskCategory.php` | - |
| Permit Companies | `PermitCompanies/Pages/ListPermitCompanies.php` | `PermitCompanies/Pages/CreatePermitCompany.php` | `PermitCompanies/Pages/EditPermitCompany.php` | - |
| Projects | `WorkProjects/Pages/ListWorkProjects.php` | `WorkProjects/Pages/CreateWorkProject.php` | `WorkProjects/Pages/EditWorkProject.php` | - |
| Activity Categories | `ActivityCategories/Pages/ListActivityCategories.php` | `ActivityCategories/Pages/CreateActivityCategory.php` | `ActivityCategories/Pages/EditActivityCategory.php` | - |
| Attendance Imports | `AttendanceImports/Pages/ListAttendanceImports.php` | `AttendanceImports/Pages/CreateAttendanceImport.php` | `AttendanceImports/Pages/EditAttendanceImport.php` | `AttendanceImports/Pages/ViewAttendanceImportResults.php` |
| Work Hour Imports | `WorkHourImports/Pages/ListWorkHourImports.php` | `WorkHourImports/Pages/CreateWorkHourImport.php` | `WorkHourImports/Pages/EditWorkHourImport.php` | - |
| Work Hour Records | `WorkHourRecords/Pages/ListWorkHourRecords.php` | - | - | - |
| Attendance Results | `AttendanceResults/Pages/ListAttendanceResults.php` | `AttendanceResults/Pages/CreateAttendanceResult.php` | `AttendanceResults/Pages/EditAttendanceResult.php` | - |
| Location Reports | `LocationReports/Pages/ListLocationReports.php` | `LocationReports/Pages/CreateLocationReport.php` | `LocationReports/Pages/EditLocationReport.php` | - |
| Meeting Bookings | `MeetingBookings/Pages/ListMeetingBookings.php` | `MeetingBookings/Pages/CreateMeetingBooking.php` | `MeetingBookings/Pages/EditMeetingBooking.php` | - |
| Meeting Rooms | `MeetingRooms/Pages/ListMeetingRooms.php` | `MeetingRooms/Pages/CreateMeetingRoom.php` | `MeetingRooms/Pages/EditMeetingRoom.php` | - |
| Vehicle Bookings | `VehicleBookings/Pages/ListVehicleBookings.php` | `VehicleBookings/Pages/CreateVehicleBooking.php` | `VehicleBookings/Pages/EditVehicleBooking.php` | - |
| Vehicles | `Vehicles/Pages/ListVehicles.php` | `Vehicles/Pages/CreateVehicle.php` | `Vehicles/Pages/EditVehicle.php` | - |
| User Management | `Users/Pages/ListUsers.php` | `Users/Pages/CreateUser.php` | `Users/Pages/EditUser.php` | - |
| Role Management | `Roles/Pages/ListRoles.php` | `Roles/Pages/CreateRole.php` | `Roles/Pages/EditRole.php` | - |

## Menu yang tidak memakai Resource standar

Empat menu berikut menggunakan satu Page khusus. Karena itu, tampilan dan fungsinya tidak dipisahkan menjadi `Schemas` dan `Tables` seperti menu lain.

| Menu | Pengendali/function | Tampilan HTML | Tempat style |
| --- | --- | --- | --- |
| Dashboard | `app/Filament/Pages/Dashboard.php` | `resources/views/filament/pages/dashboard.blade.php` | Blade tersebut dan CSS panel |
| Report Center | `app/Filament/Pages/AttendanceReportCenter.php` | `resources/views/filament/pages/attendance-report-center.blade.php` | Blade tersebut dan CSS panel |
| Meeting Calendar | `app/Filament/Pages/MeetingRoomCalendar.php` | `resources/views/filament/pages/meeting-room-calendar.blade.php` | Blade tersebut dan CSS panel |
| Vehicle Calendar | `app/Filament/Pages/VehicleBookingCalendar.php` | `resources/views/filament/pages/vehicle-booking-calendar.blade.php` | Blade tersebut dan CSS panel |

Pada menu khusus tersebut, cara kerjanya adalah:

```text
User membuka menu
    -> class di app/Filament/Pages mengisi data dan menangani action
    -> file Blade di resources/views/filament/pages menampilkan HTML
    -> CSS panel mengatur penampilan global
```

## Contoh praktis memilih file yang benar

### Ingin mengganti tulisan label atau deskripsi field

Contoh: tulisan `Due At` di Service Desk ingin diganti.

```text
app/Filament/Resources/Tickets/Schemas/TicketForm.php
```

Cari `due_at`, kemudian ubah `label()`, `helperText()`, `placeholder()`, atau konfigurasi field yang bersangkutan.

### Ingin menambahkan kolom pada daftar

Contoh: menampilkan department pada daftar Work Logs.

```text
app/Filament/Resources/WorkTasks/Tables/WorkTasksTable.php
```

Tambahkan komponen kolom pada bagian `columns([...])`. Jika data tidak tersedia, cek relasi pada:

```text
app/Models/WorkTask.php
```

### Ingin mengubah apa yang terjadi setelah Save

Contoh: setelah Service Desk dibuat, sistem harus membuat task atau assignment.

```text
app/Filament/Resources/Tickets/Pages/CreateTicket.php
```

Cari hook seperti `mutateFormDataBeforeCreate()`, `handleRecordCreation()`, atau `afterCreate()`. Untuk edit, lakukan hal yang sama di `EditTicket.php`.

### Ingin mengubah warna, jarak, atau ukuran

Untuk komponen Filament biasa, gunakan file CSS panel:

```text
public/css/filament/admin/workdesk-ui-polish.css
```

Untuk tampilan custom, cari class HTML-nya pada file Blade terlebih dahulu. Contoh Collaboration Room:

```text
resources/views/livewire/ticket-collaboration-room.blade.php
```

Lalu cari nama class seperti `.ik-collab-*` di CSS panel. Jangan menaruh aturan bisnis, query database, atau permission di CSS/Blade.

### Ingin mengubah siapa yang boleh melihat atau mengedit

Periksa secara berurutan:

1. `*Resource.php`: method seperti `canViewAny()`, `canCreate()`, `canEdit()`, dan `getEloquentQuery()`.
2. `Pages/*.php`: pengecekan action atau record tertentu.
3. `app/Models/*.php`: relasi dan helper aturan bisnis.
4. `app/Policies/*.php` bila Resource menggunakan Policy.
5. service di `app/Services` bila aturannya dipakai di banyak tempat.

Jangan hanya menyembunyikan tombol di tampilan. Backend tetap harus menolak user yang tidak berhak.

## Urutan aman sebelum mengubah kode

Untuk menu apa pun, ikuti urutan berikut:

1. Cari nama menu pada tabel dokumen ini.
2. Buka `*Resource.php` untuk melihat peta halaman dan permission.
3. Tentukan bagian yang berubah: daftar, form, proses Save, detail, atau style.
4. Buka file `Page`, `Schema`, `Table`, `Model`, atau `Service` sesuai tabel.
5. Cari test lama di `tests/Feature` menggunakan nama model atau nama fitur.
6. Buat perubahan sekecil mungkin.
7. Tambah atau perbarui test untuk aturan yang berubah.
8. Jalankan `php artisan test`.
9. Jalankan `php vendor/bin/pint --test`.
10. Periksa perubahan dengan `git diff` sebelum commit.

## Panduan rinci

- Service Desk: [Walkthrough Lengkap Service Desk](menus/SERVICE-DESK-WALKTHROUGH.md)
- Penjelasan setiap kelompok menu: [Panduan Detail Per Menu](menus/README.md)
- Dasar untuk pemula: [Panduan Developer Pemula](BEGINNER-DEVELOPER-GUIDE.md)

## Cara menelusuri menu lain sendiri

Contoh untuk Work Logs:

1. Buka `WorkTaskResource.php`.
2. Lihat `getPages()` untuk mengetahui class List/Create/View/Edit.
3. Lihat method `form()`; method menunjuk ke `WorkTaskForm.php`.
4. Lihat method `table()`; method menunjuk ke `WorkTasksTable.php`.
5. Baca `canView`, `canEdit`, dan `getEloquentQuery` untuk permission.
6. Baca Page class untuk action dan proses Save.
7. Baca model `WorkTask.php` untuk aturan status.
8. Cari service yang di-import Page, yaitu `WorkTaskMutationGuard`.
9. Cari nama field pada `tests/Feature` untuk mengetahui perilaku yang sudah dilindungi test.

Urutan tersebut dapat digunakan untuk semua Resource lain.
