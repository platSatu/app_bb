<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batas paket (App\Services\Subscription\PlanLimits).
 * packages.limits             : {"pages": {"<page_type_id>": 1}, "invoices": {"monthly": 300}, "whatsapp": {"monthly": 1000}}
 * branch_subscriptions.limits : bagian layanan itu saja, disalin saat paket diaktifkan. Key tidak ada = tanpa batas;
 *                               NULL = langganan lama sebelum fitur ini (tanpa batas sampai paket berikutnya).
 * plan_usages                 : penghitung pesan WhatsApp per branch per bulan (page & invoice dihitung dari datanya langsung).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->json('limits')->nullable()->after('apps');
        });

        Schema::table('branch_subscriptions', function (Blueprint $table) {
            $table->json('limits')->nullable()->after('ends_at');
        });

        Schema::create('plan_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('metric', 30);
            $table->char('period', 7); // YYYY-MM, reset otomatis tiap tanggal 1
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'metric', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_usages');
        Schema::table('branch_subscriptions', fn (Blueprint $table) => $table->dropColumn('limits'));
        Schema::table('packages', fn (Blueprint $table) => $table->dropColumn('limits'));
    }
};
