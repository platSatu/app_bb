<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tools › Prospek: calon pelanggan bizbos (mis. tempat kursus) yang dipilih dari Tools › Open Places,
 * kolom mengikuti Google Maps + kolom follow-up. Hanya superadmin. Data Google Places tidak disimpan
 * otomatis (ketentuan Google); rating / ulasan / jam buka diisi manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source', 20)->default('open');          // open | manual
            $table->string('external_id', 150)->nullable()->unique(); // ID tempat dari sumbernya, supaya tidak dobel
            $table->string('name', 200);
            $table->string('category', 150)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('city', 150)->nullable();                  // kecamatan / kota
            $table->string('province', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('phone', 30)->nullable()->index();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('website', 300)->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('rating_count')->nullable();
            $table->string('opening_hours', 300)->nullable();
            $table->string('maps_url', 300)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->boolean('is_closed')->default(false);
            $table->string('status', 20)->default('new')->index();
            $table->text('notes')->nullable();
            $table->dateTime('contacted_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['city', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};
