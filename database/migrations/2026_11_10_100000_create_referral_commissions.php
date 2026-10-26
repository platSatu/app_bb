<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referral versi komisi (App\Services\Referral\ReferralService):
 * - referral_settings  : persen default (20), lama ditahan (30 hari), lama berlaku sejak daftar (12 bulan).
 *                        Bonus top up lama (reward_amount, min_deposit_amount) dihapus.
 * - referral_codes     : persen khusus per pengajak (diatur superadmin, NULL = default) dan
 *                        bagian yang diberikan pengajak ke orang yang diajak sebagai potongan harga.
 * - referral_commissions: satu baris per pembelian paket; ditahan dulu, lalu cair ke saldo atau dibatalkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('default_percent')->default(20)->after('is_active');
            $table->unsignedSmallInteger('hold_days')->default(30)->after('default_percent');
            $table->unsignedSmallInteger('active_months')->default(12)->after('hold_days');
        });

        Schema::table('referral_settings', function (Blueprint $table) {
            $table->dropColumn(['reward_amount', 'min_deposit_amount']);
        });

        Schema::table('referral_codes', function (Blueprint $table) {
            $table->unsignedTinyInteger('commission_percent')->nullable()->after('code');
            $table->unsignedTinyInteger('share_percent')->default(0)->after('commission_percent');
        });

        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Hak pengajak tetap tercatat walau akun yang diajak / vouchernya dihapus.
            $table->foreignUuid('referral_id')->nullable()->constrained('referrals')->nullOnDelete();
            $table->foreignUuid('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('voucher_id')->nullable()->unique()->constrained('vouchers')->nullOnDelete();
            $table->string('package_name', 150);
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('discount_amount');
            $table->unsignedBigInteger('paid_amount');
            $table->unsignedTinyInteger('commission_percent');
            $table->unsignedTinyInteger('share_percent');
            $table->unsignedBigInteger('amount');
            $table->string('status', 20);
            $table->dateTime('release_at');
            $table->dateTime('released_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'release_at']);
            $table->index(['referrer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');

        Schema::table('referral_codes', fn (Blueprint $table) => $table->dropColumn(['commission_percent', 'share_percent']));

        Schema::table('referral_settings', function (Blueprint $table) {
            $table->dropColumn(['default_percent', 'hold_days', 'active_months']);
            $table->unsignedBigInteger('reward_amount')->default(0);
            $table->unsignedBigInteger('min_deposit_amount')->default(0);
        });
    }
};
