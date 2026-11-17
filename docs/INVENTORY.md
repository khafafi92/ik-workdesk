# Inventori, permintaan PR/SR, dan pemrosesan pengadaan

Menu **Inventori > Inventori** menyatukan Master Barang, gudang HO/Project, saldo, penerimaan barang dari PR, unit aset/perangkat, register PR/SR, dan riwayat mutasi. Master Barang dipakai bersama lintas entitas; saldo dan transaksi KPMOG/APCA tetap terpisah. SR merupakan permintaan jasa dan tidak menambah stok barang.

## Master Barang dan gudang

Pilih tab Master Barang untuk menambah kode, nama, jenis ATK/material/perangkat, kategori, satuan, merek/model, ukuran, dan spesifikasi. Jenis tidak diubah setelah master dibuat; satuan yang sudah dipakai transaksi dipertahankan. Master Barang ATK otomatis memiliki referensi pada modul ATK. Route master ATK lama tetap tersedia untuk stok, kategori, satuan, minimum stok, dan penghitungan fisik, tetapi tidak menjadi menu master tambahan.

- Satu Master Barang menjadi referensi item PR, ATK, dan perangkat. Perangkat tetap memiliki catatan per unit untuk nomor aset, serial number, kondisi, lokasi, dan pemakai.
- Gudang HO tersedia untuk KPMOG dan APCA. Gudang Project dikaitkan dengan master Project dan ditampilkan sesuai nama Project serta entitas. Identitas gudang memakai referensi Project yang tetap agar perubahan nama tidak memisahkan riwayat stok. Sinkronkan gudang Project menyediakan konteks gudang untuk master Project aktif.
- Barang yang sama dapat berada di beberapa gudang. Saldo dicatat menurut barang, gudang, dan entitas yang berhak atas stok tersebut; saldo HO dan setiap Project tidak dijadikan satu saldo bersama.
- Pada PR, pilih Master Barang untuk mengisi nama, merek/model, spesifikasi, kode, dan satuan. Barang baru tetap dapat diusulkan dengan isian manual; petugas menghubungkannya ke master yang benar sebelum penerimaan gudang. Dokumen menyimpan salinan isian sehingga pengubahan master tidak mengubah isi PR lama. Persetujuan PR dan penerimaan tugas oleh SCM/Purchasing Officer tidak menambah stok barang.
- Alur penerimaan ATK dipertahankan: petugas menyerahkan barang tersedia, stok gudang berkurang saat penyerahan, lalu peminta mengonfirmasi jumlah yang sudah diserahkan. Konfirmasi menambah saldo departemen sesuai mekanisme yang sekarang. Penyerahan sebagian, jumlah belum dikonfirmasi, dan riwayatnya tetap berlaku.
- Penerimaan fisik dari vendor ke gudang dan konfirmasi penerimaan ATK oleh peminta merupakan dua pencatatan berbeda. Integrasi master dan gudang tidak membuat penerimaan ATK baru atau pencatatan stok ganda.
- Migrasi menambah referensi katalog untuk ATK dan perangkat lama tanpa mengubah nama, jumlah, nomor aset, serial number, atau pemakainya. Master perangkat lama tidak digabung hanya berdasarkan nama. Pemetaan beberapa unit ke satu jenis barang dilakukan melalui pilihan Master Barang pada edit perangkat; referensi lama yang tidak dipakai dapat dinonaktifkan oleh pengelola.

## Pemetaan saldo ATK lama

Saldo lama belum menyimpan kepemilikan entitas. Migrasi menempatkannya pada **Gudang HO / Saldo ATK lama**, dengan entitas kosong dan jumlah tetap. Saldo ini bukan stok bersama yang dapat dipakai KPMOG/APCA.

1. Buka **Stok per gudang** atau **Ringkasan ATK**.
2. Pilih **Petakan saldo ATK lama** atau **Periksa pemetaan entitas** pada barang terkait.
3. Isi jumlah yang benar-benar milik KPMOG dan APCA berdasarkan data gudang.
4. Simpan. Jumlah berpindah ke gudang HO masing-masing entitas; total ATK tidak berubah.

