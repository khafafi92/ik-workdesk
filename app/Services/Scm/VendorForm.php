<?php

namespace App\Services\Scm;

class VendorForm
{
    public const TITLES = [1 => 'Data Perusahaan', 2 => 'Manajemen Mutu', 3 => 'Manajemen K3L / HSE', 4 => 'Manajemen Proses', 5 => 'Pernyataan'];

    public static function fields(int $step): array
    {
        return match ($step) {
            1 => [
                'name' => ['1.1.1 Nama lengkap perusahaan', 'text', true],
                'previous_name' => ['1.1.2 Nama perusahaan sebelumnya (jika ada)', 'text'],
                'address' => ['1.2.1 Alamat', 'textarea', true], 'city' => ['1.2.2 Kota', 'text', true],
                'province' => ['1.2.3 Provinsi', 'text', true], 'postcode' => ['1.2.4 Kode pos', 'text', true],
                'country' => ['1.2.5 Negara', 'text', true], 'phone' => ['1.2.6 Telepon perusahaan', 'text', true],
                'fax' => ['1.2.7 Fax (jika ada)', 'text'], 'email' => ['1.2.8 Email perusahaan', 'email', true],
                'website' => ['1.2.9 Website (jika ada)', 'url'],
                'ceo_name' => ['1.3.1 Nama pimpinan', 'text', true], 'ceo_email' => ['Email pimpinan', 'email', true],
                'ceo_phone' => ['Telepon pimpinan', 'text', true], 'pic_name' => ['1.3.2 Nama contact person', 'text', true],
                'pic_position' => ['Jabatan contact person', 'text', true], 'pic_email' => ['Email contact person', 'email', true],
                'pic_phone' => ['Telepon contact person', 'text', true],
                'supplied_before' => ['1.4.1 Pernah memasok barang/jasa kepada perusahaan tujuan?', 'choice', true],
                'supplied_description' => ['1.4.2 Barang/jasa yang pernah dipasok (jika ya)', 'textarea'],
                'has_amendments' => ['Apakah ada perubahan akta perusahaan?', 'choice', true],
                'non_pkp' => ['Apakah perusahaan Non PKP?', 'choice', true],
            ],
            2 => [
                'quality_system' => ['2.1 Memiliki sistem manajemen mutu terdokumentasi?', 'choice', true],
                'quality_standards' => ['2.2 Standar yang digunakan (jika ada)', 'textarea'],
                'quality_certified' => ['2.3 Sistem telah disertifikasi badan terakreditasi?', 'choice', true],
                'certifying_body' => ['2.4 Nama badan sertifikasi (jika tersertifikasi)', 'text'],
                'quality_expiry' => ['2.5 Tanggal berakhir sertifikat (jika tersertifikasi)', 'date'],
                'certification_progress' => ['2.6 Rencana/progres sertifikasi jika belum tersertifikasi', 'textarea'],
                'quality_remarks' => ['2.7 Informasi tambahan manajemen mutu', 'textarea'],
            ],
            3 => [
                'ohs_system' => ['3.1.1 Memiliki sistem manajemen K3 formal?', 'choice', true],
                'ohs_standards' => ['3.1.2 Standar/pedoman K3 yang digunakan', 'textarea'],
                'ohs_certified' => ['3.1.3 Sistem K3 telah disertifikasi?', 'choice', true],
                'health_policy' => ['3.1.4 Memiliki kebijakan kesehatan terdokumentasi?', 'choice', true],
                'drug_policy' => ['3.1.5 Memiliki kebijakan obat-obatan dan alkohol?', 'choice', true],
                'risk_assessment' => ['3.1.6 Memiliki prosedur identifikasi bahaya, penilaian risiko, dan pengendalian?', 'choice', true],
                'ohs_remarks' => ['3.1.7 Informasi tambahan K3', 'textarea'],
                'environment_system' => ['3.2.1 Memiliki sistem manajemen lingkungan terdokumentasi?', 'choice', true],
                'environment_standards' => ['3.2.2 Standar/pedoman lingkungan yang digunakan', 'textarea'],
                'environment_certified' => ['3.2.3 Sistem lingkungan telah disertifikasi?', 'choice', true],
                'environment_offence' => ['3.2.4 Pernah menerima sanksi atau teguran dari instansi terkait lingkungan?', 'choice', true],
                'environment_details' => ['Rincian masalah lingkungan (jika ya)', 'textarea'],
                'environment_remarks' => ['3.2.6 Informasi tambahan lingkungan', 'textarea'],
                'equipment_control' => ['3.3.1 Cara memastikan peralatan terdaftar, tersertifikasi, diinspeksi, dan dirawat dengan aman', 'textarea', true],
                'operator_licensed' => ['3.3.2 Operator memiliki lisensi K3 dan buku kerja sesuai peralatan?', 'choice', true],
            ],
            4 => [
                'receipt_inspection' => ['4.1 Memiliki sistem inspeksi saat penerimaan material?', 'choice', true],
                'nonconformity_system' => ['4.2 Memiliki sistem penanganan ketidaksesuaian?', 'choice', true],
                'process_notes' => ['4.3 Penjelasan prosedur coating, loading-unloading, dan JSA; jelaskan jika tidak relevan', 'textarea', true],
            ],
            5 => [
                'full_name' => ['5 Nama jelas pemberi pernyataan', 'text', true],
                'position' => ['Jabatan', 'text', true], 'date' => ['Tanggal pernyataan', 'date', true],
                'confirmed' => ['Saya menyatakan informasi dan dokumen yang disampaikan benar dan lengkap.', 'confirm', true],
            ],
            default => [],
        };
    }

