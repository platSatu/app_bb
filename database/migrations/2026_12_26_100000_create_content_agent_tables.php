<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tools › Konten Harian & Tools › Akun Sosmed.
 *
 * - content_agent_settings: pengaturan Agent Konten (satu baris): pilar, gaya bahasa, model media, batas harian.
 * - content_posts: satu konten (reel / carousel / gambar) per tanggal & jam tayang.
 *   status: draft -> approved (sudah dibaca) -> ready (media jadi) -> scheduled -> published | partial | failed.
 * - social_apps: kredensial app developer (Meta, TikTok, Google), rahasia terenkripsi.
 * - social_accounts: akun yang tersambung (Instagram, Facebook Page, TikTok, YouTube), token terenkripsi.
 * - content_publications: satu konten ke satu akun; status kiriman & ID posting di platform.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_agent_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('pillars')->nullable();                 // [{name, description}]
            $table->text('brand_voice')->nullable();
            $table->text('audience')->nullable();
            $table->text('offer')->nullable();                   // fakta & ajakan yang boleh disebut (tanpa harga karangan)
            $table->text('visual_style')->nullable();
            $table->json('formats')->nullable();                 // {"reel": 40, "carousel": 40, "image": 20} (persen)
            $table->json('post_times')->nullable();              // ["11:30", "19:00"]
            $table->boolean('auto_media')->default(true);        // konten disetujui -> media dibuat otomatis
            $table->string('image_model', 100)->nullable();
            $table->string('video_model', 100)->nullable();
            $table->string('video_resolution', 10)->nullable();
            $table->unsignedTinyInteger('video_seconds')->default(8);
            $table->unsignedTinyInteger('carousel_slides')->default(5);
            $table->unsignedSmallInteger('media_daily_limit')->default(10);
            $table->json('plan_state')->nullable();              // progres "Buat rencana"
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('content_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('batch_id', 40)->nullable()->index();
            $table->date('publish_date');
            $table->string('publish_time', 5)->default('19:00');
            $table->string('format', 20);                         // reel | carousel | image
            $table->string('pillar', 80)->nullable();
            $table->string('title', 200);
            $table->string('hook', 300)->nullable();
            $table->text('script')->nullable();
            $table->json('slides')->nullable();                  // carousel: [{heading, body}]
            $table->text('caption')->nullable();
            $table->string('hashtags', 500)->nullable();
            $table->text('visual_prompt')->nullable();
            $table->string('status', 20)->default('draft');
            $table->dateTime('approved_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('media_status', 20)->nullable()->index(); // queued | processing | done | failed
            $table->json('media')->nullable();                   // [{path, mime, size}]
            $table->boolean('media_ai')->default(false);         // media buatan AI (label AI saat upload)
            $table->string('media_job_id', 100)->nullable();
            $table->string('media_error', 300)->nullable();
            $table->unsignedBigInteger('media_cost_micro_rp')->nullable();
            $table->dateTime('media_started_at')->nullable()->index();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->foreignUuid('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('options')->nullable();                 // mis. privasi & komentar TikTok
            $table->timestamps();

            $table->index(['status', 'publish_date']);
        });

        Schema::create('social_apps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('platform', 20)->unique();            // meta | tiktok | google
            $table->string('client_id', 200);
            $table->text('client_secret');
            $table->json('meta')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('social_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('platform', 20);                       // instagram | facebook | tiktok | youtube
            $table->string('external_id', 100);
            $table->string('name', 200);
            $table->string('username', 200)->nullable();
            $table->text('avatar_url')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->dateTime('refresh_expires_at')->nullable();
            $table->json('meta')->nullable();
            $table->string('status', 20)->default('connected');  // connected | expired | error
            $table->string('status_reason', 300)->nullable();
            $table->boolean('is_default')->default(true);        // tujuan bawaan saat "Jadwalkan terpilih"
            $table->foreignUuid('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'external_id']);
        });

        Schema::create('content_publications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('content_post_id')->constrained('content_posts')->cascadeOnDelete();
            $table->foreignUuid('social_account_id')->nullable()->constrained('social_accounts')->nullOnDelete();
            $table->string('platform', 20);
            $table->string('status', 20)->default('pending');    // pending | publishing | processing | published | failed
            $table->string('remote_id', 150)->nullable();        // ID container / tugas di platform (sebelum terbit)
            $table->string('external_id', 150)->nullable();      // ID posting setelah terbit
            $table->text('permalink')->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();

            $table->unique(['content_post_id', 'social_account_id']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_publications');
        Schema::dropIfExists('social_accounts');
        Schema::dropIfExists('social_apps');
        Schema::dropIfExists('content_posts');
        Schema::dropIfExists('content_agent_settings');
    }
};
