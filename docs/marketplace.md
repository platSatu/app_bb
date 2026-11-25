# Marketplace (Lazada, Shopee & TikTok Shop) — tab di Catalog Online

## Alur pemakaian
1. Superadmin membuka **Settings › Marketplace Apps** dan menambahkan app per channel:
   - **Lazada**: isi App Key dan App Secret (ISV Console), Environment = Live. Callback URL di ISV Console harus **sama persis** dengan `/marketplace/lazada/callback`.
   - **TikTok Shop**: isi App Key, App Secret, dan **Service ID** (Partner Center › App & Service), Environment = Live. Redirect URL app = `/marketplace/tiktok/callback`. Untuk uji coba, pakai Seller Center development shop.
   - **Shopee**: isi Partner ID dan Partner Key (Console).
     - Sandbox memakai Test Partner ID/Key dengan Environment = Sandbox.
     - Produksi memakai Live Partner ID/Key dengan Environment = Live.
     - Isi **domain** bizbos di Redirect URL Domain (Test / Live).
2. Owner membuka **Pages › page katalog › tab Marketplace** lalu klik **Sambungkan Toko**.
   - Lazada: pilih Site sesuai negara toko, login lewat tab Seller Center, lalu kembali ke tab otorisasi dan klik **Authorize**.
   - Shopee: login akun toko (bukan sub-account), masukkan OTP (sandbox: `123456`), pilih masa otorisasi, lalu klik **Confirm Authorization**.
   - TikTok Shop: login akun seller lalu setujui. bizbos mengambil `shop_cipher` lewat Get Authorized Shops (kalau ada beberapa toko, yang dipakai toko pertama di negara seller).
3. **Produk di {channel}**: klik "Tarik Produk" untuk menarik daftar produk toko ke `marketplace_products`.
4. **Upload ke {channel}**: pilih produk katalog, kategori leaf (lewat pencarian atau saran), atribut wajib, brand, harga, stok, dan paket (+ jasa kirim untuk Shopee).
5. `marketplace:refresh-tokens` berjalan **setiap jam** dan memperpanjang token sebelum habis:
   - Lazada: kurang lebih 7 hari, diperpanjang saat sisa kurang dari 2 hari.
   - Shopee: 4 jam, diperpanjang saat sisa kurang dari 90 menit.
   - TikTok Shop: 7 hari, diperpanjang saat sisa kurang dari 2 hari.
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
  Tiktok/TiktokClient|TiktokChannel|TiktokPublisher
app/Http/Controllers/Marketplace/
  MarketplaceController.php                    tab hub (semua channel)
  StoreAuthController.php                      abstrak: connect, callback, refresh, disconnect
  StoreProductController.php                   abstrak: daftar & tarik produk
  StorePublishController.php                   abstrak: daftar upload, form, simpan, JSON kategori/saran/atribut
  Lazada/*Controller.php                       turunan tipis (channelKey, rules, publisher)
  Shopee/*Controller.php                       turunan + jasa kirim & brand per kategori
  Tiktok/*Controller.php                       turunan + gudang & pencarian brand
resources/views/marketplace/
  index.blade.php                              tab hub
  shared/                                      halaman & partial bersama semua channel
  lazada/publish-form.blade.php                form upload Lazada
  shopee/publish-form.blade.php                form upload Shopee
  tiktok/publish-form.blade.php                form upload TikTok Shop
public/js/marketplace/
  category-picker.js                           helper form + pemilih kategori (event marketplace:category)
  lazada-form.js | shopee-form.js | tiktok-form.js   atribut & brand per channel
routes/web/marketplace.php                     $channelRoutes(): route standar per channel
```

## Perbedaan penting antar channel (dari dokumentasi resmi)
| | Lazada | Shopee | TikTok Shop |
|---|---|---|---|
| Kredensial | App Key + App Secret | Partner ID + Partner Key | App Key + App Secret + **Service ID** |
| Signature | parameter diurutkan, HMAC-SHA256, hex **besar** | `partner_id+path+timestamp(+access_token+shop_id)`, hex **kecil** | `secret + path + {key}{value} terurut + body + secret`, HMAC-SHA256, hex kecil |
| Token di request | parameter `access_token` | query `access_token` + `shop_id` | header `x-tts-access-token` + query `shop_cipher` |
| Masa token | access ±7 hari, refresh 30 hari | access **4 jam**, refresh 30 hari & **sekali pakai** | access 7 hari, refresh = masa otorisasi |
| Gambar | MigrateImage dari **URL publik** | upload_image **file** | images/upload **file** |
| Brand | Brand ID (tidak ada pencarian) | get_brand_list, No Brand = 0 | brands (bisa dicari), tanpa brand = tidak dikirim |
| Stok | quantity di SKU | seller_stock | inventory per **warehouse_id** |
| Ongkir | dari berat/dimensi | wajib `logistic_info` | dari berat/dimensi + pengaturan toko |
| Harga coret | special_price | lewat Promosi | lewat Promosi |
| Uji coba | tidak ada sandbox | sandbox + toko tes | development shop |

## Menambah channel baru
1. Buat driver `App\Services\Marketplace\{Channel}\{Channel}Channel` yang mengimplementasikan `MarketplaceChannel` dan `PublishesProducts`, plus publisher turunan `ListingPublisher`.
2. Buat controller turunan `Store{Auth,Product,Publish}Controller` di `App\Http\Controllers\Marketplace\{Channel}`.
3. Buat view `resources/views/marketplace/{channel}/publish-form.blade.php` (memakai partial `shared/`) dan script `public/js/marketplace/{channel}-form.js`.
4. Tambahkan `$channelRoutes('{channel}', ...)` dan route callback di `routes/web/marketplace.php`.
5. Set `enabled => true` dan `driver` di `config/marketplace.php`, lalu tambahkan label kredensial di `MarketplaceApp::CREDENTIAL_LABELS`.

## Catatan
- Produk katalog bizbos hanya punya 1 SKU, jadi variasi belum didukung.
- Upload ke Lazada butuh foto yang bisa dibuka dari internet. Shopee mengirim file langsung dari server.
- Shopee hanya mendukung otorisasi dari **akun toko**. Main account (CNSC/KRSC) belum didukung.
