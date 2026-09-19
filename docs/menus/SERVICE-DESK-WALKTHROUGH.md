# Walkthrough Lengkap Menu Service Desk

Dokumen ini menjelaskan Service Desk dari layar browser sampai database. Tujuannya agar developer pemula mengetahui file yang harus dibuka untuk setiap jenis perubahan.

## 1. Saat user mengklik menu Service Desk

URL daftar:

```text
/panel/service-desk
```

Alur kode:

```text
Klik sidebar Service Desk
    |
    v
TicketResource::getPages()['index']
    |
    v
Pages/ListTickets.php
    |
    +--> tombol Create berasal dari ListTickets::getHeaderActions()
    |
    +--> susunan tabel diminta dari TicketResource::table()
              |
              v
         Tables/TicketsTable.php
    |
    +--> data yang boleh terlihat berasal dari TicketResource::getEloquentQuery()
```

File yang pertama dibuka:

```text
app/Filament/Resources/Tickets/TicketResource.php
```

Pada bagian bawah file terdapat:

```php
public static function getPages(): array
{
    return [
        'index' => ListTickets::route('/'),
        'create' => CreateTicket::route('/create'),
        'view' => ViewTicket::route('/{record}'),
        'edit' => EditTicket::route('/{record}/edit'),
    ];
}
```

Bagian ini adalah peta URL ke Page class.

## 2. Mengubah halaman daftar Service Desk

Halaman daftar berisi tabel seluruh request.

### File Page

```text
app/Filament/Resources/Tickets/Pages/ListTickets.php
```

File ini hanya mengatur perilaku halaman, misalnya tombol header:

```php
protected function getHeaderActions(): array
{
    return [
        CreateAction::make(),
    ];
}
```

### File kolom tabel

```text
app/Filament/Resources/Tickets/Tables/TicketsTable.php
```

Buka file ini jika ingin:

- menambah atau menghapus kolom;
- mengganti label kolom;
- mengubah format tanggal;
- mengubah warna badge Priority atau Status;
- menambah filter;
- mengubah tombol View/Edit pada setiap baris;
- mengubah default sorting.

Kolom yang saat ini tersedia:

| Field | Yang terlihat di layar |
| --- | --- |
| `ticket_no` | nomor Service Desk |
| `subject` | judul request |
| `employee.name` | requester |
| `requesterDepartment.name` | department requester |
| `handlerDepartment.name` | department tujuan |
| `category.name` | request category |
| `permitCompany.name` | Permit Company |
| `permitKbli.code` | KBLI |
| `priority` | badge priority |
| `status` | badge status |
| `resolution_notes` | catatan penyelesaian |
| `workTasks.employee.name` | PIC Work Log |
| `reported_at` | tanggal dilaporkan |
| `due_at` | tanggal target |
| `resolved_at` | tanggal selesai aktual |

### Contoh mengubah label kolom

Cari:

```php
TextColumn::make('due_at')
```

Kemudian ubah label:

```php
TextColumn::make('due_at')
    ->label('Target Selesai')
    ->dateTime('d M Y H:i')
    ->placeholder('-');
```

Perubahan ini hanya memengaruhi daftar, bukan form dan bukan database.

### Mengubah data yang terlihat

Jangan mengubah `ListTickets.php`. Buka:

```text
app/Filament/Resources/Tickets/TicketResource.php
```

Cari:

```php
public static function getEloquentQuery(): Builder
```

Method ini menentukan Ticket mana yang terlihat oleh requester, department, manager, atau admin.

## 3. Mengubah halaman Create Service Desk

URL:

```text
/panel/service-desk/create
```

Alur kode:

```text
CreateTicket.php
    |
    +--> meminta form dari TicketResource::form()
    |         |
    |         v
    |    Schemas/TicketForm.php
    |
    +--> sebelum insert: mutateFormDataBeforeCreate()
    |
    +--> proses insert: handleRecordCreation()
    |         |
    |         +--> membuat Ticket
    |         +--> membuat WorkTask per department
    |         `--> membuat TicketAssignment
    |
    v
