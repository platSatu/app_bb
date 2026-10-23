<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Third Party > Google Form: setiap form yang diisi orang otomatis dibalas WhatsApp lewat device pilihan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_form_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained('wa_devices')->cascadeOnDelete();
            $table->string('type', 20)->default('google_form');
            $table->string('name', 150);
            // Judul pertanyaan di Google Form yang berisi nomor WhatsApp pengisi.
            $table->string('phone_field', 191);
            $table->text('message');
            // Token di URL webhook = kunci akses; bisa diganti kapan saja.
            $table->string('webhook_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('trigger_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'type']);
        });

        Schema::create('wa_form_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('integration_id')->constrained('wa_form_integrations')->cascadeOnDelete();
            $table->json('payload');
            $table->string('phone', 20)->nullable();
            $table->string('status', 20); // sent | failed
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['integration_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_form_submissions');
        Schema::dropIfExists('wa_form_integrations');
    }
};
