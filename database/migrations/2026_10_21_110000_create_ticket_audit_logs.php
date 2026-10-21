<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit tiket: hanya ditambah, tidak pernah diubah/dihapus aplikasi
 * (kecuali status email yang naik queued -> sent/failed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_stock_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('ticket_categories')->cascadeOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('ticket_orders')->nullOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->unsignedInteger('quota_before');
            $table->unsignedInteger('quota_after');
            $table->unsignedInteger('reserved_before');
            $table->unsignedInteger('reserved_after');
            $table->unsignedInteger('sold_before');
            $table->unsignedInteger('sold_after');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['category_id', 'created_at']);
            $table->index(['page_id', 'created_at']);
        });

        Schema::create('ticket_email_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->foreignUuid('order_id')->constrained('ticket_orders')->cascadeOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('trigger', 10);
            $table->string('status', 10);
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['page_id', 'created_at']);
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_email_logs');
        Schema::dropIfExists('ticket_stock_logs');
    }
};
