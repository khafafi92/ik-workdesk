# Menu Operasional Utama

Panduan ini membahas Dashboard, Reminders, Service Desk, Work Logs, Aktivitas Harian, dan Laporan Aktivitas.

## 1. Dashboard

### Fungsi

Dashboard adalah halaman pertama setelah user masuk ke Filament Panel. Halaman ini menampilkan ringkasan Service Desk dan Work Logs berdasarkan data yang boleh dilihat oleh user.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Query statistik dan data dashboard | `app/Filament/Pages/Dashboard.php` |
| Tampilan kartu dan tabel ringkas | `resources/views/filament/pages/dashboard.blade.php` |
| Registrasi Dashboard sebagai halaman awal panel | `app/Providers/Filament/AdminPanelProvider.php` |
| Style panel | `public/css/filament/admin/workdesk-theme.css` dan `workdesk-ui-polish.css` |

### Jika ingin mengubah

| Kebutuhan | Lokasi |
| --- | --- |
| Judul atau susunan kartu | Blade dashboard |
| Jumlah Open, In Progress, atau Done | query pada `Dashboard.php` |
| Data tabel ringkas | query dan variable pada `Dashboard.php`, lalu Blade |
| Warna kartu | CSS panel dan class pada Blade |
| Menu yang muncul di sidebar | `AdminPanelProvider.php` atau Resource terkait |

### Hal yang perlu hati-hati

- Query dashboard harus mengikuti scope user dan department.
- Jangan memakai `Model::count()` tanpa pembatasan jika data bersifat rahasia antar-department.
- Gunakan query yang sama prinsip aksesnya dengan `TicketResource` dan `WorkTaskResource`.

### Test yang disarankan

- `tests/Feature/MinimalDashboardTest.php`
- test authorization Ticket dan Work Log;
- render dashboard sebagai admin, manager, dan user biasa.

## 2. Reminders

### Fungsi

Reminders menyimpan pengingat personal atau department. Reminder dapat dikaitkan ke Work Log dan dapat memiliki alarm email.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Menu, query scope, akses | `app/Filament/Resources/Reminders/ReminderResource.php` |
| Field form | `Reminders/Schemas/ReminderForm.php` |
| Kolom daftar dan action Done | `Reminders/Tables/RemindersTable.php` |
| Proses create/edit | `Reminders/Pages/CreateReminder.php` dan `EditReminder.php` |
| Halaman detail | `Reminders/Pages/ViewReminder.php` |
| Kalender reminder | `Reminders/Widgets/ReminderCalendarWidget.php` |
| Model | `app/Models/Reminder.php` |
| Email alarm | `app/Notifications` dan command/scheduler terkait reminder |

### Field form

| Field | Fungsi |
| --- | --- |
| `work_task_id` | menghubungkan reminder ke Work Log |
| `reminder_type` | menentukan jenis reminder |
| `title` | judul reminder |
| `description` | keterangan tambahan |
| `employee_id` | target employee |
| `department_id` | target department |
| `reminder_at` | waktu reminder aktif |
| `email_alarm_days` | jarak hari pengiriman alarm email |
| `status` | status reminder |

### Kolom daftar

Jenis reminder, judul, nomor Work Log, employee, department, waktu reminder, dan status.

### Action penting

`markAsDone` pada `RemindersTable.php` menyelesaikan reminder. Periksa authorization sebelum mengubah action ini.

### Hal yang perlu hati-hati

- Employee dan department dapat diisi otomatis berdasarkan user atau Work Log.
- System Administrator memiliki scope yang lebih luas.
- Alarm email dapat menghasilkan queued job; test notification dan queue bila diubah.

### Test yang disarankan

- `tests/Feature/ReminderTaskSourceTest.php`
- test permission antar-user;
- test alarm email dan status Done.

## 3. Service Desk

### Fungsi

Service Desk adalah request utama dari requester. Satu request dapat menghasilkan satu atau beberapa Work Log, termasuk workflow collaborative dan approval Legal.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Registrasi menu, badge, query scope, permission | `app/Filament/Resources/Tickets/TicketResource.php` |
| Seluruh field form | `Tickets/Schemas/TicketForm.php` |
| Kolom list dan filter | `Tickets/Tables/TicketsTable.php` |
| Picker KBLI Permit | `Tickets/Tables/PermitKblisPickerTable.php` |
| Membuat Ticket dan Work Log | `Tickets/Pages/CreateTicket.php` |
| Validasi edit | `Tickets/Pages/EditTicket.php` |
| Detail dan action | `Tickets/Pages/ViewTicket.php` |
| Comments | `Tickets/RelationManagers/CommentsRelationManager.php` |
| Findings | `Tickets/RelationManagers/FindingsRelationManager.php` |
| Aturan bisnis Ticket | `app/Models/Ticket.php` |
| Collaboration Room backend | `app/Livewire/TicketCollaborationRoom.php` |
| Collaboration Room UI | `resources/views/livewire/ticket-collaboration-room.blade.php` |

