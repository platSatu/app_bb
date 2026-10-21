<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Portal murid/orang tua & pengajar: satu akun (users.user_type = member) bisa terhubung ke banyak murid/pengajar.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_students', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('schedule_teachers', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->after('branch_id')->constrained('users')->nullOnDelete();
            $table->unique(['branch_id', 'user_id']);
        });

        Schema::create('schedule_portal_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'type']);
        });

        Schema::create('schedule_portal_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name', 150);
            $table->string('guardian_name', 150)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('status', 20)->default('pending');
            // Murid/pengajar yang dihubungkan saat disetujui.
            $table->uuid('member_id')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_portal_requests');
        Schema::dropIfExists('schedule_portal_links');

        Schema::table('schedule_teachers', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('schedule_students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
