# Integrasi Invoice bizbos

Panduan ini untuk developer website/aplikasi yang ingin menerima pembayaran lewat **bizbos**.
Website Anda cukup berbicara dengan bizbos. bizbos yang meneruskan pembayaran ke Duitku,
lalu mengabari website Anda saat invoice lunas. Anda **tidak perlu** punya akun Duitku sendiri.

> Di halaman Dokumentasi dalam aplikasi, `{{BASE_URL}}` sudah otomatis diganti dengan alamat bizbos yang sedang Anda pakai.

## 1. Gambaran alur

```
Website Anda (server)          bizbos                         Duitku
        |                         |                              |
 1. POST /api/v1/invoices  ---->  |  buat invoice  ------------> |  buat tagihan
        | <-- checkout_url ------ |  <------------ payment_url - |
        |     + payment_url       |                              |
 2. Browser customer membuka checkout_url (popup) / payment_url  |
        |                         |  3. customer membayar  -----> |  VA / QRIS / e-wallet
        |                         |  <---- callback lunas ------- |
        |                         |  4. saldo Anda bertambah      |
 5. <---- webhook invoice.paid -- |     (dipotong biaya layanan)  |
 6. Website Anda menandai pesanan LUNAS                          |
```

Aturan emas: **status lunas yang sah hanya dari webhook `invoice.paid` atau dari `GET /api/v1/invoices/{id}`**.
Callback JavaScript di browser (popup) hanya untuk tampilan, jangan dipakai untuk menandai pesanan lunas.

## 2. Persiapan

1. Login bizbos, lalu buka **Invoice › API & Integrasi**. Pilih branch yang akan menerima uang.
2. Klik **Buat API Key**. Anda mendapat:
   - **API Key** (`pk_...`): boleh terlihat, dikirim di header.
   - **Secret Key** (`sk_...`): rahasia, untuk tanda tangan request. **Hanya ditampilkan sekali.**
   - **Webhook Secret** (`whsec_...`): rahasia, untuk memeriksa webhook dari bizbos. **Hanya ditampilkan sekali.**
3. Simpan ketiganya di file `.env` server Anda, **jangan pernah** di JavaScript/HTML.
4. Isi **URL Webhook** (wajib `https://` di production), mis. `https://tokosaya.com/bizbos/webhook`.
5. Isi **Website yang boleh membuka popup**: origin lengkap tanpa path, satu per baris,
   mis. `https://tokosaya.com`. Untuk uji coba lokal: `http://localhost`.
6. Klik **Kirim Webhook Test** untuk memastikan server Anda menerima event `ping`.

Contoh isi `.env` di website Anda:

```
BIZBOS_BASE_URL={{BASE_URL}}
BIZBOS_API_KEY=pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
BIZBOS_SECRET=sk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
BIZBOS_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

## 3. Autentikasi & tanda tangan (signature)

Setiap request ke API wajib membawa 3 header:

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
- `path`: path endpoint **mulai dari `/api/`**, tanpa domain, tanpa folder instalasi, tanpa query string.
  Contoh: `/api/v1/invoices`. Walaupun bizbos dipasang di `http://localhost/app.bizbos/public`, path tetap `/api/v1/invoices`.
- `body`: isi JSON **persis byte-per-byte** yang Anda kirim. Untuk `GET` isinya string kosong.
- Selisih timestamp dengan jam server bizbos maksimal **5 menit**. Pastikan jam server Anda sinkron (NTP).
- Batas: 120 request per menit per API key.

### Contoh PHP (tanpa library)

```php
function bizbosRequest(string $method, string $path, ?array $payload = null): array
{
    $base = rtrim(env('BIZBOS_BASE_URL'), '/');
    $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp."\n".strtoupper($method)."\n".$path."\n".$body, env('BIZBOS_SECRET'));

    $ch = curl_init($base.$path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Bizbos-Key: '.env('BIZBOS_API_KEY'),
            'X-Bizbos-Timestamp: '.$timestamp,
            'X-Bizbos-Signature: '.$signature,
        ],
        CURLOPT_POSTFIELDS => $body === '' ? null : $body,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'json' => json_decode((string) $response, true)];
}
```

### Contoh Laravel (Http client)

