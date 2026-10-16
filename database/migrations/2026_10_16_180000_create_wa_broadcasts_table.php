<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| WhatsApp > Settings > Message: pesan sekali kirim / blast, langsung, terjadwal, atau berulang.
| Setiap kali dijalankan dibuat satu wa_campaigns (progres per putaran), penerimanya baris wa_outbox.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_broadcasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained('wa_devices')->cascadeOnDelete();
            $table->string('name', 150);
            // once = ke kontak/nomor pilihan, blast = ke Group kontak
            $table->string('type', 10);
            $table->text('body')->nullable();
            $table->foreignUuid('upload_id')->nullable()->constrained('wa_uploads')->nullOnDelete();
            $table->string('upload_kind', 20)->nullable();
            $table->string('upload_name', 191)->nullable();
            // {group_ids: [], customer_ids: [], phones: []}
            $table->json('target');
            // now | at | daily | weekly | monthly
            $table->string('schedule', 10)->default('now');
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('runs_count')->default(0);
            // active | paused | done | cancelled
            $table->string('status', 20)->default('active');
            $table->string('last_error')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'next_run_at']);
        });

        Schema::table('wa_campaigns', function (Blueprint $table) {
            $table->foreignUuid('broadcast_id')->nullable()->after('device_id')->constrained('wa_broadcasts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wa_campaigns', fn (Blueprint $table) => $table->dropConstrainedForeignId('broadcast_id'));
        Schema::dropIfExists('wa_broadcasts');
    }
};
