# Marketplace (Lazada & Shopee) — tab di Catalog Online

## Alur pemakaian
1. Superadmin membuka **Settings › Marketplace Apps** dan menambahkan app per channel:
   - **Lazada**: isi App Key dan App Secret (ISV Console), Environment = Live. Callback URL di ISV Console harus **sama persis** dengan `/marketplace/lazada/callback`.
   - **Shopee**: isi Partner ID dan Partner Key (Console).
     - Sandbox memakai Test Partner ID/Key dengan Environment = Sandbox.
     - Produksi memakai Live Partner ID/Key dengan Environment = Live.
     - Isi **domain** bizbos di Redirect URL Domain (Test / Live).
2. Owner membuka **Pages › page katalog › tab Marketplace** lalu klik **Sambungkan Toko**.
   - Lazada: pilih Site sesuai negara toko, login lewat tab Seller Center, lalu kembali ke tab otorisasi dan klik **Authorize**.
   - Shopee: login akun toko (bukan sub-account), masukkan OTP (sandbox: `123456`), pilih masa otorisasi, lalu klik **Confirm Authorization**.
3. **Produk di {channel}**: klik "Tarik Produk" untuk menarik daftar produk toko ke `marketplace_products`.
4. **Upload ke {channel}**: pilih produk katalog, kategori leaf (lewat pencarian atau saran), atribut wajib, brand, harga, stok, dan paket (+ jasa kirim untuk Shopee).
5. `marketplace:refresh-tokens` berjalan **setiap jam** dan memperpanjang token sebelum habis:
   - Lazada: kurang lebih 7 hari, diperpanjang saat sisa kurang dari 2 hari.
   - Shopee: 4 jam, diperpanjang saat sisa kurang dari 90 menit.
   - Refresh token Shopee hanya bisa dipakai sekali, jadi setiap refresh dikunci per toko (`Cache::lock`).

## Struktur kode
```
config/marketplace.php                         daftar channel + driver
app/Services/Marketplace/
  Contracts/MarketplaceChannel.php             kontrak: otorisasi, token, tarik produk
  Contracts/PublishesProducts.php              kontrak: kategori, saran, atribut (untuk form upload)
  MarketplaceManager.php                       pembuat driver, ensureFreshToken(), refresh() + lock
  ProductSyncService.php                       tarik produk (semua channel)
  ListingPublisher.php                         induk publisher: catat marketplace_listings, gagal/berhasil
  Lazada/LazadaClient|LazadaChannel|LazadaPublisher
  Shopee/ShopeeClient|ShopeeChannel|ShopeePublisher
app/Http/Controllers/Marketplace/
  MarketplaceController.php                    tab hub (semua channel)
  StoreAuthController.php                      abstrak: connect, callback, refresh, disconnect
  StoreProductController.php                   abstrak: daftar & tarik produk
  StorePublishController.php                   abstrak: daftar upload, form, simpan, JSON kategori/saran/atribut
  Lazada/*Controller.php                       turunan tipis (channelKey, rules, publisher)
  Shopee/*Controller.php                       turunan + jasa kirim & brand per kategori
resources/views/marketplace/
  index.blade.php                              tab hub
  shared/                                      halaman & partial bersama semua channel
  lazada/publish-form.blade.php                form upload Lazada
  shopee/publish-form.blade.php                form upload Shopee
public/js/marketplace/
  category-picker.js                           helper form + pemilih kategori (event marketplace:category)
  lazada-form.js | shopee-form.js              atribut (dan brand, untuk Shopee) per channel
routes/web/marketplace.php                     $channelRoutes(): route standar per channel
```

## Perbedaan penting antar channel (dari dokumentasi resmi)
| | Lazada | Shopee |
|---|---|---|
| Signature | parameter diurutkan, HMAC-SHA256, hex **besar** | `partner_id+path+timestamp(+access_token+shop_id)`, HMAC-SHA256, hex **kecil** |
| Parameter | semuanya (query/form) ikut di-sign | parameter umum di query, body JSON |
| Token | access ±7 hari, refresh 30 hari | access **4 jam**, refresh 30 hari & **sekali pakai** |
| Gambar | MigrateImage dari **URL publik** | upload_image **file** (multipart) |
| Brand | Brand ID (tidak ada pencarian nama) | get_brand_list per kategori, No Brand = 0 |
| Ongkir | dari berat/dimensi | wajib `logistic_info` (jasa kirim aktif toko) |
| Harga coret | special_price di SKU | lewat Promosi (tidak dikirim saat add_item) |
| Sandbox | tidak ada | ada (Test Partner ID + toko tes) |

## Menambah channel baru (mis. TikTok Shop)
1. Buat driver `App\Services\Marketplace\{Channel}\{Channel}Channel` yang mengimplementasikan `MarketplaceChannel` dan `PublishesProducts`, plus publisher turunan `ListingPublisher`.
2. Buat controller turunan `Store{Auth,Product,Publish}Controller` di `App\Http\Controllers\Marketplace\{Channel}`.
3. Buat view `resources/views/marketplace/{channel}/publish-form.blade.php` (memakai partial `shared/`) dan script `public/js/marketplace/{channel}-form.js`.
4. Tambahkan `$channelRoutes('{channel}', ...)` dan route callback di `routes/web/marketplace.php`.
5. Set `enabled => true` dan `driver` di `config/marketplace.php`, lalu tambahkan label kredensial di `MarketplaceApp::CREDENTIAL_LABELS`.

## Catatan
- Produk katalog bizbos hanya punya 1 SKU, jadi variasi belum didukung.
- Upload ke Lazada butuh foto yang bisa dibuka dari internet. Shopee mengirim file langsung dari server.
- Shopee hanya mendukung otorisasi dari **akun toko**. Main account (CNSC/KRSC) belum didukung.