    public static function documents(): array
    {
        return [
            'AKTA_PENDIRIAN' => ['1. Salinan Akta Pendirian Perusahaan', 1, 'always', false],
            'AKTA_PERUBAHAN' => ['2. Perubahan akta terakhir (jika ada)', 1, 'amendments', false],
            'IZIN_USAHA' => ['3. Perizinan SIUP/SUJK/UJK/SIUPAL yang masih berlaku', 1, 'always', true],
            'TDP' => ['4. Salinan Tanda Daftar Perusahaan (TDP)', 1, 'always', false],
            'DOMISILI' => ['5. Salinan Surat Keterangan Domisili', 1, 'always', false],
            'SERTIFIKAT_ASOSIASI' => ['6. Sertifikat asosiasi perusahaan/profesi yang masih berlaku', 1, 'always', true],
            'PERNYATAAN_HUKUM' => ['7. Pernyataan tidak dalam pengawasan pengadilan, tidak bangkrut, usaha tidak dihentikan, dan tidak menjalani sanksi pidana', 1, 'construction', false],
            'NPWP' => ['8. Salinan NPWP', 1, 'always', false],
            'PKP' => ['9. Salinan PKP', 1, 'pkp', false],
            'REFERENSI_BANK' => ['10. Surat referensi bank asli', 1, 'construction', false],
            'KTP' => ['KTP penanggung jawab', 1, 'always', false],
            'COMPANY_PROFILE' => ['Company Profile', 1, 'always', false],
            'DAFTAR_PENGALAMAN' => ['List of Experience / daftar pengalaman', 1, 'always', false],
            'KATALOG_PRODUK' => ['Catalogue Products / katalog produk', 1, 'always', false],
            'DAFTAR_REFERENSI' => ['1.4.4 Daftar referensi perusahaan', 1, 'always', false],
            'MANUAL_MUTU' => ['Manual / sistem manajemen mutu', 2, 'quality_system', false],
            'SERTIFIKAT_MUTU' => ['Sertifikat manajemen mutu', 2, 'quality_certified', true],
            'MANUAL_K3' => ['Kebijakan, manual, dan SOP K3', 3, 'ohs_system', false],
            'SERTIFIKAT_K3' => ['Sertifikat sistem K3', 3, 'ohs_certified', true],
            'KEBIJAKAN_KESEHATAN' => ['Kebijakan kesehatan', 3, 'health_policy', false],
            'KEBIJAKAN_ALKOHOL' => ['Kebijakan obat-obatan dan alkohol', 3, 'drug_policy', false],
            'PENILAIAN_RISIKO' => ['Prosedur penilaian risiko', 3, 'risk_assessment', false],
            'MANUAL_LINGKUNGAN' => ['Kebijakan, manual, dan SOP lingkungan', 3, 'environment_system', false],
            'SERTIFIKAT_LINGKUNGAN' => ['Sertifikat sistem lingkungan', 3, 'environment_certified', true],
            'KONTROL_PERALATAN' => ['Prosedur, bukti perawatan, dan surat izin peralatan (jika relevan)', 3, 'optional', false],
            'LISENSI_OPERATOR' => ['Lisensi operator K3', 3, 'operator_licensed', false],
            'PROSEDUR_COATING' => ['Instruksi kerja coating process (jika relevan)', 4, 'optional', false],
            'PROSEDUR_LOADING' => ['Instruksi kerja loading-unloading (jika relevan)', 4, 'optional', false],
            'JSA' => ['Job Safety Analysis / JSA (jika relevan)', 4, 'optional', false],
        ];
    }

    public static function required(string $code, array $answers, array $context): bool
    {
        $rule = self::documents()[$code][2];

        return match ($rule) {
            'always' => true, 'optional' => false,
            'amendments' => ($answers[1]['has_amendments'] ?? '') === 'yes',
            'pkp' => ($answers[1]['non_pkp'] ?? '') !== 'yes',
            'construction' => ($context['is_construction'] ?? false) && ($context['amount_minor'] ?? 0) > 10000000000 && ($context['duration_months'] ?? 0) > 3,
            default => ($answers[in_array($rule, ['quality_system', 'quality_certified']) ? 2 : 3][$rule] ?? '') === 'yes',
        };
    }
}
