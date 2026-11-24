<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->superadmin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);

    $provider = new AiProvider(['name' => 'Classai', 'driver' => 'classai', 'model' => 'claude-sonnet-5.5']);
    $provider->api_key = 'sk_live_test';
    $provider->is_active = true;
    $provider->save();
    $this->provider = $provider;

    Http::fake([
        'router.classai.id/v1/models' => Http::response(['data' => [
            ['id' => 'claude-sonnet-5.5', 'name' => 'Claude Sonnet 5.5'],
            ['id' => 'claude-haiku-5.5', 'name' => 'Claude Haiku 5.5'],
        ]]),
        'router.classai.id/v1/account' => Http::response(['balance' => ['credits' => 500000000, 'label' => '500 jt kredit', 'idr' => 75000]]),
        'router.classai.id/v1/messages' => fn () => Http::response(
            "data: {\"type\":\"message_start\",\"message\":{\"usage\":{\"input_tokens\":10}}}\n\n"
            ."data: {\"type\":\"content_block_delta\",\"delta\":{\"type\":\"text_delta\",\"text\":\"Halo dari AI\"}}\n\n"
            ."data: {\"type\":\"message_delta\",\"usage\":{\"output_tokens\":4}}\n\n",
            200, ['Content-Type' => 'text/event-stream']
        ),
    ]);
});

it('hanya superadmin yang bisa membuka Asisten AI', function () {
    $user = User::factory()->create(['user_type' => User::TYPE_USER]);

    $this->actingAs($user)->get(route('superadmin.ai-assistant.index'))->assertForbidden();
    $this->actingAs($this->superadmin)->get(route('superadmin.ai-assistant.index'))->assertOk()
        ->assertSee('Mau dibantu apa hari ini?')->assertSee('Claude Haiku 5.5');
});

it('mengirim pesan, menyimpan percakapan, dan memakai model yang dipilih', function () {
    $response = $this->actingAs($this->superadmin)->postJson(route('superadmin.ai-assistant.send'), [
        'message' => 'Buatkan caption promo',
        'model' => 'claude-haiku-5.5',
    ]);

    $response->assertOk();
    expect($response->streamedContent())->toContain('Halo dari AI')->toContain('"t":"done"');

    $conversation = AiConversation::first();
    expect($conversation->user_id)->toBe($this->superadmin->id)
        ->and($conversation->model)->toBe('claude-haiku-5.5')
        ->and($conversation->messages()->pluck('role')->all())->toBe([AiMessage::ROLE_USER, AiMessage::ROLE_ASSISTANT])
        ->and($conversation->messages()->where('role', 'assistant')->value('output_tokens'))->toBe(4);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/messages') && $request['model'] === 'claude-haiku-5.5');
});

it('menolak model yang tidak ada di daftar Classai', function () {
    $this->actingAs($this->superadmin)->postJson(route('superadmin.ai-assistant.send'), [
        'message' => 'Halo', 'model' => 'model-palsu',
    ])->assertStatus(422);

    expect(AiConversation::count())->toBe(0);
});

it('percakapan superadmin lain tidak bisa dibuka atau dihapus', function () {
    $other = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);
    $conversation = (new AiConversation(['title' => 'Rahasia']))->forceFill(['user_id' => $other->id]);
    $conversation->save();

    $this->actingAs($this->superadmin)->get(route('superadmin.ai-assistant.show', $conversation))->assertNotFound();
    $this->actingAs($this->superadmin)->delete(route('superadmin.ai-assistant.destroy', $conversation))->assertNotFound();
    $this->actingAs($this->superadmin)->postJson(route('superadmin.ai-assistant.send'), [
        'message' => 'Halo', 'conversation_id' => $conversation->id,
    ])->assertNotFound();

    expect(AiConversation::count())->toBe(1);
});

it('menampilkan daftar model dan saldo Classai di Settings › AI', function () {
    $this->actingAs($this->superadmin)->getJson(route('superadmin.ai.models', $this->provider))
        ->assertOk()->assertJsonPath('models.1.id', 'claude-haiku-5.5');

    $this->actingAs($this->superadmin)->getJson(route('superadmin.ai.account', $this->provider))
        ->assertOk()->assertJsonPath('label', '500 jt kredit')->assertJsonPath('idr', 75000);
});
