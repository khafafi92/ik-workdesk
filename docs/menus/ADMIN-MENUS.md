# Menu Administrasi

Menu administrasi mengontrol akun, role, permission, dan akses department. Kesalahan pada area ini dapat membuka data lintas department atau mengunci seluruh user dari panel.

## 1. User Management

### Fungsi

Membuat dan mengelola akun login, hubungan akun dengan Employee, role, department tambahan, dan akses fitur opsional.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Menu, query, dan perlindungan account | `app/Filament/Resources/Users/UserResource.php` |
| Form | `Users/Schemas/UserForm.php` |
| Tabel | `Users/Tables/UsersTable.php` |
| Create/edit hooks | `Users/Pages/CreateUser.php` dan `EditUser.php` |
| Model User | `app/Models/User.php` |
| Model Employee | `app/Models/Employee.php` |
| Sinkronisasi role | `app/Services/RoleAssignmentService.php` |
| Akses tambahan | `app/Services/UserAdditionalAccessService.php` |
| Seeder role/permission | `database/seeders/AccessControlSeeder.php` |

### Bagian form

#### Login Account

| Field | Fungsi |
| --- | --- |
| `employee_id` | menghubungkan akun ke Employee |
| `name` | nama akun |
| `email` | email login |
| `password` | password akun |
| `is_admin` | super administrator |

#### Pilih Role

`role_ids` menentukan role yang dimiliki user. Satu user dapat memiliki lebih dari satu role.

#### Department Access

`accessibleDepartments` memberikan akses tambahan di luar home department Employee.

#### Akses Tambahan

`additional_access` adalah kelompok permission opsional yang disinkronkan melalui `UserAdditionalAccessService`.

### Kolom tabel

Name, Employee, Department, Roles, Accessible Departments, Email, Is Admin, dan Created At.

### Permission

Menu memerlukan `users.manage`.

### Perlindungan account

`UserResource.php` memiliki aturan khusus:

- user manager biasa tidak boleh mengedit dirinya sendiri;
- user manager biasa tidak boleh mengedit Super Admin;
- account dengan role System Administrator dilindungi;
- delete hanya untuk Super Admin;
- Super Admin tidak boleh menghapus dirinya sendiri.

### Hubungan User dan Employee

```text
User
    |
    +--> Employee --> home Department
    +--> Roles --> Permissions
    +--> Direct Permissions
    `--> Accessible Departments
