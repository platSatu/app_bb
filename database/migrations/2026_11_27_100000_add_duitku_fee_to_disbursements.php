<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pisahkan biaya Duitku dari biaya tarik saldo/disbursement yang ditagih ke user (hanya untuk laporan admin).
 * - disbursement_settings.duitku_fee : biaya Duitku per transfer saat ini (diatur superadmin).
 * - wallet_withdrawals / disbursements.duitku_fee : biaya Duitku yang berlaku saat transfer dibuat (snapshot),
 *   supaya laporan lama tidak berubah kalau tarif Duitku diganti.
 * Transfer lama diisi Rp 5.000 (tarif Duitku yang berlaku sampai sekarang).
 */
return new class extends Migration
{
    private const CURRENT_DUITKU_FEE = 5000;

    public function up(): void
    {
        Schema::table('disbursement_settings', function (Blueprint $table) {
            $table->unsignedInteger('duitku_fee')->default(self::CURRENT_DUITKU_FEE)->after('withdrawal_fee');
        });

        foreach (['wallet_withdrawals', 'disbursements'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedInteger('duitku_fee')->nullable()->after('fee');
            });

            DB::table($name)->whereNull('duitku_fee')->update(['duitku_fee' => self::CURRENT_DUITKU_FEE]);
        }
    }

    public function down(): void
    {
        foreach (['wallet_withdrawals', 'disbursements', 'disbursement_settings'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('duitku_fee'));
        }
    }
};
