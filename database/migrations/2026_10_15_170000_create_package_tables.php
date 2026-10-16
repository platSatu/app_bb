<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_application_id')->constrained('category_applications')->restrictOnDelete();
            $table->string('name', 150);
            $table->unsignedBigInteger('price');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['category_application_id', 'status']);
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('package_id')->constrained('packages')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->unsignedBigInteger('price');
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->foreignUuid('redeemed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('redeemed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'redeemed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('category_applications');
    }
};
