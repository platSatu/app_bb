<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Template Catalog Online. page_id = page_categories (page milik branch), jadi 1 page katalog = 1 toko.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['page_id', 'slug'], 'catalog_categories_slug_unique');
        });

        Schema::create('catalog_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('catalog_categories')->nullOnDelete();
            // physical | digital (digital disiapkan, belum dipakai).
            $table->string('type', 20)->default('physical');
            $table->string('name', 150);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['page_id', 'slug'], 'catalog_products_slug_unique');
            $table->index(['page_id', 'status', 'sort_order'], 'catalog_products_list_index');
        });

        Schema::create('catalog_product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('catalog_products')->cascadeOnDelete();
            $table->string('name', 150)->nullable();
            $table->string('description')->nullable();
            $table->string('image');
            $table->string('thumbnail');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['product_id', 'sort_order'], 'catalog_images_order_index');
        });

        Schema::create('catalog_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('user_companies')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('reference', 20)->unique();
            $table->string('name', 150);
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('total');
            $table->string('status', 20)->default('new');
            $table->boolean('stock_restored')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['page_id', 'created_at'], 'catalog_orders_page_index');
            $table->index(['company_id', 'created_at'], 'catalog_orders_company_index');
        });

        // Nama & harga disalin saat checkout, supaya pesanan lama tidak berubah saat produk diedit.
        Schema::create('catalog_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('catalog_orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('catalog_products')->nullOnDelete();
            $table->string('name', 150);
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_order_items');
        Schema::dropIfExists('catalog_orders');
        Schema::dropIfExists('catalog_product_images');
        Schema::dropIfExists('catalog_products');
        Schema::dropIfExists('catalog_categories');
    }
};