```php
use Illuminate\Support\Facades\Http;

$path = '/api/v1/invoices';
$body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$timestamp = (string) time();

$response = Http::withHeaders([
        'X-Bizbos-Key' => config('services.bizbos.key'),
        'X-Bizbos-Timestamp' => $timestamp,
        'X-Bizbos-Signature' => hash_hmac('sha256', $timestamp."\nPOST\n".$path."\n".$body, config('services.bizbos.secret')),
        'Accept' => 'application/json',
    ])
    ->withBody($body, 'application/json')   // kirim body yang SAMA dengan yang ditandatangani
    ->post(rtrim(config('services.bizbos.url'), '/').$path);
```

### Contoh Node.js

```js
const crypto = require('crypto');

async function bizbos(method, path, payload) {
  const body = payload ? JSON.stringify(payload) : '';
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const signature = crypto.createHmac('sha256', process.env.BIZBOS_SECRET)
    .update(`${timestamp}\n${method}\n${path}\n${body}`).digest('hex');

  const res = await fetch(process.env.BIZBOS_BASE_URL + path, {
    method,
    headers: {
      'Content-Type': 'application/json', Accept: 'application/json',
      'X-Bizbos-Key': process.env.BIZBOS_API_KEY,
      'X-Bizbos-Timestamp': timestamp,
      'X-Bizbos-Signature': signature,
    },
    body: body || undefined,
  });
  return { status: res.status, json: await res.json() };
}
```

## 4. Endpoint

Base URL: `{{BASE_URL}}/api/v1`

### 4.1 Buat invoice — `POST /api/v1/invoices`

| Field | Wajib | Keterangan |
|---|---|---|
| `external_id` | ya | ID pesanan di sistem Anda (huruf, angka, `. _ - :`, maks 100). **Unik per API key.** Mengirim `external_id` yang sama = mendapat invoice yang sama (aman di-retry). |
| `amount` | ya | Nominal rupiah (bilangan bulat), minimal {{MIN_AMOUNT}}, maksimal {{MAX_AMOUNT}}. |
| `description` | ya | Keterangan yang dilihat customer, maks 255 karakter. |
| `customer.name` | ya | Nama customer. |
| `customer.email` | tidak | Untuk email tagihan/bukti bayar. Disarankan diisi. |
| `customer.phone` | tidak | Nomor WhatsApp, mis. `081234567890`. |
| `due_date` | tidak | Jatuh tempo `YYYY-MM-DD` (hanya informasi, tanpa denda). |
| `expiry_minutes` | tidak | Masa berlaku invoice, 5–43200 menit. Default {{EXPIRY}} menit. Lewat dari ini invoice menjadi `expired`. |
| `return_url` | tidak | Halaman website Anda setelah bayar (mode redirect). |
| `send_email` | tidak | `true` = bizbos mengirim email tagihan berisi link bayar ke `customer.email`. |

Contoh request:

```json
{
  "external_id": "ORDER-1001",
  "amount": 150000,
  "description": "Pembelian Paket Belajar Bass",
  "customer": { "name": "Budi Santoso", "email": "budi@example.com", "phone": "081234567890" },
  "expiry_minutes": 120,
  "return_url": "https://tokosaya.com/orders/1001"
}
```

Respons `201 Created` (baru dibuat) atau `200 OK` dengan `"created": false` (external_id sudah pernah dibuat):

```json
{
  "success": true,
  "created": true,
  "data": {
    "id": "9d2f6c1e-0b8a-4a63-9f1e-5c2b7c8d9e10",
    "external_id": "ORDER-1001",
    "number": "INV-2610AB12CD",
    "status": "unpaid",
    "description": "Pembelian Paket Belajar Bass",
    "customer": { "name": "Budi Santoso", "email": "budi@example.com", "phone": "6281234567890" },
    "amount": 150000,
    "late_fee": 0,
    "total": 150000,
    "fee": 0,
    "net_amount": 0,
    "payment_method": null,
    "checkout_url": "{{BASE_URL}}/pay/Xy...48karakter",
    "payment_url": "https://...duitku.com/... (link halaman bayar Duitku)",
    "due_date": null,
    "expires_at": "2026-10-16T14:00:00+07:00",
    "paid_at": null,
    "cancelled_at": null,
    "created_at": "2026-10-16T12:00:00+07:00"
  }
}
```

Simpan `data.id` dan `data.checkout_url` di pesanan Anda.

