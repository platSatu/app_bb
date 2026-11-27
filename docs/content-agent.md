# Agent Konten & Akun Sosmed

Superadmin › Tools › **Konten Harian** dan **Akun Sosmed**. Keduanya khusus superadmin (biaya AI dan token akun resmi).

## Alur

1. **Buat rencana** (tab Buat Rencana & Pengaturan): pilih tanggal mulai, 7/14/30 hari, 1–3 konten per hari.
   `ContentAgent` menyusun slot (tanggal, jam, format, pilar), lalu `GenerateContentPlan` mengisi 3 slot per putaran lewat AI aktif (Settings › AI).
   Semua konten masuk sebagai **Draf**.
2. **Baca & setujui** (tab Konten): per konten, atau centang lalu "Setujui". "Pilih semua di halaman ini" mencentang satu halaman;
   "Pilih semua N konten sesuai filter" memproses semua hasil filter (maks 500).
3. **Media** dibuat otomatis setelah disetujui (`ContentMedia` + Classai Router), maksimal *Media maks / hari*:
   gambar/carousel 4:5 (disimpan JPEG), Reels berupa klip video 9:16. Media bisa diganti file sendiri (mis. video hasil edit).
   Status konten menjadi **Siap upload**. Cek pratinjau, karena gambar AI kadang salah eja tulisan.
4. **Jadwalkan**: per konten (pilih akun) atau massal (akun bertanda "Tujuan bawaan" di Akun Sosmed).
   `content:publish` (tiap menit) mengirim saat jam tayang tiba, lalu mengecek sampai terposting.

## Pengaman

- Kiriman per akun diklaim atomik (`pending -> publishing`, `processing -> publishing`), jadi satu konten tidak pernah terkirim dua kali ke akun yang sama.
- Kiriman gagal **tidak diulang otomatis**. Kiriman yang macet ditandai gagal dengan pesan "cek akun dulu". Tombol "Coba lagi" ada di tiap kiriman.
- Token & App Secret terenkripsi (`encrypted` cast), tidak pernah ditampilkan atau dikirim ke browser.
- State OAuth disimpan di session (30 menit, terikat user & penyedia).
- File media privat (disk `local`). Platform mengambilnya lewat `content.media`: link bertanda tangan 6 jam, hanya untuk konten yang sudah dijadwalkan/diposting.
- Hasil AI diperlakukan sebagai data: hanya kolom yang dikenal dipakai, tag HTML dibuang, panjang dibatasi, dan ditampilkan ter-escape.
- Media buatan AI diberi label AI saat upload (Instagram `is_ai_generated`, TikTok `is_aigc`, YouTube `containsSyntheticMedia`).

## Setup server

```bash
php artisan migrate
# .env: retry_after antrean harus > timeout job terlama (900 dtk)
DB_QUEUE_RETRY_AFTER=960
# worker (supervisor/systemd)
php artisan queue:work --timeout=900
```

Scheduler (`content:publish` tiap menit, `social:refresh-tokens` tiap jam) memakai cron Laravel yang sudah ada.
Upload media sendiri sampai 300 MB butuh `upload_max_filesize` / `post_max_size` PHP (dan `client_max_body_size` nginx) yang cukup.

## Callback OAuth

| Penyedia | Isi di console | URL |
|---|---|---|
| Meta | Facebook Login › Valid OAuth Redirect URIs | `/superadmin/tools/social/callback/meta` |
| TikTok | Login Kit › Redirect URI | `/superadmin/tools/social/callback/tiktok` |
| Google | OAuth client › Authorized redirect URIs | `/superadmin/tools/social/callback/google` |

## Batasan platform

- **Instagram**: akun Professional yang terhubung ke Facebook Page; gambar wajib JPEG; ada batas posting API per 24 jam.
- **Facebook Reels**: 9:16, minimal 540×960 (pilih resolusi video 720p ke atas).
- **TikTok**: sebelum app lolos audit, hanya posting privat ("Hanya saya") ke akun privat. Foto memakai PULL_FROM_URL, jadi domain bizbos harus diverifikasi di URL properties. Privasi wajib dipilih saat menjadwalkan.
- **YouTube**: hanya video. Project Google yang belum lolos audit YouTube API membuat video terkunci Private. Selama OAuth consent screen berstatus Testing, refresh token habis tiap 7 hari.