Pemetaan dapat dilakukan sebagian, tetapi tidak boleh melebihi sisa saldo lama. Selama masih ada saldo belum dipetakan untuk barang yang kekurangan, pengajuan PR kekurangan ditahan agar sistem tidak membeli ulang berdasarkan saldo yang belum jelas entitasnya. Penyerahan ATK memakai stok pada gudang sesuai entitas dan HO/Project permintaan.

## Penerimaan PR dan mutasi gudang

1. Petugas dengan akses **Kelola Inventori** membuka **Penerimaan barang** di Inventori, lalu memilih **Catat barang diterima di gudang** pada PR yang sudah memiliki PO. Tautan ke Inventori juga tersedia pada dokumen pengadaan.
2. Pilih gudang utama yang sesuai entitas dan HO/Project PR. Isi tanggal, nomor surat jalan/bukti penerimaan, dan jumlah fisik pada pengiriman ini. Pengiriman langsung ke Project dicatat di gudang utama Project.
3. Untuk baris lama yang belum memiliki master, pilih Master Barang dengan satuan yang sama. Baris yang belum dikirim diisi nol.
4. Simpan. Penerimaan terkait dengan PO dan PR; penerimaan sebagian diperbolehkan, dan jumlah kumulatif tidak boleh melebihi PR. Klik berulang dengan token transaksi yang sama tidak menggandakan stok. PO tidak dapat diubah setelah penerimaan tercatat.
5. Pada **Stok per gudang**, pilih saldo di gudang utama lalu **Catat mutasi** dan **Pindah ke gudang departemen asal**. Gudang departemen dibentuk sesuai departemen asal PR, entitas, dan HO/Project. Isi jumlah dan referensi perpindahan.
6. Petugas dengan izin **Distribusi gudang departemen** dapat memilih **Catat distribusi** pada saldo departemen yang dapat diaksesnya. Isi jumlah serta nama penerima atau departemen tujuan pada referensi. Distribusi mengurangi saldo gudang departemen dan tercatat pada riwayat mutasi. Pendaftaran unit perangkat dari stok tetap dapat dilakukan melalui modul IT.

PO pada modul pengadaan saat ini masih berstatus draft tersimpan; belum ada status penerbitan terpisah. Penerimaan mensyaratkan PO tersimpan tersebut. Penerimaan lama tanpa referensi PO tetap dipertahankan. Untuk permintaan ATK yang memakai penyerahan dan konfirmasi ATK yang sudah ada, lanjutkan proses ATK dari gudang asalnya; pemindahan ke gudang departemen tidak otomatis memenuhi permintaan ATK tersebut.

Saldo menyimpan departemen asal pencatatan untuk mempertahankan akses departemen. Ini tidak membuat alur persetujuan baru. Pengelola inventori dapat mencatat stok awal/masuk, stok keluar, atau transfer antar-gudang dalam entitas yang sama. Transfer biasa tidak memindahkan kepemilikan KPMOG ke APCA. Riwayat memperlihatkan jumlah, saldo sebelum/sesudah, pelaksana, dan referensi. Saldo dikelompokkan berdasarkan barang, gudang, dan departemen; mutasi dari saldo gabungan tidak ditautkan secara individual ke satu PR.

Untuk impor ATK atau stok awal melalui master ATK, gudang entitas wajib dipilih pada antarmuka. Quantity impor menjadi target saldo gudang tersebut. Actual lama tetap merupakan hasil pemeriksaan fisik ATK dan tidak dipecah otomatis per entitas atau gudang.

## Unit perangkat

Master Barang jenis Perangkat/aset mewakili jenis barang, bukan satu nomor aset. Pada pendaftaran perangkat, pilih master yang sama untuk unit sejenis. Jika unit berasal dari penerimaan gudang, pilih **Ambil unit dari stok gudang**; sistem mengurangi satu unit yang belum didaftarkan dan menyimpan referensi mutasinya. Entitas nomor aset, master, departemen, dan saldo diperiksa. Pendaftaran unit yang sama tidak dapat mengambil stok dua kali.