- `checkout_url`: halaman bayar bizbos (popup / redirect). Selalu ada dan selalu bisa dipakai.
- `payment_url`: halaman bayar Duitku yang langsung dibuat bizbos saat invoice dibuat. Bisa `null` kalau Duitku
  sedang tidak bisa dihubungi atau tagihannya sudah lewat masa berlaku; pakai `checkout_url` saja, bizbos
  membuat tagihan Duitku baru otomatis. Memanggil ulang dengan `external_id` yang sama juga memperbarui `payment_url`.

### 4.2 Cek invoice — `GET /api/v1/invoices/{id}`

`{id}` boleh **id bizbos** atau **external_id** Anda. Body kosong (string kosong saat menandatangani).
Respons: `{ "success": true, "data": { ...objek invoice... } }`.

Pakai endpoint ini untuk memastikan status sebelum mengirim barang, atau saat webhook belum datang.

### 4.3 Batalkan invoice — `POST /api/v1/invoices/{id}/cancel`

Body opsional: `{ "reason": "Pesanan dibatalkan customer" }`.
Hanya invoice `unpaid` yang bisa dibatalkan. Invoice yang sudah `cancelled` mengembalikan sukses (aman di-retry).
Invoice yang sudah `paid` mengembalikan `409`.

### 4.4 Cek saldo — `GET /api/v1/balance`

```json
{ "success": true, "data": { "balance": 1250000, "available_balance": 900000, "pending_balance": 350000, "currency": "IDR" } }
```

- `balance`: total saldo.
- `available_balance`: yang **bisa ditarik / dikirim saat ini** (pembayaran sudah settlement Duitku). Payout lebih besar dari ini ditolak `422`.
- `pending_balance`: pembayaran yang masih dalam proses pencairan (tergantung metode bayar, mis. QRIS / e-wallet / retail).

## 5. Status invoice

| Status | Arti |
|---|---|
| `unpaid` | Menunggu pembayaran. |
| `paid` | Lunas. `total` = yang dibayar customer, `fee` = biaya layanan bizbos, `net_amount` = yang masuk ke saldo Anda. |
| `expired` | Lewat `expires_at` tanpa pembayaran. Buat invoice baru (dengan `external_id` baru) kalau customer masih ingin bayar. |
| `cancelled` | Dibatalkan (lewat API atau dashboard). |

Catatan: kalau customer sempat membuka halaman Duitku sebelum invoice kedaluwarsa lalu benar-benar membayar,
invoice tetap menjadi `paid` dan Anda menerima webhook `invoice.paid`.

## 6. Menampilkan pembayaran ke customer

### 6.1 Popup (disarankan)

Pasang script sekali di halaman checkout website Anda:

```html
<script src="{{BASE_URL}}/js/bizbos-pay.js"></script>
```

Lalu buka popup dengan `checkout_url` yang didapat dari server Anda:

```html
<button id="pay">Bayar Sekarang</button>
<script>
  document.getElementById('pay').addEventListener('click', function () {
    BizbosPay.open('CHECKOUT_URL_DARI_SERVER_ANDA', {
      onSuccess: function (result) {
        // result.status === 'paid'. Ini HANYA tampilan; cek ulang ke server Anda.
        window.location.href = '/orders/1001?check=1';
      },
      onClose: function (result) {
        // Popup ditutup / dibatalkan / kedaluwarsa. Tetap cek status di server Anda.
        window.location.href = '/orders/1001?check=1';
      }
    });
  });
</script>
```

- Popup harus dibuka dari klik user (aturan browser). Kalau popup diblokir, script otomatis pindah ke halaman bayar biasa.
- Kabar dari popup hanya dikirim ke origin yang terdaftar di **Website yang boleh membuka popup**.
  Kalau origin website Anda belum didaftarkan, popup tetap bisa dipakai untuk membayar, tetapi `onSuccess` tidak terpanggil (gunakan `onClose`).
- Halaman bayar bizbos tidak bisa dimasukkan ke dalam iframe (demi keamanan), karena itu memakai popup.

### 6.2 Redirect biasa

Arahkan customer ke `checkout_url`. Setelah lunas, halaman bayar menampilkan tombol **Kembali ke website Anda**
yang menuju `return_url`. Di halaman `return_url`, cek status lewat `GET /api/v1/invoices/{id}` dari server Anda.

## 7. Webhook

bizbos mengirim `POST` JSON ke URL Webhook Anda setiap ada perubahan:

