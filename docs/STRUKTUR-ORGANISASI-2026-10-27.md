# Pemetaan organisasi dan persetujuan PR/SR

Sumber: dua gambar Struktur Organisasi APCA GROUP yang dikirim pengguna pada 27 Oktober 2026. Nama disalin sesuai yang tertulis pada bagan; singkatan tidak diperluas berdasarkan tebakan. Hubungan yang belum jelas tidak diaktifkan sebagai aturan approval.

Pengguna mengonfirmasi bahwa **Jerry pada akun BD adalah Yerri Intarto**, Business Development Manager KPMOG. Pengguna juga menetapkan bahwa staf yang memiliki SPV dan Manager harus memperoleh **persetujuan SPV lalu Manager**.

Pengguna menegaskan agar form yang sudah ada tidak diubah. Lembar PR/SR mengikuti format Excel yang telah disepakati: susunan, isian, label, dan empat kolom tanda tangan dipertahankan. Pemetaan organisasi, tahap persetujuan SPV, serta informasi status ditempatkan di master dan panel alur di luar lembar formulir.

## Pimpinan group

| Jabatan | Pemegang pada bagan |
| --- | --- |
| Chairman | Zulfan Zahar |
| Vice Chairman | Budhy Riyadi |
| COO I | Aditya Wardana Y |
| COO II | Lenaldy Nuari G |
| COO III | Budhy Riyadi |
| CBO | Pradipta Aji A |
| Internal Audit | Nama pemegang tidak ditampilkan |

CBO dan COO I/II/III setara sebagai jabatan Management. Urutan CBO sebelum COO pada persetujuan pengadaan merupakan aturan proses. Budhy adalah satu orang yang merangkap Vice Chairman dan COO III; nama jabatan lengkap dapat dicatat pada Position, sedangkan jenjang utama dan penugasan approval dicatat sesuai konteks. Pemetaan ini tidak memerlukan akun Budhy kedua.

Jenjang Direktur tetap tersedia sesuai permintaan pengguna, tetapi pemegangnya tidak ditampilkan pada dua bagan yang diterima.

## Cabang di bawah CBO

| Manager/posisi | Pemegang | Bawahan yang terlihat pada bagan rinci |
| --- | --- | --- |
| FAT Manager 1 | Adhitia W | FAT Officer 1: Megawati |
| FAT Manager 2 | Agung K | FAT Officer 2: Arief |
| Commercial Finance Specialist | M. Reyhan R | Bawahan tidak ditampilkan |
| Legal Manager 1 | Nasyila A | Cabang Legal Officer: Dean, Audrey |
| Legal Manager 2 | Erfinda A | Penetapan officer per Manager perlu dipastikan saat pemetaan akun |
| HR Manager | Herlinda | HR Dev Specialist: vacant; HR Service Specialist: Furgoan; HR Officer: Salsha |
| GA & Purchasing Manager | Fadhila | GA Officer: Irfan; Purchasing Officer: Indah |
| Procurement Manager 1 | Hevy Tri P | Procurement SPV 1: Wiwin; Procurement Officer 1: vacant |
| Procurement Manager 2 | Vacant | Procurement SPV 2: Meyfi; Procurement Officer 2: Iman R |
| IT Manager | Riyan Khafafi | IT Officer: Fajar |

Pada cabang GA juga terlihat Messenger Zainal, Driver Arisman/Decan/Budiman/Deri/Rizal, serta Office Support Rudy/Ahmad. Garis atasan langsung untuk kelompok ini perlu dicocokkan saat memasukkan orang ke master; tidak ditetapkan otomatis hanya dari urutan kotak.

## Cabang GM dan operasional

| Posisi pada bagan | Pemegang |
| --- | --- |
| Plt. GM Technic & Operation ATE | Farid Afandi |
| GM Business Development ATE | Beny Yusron |
| GM Technic & Operation KPMOG | Fandi Muhammad |
| GM Business Development KPMOG | Vacant |
| GM APCA Nusantara Konstruksi | Aqsha Prathinta |
| GM Arkan Tambang Mandiri | Vacant |
| GM Arkan Trans Continental | Dony Ari Nugroho |
| GM PSW Antasari Hotel | Yoshie S Anwar |

Cabang Business Development KPMOG memuat Business Development Manager **Yerri Intarto/Jerry**, Tender Manager yang vacant, dan Stakeholder Relation Manager Ayip Idris. Anggi tidak tertulis pada dua gambar ini; hubungan **Anggi → Jerry** berasal dari konfirmasi pengguna dan data akun di server.