Pendataan perangkat lama tetap dapat dilakukan tanpa mengambil stok. Nomor aset, serial number, pemakai, kondisi, lokasi, pemeriksaan, dan perpindahan pemakai tetap mengikuti modul IT. Ruang server tidak menjadi stok barang. Gudang pada daftar aset menunjukkan asal pendaftaran; lokasi operasional tetap mengikuti data perangkat.

## Tampilan seluruh entitas

Izin **Lihat seluruh entitas** (`inventory.view-all`) menampilkan data inventori KPMOG dan APCA pada halaman yang sama, dengan filter entitas, HO/Project, dan gudang. Izin ini tidak memberikan kewenangan mengedit, menerima PR, atau mengeluarkan stok. Data vendor tetap memakai menu Data Vendor bersama yang sudah ada beserta filter entitasnya.

Izin **Kelola Inventori** (`inventory.manage`) mengelola seluruh master dan mutasi gudang serta penerimaan barang. Izin **Distribusi gudang departemen** (`inventory.department-issue`) hanya mengizinkan stok keluar dari gudang departemen yang dapat diakses pengguna. Izin ATK, IT, dan SCM yang sudah ada tetap menentukan pengelolaan jenis master masing-masing, akses unit perangkat, serta pemrosesan PR. Migrasi tidak memberikan izin baru kepada akun atau role tertentu.

## Permintaan ATK dan kekurangan stok

1. Peminta memilih entitas KPMOG/APCA dan kebutuhan HO atau Project. Project wajib memiliki master kode Project aktif.
2. Petugas menyerahkan barang melalui aksi penyerahan ATK yang sama. Stok gudang sesuai entitas dan kebutuhan berkurang sesuai jumlah yang diserahkan; peminta tetap mengonfirmasi penerimaan.
3. Petugas dengan izin `atk.manage` dan `scm.request` dapat memilih **Ajukan PR kekurangan**. Jumlah draft PR adalah sisa permintaan yang belum diserahkan dikurangi stok sistem saat pembuatan draft. Barang dengan stok cukup tidak masuk PR.
4. PR dibuat sebagai draft atas nama petugas yang menekan tombol. Departemen asal ATK dipakai jika petugas berhak mengaksesnya; jika tidak, PR memakai departemen petugas. Peminta ATK asal dan nomor ATK tetap tercatat sebagai referensi.
5. Periksa draft melalui menu PR. Lengkapi persetujuan, tanda tangan Requested by, dan ajukan melalui alur PR biasa. Pembuatan draft tidak memotong stok, mereservasi stok, atau melewati persetujuan.
6. Setelah barang hasil pengadaan benar-benar diterima di gudang, catat penerimaannya melalui Register PR/SR. Lanjutkan penyerahan sisa barang dari permintaan ATK yang sama. Jika penerimaan sudah dicatat melalui PR, jangan mencatat stok masuk yang sama lagi melalui transaksi manual ATK.

Satu permintaan ATK terhubung dengan paling banyak satu PR. Klik berulang mengembalikan PR yang sama. Jumlah asal konversi disimpan sebagai riwayat; petugas harus meninjau jumlah draft jika ketersediaan stok berubah. Draft tetap dapat diedit melalui alur PR biasa, sehingga jumlah akhirnya dapat berbeda dari jumlah awal konversi.

## Pengajuan dan pemrosesan

Semua pengajuan dibuat melalui **Permintaan > PR** dan **Permintaan > SR**. Peminta melihat permintaannya sendiri; superuser tetap dapat membuat permintaan atas nama pemohon dan melihat semua permintaan. Requestor menampilkan nama pengguna saja, sedangkan Department/Div tersedia di baris tersendiri. Form pengajuan tidak menampilkan tombol penerimaan atau pengeditan proses pengadaan.

Setelah seluruh persetujuan lengkap, dokumen yang sama tampil pada salah satu menu pemrosesan di kelompok **Pengadaan**:

