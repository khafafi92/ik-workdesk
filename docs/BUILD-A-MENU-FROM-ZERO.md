# Membuat Menu Baru dari Nol

Panduan ini ditujukan untuk pembaca yang belum terbiasa dengan Laravel dan Filament. Contoh yang dipakai adalah menu `Knowledge Articles`, tetapi polanya sama dengan seluruh menu Resource pada IK WorkDesk.

Jangan mulai dari warna atau tombol. Urutan yang benar adalah menentukan data, membuat database, membuat Model, baru membuat tampilan dan aturan akses.

## 1. Gambaran hasil yang akan dibuat

Menu contoh memiliki data berikut:

| Field | Kegunaan |
| --- | --- |
| `title` | judul artikel |
| `description` | isi atau penjelasan artikel |
| `is_active` | menentukan artikel aktif |
| `created_by_user_id` | menyimpan pembuat artikel |

Alur sistemnya:

```text
Klik Knowledge Articles pada sidebar
    -> KnowledgeArticleResource.php memilih Page
    -> ListKnowledgeArticles.php membuka tabel
    -> KnowledgeArticlesTable.php menyusun kolom

Klik Create
    -> CreateKnowledgeArticle.php menangani penyimpanan
    -> KnowledgeArticleForm.php menyusun field
    -> KnowledgeArticle.php menyimpan data
    -> tabel knowledge_articles pada database
```

## 2. Kenali folder yang akan dibuat

Struktur akhirnya kurang lebih seperti berikut:

```text
app/
|-- Models/
|   `-- KnowledgeArticle.php
`-- Filament/Resources/KnowledgeArticles/
    |-- KnowledgeArticleResource.php
    |-- Pages/
    |   |-- ListKnowledgeArticles.php
    |   |-- CreateKnowledgeArticle.php
    |   `-- EditKnowledgeArticle.php
    |-- Schemas/
    |   `-- KnowledgeArticleForm.php
    `-- Tables/
        `-- KnowledgeArticlesTable.php

database/
|-- migrations/
|   `-- xxxx_xx_xx_xxxxxx_create_knowledge_articles_table.php
`-- seeders/
    `-- AccessControlSeeder.php

tests/Feature/
`-- KnowledgeArticleAccessTest.php
```

Tanggung jawabnya:

| File | Tanggung jawab |
| --- | --- |
| Migration | bentuk tabel database |
| Model | hubungan dan perilaku data |
| Resource | nama menu, icon, akses, dan rute Page |
| Schema Form | field, label, deskripsi, validasi input |
| Table | kolom, pencarian, filter, dan tombol per record |
| Page | proses halaman dan hook sebelum/sesudah Save |
| Seeder | daftar permission bawaan |
| Test | membuktikan fungsi dan akses tetap benar |

## 3. Buat branch sebelum bekerja

```bash
git status
git switch -c feature/knowledge-articles
```

Pastikan `git status` dipahami terlebih dahulu. Jangan menghapus perubahan orang lain yang sudah ada.

## 4. Buat Model dan Migration

Jalankan:

```bash
php artisan make:model KnowledgeArticle -m
```

Perintah ini membuat Model dan Migration. Buka Migration baru di `database/migrations`, kemudian definisikan kolom:

```php
Schema::create('knowledge_articles', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->foreignId('created_by_user_id')
        ->constrained('users')
        ->cascadeOnUpdate()
        ->restrictOnDelete();
    $table->timestamps();
});
```

Arti bagian penting:

- `nullable()` berarti field boleh kosong;
- `default(true)` memberi nilai awal;
- `foreignId()` membuat hubungan ke tabel lain;
- `restrictOnDelete()` mencegah user pembuat dihapus jika masih digunakan;
- `timestamps()` membuat `created_at` dan `updated_at`.

Jalankan Migration pada database development:

```bash
php artisan migrate
```

Jangan menjalankan `migrate:fresh` pada server live karena perintah tersebut menghapus seluruh tabel.

## 5. Isi Model

Buka:

```text
app/Models/KnowledgeArticle.php
```

Contoh struktur:

```php
class KnowledgeArticle extends Model
{
    protected $fillable = [
        'title',
        'description',
        'is_active',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
```

Model bukan tempat menyusun warna atau posisi field. Model digunakan untuk data, relasi, cast, scope, dan aturan yang melekat pada record.

## 6. Buat Filament Resource

Lihat bantuan command yang tersedia pada versi project:

```bash
php artisan help make:filament-resource
```

Kemudian buat Resource:

```bash
php artisan make:filament-resource KnowledgeArticle
```

Jika generator menghasilkan struktur berbeda, ikuti pola Resource yang sudah ada pada project. Resource sederhana yang paling mudah dicontoh adalah:

```text
app/Filament/Resources/ActivityCategories/
```

## 7. Atur nama menu dan rute Page

Buka:

```text
app/Filament/Resources/KnowledgeArticles/KnowledgeArticleResource.php
```

Bagian Resource harus menjawab:

- Model apa yang digunakan?
- Nama apa yang muncul di sidebar?
- Icon apa yang digunakan?
- Menu masuk kelompok apa?
- Siapa yang boleh membuka?
- Page apa yang menangani daftar, create, dan edit?

Pola penting:

```php
protected static ?string $model = KnowledgeArticle::class;

protected static ?string $navigationLabel = 'Knowledge Articles';

public static function getNavigationGroup(): ?string
{
    return 'Master Data';
}

public static function form(Schema $schema): Schema
{
    return KnowledgeArticleForm::configure($schema);
}

public static function table(Table $table): Table
{
    return KnowledgeArticlesTable::configure($table);
}

public static function getPages(): array
{
    return [
        'index' => ListKnowledgeArticles::route('/'),
        'create' => CreateKnowledgeArticle::route('/create'),
        'edit' => EditKnowledgeArticle::route('/{record}/edit'),
    ];
}
```

Resource adalah pintu masuk menu. Field jangan ditumpuk di sini jika sudah mempunyai class `Schemas`. Kolom tabel jangan ditumpuk di sini jika sudah mempunyai class `Tables`.

## 8. Susun Form

Buka atau buat:

```text
app/Filament/Resources/KnowledgeArticles/Schemas/KnowledgeArticleForm.php
```

Contoh:

```php
class KnowledgeArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Judul')
                ->helperText('Tuliskan judul yang mudah dicari.')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->label('Deskripsi')
                ->rows(6)
                ->columnSpanFull(),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
        ]);
    }
}
```

Di file Form inilah orang awam dapat mengubah:

- `label()` untuk nama field;
- `helperText()` untuk keterangan di bawah field;
- `placeholder()` untuk contoh isi;
- `required()` untuk field wajib;
- `maxLength()` untuk panjang maksimal;
- `visible()` atau `hidden()` untuk kondisi tampilan;
- `disabled()` untuk kondisi field tidak dapat diubah.

Validasi tampilan tetap harus didukung aturan backend bila menyangkut keamanan atau permission.

## 9. Susun Tabel Daftar

Buka atau buat:

```text
app/Filament/Resources/KnowledgeArticles/Tables/KnowledgeArticlesTable.php
```

Contoh:

```php
class KnowledgeArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh'),

                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
```

Gunakan `searchable()` bila kolom perlu dicari dan `sortable()` bila perlu diurutkan. `creator.name` bekerja karena Model mempunyai relasi `creator()`.

## 10. Atur proses Create dan Edit

File Page berada di:

```text
app/Filament/Resources/KnowledgeArticles/Pages/
```

Jika pembuat record harus diisi otomatis, tambahkan hook pada `CreateKnowledgeArticle.php`:

```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    $data['created_by_user_id'] = auth()->id();

    return $data;
}
```

Jangan membuat field `created_by_user_id` yang bebas dipilih user biasa. Nilai kepemilikan seharusnya ditentukan backend.

Gunakan Page hook untuk proses yang hanya terjadi pada satu halaman. Jika aturan juga dipakai API, command, atau menu lain, pindahkan ke service di `app/Services`.