| Event | Kapan |
|---|---|
| `invoice.paid` | Pembayaran terkonfirmasi Duitku. |
| `invoice.expired` | Invoice lewat `expires_at` tanpa pembayaran. |
| `invoice.cancelled` | Invoice dibatalkan. |
| `payout.success` | Disbursement berhasil ditransfer (lihat bagian 13). |
| `payout.failed` | Disbursement gagal, saldo sudah dikembalikan. |
| `payout.needs_review` | Transfer terkirim tapi hasilnya belum pasti, sedang dicek tim bizbos. |
| `ping` | Tombol **Kirim Webhook Test**. |

Header:

| Header | Isi |
|---|---|
| `X-Bizbos-Event` | Nama event |
| `X-Bizbos-Timestamp` | Unix time saat dikirim |
| `X-Bizbos-Signature` | hex HMAC-SHA256 dari `timestamp + "." + rawBody` dengan **Webhook Secret** |

Body:

```json
{
  "id": "5b0e3f0e-6a4c-4b5e-9d7a-2c1f0e9a8b7c",
  "event": "invoice.paid",
  "created_at": "2026-10-16T12:05:10+07:00",
  "data": { "...": "objek invoice, sama dengan respons API" }
}
```

Aturan menerima webhook:

1. Periksa signature dengan **raw body** (sebelum di-parse) dan pastikan timestamp tidak lebih dari 5 menit.
2. Balas **HTTP 2xx secepatnya** (di bawah 10 detik). Proses berat jalankan di antrean.
3. Webhook bisa datang **lebih dari sekali**. Jadikan idempoten: kalau pesanan sudah lunas, abaikan.
4. Kalau gagal (bukan 2xx / timeout), bizbos mencoba ulang 6 kali: 1 menit, 5 menit, 30 menit, 2 jam, 6 jam.
   Semua percobaan terlihat di **Log Webhook**.
5. bizbos tidak mengikuti redirect. URL webhook harus langsung menjawab.

### Contoh penerima webhook (Laravel)

```php
// routes/web.php  (kecualikan dari CSRF, lihat di bawah)
Route::post('bizbos/webhook', function (\Illuminate\Http\Request $request) {
    $raw = $request->getContent();
    $timestamp = (string) $request->header('X-Bizbos-Timestamp');
    $expected = hash_hmac('sha256', $timestamp.'.'.$raw, config('services.bizbos.webhook_secret'));

    if (! hash_equals($expected, (string) $request->header('X-Bizbos-Signature')) || abs(time() - (int) $timestamp) > 300) {
        return response('invalid signature', 401);
    }

    $event = json_decode($raw, true);

    if ($event['event'] === 'invoice.paid') {
        $order = \App\Models\Order::where('external_id', $event['data']['external_id'])->first();

        if ($order && ! $order->is_paid) {
            $order->update(['is_paid' => true, 'paid_at' => now()]);   // idempoten
        }
    }

    return response('OK', 200);
});

// bootstrap/app.php (Laravel 11/12)
// $middleware->validateCsrfTokens(except: ['bizbos/webhook']);
```

### Contoh penerima webhook (PHP biasa)

```php
$raw = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_BIZBOS_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_BIZBOS_SIGNATURE'] ?? '';
$expected = hash_hmac('sha256', $timestamp.'.'.$raw, getenv('BIZBOS_WEBHOOK_SECRET'));

if (! hash_equals($expected, $signature) || abs(time() - (int) $timestamp) > 300) {
    http_response_code(401);
    exit('invalid signature');
}

$event = json_decode($raw, true);
// ... tandai pesanan lunas kalau $event['event'] === 'invoice.paid'
http_response_code(200);
echo 'OK';
```

## 8. Biaya & saldo

- Customer membayar `total`. bizbos memotong **biaya layanan** (`fee`), sisanya (`net_amount`) masuk ke **saldo bizbos** branch Anda.
- Mutasi saldo terlihat di **My Account › History** dengan jenis *Pembayaran Invoice*.
- Saldo dicairkan ke rekening lewat **My Account › Tarik Saldo**.
- Biaya Duitku sudah termasuk dalam biaya layanan bizbos.

## 9. Format error

Semua error berbentuk:

```json
{ "success": false, "message": "Pesan yang bisa dibaca manusia", "errors": { "amount": ["..."] } }
```

