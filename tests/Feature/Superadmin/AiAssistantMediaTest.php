<?php

use App\Jobs\GenerateAiMedia;
use App\Models\AiMessage;
use App\Models\AiProvider;
use App\Models\User;
use App\Services\Ai\ClassaiMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/** Balasan Classai tiruan; $override menimpa balasan bawaan untuk alamat yang sama. */
function fakeClassaiMedia(array $override = []): void
{
    $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    $mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 64);

    Http::fake([...[
        // Katalog dari API tidak terbaca -> dipakai katalog dari dokumentasi.
        'router.classai.id/v1/media/models' => Http::response([], 500),
        'router.classai.id/v1/audio/voices?model=*' => Http::response(['data' => [['id' => 'v-ayu', 'name' => 'Ayu', 'gender' => 'female']]]),
        'router.classai.id/v1/audio/voices' => Http::response([], 500),
        'router.classai.id/v1/images/generations' => Http::response(['created' => 1, 'data' => [['b64_json' => $png]]], 200, ['x-classai-cost-micro-rp' => '495000000']),
        'router.classai.id/v1/audio/speech' => Http::response('ID3'.str_repeat("\x00", 200), 200, ['Content-Type' => 'audio/mpeg']),
        'router.classai.id/v1/videos' => Http::response(['id' => 'vid_1', 'object' => 'video', 'status' => 'in_progress']),
        'router.classai.id/v1/videos/vid_1' => Http::response(['id' => 'vid_1', 'object' => 'video', 'status' => 'completed']),
        'router.classai.id/v1/videos/vid_1/content' => Http::response($mp4, 200, ['Content-Type' => 'video/mp4']),
    ], ...$override]);
}

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();

    $this->superadmin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);

    $provider = new AiProvider(['name' => 'Classai', 'driver' => 'classai', 'model' => 'claude-sonnet-5.5']);
    $provider->api_key = 'sk_live_test';
    $provider->is_active = true;
    $provider->save();

});

function runMediaJob(string $messageId): void
{
    (new GenerateAiMedia($messageId))->handle(app(ClassaiMedia::class));
}

it('membuat gambar lewat antrean dan file hanya bisa dibuka pemiliknya', function () {
    fakeClassaiMedia();
    $response = $this->actingAs($this->superadmin)->post(route('superadmin.ai-assistant.media.generate'), [
        'kind' => 'image', 'model' => 'flux-2-klein', 'prompt' => 'Poster les privat', 'aspect_ratio' => '4:5', 'resolution' => '1K', 'n' => 1,
    ], ['Accept' => 'application/json']);

    $response->assertOk()->assertJsonPath('assistant.status', 'pending')->assertJsonPath('user.kind', 'image');
    Queue::assertPushed(GenerateAiMedia::class);

    $id = $response->json('assistant.id');
    runMediaJob($id);

    $message = AiMessage::find($id);
    expect($message->status)->toBe(AiMessage::STATUS_DONE)
        ->and($message->media)->toHaveCount(1)
        ->and($message->options['cost_micro_rp'])->toBe(495000000);
    Storage::disk('local')->assertExists($message->media[0]['path']);

    $this->actingAs($this->superadmin)->getJson(route('superadmin.ai-assistant.media.status', $id))
        ->assertOk()->assertJsonPath('message.status', 'done')->assertJsonPath('message.cost_rp', 495);
    $this->actingAs($this->superadmin)->get(route('superadmin.ai-assistant.file', [$id, 0]))
        ->assertOk()->assertHeader('Content-Type', 'image/png');

    $other = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);
    $this->actingAs($other)->get(route('superadmin.ai-assistant.file', [$id, 0]))->assertNotFound();
    $this->actingAs($other)->getJson(route('superadmin.ai-assistant.media.status', $id))->assertNotFound();

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/images/generations') && $r['aspect_ratio'] === '4:5' && $r['model'] === 'flux-2-klein');
});

it('membuat video: mulai tugas, cek status, unduh hasil', function () {
    fakeClassaiMedia();
    $response = $this->actingAs($this->superadmin)->post(route('superadmin.ai-assistant.media.generate'), [
        'kind' => 'video', 'model' => 'wan-3.0', 'prompt' => 'Guru tersenyum', 'aspect_ratio' => '9:16', 'resolution' => '720p', 'seconds' => 8,
    ], ['Accept' => 'application/json'])->assertOk();

    $id = $response->json('assistant.id');
    runMediaJob($id);                 // tahap 1: tugas dimulai
    expect(AiMessage::find($id)->job_id)->toBe('vid_1');

    runMediaJob($id);                 // tahap 2: completed -> unduh
    $message = AiMessage::find($id);
    expect($message->status)->toBe(AiMessage::STATUS_DONE)->and($message->media[0]['mime'])->toBe('video/mp4');

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/videos') && $r['seconds'] === '8' && $r['aspect_ratio'] === '9:16');
});

