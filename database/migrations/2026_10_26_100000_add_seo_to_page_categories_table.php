<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Banner header & SEO per page. Semua opsional; kosong = otomatis dari nama/deskripsi/banner/gambar bizbos.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_categories', function (Blueprint $table) {
            $table->string('banner')->nullable()->after('description');
            $table->string('meta_title', 70)->nullable()->after('banner');
            $table->string('meta_description', 160)->nullable()->after('meta_title');
            $table->string('og_image')->nullable()->after('meta_description');
            $table->string('ga_id', 20)->nullable()->after('og_image');
        });
    }

    public function down(): void
    {
        Schema::table('page_categories', function (Blueprint $table) {
            $table->dropColumn(['banner', 'meta_title', 'meta_description', 'og_image', 'ga_id']);
        });
    }
};