| HTTP | Arti |
|---|---|
| `401` | Header kurang, API key/signature salah, atau timestamp lewat 5 menit. |
| `403` | API dinonaktifkan pemilik akun, atau branch tidak aktif. |
| `404` | Invoice/endpoint tidak ditemukan (atau bukan milik API key ini). |
| `409` | Aksi tidak bisa dilakukan pada status sekarang (mis. membatalkan invoice lunas). |
| `422` | Data tidak valid; lihat `errors`. |
| `429` | Terlalu banyak request; tunggu beberapa detik. |

Penyebab `401` yang paling sering:

1. Body yang ditandatangani tidak sama persis dengan yang dikirim (mis. di-encode dua kali, spasi/escape berbeda).
2. Path memakai folder instalasi (`/app.bizbos/public/api/...`). Yang benar: `/api/v1/...`.
3. Jam server berbeda lebih dari 5 menit.
4. Memakai Webhook Secret untuk request API (atau sebaliknya).

## 10. Uji coba lokal

Skenario: website Anda (mis. `http://localhost/nama-project/public`) membuat invoice ke bizbos (`{{BASE_URL}}`) di komputer yang sama.

1. Di bizbos: pastikan **Superadmin › Deposit › Payment Gateway** sudah berisi kredensial **Duitku Sandbox**.
2. Di bizbos (sebagai owner): **Invoice › API & Integrasi** › **Buat API Key**, simpan Secret Key & Webhook Secret.
3. Isi **Website yang boleh membuka popup**: `http://localhost`.
4. Isi **URL Webhook**, mis. `http://localhost/nama-project/public/bizbos/webhook`. Di lokal (`APP_ENV=local`) bizbos mengizinkan `http://` dan `localhost`; di production wajib `https://` publik.
5. Di website Anda: isi `.env` (bagian 2), buat route penerima webhook (bagian 7) dan kecualikan dari CSRF.
6. Di website Anda: buat route uji yang memanggil `POST /api/v1/invoices` (bagian 3–4), lalu tampilkan tombol **Bayar** yang memanggil `BizbosPay.open(checkout_url)`.
7. Klik **Bayar**. Di popup, klik **Bayar Sekarang**, pilih metode di Duitku Sandbox, lalu selesaikan pembayaran simulasi.
8. Karena Duitku tidak bisa memanggil `localhost`, callback tidak akan sampai. Setelah kembali dari Duitku, halaman bayar bizbos
   **mengecek langsung ke Duitku** dan menandai lunas kalau sudah dibayar. Bisa juga lewat link **Cek status pembayaran**,
   atau jalankan `php artisan invoices:expire` di server bizbos.
9. Pastikan:
   - invoice berstatus **Lunas** di **Invoice › Semua Invoice**;
   - saldo bertambah (`net_amount`) di **My Account › History**;
   - webhook `invoice.paid` tercatat **HTTP 200** di **Log Webhook**, dan pesanan di website Anda ikut lunas.
10. Agar webhook & email terkirim, antrean bizbos harus berjalan: `php artisan queue:work`.

## 11. Checklist sebelum go-live

- [ ] Secret Key & Webhook Secret hanya ada di server (`.env`), tidak di Git, tidak di JavaScript.
- [ ] Pesanan ditandai lunas **hanya** dari webhook terverifikasi atau `GET /api/v1/invoices/{id}`.
- [ ] Penerima webhook idempoten dan membalas 2xx dengan cepat.
- [ ] `external_id` unik per pesanan; retry request memakai `external_id` yang sama.
- [ ] URL webhook `https://` dan bisa diakses dari internet.
- [ ] Origin popup sudah didaftarkan untuk domain production.
- [ ] Jam server sinkron (NTP).
- [ ] Kalau Secret bocor: buat Secret baru di **API & Integrasi** (yang lama langsung tidak berlaku).

## 12. Pertanyaan umum

**Customer menutup popup sebelum bayar. Apa yang terjadi?** Invoice tetap `unpaid` sampai `expires_at`. Customer bisa membuka `checkout_url` lagi.

**Customer membayar dua kali?** Pembayaran kedua tetap masuk saldo dan ditandai *Perlu Refund* di detail invoice. Kembalikan dananya ke customer secara manual.

**Bisa ubah nominal invoice?** Tidak. Batalkan lalu buat invoice baru dengan `external_id` baru.

**Bisa dipakai tanpa website?** Bisa. Buat invoice dari dashboard (**Invoice › Kategori › Invoice Periode**), lalu bagikan link bayarnya ke customer. Email tagihan & pengingat dikirim otomatis kalau diaktifkan.

