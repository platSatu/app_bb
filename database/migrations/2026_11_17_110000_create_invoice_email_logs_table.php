<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Riwayat email invoice ke customer (tagihan, pengingat, bukti lunas) beserta status kirimnya. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_email_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('recipient');
            // queued (masuk antrean) | sent (diterima server email) | failed
            $table->string('status', 20)->default('queued');
            $table->string('error', 500)->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_email_logs');
    }
};
