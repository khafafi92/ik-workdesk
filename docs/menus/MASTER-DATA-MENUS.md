# Menu Master Data

Master Data digunakan oleh banyak transaksi. Perubahan pada data ini dapat memengaruhi pilihan Select, permission, workflow, dan laporan di menu lain.

## 1. Departments

### Fungsi

Menyimpan daftar department yang digunakan oleh Employee, Service Desk, Work Logs, project, category, dan scope akses user.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource dan akses admin | `app/Filament/Resources/Departments/DepartmentResource.php` |
| Form | `Departments/Schemas/DepartmentForm.php` |
| Tabel | `Departments/Tables/DepartmentsTable.php` |
| Model | `app/Models/Department.php` |
| Migration awal | `database/migrations/*create_departments_table.php` |

### Field

- `code`: kode unik department;
- `name`: nama department;
- `is_active`: menentukan department dapat dipilih pada transaksi baru.

### Kolom tabel

Code, Name, Active, Created At, dan Updated At.

### Hal yang perlu hati-hati

- Jangan mengubah code Legal tanpa memeriksa `Department::isLegal()` dan seluruh test Legal.
- Menonaktifkan department sebaiknya tidak menghapus histori Ticket/Work Log lama.
- Department dipakai dalam `accessibleDepartmentIds()` pada model User.
- Perubahan relasi department dapat membuka atau menutup akses data lintas divisi.

### Test yang disarankan

- department aktif muncul pada Select;
- department nonaktif tidak dapat dipilih untuk transaksi baru;
- histori lama tetap dapat dibaca;
- scope user antar-department tetap terisolasi.

## 2. Employees

### Fungsi

Menyimpan identitas employee, department asal, dan hubungan ke akun login User.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource | `app/Filament/Resources/Employees/EmployeeResource.php` |
| Form | `Employees/Schemas/EmployeeForm.php` |
| Tabel | `Employees/Tables/EmployeesTable.php` |
| Model | `app/Models/Employee.php` |
| Akun login | `app/Models/User.php` |

### Field

| Field | Fungsi |
| --- | --- |
| `user_id` | akun login yang terhubung |
| `department_id` | department asal employee |
| `employee_no` | nomor employee |
| `name` | nama employee |
| `email` | email employee |
| `phone` | nomor telepon |
| `position` | jabatan |
| `is_active` | status employee aktif |

### Kolom tabel

Department, Employee No, Name, Email, Phone, Position, Active, Created At, dan Updated At.

### Hal yang perlu hati-hati

- `user_id` dan Employee memiliki hubungan satu-ke-satu.
- Banyak permission record-level memakai `$user->employee`.
- Menonaktifkan Employee dapat mencegah akses panel melalui `User::isActiveForAccess()`.
- Mengubah department Employee mengubah home department dan scope otomatis user.
- PIC Work Log disimpan sebagai `employee_id`, bukan `user_id`.

### Test yang disarankan

- employee-user relation unik;
- employee nonaktif tidak dapat mengakses panel;
- perubahan department memperbarui scope;
- employee yang menjadi PIC tetap terhubung ke histori Work Log.

## 3. Work Locations

### Fungsi

Menyimpan lokasi GPS yang dianggap valid untuk pemeriksaan attendance.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource | `app/Filament/Resources/WorkLocations/WorkLocationResource.php` |
| Form | `WorkLocations/Schemas/WorkLocationForm.php` |
| Tabel | `WorkLocations/Tables/WorkLocationsTable.php` |
| Model | `app/Models/WorkLocation.php` |
| Processor attendance | `app/Services/AttendanceReportProcessor.php` |

### Field

- `gps_name`;
- `address`;
- `latitude` dan `longitude`;
- `radius_meters`;
- `is_flexible`;
- `is_active`.

### Kolom tabel

GPS Name, Latitude, Longitude, Radius, Flexible, Active, Created At, dan Updated At.

### Hal yang perlu hati-hati

- Latitude dan longitude harus valid.
- Radius menggunakan meter.
- `is_flexible` dapat mengubah cara processor menilai lokasi.
- Location nonaktif sebaiknya tidak digunakan untuk import baru, tetapi histori lama tetap dipertahankan.

### Test yang disarankan

- koordinat dan radius;
- lokasi di dalam/luar radius;
- flexible location;
- lokasi nonaktif.

## 4. Ticket Categories

### Fungsi

Menentukan jenis Service Desk, department penanganan, workflow, kebutuhan Permit, dan reviewer department.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource | `app/Filament/Resources/TicketCategories/TicketCategoryResource.php` |
| Form | `TicketCategories/Schemas/TicketCategoryForm.php` |
| Tabel | `TicketCategories/Tables/TicketCategoriesTable.php` |
| Model | `app/Models/TicketCategory.php` |
| Penggunaan saat create Ticket | `Tickets/Schemas/TicketForm.php` dan `Tickets/Pages/CreateTicket.php` |

### Field

| Field | Fungsi |
| --- | --- |
| `workflow_type` | single atau collaborative |
| `handler_department_id` | department penanganan utama |
| `name` | nama category |
| `code` | kode category |
| `requires_permit` | mengaktifkan detail Permit |
| `reviewerDepartments` | department tambahan/reviewer |
| `is_active` | status aktif |

