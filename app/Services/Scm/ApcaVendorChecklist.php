<?php

namespace App\Services\Scm;

class ApcaVendorChecklist
{
    public const PROFILE = 'apca_v3';

    public const TITLES = [1 => 'Data Perusahaan', 2 => 'Checklist Dokumen APCA', 5 => 'Pernyataan'];

    public const KINDS = ['barang' => 'Barang', 'konsultan' => 'Jasa Konsultan', 'kontraktor' => 'Jasa Kontraktor'];

    public const SCALES = ['kecil' => 'Kecil', 'besar' => 'Besar'];

    private const PROFILES = ['barang_kecil', 'barang_besar', 'konsultan_kecil', 'konsultan_besar', 'kontraktor_kecil', 'kontraktor_besar'];

    public static function applies(array $context): bool
    {
        return ($context['document_profile'] ?? null) === self::PROFILE;
    }

    public static function catalog(): array
    {
        // Matrix positions follow columns E–J. R = required, O = optional, - = not applicable.
        return [
            'A. Legalitas' => [
                'APCA_AKTA_PENDIRIAN' => ['Akta Pendirian Perusahaan + SK pengesahan Kemenkumham (AHU)', 'RRRRRR', 'Salinan lengkap, termasuk lampiran anggaran dasar.'],
                'APCA_AKTA_PERUBAHAN' => ['Akta Perubahan terakhir + SK persetujuan/penerimaan pemberitahuan Kemenkumham', 'RRRRRR', 'Jika ada perubahan setelah akta pendirian (modal, pengurus, alamat, maksud dan tujuan).'],
                'APCA_NIB' => ['Nomor Induk Berusaha (NIB)', 'RRRRRR', 'Dari OSS. KBLI harus sesuai bidang usaha yang didaftarkan.'],
                'APCA_PERIZINAN' => ['Perizinan Berusaha / Sertifikat Standar sesuai KBLI', 'RRRRRR', 'Izin operasional sesuai risiko usaha pada OSS.'],
                'APCA_SBU' => ['Sertifikat Badan Usaha (SBU) Jasa Konstruksi / Konsultansi Konstruksi', '--ORRR', 'Sesuai klasifikasi dan kualifikasi serta masih berlaku. Kontraktor wajib melampirkan SBU sesuai skala usaha. Bagi konsultan konstruksi, SBU opsional untuk skala kecil dan wajib untuk skala besar.'],
                'APCA_DATA_PERSEROAN' => ['Data susunan pengurus dan pemegang saham (SK data perseroan / AHU)', 'RRRRRR', 'Harus konsisten dengan akta terakhir.'],
            ],
            'B. Perpajakan' => [
                'APCA_NPWP' => ['NPWP Perusahaan', 'RRRRRR', 'Status aktif dan alamat sesuai.'],
                'APCA_PKP' => ['Surat Pengukuhan Pengusaha Kena Pajak (PKP)', 'OROROR', 'Untuk perusahaan berstatus PKP. Opsional untuk skala kecil dan wajib untuk skala besar.'],
                'APCA_NON_PKP' => ['Surat Pernyataan Non-PKP', 'OROROR', 'Untuk perusahaan non-PKP. Cantumkan alasannya dalam surat. Opsional untuk skala kecil dan wajib untuk skala besar.'],
                'APCA_SPT_TAHUNAN' => ['SPT Tahunan PPh Badan tahun terakhir + bukti penerimaan (BPE/NTPN)', 'RRRRRR', 'Tahun pajak terakhir yang wajib dilaporkan.'],
                'APCA_SPT_PPN' => ['SPT Masa PPN 3 bulan terakhir', 'OOOOOO', 'Dilengkapi bila berlaku atau diminta APCA. Hanya untuk PKP.'],
                'APCA_KETERANGAN_FISKAL' => ['Surat Keterangan Fiskal / bukti status Wajib Pajak aktif', 'RRRRRR', 'Untuk memastikan kepatuhan pajak penyedia.'],
            ],
            'C. Keuangan & Perbankan' => [
                'APCA_REKENING_KORAN' => ['Rekening koran perusahaan', 'RRRRRR', 'Menunjukkan aktivitas dan kesehatan keuangan.', ['kecil' => 'Lampirkan rekening koran 3 bulan terakhir.', 'besar' => 'Lampirkan rekening koran 6 bulan terakhir.']],
                'APCA_LAPORAN_KEUANGAN' => ['Laporan Keuangan (neraca dan laba rugi)', 'RRRRRR', 'Diaudit akuntan publik untuk nilai pekerjaan besar.', ['kecil' => 'Lampirkan laporan 1 tahun terakhir yang ditandatangani direksi. Audit tidak wajib.', 'besar' => 'Lampirkan laporan 2 tahun terakhir yang diaudit akuntan publik.']],
                'APCA_PERNYATAAN_PAILIT' => ['Surat pernyataan tidak pailit / tidak dalam pengawasan pengadilan / tidak dalam sengketa material', 'RRRRRR', 'Bermaterai dan ditandatangani direksi.'],
            ],
            'D. Kompetensi & Pengalaman' => [
                'APCA_COMPANY_PROFILE' => ['Company profile', 'RRRRRR', 'Struktur organisasi, bidang usaha, dan klien utama.'],
                'APCA_PENGALAMAN' => ['Daftar pengalaman pekerjaan sejenis + kontrak/BAST/surat referensi', 'ORRRRR', 'Sertakan bukti kontrak atau referensi pemberi kerja.', ['kecil' => 'Pengalaman 3 tahun terakhir.', 'besar' => 'Pengalaman 5 tahun terakhir dengan nilai kontrak sejenis sesuai persyaratan proyek.']],
                'APCA_PERSONEL' => ['Daftar personel inti + CV + ijazah + Sertifikat Kompetensi Kerja (SKK)', '--RRRR', 'Sesuai klasifikasi pekerjaan.', ['kecil' => 'Personel inti minimum sesuai pekerjaan.', 'besar' => 'Tenaga ahli lengkap per bidang dengan SKK sesuai jenjang.']],
                'APCA_PERALATAN' => ['Daftar peralatan utama + bukti kepemilikan/sewa', '---ORR', 'Foto, dokumen kepemilikan atau perjanjian sewa.', ['kecil' => 'Bukti sewa diperbolehkan.', 'besar' => 'Daftar peralatan lengkap dengan bukti kepemilikan atau sewa.']],
                'APCA_KEAGENAN' => ['Surat keagenan / distributor resmi dari prinsipal', 'RR----', 'Untuk penyedia barang non-produsen.'],
                'APCA_KATALOG' => ['Katalog produk / datasheet / spesifikasi teknis', 'RR----', 'Produk utama yang ditawarkan.'],
                'APCA_SERTIFIKAT_PRODUK' => ['Sertifikat produk (SNI, TKDN, dll.)', 'OO----', 'Sesuai jenis barang dan persyaratan proyek.'],
                'APCA_IZIN_IMPOR' => ['Izin impor (API-U / API-P)', 'OO----', 'Hanya untuk barang impor.'],
            ],
            'E. K3 & Ketenagakerjaan' => [
                'APCA_K3' => ['Sertifikat SMK3 / kebijakan K3 + Ahli K3 Umum', '----RR', 'Wajib untuk pekerjaan lapangan berisiko.', ['kecil' => 'Kebijakan K3 dan Ahli K3 Umum atau petugas K3.', 'besar' => 'Sertifikat SMK3 dan Ahli K3 Umum.']],
            ],
            'F. Pernyataan & Kepatuhan' => [
                'APCA_PAKTA_INTEGRITAS' => ['Pakta Integritas', 'RRRRRR', 'Bermaterai dan ditandatangani direksi.'],
                'APCA_KONFLIK_KEPENTINGAN' => ['Surat pernyataan tidak ada konflik kepentingan', 'RRRRRR', 'Termasuk hubungan afiliasi dengan karyawan atau pengurus APCA dan grup.'],
                'APCA_DAFTAR_HITAM' => ['Surat pernyataan tidak masuk daftar hitam (blacklist)', 'RRRRRR', 'Bermaterai.'],
                'APCA_ANTI_SUAP' => ['Pernyataan kepatuhan anti-suap, anti-korupsi, dan kode etik', 'RRRRRR', 'Persetujuan atas kebijakan APCA dan grup.'],
                'APCA_NDA' => ['Perjanjian kerahasiaan (NDA)', 'OOOOOO', 'Dilengkapi jika diwajibkan APCA.'],
            ],
        ];
    }