### Field form utama

| Kelompok | Field |
| --- | --- |
| Identitas | `ticket_no`, `employee_id`, `requester_department_id` |
| Tampilan requester | `requester_display`, `requester_department_display` |
| Tujuan | `handler_department_id`, `ticket_category_id`, `reviewer_department_ids` |
| Permit | `permit_company_id`, `permit_kbli_id`, `permit_kbli_unavailable` |
| Isi request | `subject`, `description`, `attachments` |
| Detail Legal | `legal_background`, `legal_objective`, `legal_desired_scheme`, `legal_document_types` |
| Kontrol | `priority`, `status`, `reported_at`, `due_at` |

### Kolom daftar

Nomor ticket, subject, requester, department requester, department tujuan, category, Permit company/KBLI, priority, status, resolution notes, PIC Work Log, Reported At, Due At, dan Resolved At.

### Workflow penting

1. Requester membuat Ticket.
2. `CreateTicket.php` membuat Work Log sesuai workflow dan department assignment.
3. Jika tujuan Legal, Work Log menunggu approval CBO.
4. Department manager menetapkan PIC.
5. PIC mengerjakan Work Log.
6. Ketika seluruh required Work Log Done, `Ticket::syncCollaborativeStatus()` mengubah Ticket menjadi Resolved.

### Status dan tanggal

| Field | Arti |
| --- | --- |
| `reported_at` | waktu request dibuat/dilaporkan |
| `due_at` | target selesai, dapat kosong |
| `resolved_at` | waktu aktual Ticket selesai |

Jangan mengisi `due_at` secara otomatis saat Done. Waktu aktual selesai menggunakan `resolved_at`.

### Permission utama

- `tickets.view`: melihat Ticket sesuai scope;
- `tickets.create`: membuat Ticket;
- `tickets.manage`: mengelola Ticket department;
- scope data juga mempertimbangkan employee requester, department, dan assignment.

### Hal yang perlu hati-hati

- Perubahan `handler_department_id`, category, atau workflow dapat memengaruhi Work Log otomatis.
- Legal memiliki status approval tambahan pada Work Log.
- Upload disimpan private dan download wajib melalui route authorization.
- Jangan menampilkan Ticket department lain hanya karena user memiliki permission view.
- Status form tidak boleh menjadi satu-satunya pengaman; aturan backend tetap diperlukan.

### Test utama

- `tests/Feature/WorkLogAuthorizationTest.php`
- `tests/Feature/LegalTaskApprovalTest.php`
- `tests/Feature/PendingLegalCollaborationLockTest.php`
- `tests/Feature/CollaborativeLeadCompletionTest.php`
- `tests/Feature/LegalRequestDetailsTest.php`
- `tests/Feature/PermitWorkflowTest.php`
- test lain dengan nama `Ticket*Test.php`.

## 4. Work Logs

### Fungsi

Work Logs adalah pekerjaan operasional yang dibuat dari Service Desk atau secara manual. Work Log memiliki department, PIC, status, progress, jadwal, catatan, approval Legal, dan hasil Permit.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Menu, scope, badge, permission | `app/Filament/Resources/WorkTasks/WorkTaskResource.php` |
| Form dan bagian Work Execution | `WorkTasks/Schemas/WorkTaskForm.php` |
| Daftar, approval, reject | `WorkTasks/Tables/WorkTasksTable.php` |
| Create/edit | `WorkTasks/Pages/CreateWorkTask.php` dan `EditWorkTask.php` |
| View, claim, approve, reject, complete | `WorkTasks/Pages/ViewWorkTask.php` |
| Activity history | `WorkTasks/RelationManagers/ActivityHistoryRelationManager.php` |
| Findings | `WorkTasks/RelationManagers/FindingsRelationManager.php` |
| Model dan otomatisasi | `app/Models/WorkTask.php` |
| Validasi perubahan backend | `app/Services/WorkTaskMutationGuard.php` |
| Observer notification | `app/Observers/WorkTaskObserver.php` |

### Field Work Execution

| Field | Fungsi |
| --- | --- |
| `task_no` | nomor Work Log |
| `ticket_id` | Service Desk sumber |
| `department_id` | department pelaksana |
| `employee_id` | PIC/pelaksana |
| `work_project_id` | project terkait |
| `title`, `description` | isi pekerjaan |
| `priority` | prioritas pekerjaan |
| `status` | Planned, In Progress, Done, Hold, Cancel |
| `status_reason` | alasan Hold/Cancel |
| `progress_percent` | persentase progress |
| `start_at`, `due_at`, `completed_at` | tanggal mulai, target, dan selesai aktual |
| `notes` | catatan pengerjaan |

### Field khusus Legal dan Permit

- `approval_status`;
- approver/rejector dan alasan reject;
- `permit_result_notes`;
- `permit_result_attachments`.

