<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Marketplace (tab di Catalog Online). Satu page katalog bisa menyambungkan beberapa toko
| marketplace (Lazada sekarang; TikTok Shop & Shopee menyusul).
| - marketplace_apps     : kredensial app developer (App Key/Secret) yang diisi superadmin.
|                          Boleh lebih dari satu per channel (akun developer berbeda).
| - marketplace_stores   : toko yang sudah diotorisasi (token disimpan terenkripsi).
| - marketplace_products : produk yang ditarik dari toko marketplace (untuk dilihat / dijual ulang).
| - marketplace_listings : produk yang diunggah ke toko marketplace, sumbernya produk katalog
|                          sendiri atau produk marketplace lain (mis. Lazada -> TikTok).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_apps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel', 20);
            $table->string('name', 100);
            $table->string('app_key', 100);
            // Terenkripsi (cast 'encrypted' di model).
            $table->text('app_secret');
            // Kosong = pakai alamat callback bawaan bizbos (route marketplace.{channel}.callback).
            $table->string('callback_url')->nullable();
            // Negara default API (id, my, sg, th, vn, ph).
            $table->string('region', 5)->default('id');
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'app_key'], 'marketplace_apps_key_unique');
        });

        Schema::create('marketplace_stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Token toko hanya berlaku untuk App Key yang mengotorisasinya.
            $table->foreignUuid('app_id')->constrained('marketplace_apps')->restrictOnDelete();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            // lazada | tiktok | shopee
            $table->string('channel', 20);
            // Id toko/seller di marketplace (Lazada: seller_id).
            $table->string('external_id', 64);
            $table->string('name', 150)->nullable();
            $table->string('country', 5)->nullable();
            $table->string('account')->nullable();
            $table->string('short_code', 50)->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_expires_at')->nullable();
            $table->timestamp('refresh_expires_at')->nullable();
            // connected | expired | error
            $table->string('status', 20)->default('connected');
            $table->string('status_reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'channel', 'external_id'], 'marketplace_stores_unique');
            $table->index(['status', 'access_expires_at'], 'marketplace_stores_refresh_index');
        });

        Schema::create('marketplace_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->string('external_id', 64);
            $table->string('name');
            $table->string('sku', 150)->nullable();
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedBigInteger('special_price')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->string('status', 50)->nullable();
            $table->string('category_id', 50)->nullable();
            $table->text('image')->nullable();
            $table->text('url')->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('external_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'external_id'], 'marketplace_products_unique');
            $table->index(['page_id', 'store_id'], 'marketplace_products_page_index');
        });

        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            // Toko tujuan unggah.
            $table->foreignUuid('store_id')->constrained('marketplace_stores')->cascadeOnDelete();
            // catalog_product | marketplace_product
            $table->string('source_type', 30);
            $table->uuid('source_id');
            $table->string('external_id', 64)->nullable();
            $table->string('external_sku', 150)->nullable();
            // submitted (menunggu QC marketplace) | failed
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'marketplace_listings_source_index');
            $table->index(['store_id', 'status'], 'marketplace_listings_store_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listings');
        Schema::dropIfExists('marketplace_products');
        Schema::dropIfExists('marketplace_stores');
        Schema::dropIfExists('marketplace_apps');
    }
};