Model Ticket dan WorkTask menjalankan event/aturan otomatis
```

### Mengubah field, tulisan, atau deskripsi

Buka:

```text
app/Filament/Resources/Tickets/Schemas/TicketForm.php
```

File ini dipakai bersama oleh Create, Edit, dan View. Karena itu, satu perubahan dapat muncul pada beberapa halaman.

Gunakan file ini untuk:

- label;
- helper text;
- placeholder;
- pilihan Select;
- required/nullable;
- urutan field;
- Section;
- kondisi visible/disabled;
- upload attachment.

### Field form dan lokasi konsepnya

| Field | Fungsi |
| --- | --- |
| `ticket_no` | nomor otomatis; user tidak mengisi manual |
| `employee_id` | requester employee; hidden |
| `requester_department_id` | department requester; hidden |
| `requester_display` | tampilan requester; bukan kolom database |
| `requester_department_display` | tampilan department requester |
| `handler_department_id` | department tujuan |
| `ticket_category_id` | kategori request |
| `reviewer_department_ids` | reviewer collaborative; data sementara |
| `permit_company_id` | company untuk Permit |
| `permit_kbli_id` | KBLI Permit |
| `permit_kbli_unavailable` | KBLI belum tersedia |
| `subject` | judul request |
| `description` | uraian request |
| field Legal | background, objective, scheme, document types |
| `attachments` | dokumen pendukung |
| `priority` | Low sampai Urgent |
| `status` | status Ticket; pada record biasanya read-only |
| `reported_at` | waktu laporan |
| `due_at` | target selesai opsional |

### Contoh mengubah deskripsi

Jika ingin menambah keterangan pada Subject, cari:

```php
TextInput::make('subject')
```

Tambahkan:

```php
->helperText('Tuliskan ringkasan singkat kebutuhan Anda.')
```

### Mengubah fungsi saat tombol Create ditekan

Buka:

```text
app/Filament/Resources/Tickets/Pages/CreateTicket.php
```

Ada dua method penting.

#### `mutateFormDataBeforeCreate()`

Digunakan sebelum Ticket dibuat. Saat ini method ini:

- mengambil reviewer department dari field sementara;
- menghapus field sementara agar tidak ikut insert ke tabel tickets;
- mengambil workflow type dari Ticket Category;
- membuat nomor Ticket otomatis;
- mengisi Reported At bila kosong;
- mengisi requester dari user login.

#### `handleRecordCreation()`

Digunakan untuk proses database. Saat ini method ini:

- membuka database transaction;
- menyimpan Ticket;
- menentukan department pelaksana;
- membuat Work Log per department;
- membuat Ticket Assignment;
- menentukan lead dan reviewer department.

Jika ingin mengubah Work Log otomatis, file ini adalah salah satu file utama. Setelah itu tetap periksa `app/Models/WorkTask.php`.

## 4. Mengubah halaman View Service Desk

URL:

```text
/panel/service-desk/{id}
```

Contoh:

```text
/panel/service-desk/8
```

Alur kode:

```text
Pages/ViewTicket.php
    |
    +--> getFormContentComponent()
    |         |
    |         v
    |    Schemas/TicketForm.php dalam mode view
    |
    +--> TicketCollaborationRoom Livewire component
              |
              +--> app/Livewire/TicketCollaborationRoom.php
              `--> resources/views/livewire/ticket-collaboration-room.blade.php
```

### File utama

```text
app/Filament/Resources/Tickets/Pages/ViewTicket.php
```

Method `content()` menyusun dua bagian:

1. Form detail Ticket.
2. Collaboration Room.

### Mengubah tombol halaman detail

Masuk ke `ViewTicket.php`, lalu cari:

```php
protected function getHeaderActions(): array
```

Saat ini terdapat action `reviseAndResubmit` untuk request Legal yang ditolak.

### Mengubah detail yang tampil

Karena View menggunakan form yang sama, buka:

```text
Tickets/Schemas/TicketForm.php
```

Gunakan kondisi berdasarkan `$record` atau permission jika field hanya boleh tampil pada kondisi tertentu.

## 5. Mengubah halaman Edit Service Desk

URL:

```text
/panel/service-desk/{id}/edit
```

Alur kode:

```text
Pages/EditTicket.php
    |
    +--> form dari Schemas/TicketForm.php
    +--> mutateFormDataBeforeSave()
    +--> handleRecordUpdate()
    `--> Model Ticket menyimpan data