- **Purchasing Officer**: PR/SR HO KPMOG/APCA. Nama ini menggantikan GA pada antarmuka proses.
- **SCM**: PR/SR Project sesuai entitas dan akses departemen petugas.

Petugas menerima permintaan melalui menu pemrosesan tersebut, lalu melanjutkan vendor, penawaran, CBE, legalitas, lampiran privat, dan audit. Tidak ada pembuatan PR/SR baru di menu pemrosesan. Peminta tetap dapat memantau tahap dan hasil final pada dokumen pengajuannya.

Rekap Material PR dan Data Vendor berada pada kelompok Pengadaan. Master vendor dan dokumennya dipakai bersama, sementara daftar permintaan dibatasi HO/Project, entitas, dan departemen. Tab Pengaturan pada menu pemrosesan tetap memerlukan izin konfigurasi. Draft dan permintaan yang belum selesai disetujui tidak masuk antrean pemrosesan, termasuk untuk superuser. Pemisahan kebutuhan juga diperiksa saat dokumen dibuka atau aksinya dipanggil secara langsung.

Akses **Rekap Material PR** diatur terpisah melalui User Management pada kelompok SCM. Centang akses ini bagi pengguna yang membutuhkan menu tersebut; izin PR/SR atau pemrosesan pengadaan saja tidak lagi membuka menu rekap. Isi rekap tetap mengikuti cakupan PR yang boleh dilihat pengguna. Setelah migrasi izin baru dijalankan, tetapkan akses ini untuk pengguna atau role yang berhak.

Atur pilihan berikut pada User Management setelah migrasi:

| Tim yang dimaksud | Pilihan akses | Cakupan proses |
| --- | --- | --- |
| Indah dan staf GA | Purchasing Officer (HO KPMOG/APCA) | HO kedua entitas |
| Meyfi, Imam, dan staf SCM KPMOG | Pengadaan Project KPMOG | Project KPMOG |
| Hevy dan staf SCM APCA | Pengadaan Project APCA | Project APCA |

Pilihan Purchasing Officer tetap memakai kode izin `ga.process` dan `scm.request`, sehingga pemetaan yang sudah ada tidak perlu diganti. Untuk mengelola ATK dan membuat PR kekurangannya, berikan juga akses pengelolaan ATK. Pilihan Project memberi `scm.process-kpmog` atau `scm.process-apca`. Tambahkan `scm.request` jika petugas juga perlu membuat pengajuan sendiri. Tetapkan departemen yang dapat diakses pengguna sesuai kewenangannya.

Izin lama `scm.process` tetap membuka kedua menu pemrosesan demi kompatibilitas. Antrean SCM tetap hanya Project dan Purchasing Officer tetap hanya HO. Untuk membatasi seorang petugas ke tim tertentu, ganti akses SCM lama dengan akses tim yang sesuai, dan periksa izin dari role maupun izin langsung. Migrasi tidak menambah izin kepada akun tertentu atau mencocokkan pengguna berdasarkan nama.

## Penerapan

Terdapat tiga migrasi bertanggal `2026_11_06`: konteks ATK/PR, izin tim, dan master inventori bersama. Buat backup sebelum menerapkannya pada lingkungan tujuan, jalankan migrasi, build frontend, dan perbarui cache aplikasi sesuai prosedur deployment. Migrasi ketiga menambah katalog, gudang, saldo, penerimaan, mutasi, serta referensi barang pada PR/ATK/perangkat. Saldo ATK lama tetap menunggu pemetaan entitas oleh petugas.

Migrasi `2026_11_11` menambahkan izin rekap, gudang departemen, referensi PO pada penerimaan baru, dan izin distribusi departemen. Berikan izin baru kepada pengguna yang berhak setelah migrasi; tidak ada pemberian otomatis.

Jika migrasi dijalankan dengan akun database admin, pastikan akun database aplikasi memperoleh SELECT/INSERT/UPDATE yang diperlukan pada tabel baru dan USAGE pada sequence-nya. Penerimaan dan mutasi yang sudah digunakan harus dipertahankan; rollback migrasi ditolak setelah transaksi baru ada. Tidak ada pemetaan izin akun secara otomatis.
