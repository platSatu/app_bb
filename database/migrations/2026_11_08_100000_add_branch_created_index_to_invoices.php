<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuota invoice bulanan (PlanLimits) menghitung invoice per branch sejak tanggal 1 setiap kali invoice dibuat.
 * Index lama (branch_id, status, created_at) tidak bisa langsung dipakai untuk rentang tanggal tanpa status,
 * jadi untuk branch dengan banyak invoice hitungannya makin lambat. Index ini membuatnya tetap cepat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'created_at']);
        });
    }
};