it('membuat suara dengan suara yang dipilih', function () {
    fakeClassaiMedia();
    $this->actingAs($this->superadmin)->getJson(route('superadmin.ai-assistant.media.voices', ['model' => 'voxcpm2']))
        ->assertOk()->assertJsonPath('voices.0.id', 'v-ayu');

    $response = $this->actingAs($this->superadmin)->post(route('superadmin.ai-assistant.media.generate'), [
        'kind' => 'audio', 'model' => 'voxcpm2', 'prompt' => 'Coba gratis di bizbos.', 'voice' => 'v-ayu', 'style' => 'iklan', 'speed' => 1.1,
    ], ['Accept' => 'application/json'])->assertOk();

    $id = $response->json('assistant.id');
    runMediaJob($id);

    expect(AiMessage::find($id)->status)->toBe(AiMessage::STATUS_DONE);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/audio/speech') && $r['voice'] === 'v-ayu' && $r['input'] === 'Coba gratis di bizbos.');
});

it('menolak isian yang tidak sesuai model', function () {
    fakeClassaiMedia();
    $user = $this->actingAs($this->superadmin);

    // Model edit wajib 1 gambar.
    $user->post(route('superadmin.ai-assistant.media.generate'), [
        'kind' => 'image', 'model' => 'flux-kontext-pro', 'prompt' => 'Ganti latar', 'aspect_ratio' => '1:1',
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('reference');

    // GPT Image 1 hanya 1:1, 3:2, 2:3.
    $user->post(route('superadmin.ai-assistant.media.generate'), [
        'kind' => 'image', 'model' => 'gpt-image-1', 'prompt' => 'x', 'aspect_ratio' => '9:16',
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('aspect_ratio');

    // Veo hanya 4/6/8 detik.
    $user->post(route('superadmin.ai-assistant.media.generate'), [
        'kind' => 'video', 'model' => 'veo-3.1-fast', 'prompt' => 'x', 'aspect_ratio' => '16:9', 'resolution' => '720p', 'seconds' => 5,
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('seconds');

    // Model tidak dikenal.
    $user->post(route('superadmin.ai-assistant.media.generate'), [
        'kind' => 'image', 'model' => 'model-palsu', 'prompt' => 'x', 'aspect_ratio' => '1:1',
    ], ['Accept' => 'application/json'])->assertStatus(422);

    // File bukan gambar ditolak.
    $user->post(route('superadmin.ai-assistant.media.generate'), [
        'kind' => 'image', 'model' => 'flux-kontext-pro', 'prompt' => 'x', 'aspect_ratio' => '1:1',
        'reference' => UploadedFile::fake()->create('virus.php', 10, 'text/x-php'),
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('reference');

    expect(AiMessage::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('gambar gagal ditandai gagal dengan pesan yang jelas', function () {
    fakeClassaiMedia(['router.classai.id/v1/images/generations' => Http::response(['error' => ['message' => 'insufficient balance']], 402)]);

    $id = $this->actingAs($this->superadmin)->post(route('superadmin.ai-assistant.media.generate'), [
        'kind' => 'image', 'model' => 'flux-2-klein', 'prompt' => 'x', 'aspect_ratio' => '1:1',
    ], ['Accept' => 'application/json'])->json('assistant.id');

    runMediaJob($id);

    $message = AiMessage::find($id);
    expect($message->status)->toBe(AiMessage::STATUS_FAILED)->and($message->content)->toContain('Saldo');
});

it('media hanya tersedia kalau penyedia aktif Classai', function () {
    fakeClassaiMedia();
    AiProvider::query()->update(['driver' => 'openai']);

    $this->actingAs($this->superadmin)->getJson(route('superadmin.ai-assistant.media.options'))->assertStatus(422);
});

it('job yang sama tidak membuat gambar dua kali', function () {
    fakeClassaiMedia();
    $id = $this->actingAs($this->superadmin)->post(route('superadmin.ai-assistant.media.generate'), [
        'kind' => 'image', 'model' => 'flux-2-klein', 'prompt' => 'x', 'aspect_ratio' => '1:1',
    ], ['Accept' => 'application/json'])->json('assistant.id');

    runMediaJob($id);
    runMediaJob($id);

    expect(Http::recorded(fn ($request) => str_ends_with($request->url(), '/images/generations')))->toHaveCount(1);
});