```

### File tampilan form

```text
app/Filament/Resources/Tickets/Schemas/TicketForm.php
```

### File fungsi Save

```text
app/Filament/Resources/Tickets/Pages/EditTicket.php
```

Saat ini Edit memiliki aturan khusus revisi Legal:

- memeriksa apakah rejected Legal request boleh direvisi;
- hanya mengizinkan field tertentu;
- update dilakukan dalam transaction;
- Work Log rejected diajukan ulang;
- notification title berubah.

Jangan menambahkan field editable hanya pada Form. Jika request rejected menggunakan whitelist di `mutateFormDataBeforeSave()`, field baru juga harus dipertimbangkan di sana.

## 6. Mengubah Collaboration Room

Collaboration Room terdiri dari tiga lapisan.

### Backend/function

```text
app/Livewire/TicketCollaborationRoom.php
```

Method penting:

| Method | Fungsi |
| --- | --- |
| `mount()` | menerima record Ticket dan memeriksa akses |
| `addMessage()` | validasi dan menyimpan Post to group |
| `canPostMessage()` | menentukan user boleh posting |
| `render()` | mengambil participants, timeline, dan summary |
| `activityContext()` | menentukan department dan Work Log sumber aktivitas |
| `storeFiles()` | menyimpan attachment |

### HTML/tampilan

```text
resources/views/livewire/ticket-collaboration-room.blade.php
```

Ubah file ini untuk:

- judul Collaboration Room;
- summary Planned/In Progress/Done;
- textarea Post to group;
- pesan nonaktif;
- tombol attachment;
- tampilan timeline.

### CSS/style

```text
public/css/filament/admin/workdesk-ui-polish.css
```

Cari class yang diawali:

```text
.ik-collab-
```

Contoh:

- `.ik-collab-room`;
- `.ik-collab-header`;
- `.ik-collab-composer`;
- `.ik-collab-composer.is-disabled`;
- `.ik-collab-timeline`;
- `.ik-collab-card`.

### Test

```text
tests/Feature/PendingLegalCollaborationLockTest.php
```

Jika permission Post to group berubah, test minimal harus mencakup:

- pending approval;
- approved tetapi PIC kosong;
- user bukan PIC;
- user adalah PIC;
- requester;
- system admin;
- backend call langsung.

## 7. Mengubah style umum Service Desk

Filament field seperti TextInput dan Select tidak memiliki CSS sendiri di folder Tickets.

Urutan mencari style:

1. Periksa class khusus pada Blade atau component.
2. Periksa:

   ```text
   public/css/filament/admin/workdesk-ui-polish.css
   ```

3. Periksa theme dasar:

   ```text
   public/css/filament/admin/workdesk-theme.css
   ```

4. Registrasi kedua CSS berada di:

   ```text
   app/Providers/Filament/AdminPanelProvider.php
   ```

Jangan mengedit file vendor Filament atau generated CSS `public/css/filament/filament/app.css`.

## 8. Mengubah nama menu, icon, badge, atau group sidebar

Buka:

```text
app/Filament/Resources/Tickets/TicketResource.php
```

Bagian penting:

| Property/method | Fungsi |
| --- | --- |
| `$slug = 'service-desk'` | URL menu |
| `$navigationIcon` | icon sidebar |
| `getNavigationLabel()` | tulisan Service Desk |
| `getNavigationBadge()` | angka merah pada menu |
| `getNavigationBadgeColor()` | warna badge |
| `getNavigationGroup()` | group sidebar |
| `getNavigationSort()` | urutan menu |

Mengubah slug akan mengubah URL dan dapat merusak bookmark/link. Jangan lakukan tanpa rencana redirect.

## 9. Mengubah permission dan scope data

Masih di:

```text
TicketResource.php
```

Method penting:

| Method | Fungsi |
| --- | --- |
| `shouldRegisterNavigation()` | menu tampil di sidebar atau tidak |
| `canViewAny()` | boleh membuka halaman list |
| `canView($record)` | boleh membuka detail record |
| `canCreate()` | boleh membuat request |
| `canEdit($record)` | boleh edit |
| `canDelete($record)` | boleh delete |
| `getEloquentQuery()` | data mana yang muncul |

Permission code terkait:

```text
tickets.view
tickets.create
tickets.manage
```

Prinsip penting:

- sidebar hidden bukan authorization;
- URL langsung harus tetap ditolak;
- query harus membatasi data department;
- requester tetap dapat melihat request miliknya;
- manager hanya melihat department yang dapat diakses.

## 10. Mengubah fungsi status

Jangan mengubah hanya pilihan Select Status pada Form.

Periksa:

```text
app/Models/Ticket.php
app/Models/WorkTask.php
app/Filament/Resources/Tickets/Pages/CreateTicket.php
app/Filament/Resources/Tickets/Pages/EditTicket.php
```

Method penting pada Ticket:

```text
syncCollaborativeStatus()
requiresPermitDiscussion()
```

Status Ticket dapat berasal dari status Work Log, bukan dari input manual.

Contoh:

```text
Semua required Work Log Done
    |
    v
