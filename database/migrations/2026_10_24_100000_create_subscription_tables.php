<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Langganan per branch per layanan (App\Support\Apps). Masa aktif bertumpuk saat diperpanjang.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Layanan yang dibuka paket ini, mis. ["schedules"] atau paket lengkap ["pages","schedules","invoices","whatsapp"].
            $table->json('apps')->nullable()->after('duration');
            $table->boolean('is_trial')->default(false)->after('apps');
        });

        // Satu baris per branch per layanan: satu-satunya sumber "menu terbuka/tertutup".
        Schema::create('branch_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('app_key', 30);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();

            $table->unique(['branch_id', 'app_key']);
            $table->index('ends_at');
        });

        // Jejak setiap penambahan masa aktif (beli, redeem voucher, trial, manual superadmin).
        Schema::create('subscription_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('app_key', 30);
            $table->foreignUuid('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->foreignUuid('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->string('source', 20);
            $table->integer('days');
            $table->dateTime('previous_ends_at')->nullable();
            $table->dateTime('new_ends_at');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'created_at']);
        });

        // Trial hanya sekali per nomor HP dan sekali per email.
        Schema::create('trial_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('user_branches')->nullOnDelete();
            $table->foreignUuid('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->timestamps();
        });

        // Pengingat H-7/H-4/H-2/H-1 yang sudah terkirim, per tanggal berakhir (diperpanjang = siklus baru).
        Schema::create('subscription_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subscription_id')->constrained('branch_subscriptions')->cascadeOnDelete();
            $table->dateTime('ends_at');
            $table->unsignedTinyInteger('days_before');
            $table->timestamps();

            $table->unique(['subscription_id', 'ends_at', 'days_before'], 'subscription_reminders_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_reminders');
        Schema::dropIfExists('trial_claims');
        Schema::dropIfExists('subscription_histories');
        Schema::dropIfExists('branch_subscriptions');

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['apps', 'is_trial']);
        });
    }
};
