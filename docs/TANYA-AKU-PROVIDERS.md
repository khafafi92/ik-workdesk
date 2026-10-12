# Penyedia AI untuk Tanya Aku

Tanya Aku mendukung OpenAI, DeepSeek, Claude, dan Gemini melalui API masing-masing. Tidak memerlukan Odysseus. Setiap penyedia memiliki satu ID model yang dapat diubah oleh administrator.

## Pengaturan administrator

1. Buka **Collaboration > Master Tanya Aku**.
2. Pilih penyedia AI.
3. Isi ID model yang tersedia pada akun penyedia, API key, dan batas token internal per bulan.
4. Aktifkan penyedia untuk pengguna. Pilih model default jika diperlukan.
5. Klik **Simpan pengaturan** sebelum beralih ke penyedia lain. Perubahan yang belum disimpan akan dibuang saat beralih.

API key disimpan menggunakan encrypted cast Laravel. Key yang sudah tersimpan tidak dikirim kembali ke halaman; field kosong mempertahankan key lama. Mengisi field tersebut mengganti key untuk penyedia yang dipilih. Pengaturan hanya tersedia bagi admin atau role `system-admin`.

Untuk Gemini, buat API key di [Google AI Studio](https://aistudio.google.com/apikey), lalu pilih **Gemini (Google)** di Master Tanya Aku. Isi ID model teks yang tersedia pada proyek Google tersebut, misalnya `gemini-3.8-flash` sesuai [daftar model Google](https://ai.google.dev/gemini-api/docs/models). Masukkan ID saja, tanpa awalan `models/` atau URL endpoint. Key Gemini terpisah dari key OpenAI, DeepSeek, dan Claude.

## Pemakaian pengguna

Di **Collaboration > Tanya Aku**, pilih penyedia dan model pada field **Model AI**. Hanya penyedia aktif yang sudah memiliki ID model, key, dan batas token yang muncul. Model default dipilih saat halaman dibuka. Jika default dinonaktifkan, penyedia aktif berikutnya menjadi default.

Riwayat dipisahkan per akun dan per penyedia. Mengganti penyedia tidak meneruskan riwayat dari penyedia sebelumnya. Jika administrator mengganti ID model dalam penyedia yang sama, riwayat penyedia itu tetap tersedia. Jawaban baru menyimpan ID model yang dipakai.

## Token dan biaya

Batas token internal berlaku per penyedia, untuk seluruh pengguna, dan mengikuti bulan pada zona waktu aplikasi. WorkDesk mencatat token input dan output dari jawaban berhasil yang memiliki respons lengkap. Claude juga menghitung token input cache jika dilaporkan API. Gemini menghitung token thinking sebagai output; token cache Gemini sudah termasuk input, sehingga tidak ditambahkan ulang. Isi thinking tidak ditampilkan sebagai jawaban.

Gemini menyediakan free tier untuk model tertentu dengan batas pemakaian. Jika proyek memakai paid tier, biaya mengikuti model dan pemakaian API Google. Lihat [harga Gemini API](https://ai.google.dev/gemini-api/docs/pricing) sebelum mengaktifkan billing.

Batas tersebut bukan saldo API atau batas tagihan resmi. WorkDesk memeriksa pemakaian sebelum mengirim permintaan; token input dan permintaan yang berjalan bersamaan dapat membuat total akhirnya melebihi batas. Gunakan pengaturan anggaran di akun penyedia untuk mengendalikan biaya API. WorkDesk tidak membaca saldo akun penyedia.

## Pembaruan instalasi

Migrasi `2026_10_12_000000_add_multiple_ai_providers.php` menambahkan default penyedia dan metadata pesan. Konfigurasi, key terenkripsi, serta pesan OpenAI lama dipertahankan. Pesan lama tetap dihitung sebagai OpenAI; ID model lama dibiarkan kosong karena tidak pernah dicatat.

```bash
php artisan migrate
```

Migrasi baru telah diterapkan pada PostgreSQL lokal saat implementasi. Instalasi di server lain tetap perlu menjalankan migrasi ketika perubahan ini dipasang.

## Integrasi

| Penyedia | Endpoint | Format |
| --- | --- | --- |
| OpenAI | `https://api.openai.com/v1/chat/completions` | Chat Completions, bearer key, `max_completion_tokens` |
| DeepSeek | `https://api.deepseek.com/chat/completions` | Chat Completions, bearer key, `max_tokens` |
| Claude | `https://api.anthropic.com/v1/messages` | Messages, `x-api-key`, `anthropic-version`, `system`, `max_tokens` |
| Gemini | `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` | Generate Content, `x-goog-api-key`, `systemInstruction`, `contents`, `generationConfig.maxOutputTokens` |

Referensi: [OpenAI Chat Completions](https://developers.openai.com/api/reference/resources/chat/subresources/completions/methods/create), [DeepSeek Chat Completions](https://api-docs.deepseek.com/api/create-chat-completion/), [Claude Messages](https://platform.claude.com/docs/en/build-with-claude/working-with-messages), [Gemini Generate Content](https://ai.google.dev/api/generate-content), [API key Gemini](https://ai.google.dev/gemini-api/docs/api-key).

Pengujian provider menggunakan respons API simulasi. Koneksi ke DeepSeek, Claude, atau Gemini belum diuji memakai key asli. Penambahan Gemini tidak membutuhkan migrasi lain setelah migrasi beberapa penyedia di atas terpasang.
