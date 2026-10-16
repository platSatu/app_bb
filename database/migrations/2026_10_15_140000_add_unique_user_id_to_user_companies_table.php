<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 1 user = 1 perusahaan (Settings user & menu Superadmin sama-sama mengandalkan ini).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_companies', function (Blueprint $table) {
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_companies', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });
    }
};