Pada cabang operasional terlihat beberapa posisi Project Management Manager, Engineer, QC Coordinator, serta pengelola hotel. Mereka perlu dipetakan menurut nama pemegang dan garis jabatan pada cabangnya; label Manager/GM/Direktur tidak otomatis memberi izin menu atau persetujuan semua departemen.

## Aturan persetujuan yang telah dikonfirmasi

| Kondisi pembuat | Urutan awal |
| --- | --- |
| Staf dengan SPV dan Manager | Requested by → Persetujuan SPV → Acknowledged by Manager |
| Staf langsung di bawah Manager | Requested by → Acknowledged by Manager |
| SPV mengajukan sendiri | Requested by SPV → Acknowledged by Manager |
| Manager yang ditetapkan dalam rute mengajukan sendiri | Mengikuti pengecualian Manager yang sudah berlaku; Acknowledged by tidak ditandatangani sendiri |

Setelah itu berlaku tahap CBO dan COO sesuai pilihan pengajuan di atas Rp5 juta dan rute SCM. Persetujuan SPV wajib dan tidak dapat dilewati. Kolom kertas tetap empat: Requested by, Acknowledged by, Crosschecked by, Approved by. Persetujuan SPV dicatat dalam riwayat sistem; tanda tangan Manager mengisi Acknowledged by.

Jabatan vacant memerlukan pejabat pengganti yang ditetapkan. Sistem menahan pengajuan jika Manager di atas SPV belum memiliki pemetaan akun aktif, sehingga pengajuan tidak langsung melompati Manager. Warna pada gambar menunjukkan tetap/kontrak/vacant dan tidak menentukan hak akses.

## Temuan PR Anggi di server

Pemeriksaan dilakukan melalui transaksi PostgreSQL read-only pada aplikasi `10.1.20.97:8093`.

- Anggi: akun 12, Employee 11, departemen BD (6), jabatan BD Officer, level Requester.
- Jerry: akun 6, Employee 6, departemen BD (6), jabatan Business Development Manager, level Manager.
- PR `005/KPMOG-OFC/PR/X/2026`, ID 3, sudah ditandatangani pembuat tetapi masih `draft`, round 0, tanpa baris penugasan approval.
- Rute yang ada hanya APCA/KPMOG untuk IT; rute KPMOG/BD belum tersedia.

## Perubahan yang disiapkan di lokal

Master Employee mempunyai Jenjang Jabatan dan Atasan Langsung. Pemetaan per orang dipakai untuk menentukan SPV dan Manager, dengan rute per perusahaan/departemen tetap menentukan CBO/COO. Akun lama tanpa pemetaan tetap mengikuti atasan rute SCM yang sudah ada.

Pilihan jenjang mencakup Staff, SPV, Manager, GM, Direktur, CBO, COO I/II/III, Chairman, Vice Chairman, dan Spesialis. Nama Position tetap tersedia untuk jabatan spesifik atau rangkap. Jenjang ini tidak mengubah level akses, role, atau checklist menu secara otomatis.

Form menolak hubungan atasan yang menunjuk diri sendiri, membentuk putaran, atau memilih atasan baru yang tidak aktif. Penghapusan atasan dengan bawahan masih terhubung ditahan. Penugasan approval yang sudah terbentuk tidak dialihkan ketika master atasan berubah.

Draft memperlihatkan tujuan persetujuan dan alasan jika rute belum lengkap. Menu Persetujuan PR/SR mempunyai angka hanya untuk pengajuan yang sedang menjadi giliran akun tersebut.

## Penerapan awal yang perlu ditinjau

1. Terapkan dua field baru pada Employee dan kode alur yang telah diuji.
2. Petakan Anggi sebagai Staff dengan atasan Jerry; Jerry sebagai Manager, dengan akun yang sudah ada.
3. Lengkapi rute KPMOG/BD: atasan Jerry dan CBO yang ditetapkan. Pada konfigurasi KPMOG yang ada, CBO bernama pradipta; gunakan akun tersebut jika penetapan BD sama.
4. Setelah pemetaan lengkap, Anggi membuka PR yang sama lalu menekan Ajukan persetujuan HO. PR dan tanda tangan pembuatnya tetap dipertahankan; tidak dibuat pengajuan pengganti atau persetujuan atas nama orang lain.
5. Pemetaan SPV/Manager cabang lain dilakukan berdasarkan bagan dan penetapan pejabat untuk jabatan vacant.

Pemeriksaan serta hasil uji: `output/employee-hierarchy-20261027/verification.md`. Perubahan ini belum diterapkan ke server produksi pada tahap persiapan ini.
