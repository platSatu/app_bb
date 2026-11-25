<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TikTok Shop: link otorisasi seller memakai service_id (berbeda dari app_key), dari halaman app di Partner Center.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_apps', function (Blueprint $table) {
            $table->string('service_id', 64)->nullable()->after('app_secret');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_apps', function (Blueprint $table) {
            $table->dropColumn('service_id');
        });
    }
};
