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
3. Anda mendapat dua kunci:
   - **API Key** (`wa_live_...`): boleh terlihat, dikirim di header.
   - **Secret Key** (`wsk_...`): rahasia, untuk tanda tangan request. **Hanya ditampilkan sekali.**
4. Simpan keduanya di file `.env` server Anda, **jangan pernah** di JavaScript/HTML/aplikasi mobile.

```
BIZBOS_BASE_URL={{BASE_URL}}
BIZBOS_WA_KEY=wa_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
BIZBOS_WA_SECRET=wsk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Satu API Key terikat ke **satu nomor WhatsApp**. Kalau Anda punya beberapa nomor (mis. CS dan Marketing),
buat API Key di masing-masing nomor.

## 2. Autentikasi & tanda tangan (signature)

Setiap request wajib membawa 3 header:

| Header | Isi |
|---|---|
| `X-Bizbos-Key` | API Key Anda |
| `X-Bizbos-Timestamp` | Waktu sekarang dalam detik (Unix time), mis. `1792051200` |
| `X-Bizbos-Signature` | HMAC-SHA256 (hex, huruf kecil) dari string di bawah, dengan Secret Key |

String yang ditandatangani (dipisah baris baru `\n`):

```
{timestamp}
{METHOD}
{path}
{body}
```

- `METHOD`: `GET` atau `POST` (huruf besar).
- `path`: path endpoint **mulai dari `/api/`**, tanpa domain dan tanpa query string. Contoh: `/api/v1/wa/messages`.
- `body`: isi JSON **persis byte-per-byte** yang Anda kirim. Untuk `GET` dan upload file (multipart), isinya string kosong.
- Selisih timestamp dengan jam server bizbos maksimal **5 menit**. Pastikan jam server Anda sinkron (NTP).

### Contoh PHP (tanpa library)

```php
function bizbosWa(string $method, string $path, ?array $payload = null): array
{
    $body = $payload === null ? '' : json_encode($payload);
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp."\n".$method."\n".$path."\n".$body, getenv('BIZBOS_WA_SECRET'));

    $ch = curl_init(getenv('BIZBOS_BASE_URL').$path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => $body === '' ? null : $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Bizbos-Key: '.getenv('BIZBOS_WA_KEY'),
            'X-Bizbos-Timestamp: '.$timestamp,
            'X-Bizbos-Signature: '.$signature,
        ],
    ]);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return $response ?? [];
}

// Kirim pesan
bizbosWa('POST', '/api/v1/wa/messages', [
    'to' => '081234567890',
    'text' => "Halo Budi, pesanan #INV-1024 sudah kami kirim. Terima kasih!",
    'ref' => 'order-1024-shipped',
]);
```

### Contoh Node.js

```js
const crypto = require('crypto');

async function bizbosWa(method, path, payload) {
  const body = payload ? JSON.stringify(payload) : '';
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const signature = crypto.createHmac('sha256', process.env.BIZBOS_WA_SECRET)
    .update(`${timestamp}\n${method}\n${path}\n${body}`).digest('hex');

  const res = await fetch(process.env.BIZBOS_BASE_URL + path, {
    method,
    body: body || undefined,
    headers: {
      'Content-Type': 'application/json',
      'X-Bizbos-Key': process.env.BIZBOS_WA_KEY,
      'X-Bizbos-Timestamp': timestamp,
      'X-Bizbos-Signature': signature,
    },
  });
  return res.json();
}
```

### Contoh cURL (untuk uji coba)

```
TS=$(date +%s)
BODY='{"to":"081234567890","text":"Halo dari bizbos"}'
SIG=$(printf "%s\nPOST\n/api/v1/wa/messages\n%s" "$TS" "$BODY" | openssl dgst -sha256 -hmac "$BIZBOS_WA_SECRET" | sed 's/^.* //')

curl -X POST {{BASE_URL}}/api/v1/wa/messages \
  -H "Content-Type: application/json" \
  -H "X-Bizbos-Key: $BIZBOS_WA_KEY" \
  -H "X-Bizbos-Timestamp: $TS" \
  -H "X-Bizbos-Signature: $SIG" \
  -d "$BODY"
```

## 3. Endpoint

### Kirim pesan teks

`POST {{BASE_URL}}/api/v1/wa/messages`

| Field | Wajib | Keterangan |
|---|---|---|
| `to` | ya | Nomor tujuan. Boleh `0812...`, `812...`, atau `62812...`, semuanya dirapikan ke `62...`. |
| `text` | ya | Isi pesan, maks 10.000 karakter. Format WhatsApp berlaku: `*tebal*`, `_miring_`, `~coret~`. |
| `ref` | tidak | Kode unik dari sistem Anda (huruf, angka, `.` `_` `:` `-`, maks 50). **Ref yang sama tidak akan dikirim dua kali**, aman bila request Anda terulang. |

Jawaban (HTTP 202):

```json
{
  "success": true,
  "message": "Pesan masuk antrean kirim.",
  "data": { "outbox_id": "9d3c1f0e-..." }
}
```

Simpan `data.outbox_id` bila ingin mengecek status pengiriman.

### Kirim gambar, video, atau dokumen

`POST {{BASE_URL}}/api/v1/wa/messages/media` dengan `multipart/form-data`:

| Field | Wajib | Keterangan |
|---|---|---|
| `to` | ya | Nomor tujuan |
| `file` | ya | File yang dikirim, maks 64 MB |
| `caption` | tidak | Teks di bawah gambar/dokumen, maks 3.000 karakter |
| `ref` | tidak | Sama seperti di atas |

Untuk multipart, bagian `body` pada tanda tangan diisi **string kosong**.

### Cek status pesan

`GET {{BASE_URL}}/api/v1/wa/messages/{outbox_id}`

Status yang mungkin: `queued` (menunggu giliran), `sent` (terkirim ke WhatsApp), `failed` (gagal, lihat `error`),
`skipped` (dilewati, mis. nomor sudah membalas STOP).

## 4. Batasan & aturan main

- Maksimal **20 request per menit** per API Key. Lebih dari itu dijawab HTTP 429, coba lagi beberapa detik kemudian.
- API hanya jalan selama **paket WhatsApp** branch Anda aktif dan nomornya terhubung.
- Nomor yang pernah membalas **STOP** otomatis tidak dikirimi lagi.
- Hindari mengirim teks yang benar-benar sama ke ratusan nomor sekaligus. Sapa dengan nama penerima supaya pesan terasa personal dan nomor Anda tetap aman.

## 5. Kode jawaban

| HTTP | Arti | Yang perlu dilakukan |
|---|---|---|
| 202 | Pesan diterima & masuk antrean | Simpan `data.outbox_id` |
| 401 | Header kurang, signature salah, atau timestamp lewat 5 menit | Cek Secret Key, path, body, dan jam server |
| 403 | API dinonaktifkan pemilik, atau paket WhatsApp habis | Aktifkan di menu Integrasi Web / perpanjang paket |
| 422 | Data tidak valid (mis. nomor salah) | Baca `message` dan `errors` |
| 429 | Terlalu banyak request | Tunggu sesuai pesan, lalu ulangi |
| 502 / 503 | Layanan WhatsApp sedang tidak bisa dihubungi | Ulangi beberapa saat lagi dengan `ref` yang sama |

Semua jawaban berbentuk JSON: `{"success": false, "message": "..."}`.

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
- Ingin menghentikan sementara? Klik **Nonaktifkan API**, semua request langsung ditolak tanpa menghapus kuncinya.
- URL webhook Google Form juga bisa diganti kapan saja lewat tombol **Ganti URL Webhook**.
