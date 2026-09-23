# Anti-slop di IK WorkDesk

Terpasang pada 21 September 2026, khusus untuk repository ini.

- Sumber: https://github.com/miqdadbadjuber/anti-slop
- Branch sumber: `main`
- Commit sumber: `eece033d2727b1357a996d02e3633b5f8cdffdf2`
- Metode: helper `skill-installer/install-skill-from-github.py`, dengan `--ref` commit di atas dan tujuan `.agents/skills`.
- Paket: `antislop`, `antislop-ui`, `antislop-copywriting`, `antislop-human`, `antislop-layoutmobile`, `antislop-code`.
- Lisensi upstream: [MIT](ANTISLOP-LICENSE.txt).

Skill disimpan di `.agents/skills`, lokasi skill repository yang didukung Codex menurut [dokumentasi OpenAI](https://learn.chatgpt.com/docs/build-skills). `AGENTS.md` di root mengarahkan agent ke aturan inti dan skill terkait. Tidak ada paket Composer/npm atau layanan MCP yang dipasang oleh instalasi ini.

## Penggunaan

Pada giliran berikutnya, minta agent menggunakan anti-slop untuk pekerjaan yang jelas. Contoh: "Gunakan anti-slop mode After untuk audit tampilan Dashboard dan laporkan temuannya." Permintaan audit tersebut belum meminta penerapan perubahan.

Untuk pekerjaan tampilan baru, tentukan mode During dan arah desain yang diinginkan. `DESIGN.md` belum dibuat karena pemasangan ini tidak menetapkan desain baru.

Jika skill belum muncul pada sesi agent, mulai sesi baru. Pemasangan tidak langsung mengubah tampilan atau fungsi IK WorkDesk.

## Pemeliharaan

Semua skill berasal dari commit yang sama. Saat memperbarui, tinjau perubahan upstream, perbarui keenam folder dari satu revisi, pertahankan lisensi, dan catat commit baru di dokumen ini. Jangan mencampur versi core dengan tambahan dari revisi berbeda.

Untuk menonaktifkan pada proyek ini, hapus hanya blok `antislop:start` sampai `antislop:end` pada `AGENTS.md` dan enam folder skill anti-slop. Pertahankan instruksi serta skill lain yang mungkin ditambahkan kemudian.

Pemeriksa kontras tersedia sebagai utilitas Python lokal di `skills/antislop-human/contrast-check.py`. Berkas `contrast-mcp.py` bawaan tersimpan tetapi tidak didaftarkan atau dijalankan sebagai server.