    public static function documents(array $answers = []): array
    {
        $documents = [];
        $scale = $answers[1]['apca_scale'] ?? '';
        foreach (self::catalog() as $group) {
            foreach ($group as $code => $definition) {
                [$name, , $help] = $definition;
                $documents[$code] = [$name, 2, '', $code === 'APCA_SBU', trim($help.' '.($definition[3][$scale] ?? ''))];
            }
        }

        return $documents;
    }

    public static function numberedGroups(array $answers): array
    {
        $number = 1;
        $groups = [];
        $nonPkp = ($answers[1]['non_pkp'] ?? '') === 'yes';

        foreach (self::catalog() as $title => $group) {
            foreach (array_keys($group) as $code) {
                if (($code === 'APCA_PKP' && $nonPkp) || ($code === 'APCA_NON_PKP' && ! $nonPkp)) {
                    continue;
                }

                $groups[$title][$number++] = $code;
            }
        }

        return $groups;
    }

    public static function requirements(array $answers): array
    {
        $company = $answers[1] ?? [];
        $index = array_search(($company['apca_kind'] ?? '').'_'.($company['apca_scale'] ?? ''), self::PROFILES, true);
        if ($index === false) {
            return [];
        }

        $requirements = [];
        foreach (self::catalog() as $group) {
            foreach ($group as $code => [, $matrix]) {
                $applies = match ($code) {
                    'APCA_AKTA_PERUBAHAN' => ($company['has_amendments'] ?? '') === 'yes',
                    'APCA_SBU' => ($company['apca_kind'] ?? '') !== 'konsultan' || ($company['construction_consultancy'] ?? 'yes') === 'yes',
                    'APCA_PKP', 'APCA_SPT_PPN' => ($company['non_pkp'] ?? '') === 'no',
                    'APCA_NON_PKP' => ($company['non_pkp'] ?? '') === 'yes',
                    'APCA_KEAGENAN' => ($company['is_producer'] ?? '') === 'no',
                    'APCA_IZIN_IMPOR' => ($company['imports_goods'] ?? '') === 'yes',
                    'APCA_K3' => ($company['high_risk_work'] ?? '') === 'yes',
                    default => true,
                };
                if ($applies && $matrix[$index] !== '-') {
                    $requirements[$code] = $matrix[$index] === 'R';
                }
            }
        }

        return $requirements;
    }
}
