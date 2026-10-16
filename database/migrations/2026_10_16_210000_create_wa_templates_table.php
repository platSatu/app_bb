<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| WhatsApp > Settings > Template: header, isi (format WA), footer, link & lampiran opsional.
| Lampiran disimpan privat di storage lokal, diunggah ke go_bizbos saat dikirim.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('header', 60)->nullable();
            $table->text('body');
            $table->string('footer', 60)->nullable();
            $table->string('link', 500)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name', 191)->nullable();
            $table->string('file_mime', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_templates');
    }
};
