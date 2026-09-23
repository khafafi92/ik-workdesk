# Perbaikan keselarasan Dashboard /dashboard

22 September 2026. Lingkup mengikuti screenshot pengguna: garis header, tepi konten, latar judul, dan kolom panel. Halaman ini memakai layout Blade yang berbeda dari Dashboard Filament `/panel`.

## Temuan dan perbaikan

1. Area logo sidebar setinggi 84,8 px, sedangkan topbar 80 px. Keduanya sekarang memakai variabel tinggi yang sama; padding vertikal brand tidak lagi menambah tinggi. Hasil pengukuran browser: keduanya berakhir pada y=80.
2. Teks topbar dimulai pada x=304, judul/konten pada x=316. Keduanya sekarang memakai variabel gutter yang sama. Hasil desktop: x=316; ponsel: x=16.
3. Judul tanpa padding mendapat latar putih dari override permukaan panel. Selector judul dikeluarkan dari override tersebut sehingga latar kembali mengikuti kanvas halaman.
4. Jarak dua panel ringkasan 12 px berbeda dari dua tabel 16 px. Jarak disamakan menjadi 16 px; breakpoint satu kolom ringkasan mengikuti tabel. Batas kolom desktop sekarang sama: 316–892,4 dan 908,4–1484,8 px.

Perubahan hanya pada `public/css/workdesk-dashboard-polish.css` dan `public/css/workdesk-upgrade.css`. Aturan shell dipakai bersama halaman Blade admin. Tidak mengubah query, permission, isi data, atau tujuan kontrol.

## Verifikasi

- Chrome terhubung: reload aplikasi dan inspeksi screenshot serta bounding rectangles pada lebar 1536, 1024, dan 375 px. Tidak ada overflow horizontal halaman pada ketiga ukuran; ringkasan dan tabel memakai kolom yang konsisten.
- Viewport browser dikembalikan ke ukuran semula setelah pemeriksaan.
- `MinimalDashboardTest`: 2 tes, 20 assertion, lulus; mencakup render `/panel` dan `/dashboard`.
- Kedua CSS lolos parser PostCSS. `git diff --check` lulus. CSS disajikan langsung dari `public/` dan hasil perubahan diverifikasi melalui reload browser.
- Log console menyimpan error Alpine `$persist` bertimestamp 11:12:00 UTC dari script Filament. Dokumen `/dashboard` yang diperiksa hanya memuat Vite dan `resources/js/app.js`; catatan lama tersebut tidak dipakai sebagai klaim bahwa console seluruh aplikasi bersih.

## Delivery Gate untuk perubahan keselarasan

- **Hard Gate: PASS untuk geometri yang diperbaiki**, dengan ukuran header, gutter, batas kolom, dan overflow diukur langsung. Audit aksesibilitas menyeluruh halaman `/dashboard` tidak termasuk pemeriksaan ini.
- **Purpose-Gate: PASS untuk perubahan**, setiap perubahan ukuran/latar menghilangkan ketidaksejajaran yang terukur; tidak menambahkan dekorasi atau aset.
- **Liveliness: arah yang ada dipertahankan**, Dashboard operasional internal dengan ENERGY 1 / RHYTHM 1 / MOTION 1; tidak merancang identitas baru.
- **Craftsmanship: PASS untuk cakupan layout**, screenshot tiga ukuran dan tes render mendukung hasil. Seluruh aksi aplikasi, seluruh keadaan data, dan audit keyboard tidak diuji ulang pada perbaikan CSS ini.
