<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Template Ticket. 1 page = 1 event. Data pembayaran memakai restrictOnDelete: catatan uang tidak boleh ikut terhapus.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('quota');
            $table->unsignedInteger('sold')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->unsignedSmallInteger('max_per_order')->default(10);
            $table->dateTime('sale_starts_at')->nullable();
            $table->dateTime('sale_ends_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['page_id', 'sort_order'], 'ticket_categories_order_index');
        });

        Schema::create('ticket_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->restrictOnDelete();
            $table->foreignUuid('company_id')->constrained('user_companies')->restrictOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('reference', 20)->unique();
            // Kunci link halaman order untuk pembeli (tidak bisa ditebak).
            $table->string('access_token', 64)->unique();
            $table->string('name', 150);
            $table->string('email');
            $table->string('phone', 20);
            $table->unsignedBigInteger('total');
            $table->string('status', 20)->default('pending');
            $table->dateTime('expires_at');
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_url')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('provider_reference', 100)->nullable();
            $table->string('failure_reason')->nullable();
            $table->dateTime('email_sent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['page_id', 'status', 'created_at'], 'ticket_orders_page_index');
            $table->index(['status', 'expires_at'], 'ticket_orders_expiry_index');
        });

        Schema::create('ticket_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('ticket_orders')->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->string('name', 100);
            $table->unsignedBigInteger('price');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });

        // 1 baris = 1 orang/QR. Dibuat hanya setelah order lunas.
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('ticket_orders')->cascadeOnDelete();
            $table->foreignUuid('page_id')->constrained('page_categories')->restrictOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->string('category_name', 100);
            $table->string('holder_name', 150);
            $table->string('booking_code', 6);
            $table->string('number', 12)->unique();
            // Isi QR & kunci link e-ticket.
            $table->string('token', 64)->unique();
            $table->unsignedSmallInteger('sequence');
            $table->dateTime('checked_in_at')->nullable();
            $table->foreignUuid('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['page_id', 'booking_code'], 'tickets_booking_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_order_items');
        Schema::dropIfExists('ticket_orders');
        Schema::dropIfExists('ticket_categories');
    }
};
