<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Shopee punya Test Partner ID/Key (sandbox) dan Live Partner ID/Key (produksi) yang tidak bisa saling dipakai.
// Lazada selalu 'live'.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_apps', function (Blueprint $table) {
            $table->string('environment', 10)->default('live')->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_apps', function (Blueprint $table) {
            $table->dropColumn('environment');
        });
    }
};