## 11. Tambahkan Permission

Contoh permission:

```text
knowledge_articles.view
knowledge_articles.create
knowledge_articles.manage
```

Daftarkan permission pada:

```text
database/seeders/AccessControlSeeder.php
```

Kemudian gunakan permission pada Resource, Page, atau Policy. Contoh konsep:

```php
public static function canViewAny(): bool
{
    return auth()->user()?->can('knowledge_articles.view') ?? false;
}

public static function canCreate(): bool
{
    return auth()->user()?->can('knowledge_articles.create') ?? false;
}
```

Aturan penting:

- sidebar tersembunyi bukan pengamanan;
- URL langsung juga harus ditolak;
- action Edit/Delete juga harus diperiksa;
- query harus dibatasi jika user hanya boleh melihat record tertentu.

## 12. Mengatur style

Gunakan komponen Filament terlebih dahulu agar tampilan konsisten. Jika benar-benar perlu CSS custom, gunakan:

```text
public/css/filament/admin/workdesk-ui-polish.css
```

Beri nama class yang khusus untuk fitur, misalnya:

```css
.ik-knowledge-card {
    border-radius: 0.75rem;
}
```

Jangan menggunakan selector terlalu umum seperti `button` atau `input`, karena dapat mengubah semua menu.

Jika UI mempunyai HTML khusus, buat Blade di `resources/views`, lalu letakkan function/backend di Page, Livewire component, atau service—bukan di Blade.

## 13. Tambahkan Test

Minimal test harus membuktikan:

1. user tanpa permission tidak dapat membuka menu;
2. user dengan permission dapat membuka daftar;
3. data valid dapat dibuat;
4. field wajib ditolak bila kosong;
5. pembuat record diisi dari user login;
6. user yang tidak berhak tidak dapat membuka URL Edit langsung.

Tempat test:

```text
tests/Feature/KnowledgeArticleAccessTest.php
```

Jalankan:

```bash
php artisan test
php vendor/bin/pint --test
```

Test bukan hanya untuk programmer berpengalaman. Test adalah daftar bukti otomatis bahwa aturan bisnis masih bekerja.

## 14. Hubungkan dengan menu yang sudah ada

Pilih contoh menu berdasarkan kebutuhan:

| Sistem yang ingin dibuat | Menu yang dipelajari |
| --- | --- |
| CRUD master sederhana | Activity Categories atau Departments |
| transaksi dengan workflow | Service Desk dan Work Logs |
| upload dan pengolahan file | Attendance Imports |
| kalender dan bentrok jadwal | Meeting/Vehicle Bookings |
| reminder dan notifikasi | Reminders |
| laporan read-only | Activity Reports atau Work Hour Records |
| role dan permission | User Management dan Role Management |
| UI custom dengan backend | Collaboration Room atau Calendar |

Detail lokasi setiap menu tersedia di [Dari Layar Menu ke File Kode](UI-TO-CODE-BY-MENU.md) dan [Panduan Detail Per Menu](menus/README.md).

## 15. Checklist sebelum dianggap selesai

- Migration mempunyai tipe data, index, dan foreign key yang benar.
- Model mempunyai fillable, cast, dan relasi yang diperlukan.
- Resource menunjuk Form, Table, dan Page yang benar.
- Form mempunyai label dan bantuan yang mudah dipahami user.
- Table mempunyai kolom, search, sort, filter, dan action yang sesuai.
- Create/Edit mengisi data otomatis pada backend.
- Permission melindungi sidebar, URL, query, dan action.
- CSS tidak merusak menu lain.
- Test fitur baru dan seluruh test suite berhasil.
- `git diff` hanya berisi perubahan yang memang dimaksud.
- Backup dan rencana rollback tersedia sebelum deployment live.

Dengan pola ini, menu baru tidak hanya terlihat benar, tetapi juga menyimpan data, membatasi akses, dan dapat dirawat dengan pola yang sama seperti seluruh IK WorkDesk.
