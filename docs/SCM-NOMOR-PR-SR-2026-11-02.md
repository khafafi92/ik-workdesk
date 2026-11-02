# Nomor PR/SR HO dan Project

Menu **Permintaan > Nomor PR/SR** menampilkan nomor, perusahaan, jenis PR/SR, HO/Project, pembuat, tanggal terbit, keterangan kebutuhan, dan status. Pencarian mencakup nomor, judul, dan alasan kebutuhan. Filter tersedia untuk HO/Project, PR/SR, perusahaan, serta nomor terpakai atau nomor yang sudah terbit tetapi draft belum disimpan.

Nomor diterbitkan saat pengguna membuka form baru. Nomor yang belum disimpan tetap tercatat dan tidak digunakan ulang. Setelah disimpan, nomor yang sama terhubung ke permintaan. Perubahan rincian draft mempertahankan nomor tersebut.

## Penomoran

- HO mempertahankan format dan urutan perusahaan yang sudah ada: misalnya `001/APCA-HO/PR/XI/2026` atau `001/KPMOG-OFC/PR/XI/2026`.
- Project menggunakan `urut/kode Project/PR atau SR/bulan Romawi/tahun`. Contoh LTRO: `001/COMP-19E001/PR/XI/2026` dan `001/COMP-19E001/SR/XI/2026`.
- Urutan Project terpisah dari HO, per kode Project, PR/SR, dan tahun. Pergantian bulan tidak mengulang urutan. Kode Project yang sama menggunakan urutan bersama antarperusahaan karena perusahaan tidak menjadi bagian nomor Project.
- Nomor lama tidak ditulis ulang. Permintaan Project lama yang memakai format HO tetap menyimpan nomor aslinya dan muncul sebagai Project dalam riwayat.

## Master dan pembuatan permintaan

Pengelola berizin `scm.configure` membuka menu **Nomor PR/SR**, kemudian bagian **Master kode Project**. Master menyimpan kode Project satu kali agar bisa dipakai berulang kali saat membuat PR/SR. Form pembuat kode mengikuti dokumen KPMOG PROJECT CODE Rev.4 yang disediakan pengguna.

Untuk Project baru, pilih **Format KPMOG**, kemudian isi:

| Isian master | Aturan | Contoh dari Rev.4 |
| --- | --- | --- |
| Singkatan Project | Empat huruf singkatan; dapat memakai pembeda setelah tanda hubung | UBAD, BMTH-LNG, BMTH-BBM |
| Tahun awarded | Isi empat digit; kode memakai dua digit terakhir | 2023 menjadi 23 |
| Jenis pekerjaan | SP untuk Supply, SC untuk Services, SPC untuk Supply & Services | SPC |
| Nomor urut Project | Isi 1 sampai 999 sesuai register; disajikan tiga digit | 1 atau 001 menjadi 001 |

Isi nama Project, periksa **Kode Project yang akan disimpan**, lalu tekan **Simpan master**. Contoh `UBAD` + `2023` + `SPC` + `1` menghasilkan `UBAD/23/SPC/001`. Saat mengedit, komponen kode akan terisi kembali. Nomor urut Project diisi pengelola; sistem tidak menetapkan aturan reset tahunan atau per jenis pekerjaan yang tidak dijelaskan dalam PDF.

Pilih **Kode lama / khusus** untuk kode yang sudah ditetapkan seperti **LTRO / COMP-19E001**. Kode LTRO dari migrasi sebelumnya tetap sama. Pengelola dapat mengedit nama atau menonaktifkan master untuk penerbitan nomor berikutnya. Kode harus unik, tidak boleh sama dengan awalan nomor HO, dan tidak dapat diganti setelah dipakai menerbitkan PR/SR.

Untuk membuat permintaan, buka PR atau SR, pilih Project, pilih master nomor aktif, lalu pilih perusahaan. Nama Project operasional dari **Master Data > Projects** tetap opsional pada panel alur di luar lembar dokumen. Master nomor mengatur kode penomoran; pilihan Project operasional tetap mengikuti akses departemen yang sudah ada.

Dengan master `UBAD/23/SPC/001`, contoh nomor PR adalah `001/UBAD/23/SPC/001/PR/XI/2026`. Nomor urut dokumen PR/SR dibuat otomatis menggunakan urutan dokumen yang sudah ada. Tahun awarded tetap menjadi bagian kode Project, sedangkan tahun di akhir nomor PR/SR mengikuti penerbitan dokumennya.

Jenis kebutuhan dan master nomor dikunci setelah nomor diterbitkan agar nomor HO tidak digunakan untuk Project atau sebaliknya. Untuk kebutuhan dengan kode lain, buat permintaan baru. Penandatangan, alur persetujuan, dan lembar cetak tetap mengikuti aturan yang sudah ada.

## Akses

Pemohon berizin `scm.request` melihat nomor miliknya, termasuk nomor yang belum memiliki draft tersimpan. Pemroses berizin `scm.process` melihat nomor permintaan yang sudah masuk alur SCM dan sesuai cakupan departemennya. Izin konfigurasi mengizinkan perubahan master, tanpa menambah hak melihat rincian permintaan pengguna lain. Pemeriksaan izin juga berjalan pada aksi Livewire.

## Penerapan

Migrasi `database/migrations/2026_11_02_100000_add_scm_project_numbering.php` menambah master dan urutan Project, metadata nomor, serta mendaftarkan permintaan lama yang belum ada dalam register. Nomor, urutan HO, dan isi permintaan lama dipertahankan.

Migrasi khusus ini sudah diterapkan pada PostgreSQL lokal setelah pencadangan. Perbandingan data memastikan permintaan, item, counter HO, persetujuan, CBE, pengguna, karyawan, peran, izin, serta atribut nomor terbit sebelumnya tetap sama. Bukti tersimpan di `output/request-numbering-20261102/local-activation.json`; cadangan lokal berada di `tmp/request-numbering-20261102/before-project-numbering.dump`.

Penerapan ke server belum dilakukan. Saat merilis, sertakan kode dan aset hasil `npm run build`, lalu jalankan migrasi tersebut saja melalui akun migrasi yang sesuai bila belum terpasang. Pembaruan form pembuat kode berdasarkan Rev.4 tidak memerlukan migrasi tambahan. Akun aplikasi tidak perlu diberi hak membuat tabel. Rollback ditolak setelah master Project menerbitkan nomor.

## Verifikasi

Pengujian mencakup urutan HO/Project, PR/SR, Project lain, lintas perusahaan, pergantian bulan dan tahun, penyimpanan ulang, penolakan penggunaan nomor yang sama dua kali, rollback saat draft gagal, migrasi nomor lama, pembatasan akses, serta konflik versi master. Uji PostgreSQL menggunakan database sementara yang terpisah untuk dua pengajuan bersamaan. Pengujian browser menggunakan data DEMO pada SQLite terpisah.

Lihat [laporan delivery anti-slop](../anti-slop/delivery-nomor-pr-sr-2026-11-02.md) untuk bukti pemeriksaan tampilan dan interaksi.
