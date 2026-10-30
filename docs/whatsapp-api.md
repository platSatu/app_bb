# API WhatsApp bizbos

Panduan ini untuk developer yang ingin **mengirim pesan WhatsApp dari website atau aplikasi sendiri**
memakai nomor yang sudah terhubung di bizbos. Contohnya notifikasi pesanan, kode OTP, pengingat
jadwal, atau ucapan terima kasih setelah pembayaran.

Pesan dikirim lewat antrean bizbos, jadi otomatis diberi jeda yang wajar supaya nomor Anda aman
dari pembatasan WhatsApp. Anda tidak perlu mengatur jeda sendiri.

> Di halaman Dokumentasi dalam aplikasi, `{{BASE_URL}}` sudah otomatis diganti dengan alamat bizbos yang sedang Anda pakai.

## 1. Persiapan

1. Login bizbos, buka **WhatsApp › Third Party › Integrasi Web**.
2. Di tab **API Key per Nomor**, klik **Buat API Key** pada nomor WhatsApp yang akan dipakai mengirim.
3. Anda mendapat tiga isian:
   - **API Host**: `{{BASE_URL}}`
   - **Token** (`wa_live_...`): dikirim di header `X-WA-Token`.
   - **Secret Key** (`wsk_...`): dikirim di header `X-WA-Secret`. **Hanya ditampilkan sekali**, simpan baik-baik.
4. Simpan di server aplikasi Anda (mis. file `.env`), **jangan pernah** di JavaScript/HTML/aplikasi mobile.

Format API ini **sama dengan API teleios/konexa**. Aplikasi yang sudah memakai API teleios cukup mengganti
API Host, Token, dan Secret Key, tanpa mengubah kode.

Satu Token terikat ke **satu nomor WhatsApp**. Kalau Anda punya beberapa nomor (mis. CS dan Marketing),
buat API Key di masing-masing nomor.

## 2. Autentikasi

Setiap request wajib membawa 2 header:

| Header | Isi |
|---|---|
| `X-WA-Token` | Token Anda |
| `X-WA-Secret` | Secret Key Anda |

- Selalu gunakan alamat **https**, supaya Token dan Secret Key terenkripsi di perjalanan.
- Token/Secret Key yang salah berulang kali dari satu alamat IP akan diblokir sementara.

### Contoh PHP (tanpa library)

```php
function bizbosWa(string $method, string $path, ?array $payload = null): array
{
    $ch = curl_init(rtrim(getenv('BIZBOS_API_HOST'), '/').$path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $payload === null ? null : json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-WA-Token: '.getenv('BIZBOS_WA_TOKEN'),
            'X-WA-Secret: '.getenv('BIZBOS_WA_SECRET'),
        ],
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $response ?? [];
}

// Kirim pesan
bizbosWa('POST', '/api/wa-api/v1/send-message', [
    'to' => '081234567890',
    'message' => "Halo Budi, pesanan #INV-1024 sudah kami kirim. Terima kasih!",
    'ref' => 'order-1024-shipped',
]);
```

### Contoh Node.js

```js
async function bizbosWa(method, path, payload) {
  const res = await fetch(process.env.BIZBOS_API_HOST + path, {
    method,
    body: payload ? JSON.stringify(payload) : undefined,
    headers: {
      'Content-Type': 'application/json',
      'X-WA-Token': process.env.BIZBOS_WA_TOKEN,
      'X-WA-Secret': process.env.BIZBOS_WA_SECRET,
    },
  });
  return res.json();
}
```

### Contoh cURL (untuk uji coba)

```
curl -X POST {{BASE_URL}}/api/wa-api/v1/send-message \
  -H "Content-Type: application/json" \
  -H "X-WA-Token: $BIZBOS_WA_TOKEN" \
  -H "X-WA-Secret: $BIZBOS_WA_SECRET" \
  -d '{"to":"081234567890","message":"Halo dari bizbos"}'
```

## 3. Endpoint

### Kirim pesan teks

`POST {{BASE_URL}}/api/wa-api/v1/send-message`

| Field | Wajib | Keterangan |
|---|---|---|
| `to` | ya | Nomor tujuan (`0812...`, `812...`, atau `62812...`, semuanya dirapikan ke `62...`), atau ID WhatsApp lengkap: `...@s.whatsapp.net` / grup `...@g.us`. |
| `message` | ya | Isi pesan, maks 10.000 karakter. Format WhatsApp berlaku: `*tebal*`, `_miring_`, `~coret~`. |
| `ref` | tidak | Kode unik dari sistem Anda (huruf, angka, `.` `_` `:` `-`, maks 50). **Ref yang sama tidak akan dikirim dua kali**, aman bila request Anda terulang. |

Jawaban (HTTP 200):

```json
{
  "success": true,
  "status": "sent",
  "message": {
    "id": "9d3c1f0e-...",
    "to": "6281234567890",
    "chat_jid": "6281234567890@s.whatsapp.net",
    "device_id": "01a1429d-...",
    "from_me": true,
    "status": "sent",
    "message_type": "text",
    "body": "Halo Budi",
    "sent_at": "2026-10-30T09:40:00+07:00",
    "created_at": "2026-10-30T09:40:00+07:00",
    "queued": true
  }
}
```