```

Scope menu seperti Service Desk dan Work Logs memakai gabungan hubungan tersebut.

### Hal yang perlu hati-hati

- Jangan menyimpan password tanpa hashing; model Laravel menangani cast hashed.
- Password edit yang kosong tidak boleh menimpa password lama.
- `is_admin` memberikan akses sangat luas dan berbeda dari role biasa.
- Mengubah Employee department mengubah home department user.
- Role dan direct permissions dapat saling menambah akses.
- Jangan menampilkan atau mencatat password ke log.
- Setelah mengubah role, pastikan cache relasi user tidak menyimpan nilai lama dalam request yang sama.

### Test utama

- `tests/Feature/MultipleUserRolesTest.php`;
- `tests/Feature/DepartmentRoleHierarchyTest.php`;
- test User Manager tidak dapat mengedit Super Admin;
- test self-edit/self-delete;
- test additional department access;
- test employee nonaktif;
- test permission langsung dan permission dari role.

## 2. Role Management

### Fungsi

Menyimpan role dan kumpulan permission. Resource masih tersedia pada kode, tetapi saat ini sengaja disembunyikan dan seluruh operasi panel dinonaktifkan.

### Lokasi kode

| Bagian | File |
| --- | --- |
| Resource dan status disabled | `app/Filament/Resources/Roles/RoleResource.php` |
| Form | `Roles/Schemas/RoleForm.php` |
| Tabel | `Roles/Tables/RolesTable.php` |
| Create/edit hooks | `Roles/Pages/CreateRole.php` dan `EditRole.php` |
| Model | `app/Models/Role.php` |
| Model Permission | `app/Models/Permission.php` |
| Seeder resmi | `database/seeders/AccessControlSeeder.php` |

### Field form

| Bagian | Field |
| --- | --- |
| Role Information | `name`, `code`, `description`, `is_active` |
| Permissions | `permissions` |

### Kolom tabel

Name, Code, jumlah Permissions, jumlah Users, Active, dan Updated At.

### Status menu saat ini

Pada `RoleResource.php`:

- `shouldRegisterNavigation()` mengembalikan false;
- `canViewAny()` false;
- `canCreate()` false;
- `canEdit()` false;
- `canDelete()` false.

Artinya Role Management tidak boleh diaktifkan hanya dengan mengubah label atau navigation. Seluruh keputusan keamanan harus ditinjau terlebih dahulu.

### Permission

Kode lama masih mengenal `roles.manage`, tetapi resource sengaja ditutup. Role bawaan dikelola melalui seeder.

### Hal yang perlu hati-hati

- Role `system-admin` membuat `hasPermission()` selalu true.
- Role yang tidak aktif tidak boleh memberi permission.
- Permission yang tidak aktif juga tidak boleh berlaku.
- Menghapus role yang sedang dipakai dapat mengubah akses banyak user.
- Perubahan `AccessControlSeeder` harus idempotent dan tidak menggandakan pivot.
- Jangan mengaktifkan Role Management tanpa test privilege escalation.

### Test yang disarankan

- role aktif/nonaktif;
- permission aktif/nonaktif;
- multiple roles;
- system admin bypass;
- direct permission;
- user manager tidak dapat memberikan permission lebih tinggi dari miliknya;
- Role Management tetap tidak dapat diakses selama kebijakan disabled berlaku.

## 3. Access Control Seeder

### Fungsi

`AccessControlSeeder.php` adalah sumber definisi permission dan role bawaan.

### Saat menambah permission baru

1. Tambahkan permission dengan code yang jelas, contoh `module.action`.
2. Tentukan role bawaan yang menerima permission.
3. Tambahkan pengecekan `hasPermission()` pada Resource atau service.
4. Tambahkan test user yang memiliki dan tidak memiliki permission.
5. Jalankan seeder pada environment yang benar.

### Contoh pola code

```text
tickets.view
tickets.create
tickets.manage
worklogs.view
worklogs.manage
legal-tasks.approve
attendance.view
attendance.upload
attendance.manage
users.manage
```

### Aturan penamaan

- Gunakan huruf kecil.
- Gunakan titik untuk pola `module.action`.
- Jangan mengganti code permission yang sudah live tanpa migration/data update.
- Label dapat berubah, tetapi code adalah identifier program.

## 4. Checklist perubahan akses

Setiap perubahan User/Role/Permission harus diuji dengan minimal actor berikut:

| Actor | Yang perlu diuji |
| --- | --- |
| Super Admin (`is_admin`) | akses penuh dan perlindungan self-delete |
| System Administrator role | bypass permission sesuai model |
| Department Manager | scope home dan accessible department |
| Supervisor | batas manage dibanding Manager |
| Requester/user biasa | hanya data sendiri atau yang ditugaskan |
| User tanpa Employee | perilaku akses yang aman |
| Employee nonaktif | panel harus ditolak |

Untuk setiap actor, uji:

- menu tampil/tidak tampil;
- URL langsung boleh/tidak;
- daftar data sesuai scope;
- create/edit/delete;
- request backend langsung;
- data department lain tetap terlindungi.

## 5. Jangan hanya mengandalkan sidebar

Menyembunyikan menu tidak mengamankan URL.

Pengamanan yang benar harus mencakup:

```text
shouldRegisterNavigation()  -> hanya tampilan sidebar
canViewAny()                -> akses halaman daftar
canView(record)             -> akses detail record
canCreate()                 -> akses create
canEdit(record)             -> akses edit
canDelete(record)           -> akses delete
getEloquentQuery()          -> pembatasan data yang terlihat
model/service guard         -> perlindungan perubahan backend
```

Semua lapisan yang relevan harus diperiksa saat permission berubah.
