<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('company_id')->constrained('user_companies')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 170)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('handphone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->nullable()->default('active')->index();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_branches');
    }
};