Bentuknya sama dengan API teleios/Konexa, jadi kode yang sudah memakai teleios cukup ganti
**API Host** (`https://app.bizbos.id/api`), **Token**, dan **Secret Key**.

Pesan masuk antrean dan dikirim berjeda (biasanya dalam hitungan detik) supaya nomor Anda aman.
Simpan `message.id` bila ingin mengecek status pengiriman akhirnya.

### Kirim gambar, video, atau dokumen

`POST {{BASE_URL}}/api/wa-api/v1/send-media` dengan `multipart/form-data`:

| Field | Wajib | Keterangan |
|---|---|---|
| `to` | ya | Nomor tujuan / ID grup |
| `file` | ya | File yang dikirim, maks 64 MB |
| `caption` | tidak | Teks di bawah gambar/dokumen, maks 3.000 karakter |
| `ref` | tidak | Sama seperti di atas |

### Cek status pesan

`GET {{BASE_URL}}/api/wa-api/v1/messages/{id}`

Status yang mungkin: `queued` (menunggu giliran), `sent` (terkirim ke WhatsApp), `failed` (gagal, lihat `error`),
`skipped` (dilewati, mis. nomor sudah membalas STOP).

### Cek paket & kuota

`GET {{BASE_URL}}/api/wa-api/v1/usage`

Menampilkan status paket WhatsApp (`package_active`, `package.name`, `package.branch`, `package.valid_from`, `package.valid_until`),
kuota pesan bulan ini (`quota.metric` = `broadcast_send`, `quota.max`, `quota.used`, `quota.remaining`; `unlimited: true` bila paket
tidak membatasi), dan jumlah request kirim (`requests.today`, `requests.this_month`, `requests.current_package_period`,
`requests.all_time`). Bentuknya sama dengan teleios. Tidak memotong kuota.

## 4. Batasan & aturan main

- Maksimal **20 request per menit** per Token. Lebih dari itu dijawab HTTP 429, coba lagi beberapa detik kemudian.
- API hanya jalan selama **paket WhatsApp** branch Anda aktif dan nomornya terhubung. Setiap pesan memotong kuota pesan bulanan paket Anda, sama seperti pesan dari dashboard.
- Nomor yang pernah membalas **STOP** otomatis tidak dikirimi lagi.
- Hindari mengirim teks yang benar-benar sama ke ratusan nomor sekaligus. Sapa dengan nama penerima supaya pesan terasa personal dan nomor Anda tetap aman.

## 5. Kode jawaban

| HTTP | Arti | Yang perlu dilakukan |
|---|---|---|
| 200 | Pesan diterima & masuk antrean kirim | Simpan `message.id` |
| 401 | Token atau Secret Key tidak ada / salah | Cek kembali Token & Secret Key |
| 403 | API dinonaktifkan pemilik, atau paket WhatsApp habis | Aktifkan di menu Integrasi Web / perpanjang paket |
| 422 | Data tidak valid (mis. nomor salah) | Baca `message` dan `errors` |
| 429 | Terlalu banyak request, atau kuota pesan bulan ini habis | Baca `error`, tunggu / upgrade paket |
| 502 / 503 | Nomor WhatsApp tidak terhubung atau layanan sedang tidak bisa dihubungi | Ulangi beberapa saat lagi dengan `ref` yang sama |

Jawaban gagal berbentuk JSON: `{"success": false, "error": "...", "message": "..."}`.

## 6. Google Form tanpa coding

Kalau Anda hanya ingin **membalas otomatis orang yang mengisi Google Form** (mis. konfirmasi pendaftaran),
tidak perlu menulis kode sama sekali:

1. Buka **WhatsApp › Third Party › Google Form**, klik **Hubungkan Google Form**.
2. Pilih nomor pengirim, isi judul pertanyaan yang berisi nomor WhatsApp, dan tulis pesannya.
   Jawaban form bisa dipakai di pesan dengan `{{Judul Pertanyaan}}`, mis. `Halo {{Nama Lengkap}}!`.
3. Salin script yang disediakan ke Google Form (**Extensions › Apps Script**), lalu pasang trigger **On form submit**.

Bisa juga mengaktifkan **pembayaran** (pendaftar menerima link bayar) dan **registrasi ulang** (pendaftar menerima QR,
petugas memindainya lewat aplikasi registrasi ulang). Laporan pendaftar, pesan terkirim, pembayaran, dan kehadiran
bisa diunduh ke Excel.

## 7. Keamanan

- Secret Key bocor? Buka halaman API Key nomor tersebut, klik **Buat Secret Key Baru**. Kunci lama langsung tidak berlaku.
- bizbos hanya menyimpan sidik (hash) Secret Key, jadi Secret Key Anda tidak bisa dilihat lagi oleh siapa pun, termasuk tim bizbos.
- Ingin menghentikan sementara? Klik **Nonaktifkan API**, semua request langsung ditolak tanpa menghapus kuncinya.
- URL webhook Google Form juga bisa diganti kapan saja lewat tombol **Ganti URL Webhook**.
