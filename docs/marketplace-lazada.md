# Marketplace (Lazada) — tab di Catalog Online

## Struktur
- `config/marketplace.php` berisi daftar channel: Lazada aktif, TikTok Shop dan Shopee masih `enabled => false`.
- Kontrak driver ada di `app/Services/Marketplace/Contracts/MarketplaceChannel.php`.
- Driver Lazada ada di `app/Services/Marketplace/Lazada/`:
  - `LazadaClient` untuk signature HMAC-SHA256 dan HTTP.
  - `LazadaChannel` untuk OAuth, token, produk, kategori, atribut, dan migrasi gambar.
  - `LazadaPublisher` untuk CreateProduct.
- Controller:
  - `app/Http/Controllers/Marketplace/MarketplaceController` untuk tab hub.
  - `app/Http/Controllers/Marketplace/Lazada/*` untuk Auth, Produk, dan Upload.
- View: `resources/views/marketplace/` (hub) dan `resources/views/marketplace/lazada/`.
- Superadmin: `Superadmin/Marketplace/MarketplaceAppController` dengan view `superadmin/marketplace/apps`.
- Route ada di `routes/web/marketplace.php`.

## Tabel
| Tabel | Isi |
|---|---|
| `marketplace_apps` | App Key/Secret per channel, diisi superadmin. Boleh beberapa app per channel, salah satunya default. Secret terenkripsi. |
| `marketplace_stores` | Toko yang sudah diotorisasi, per page katalog. Token terenkripsi, dan toko mencatat `app_id` yang dipakai saat otorisasi. |
| `marketplace_products` | Produk yang ditarik dari toko (GetProducts). |
| `marketplace_listings` | Riwayat upload ke toko. Sumbernya `catalog_product` atau `marketplace_product` (untuk jual ulang ke channel lain). |

## Alur
1. **Superadmin › Settings › Marketplace Apps**: tambahkan app Lazada (App Key, App Secret, region). Salin Callback URL yang ditampilkan ke Lazada ISV Console. Isinya harus sama persis.
2. **Owner › Pages › (page katalog) › tab Marketplace**: klik **Sambungkan Toko**, login dengan akun Seller Center, lalu klik Authorize. Setelah itu token tersimpan.
3. **Produk di Lazada**: klik **Tarik Produk dari Lazada**. Prosesnya memanggil GetProducts dengan 50 produk per halaman, maksimal `MARKETPLACE_SYNC_MAX_PRODUCTS` (default 1000).
4. **Upload ke Lazada**: pilih produk katalog, lalu kategori (cari atau saran dari nama), atribut wajib kategori, Brand ID, harga, stok, dan berat/dimensi. Sistem menjalankan MigrateImage lalu CreateProduct. Setelah itu produk menunggu QC Lazada.
5. Command `marketplace:refresh-tokens` berjalan setiap 6 jam dan memperpanjang token yang akan habis dalam 2 hari.

## Catatan
- Foto produk disalin Lazada dari URL publik bizbos, jadi upload tidak berjalan dari localhost.
- Produk katalog bizbos hanya punya 1 SKU. Variasi (sale prop) belum didukung.
- Izin API app di ISV Console:
  - Product Management untuk upload produk.
  - Price Stock Management untuk update harga dan stok (belum dipakai).
  - Order API untuk menarik pesanan (belum dipakai).
- Menambah TikTok Shop atau Shopee:
  1. Buat driver yang mengimplementasikan `MarketplaceChannel`.
  2. Daftarkan driver di `config/marketplace.php` dan set `enabled => true`.
  3. Tambahkan controller, view, dan route `marketplace/{channel}` dengan pola yang sama.