### Kolom tabel

Handler Department, Name, Code, Active, dan Requires Permit.

### Hal yang perlu hati-hati

- Workflow category memengaruhi jumlah Work Log yang dibuat.
- Reviewer department memengaruhi assignment collaborative.
- `requires_permit` mengaktifkan field dan validasi Permit di Service Desk.
- Category nonaktif tidak boleh dipilih pada request baru.
- Mengubah category yang sudah dipakai tidak boleh merusak Ticket lama.

### Test yang disarankan

- single vs collaborative;
- reviewer assignment;
- category Permit;
- category nonaktif;
- department scope.

## 5. Task Categories

### Fungsi

Menyimpan kategori pekerjaan Work Log per department.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource | `app/Filament/Resources/TaskCategories/TaskCategoryResource.php` |
| Form | `TaskCategories/Schemas/TaskCategoryForm.php` |
| Tabel | `TaskCategories/Tables/TaskCategoriesTable.php` |
| Model | `app/Models/TaskCategory.php` |

### Field

`department_id`, `name`, `code`, dan `is_active`.

### Kolom tabel

Department, Name, Code, dan Active.

### Hal yang perlu hati-hati

- Category hanya boleh digunakan oleh department yang sesuai.
- Code sebaiknya unik sesuai aturan model/database.
- Menonaktifkan category tidak menghapus Work Log lama.

### Test yang disarankan

- filter category berdasarkan department;
- category nonaktif;
- duplicate code;
- authorization master data.

## 6. Permit Companies

### Fungsi

Menyimpan perusahaan atau entitas Permit dan daftar KBLI yang dapat dipilih pada request Legal Permit.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource | `app/Filament/Resources/PermitCompanies/PermitCompanyResource.php` |
| Form company | `PermitCompanies/Schemas/PermitCompanyForm.php` |
| Tabel company | `PermitCompanies/Tables/PermitCompaniesTable.php` |
| Pengelolaan KBLI | `PermitCompanies/RelationManagers/KblisRelationManager.php` |
| Model company | `app/Models/PermitCompany.php` |
| Model KBLI | `app/Models/PermitKbli.php` |
| Picker di Service Desk | `Tickets/Tables/PermitKblisPickerTable.php` |

### Field company

`code`, `name`, dan `is_active`.

### Kolom company

Code, Name, jumlah KBLI, dan Active.

### Hal yang perlu hati-hati

- KBLI dipilih setelah Permit Company.
- Satu code KBLI dapat memiliki kebutuhan uniqueness khusus; periksa migration terbaru.
- Request lama harus tetap menampilkan company/KBLI walaupun master dinonaktifkan.
- Opsi `permit_kbli_unavailable` memiliki alur validasi tersendiri.

### Test utama

- `tests/Feature/PermitWorkflowTest.php`;
- `tests/Feature/LegalRequestDetailsTest.php`;
- test company/KBLI aktif dan nonaktif;
- test KBLI unavailable.

## 7. Projects

### Fungsi

Menyimpan project yang dapat dikaitkan ke Work Log dan Aktivitas Harian.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource | `app/Filament/Resources/WorkProjects/WorkProjectResource.php` |
| Form | `WorkProjects/Schemas/WorkProjectForm.php` |
| Tabel | `WorkProjects/Tables/WorkProjectsTable.php` |
| Model | `app/Models/WorkProject.php` |

### Field

`code`, `name`, `company_name`, `department_id`, `manager_user_id`, `status`, `start_date`, `end_date`, dan `description`.

### Kolom dan filter

Code, Name, Company, Department, Manager, Status, Start Date, End Date, serta filter Status.

### Hal yang perlu hati-hati

- Project hanya muncul pada pilihan aktif sesuai query form.
- Manager menggunakan `user_id`, sedangkan PIC Work Log menggunakan `employee_id`.
- End Date harus setelah atau sama dengan Start Date.
- Scope department perlu dipertahankan pada report dan aktivitas.

### Test yang disarankan

- validasi tanggal;
- project aktif/nonaktif;
- manager relation;
- penggunaan project pada Work Log dan Daily Activity.

## 8. Activity Categories

### Fungsi

Menyimpan klasifikasi Aktivitas Harian untuk pekerjaan operasional/non-project.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource dan akses admin | `app/Filament/Resources/ActivityCategories/ActivityCategoryResource.php` |
| Form | `ActivityCategories/Schemas/ActivityCategoryForm.php` |
| Tabel | `ActivityCategories/Tables/ActivityCategoriesTable.php` |
| Model | `app/Models/ActivityCategory.php` |
| Penggunaan | `DailyActivities/Schemas/DailyActivityForm.php` |

### Field

`code`, `name`, `description`, dan `is_active`.

### Kolom tabel

Code, Category, Description, dan Active.

### Hal yang perlu hati-hati

- Menu menggunakan `AdminOnlyResource`.
- Category aktif digunakan saat `work_context = operational`.
- Menonaktifkan category tidak boleh menghilangkan label pada histori laporan.

### Test yang disarankan

- akses admin/non-admin;
- uniqueness code;
- category aktif pada Daily Activity;
- histori category nonaktif.
