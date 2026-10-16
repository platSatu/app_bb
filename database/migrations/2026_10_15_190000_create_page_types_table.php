<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        // Nullable di DB hanya untuk page lama; dari form tetap wajib dipilih.
        Schema::table('page_categories', function (Blueprint $table) {
            $table->foreignUuid('page_type_id')->nullable()->after('branch_id')->constrained('page_types')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('page_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('page_type_id');
        });

        Schema::dropIfExists('page_types');
    }
};