Ticket::syncCollaborativeStatus()
    |
    +--> status = resolved
    +--> resolved_at = now()
    `--> resolution_notes diisi
```

## 11. Mengubah attachment

### Attachment Ticket

Field ada di:

```text
Tickets/Schemas/TicketForm.php
```

Penyimpanan dan cast terkait ada di:

```text
app/Models/Ticket.php
```

### Attachment Collaboration Room

Validasi dan penyimpanan ada di:

```text
app/Livewire/TicketCollaborationRoom.php
```

Download harus melewati route authorization. Periksa:

```text
routes/web.php
app/Http/Controllers
```

Jangan membuat link langsung ke `storage/app/private`.

## 12. Mengubah database Service Desk

Model:

```text
app/Models/Ticket.php
```

Migration terkait dapat dicari dengan:

```powershell
rg -n "tickets|ticket_assignments|ticket_comments" database\migrations
```

Tabel utama:

| Tabel | Fungsi |
| --- | --- |
| `tickets` | record Service Desk |
| `ticket_assignments` | department dan Work Log collaborative |
| `ticket_comments` | message dan activity history |
| `work_tasks` | pekerjaan turunan |
| `work_task_findings` | finding terkait pekerjaan |

Jika menambah kolom:

1. Buat migration baru.
2. Update fillable/casts model.
3. Update Form.
4. Update Table bila perlu.
5. Update whitelist Create/Edit bila ada.
6. Update test.
7. Jalankan migration dan seluruh test.

## 13. Urutan file berdasarkan jenis permintaan

### “Ubah tulisan atau deskripsi”

```text
TicketForm.php -> bila field form
TicketsTable.php -> bila header kolom
ticket-collaboration-room.blade.php -> bila Collaboration Room
TicketResource.php -> bila label menu
```

### “Ubah tampilan atau layout”

```text
TicketForm.php -> layout form/section
TicketsTable.php -> layout daftar
ViewTicket.php -> susunan detail + Collaboration Room
Blade Collaboration Room -> HTML custom
workdesk-ui-polish.css -> warna, spacing, ukuran
```

### “Ubah fungsi Save/Create”

```text
CreateTicket.php -> pembuatan Ticket/Work Log
EditTicket.php -> penyimpanan edit/revisi
Ticket.php -> aturan bisnis record
WorkTask.php -> efek ke Work Log
```

### “Ubah siapa yang boleh akses”

```text
TicketResource.php -> menu, URL, query scope
TicketCollaborationRoom.php -> permission posting
Model/Service -> backend record mutation
AccessControlSeeder.php -> definisi role/permission awal
```

### “Tambah field baru”

```text
Migration -> Ticket model -> TicketForm -> TicketsTable -> Create/Edit whitelist -> tests
```

## 14. Checklist sebelum menyatakan selesai

- Halaman list berhasil dibuka.
- Halaman create berhasil dibuka.
- Ticket dapat dibuat.
- Work Log otomatis terbentuk.
- Halaman view berhasil dibuka.
- Collaboration Room sesuai permission.
- Edit hanya tersedia untuk actor yang benar.
- Request Legal pending/rejected/approved tetap benar.
- Collaborative status tetap sinkron.
- Attachment private tetap aman.
- Test Ticket dan Work Log lulus.
- Seluruh PHPUnit lulus.
- Tidak ada error pada log.

## 15. Ringkasan satu baris

```text
Tampilan form    = Tickets/Schemas/TicketForm.php
Tampilan list    = Tickets/Tables/TicketsTable.php
Tampilan detail  = Tickets/Pages/ViewTicket.php
Style custom     = public/css/filament/admin/workdesk-ui-polish.css
Create function  = Tickets/Pages/CreateTicket.php
Edit function    = Tickets/Pages/EditTicket.php
Business logic   = app/Models/Ticket.php + app/Models/WorkTask.php
Permission/data  = Tickets/TicketResource.php
Collaboration    = app/Livewire/TicketCollaborationRoom.php + Blade
Database         = database/migrations + model
Tests            = tests/Feature
```