## 13. Disbursement (kirim dana ke rekening)

Dengan Disbursement, website Anda bisa mengirim dana dari **saldo bizbos branch Anda** ke rekening siapa pun
(mis. pengajar, admin cabang, vendor). bizbos yang meneruskan ke Duitku Disbursement; persetujuan tetap di website Anda.

```
Website Anda                 bizbos                              Duitku
 1. POST /payout-accounts ->  cek nama pemilik ke bank  ------>  inquiry
        <- data rekening ---  simpan sebagai Rekening Penerima
 2. POST /payouts --------->  tahan saldo, antre transfer
        <- status pending --  inquiry + cocokkan nama + transfer -> rekening penerima
 3. <- webhook payout.success / payout.failed
```

### 13.1 Persiapan

1. Buka **Invoice › Disbursement › Pengaturan & API**, nyalakan **Izinkan website mengirim dana lewat API**.
2. Isi **batas per transaksi** dan **batas per hari** (sangat disarankan).
3. Memakai API key, Secret Key, dan Webhook Secret yang sama dengan Invoice (bagian 3 & 7).

Biaya per transfer dan minimal kirim mengikuti pengaturan tarik saldo bizbos, tampil di tab Pengaturan & API.
Biaya **dipotong dari jumlah yang dikirim**: `net_amount = amount - fee`.

### 13.2 Daftar bank — `GET /api/v1/banks`

```json
{ "success": true, "data": [ { "bank_code": "014", "bank_name": "BANK CENTRAL ASIA" } ] }
```

### 13.3 Daftarkan rekening — `POST /api/v1/payout-accounts`

```json
{
  "external_id": "USER-123",
  "bank_code": "014",
  "account_number": "1234567890",
  "account_name": "Andi Saputra",
  "label": "Admin Cabang Bekasi"
}
```

- `external_id` (opsional): ID penerima di sistem Anda. Mengirim ulang dengan `external_id` yang sama **memperbarui** rekeningnya.
- `account_name` (opsional): kalau diisi, harus sama dengan nama pemilik di bank. Kalau berbeda, rekening ditolak.
- Nama yang disimpan selalu nama dari bank (`data.account_name`).

Respons: `{ "success": true, "data": { "id": "...", "external_id": "USER-123", "bank_name": "...", "account_last4": "7890", "account_name": "ANDI SAPUTRA", "status": "active" } }`.
Cek ulang: `GET /api/v1/payout-accounts/{id}` (`{id}` boleh id bizbos atau `external_id`).

### 13.4 Kirim dana — `POST /api/v1/payouts`

```json
{
  "external_id": "TARIK-2026-0001",
  "payout_account_id": "USER-123",
  "amount": 500000,
  "description": "Tarik saldo kelas KOM 100 Oktober"
}
```

- `external_id` **wajib** dan unik. Mengirim ulang dengan `external_id` yang sama **tidak mengirim dana lagi**, hanya mengembalikan kiriman yang sudah ada (`created: false`). Aman di-retry.
- `payout_account_id`: id bizbos atau `external_id` rekening penerima.
- Saldo langsung ditahan. Respons `201` dengan `status: "pending"`, transfer diproses di antrean beberapa detik kemudian.

### 13.5 Cek kiriman — `GET /api/v1/payouts/{id}`

`{id}` boleh id bizbos atau `external_id` Anda.

| Status | Arti |
|---|---|
| `pending` | Menunggu diproses. |
| `processing` | Sedang ditransfer. |
| `success` | Dana sudah terkirim ke rekening penerima. |
| `failed` | Gagal; saldo sudah dikembalikan. Alasan di `failure_reason`. |
| `needs_review` | Transfer terkirim tapi hasilnya belum pasti. Saldo **tidak** dikembalikan otomatis; tim bizbos mengecek lalu statusnya berubah menjadi `success` atau `failed` (Anda menerima webhook). |

### 13.6 Aturan penting

1. Jadikan status final **hanya dari webhook `payout.*` atau `GET /api/v1/payouts/{id}`**.
2. Jangan menandai `failed` sendiri untuk kiriman `needs_review`. Tunggu keputusan akhir dari bizbos.
3. Error `422` berisi pesan yang bisa ditampilkan, mis. saldo tidak cukup, melebihi batas harian, atau rekening nonaktif.
4. Error `403` berarti Disbursement lewat API belum diaktifkan pemilik akun.

