<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Paket unggulan: tampil dengan badge "Populer" di katalog & halaman harga website.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_trial');
        });
    }

    public function down(): void
    {
        Schema::table('packages', fn (Blueprint $table) => $table->dropColumn('is_featured'));
    }
};