### Permission utama

- `worklogs.view`;
- `worklogs.manage`;
- `legal-tasks.approve`;
- role manager/supervisor digunakan untuk assignment PIC sesuai department.

### Aturan PIC

- User biasa tidak otomatis menjadi PIC hanya karena dapat melihat Work Log.
- PIC harus tercatat pada `employee_id`.
- UI dan backend harus sama-sama memeriksa assignment.
- Manager/Supervisor dapat menetapkan PIC sesuai aturan department.
- Untuk Legal, assignment PIC dibatasi ke Manager.

### Otomatisasi status

- `in_progress` dapat mengisi `start_at` bila kosong;
- `done` mengisi `progress_percent = 100` dan `completed_at`;
- Hold/Cancel memerlukan alasan;
- penyelesaian Work Log menyinkronkan status Ticket;
- workflow collaborative memiliki aturan primary/requester lead.

### Test utama

- `tests/Feature/AssignedPicExecutionTest.php`
- `tests/Feature/WorkTaskClaimTest.php`
- `tests/Feature/WorkTaskStatusGovernanceTest.php`
- `tests/Feature/WorkTaskAutomaticStartTest.php`
- `tests/Feature/WorkLogCompletionTest.php`
- `tests/Feature/WorkLogAuthorizationTest.php`
- `tests/Feature/CollaborativeLeadCompletionTest.php`.

## 5. Aktivitas Harian

### Fungsi

Aktivitas Harian mencatat pekerjaan user per tanggal dan waktu. Aktivitas dapat berasal dari Work Log atau input manual, serta dapat dikaitkan dengan project atau kategori operasional.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Scope user dan permission | `app/Filament/Resources/DailyActivities/DailyActivityResource.php` |
| Form | `DailyActivities/Schemas/DailyActivityForm.php` |
| Tabel dan filter | `DailyActivities/Tables/DailyActivitiesTable.php` |
| Create/edit | `DailyActivities/Pages/CreateDailyActivity.php` dan `EditDailyActivity.php` |
| Model | `app/Models/DailyActivity.php` |
| Pengolahan data | `app/Services/DailyActivityDataService.php` |

### Bagian form

#### Waktu dan pekerjaan

`work_date`, `start_time`, `end_time`, `duration_minutes`, `title`, `description`, dan `result`.

#### Klasifikasi pekerjaan

`source_type`, `work_task_id`, `work_context`, `work_project_id`, dan `activity_category_id`.

#### Pemberi pekerjaan

`requester_type`, `requester_company_name`, `requester_department_id`, dan `requester_employee_id`.

### Kolom dan filter

Tabel menampilkan tanggal, waktu mulai, user, pekerjaan, konteks, sumber, pemberi pekerjaan, dan durasi. Filter tersedia untuk konteks, sumber, project, dan kategori aktivitas.

### Hal yang perlu hati-hati

- Durasi harus sesuai start/end time.
- Aktivitas dari Work Log harus mengarah ke Work Log yang dapat diakses user.
- User biasa hanya mengelola aktivitasnya sendiri; scope manager/admin lebih luas.
- `requester_type` menentukan field requester mana yang relevan.

### Test yang disarankan

- test perhitungan durasi;
- test scope user dan manager;
- test sumber task/manual;
- test project/operational;
- test requester company/division/individual.

## 6. Laporan Aktivitas

### Fungsi

Laporan Aktivitas adalah tampilan read-only dari data Aktivitas Harian untuk analisis pekerjaan dan durasi.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Query scope dan permission | `app/Filament/Resources/ActivityReports/ActivityReportResource.php` |
| Kolom, filter, total durasi | `ActivityReports/Tables/ActivityReportsTable.php` |
| Halaman list | `ActivityReports/Pages/ListActivityReports.php` |
| Model sumber | `app/Models/DailyActivity.php` |

### Kolom

Tanggal, user, pekerjaan, konteks, sumber, pemberi pekerjaan, dan durasi. Kolom durasi memiliki summarizer total jam dan menit.

### Filter

- periode Dari/Sampai;
- user, bila actor boleh melihat team;
- konteks;
- sumber;
- project;
- kategori aktivitas.

### Permission dan scope

- semua user terautentikasi dapat melihat laporannya sendiri;
- manager atau user dengan `worklogs.manage` dapat melihat team sesuai department access;
- system admin dapat melihat seluruh data;
- menu tidak menyediakan create, edit, atau delete.

### Hal yang perlu hati-hati

- Jangan membuka filter user untuk actor yang hanya boleh melihat dirinya sendiri.
- Penjumlahan durasi harus memakai `duration_minutes`.
- Query harus eager-load relasi agar tabel tidak menghasilkan query berulang.

### Test yang disarankan

- user hanya melihat aktivitas sendiri;
- manager melihat department yang diizinkan;
- filter periode;
- total durasi;
- create/edit/delete tetap ditolak.
