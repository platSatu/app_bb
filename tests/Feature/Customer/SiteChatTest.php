<?php

use App\Models\AiProvider;
use App\Models\Customer;
use App\Models\User;
use App\Models\WebFaq;
use App\Models\WebSetting;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => config(['services.frontend.key' => 'fe-key']));

function chat(array $body, string $key = 'fe-key')
{
    return test()->withHeaders(['X-API-KEY' => $key])->postJson('/api/frontend/chat', $body);
}

it('menjawab lewat AI kalau dinyalakan superadmin, dengan pengetahuan dari konten website', function () {
    WebSetting::current()->update(['chat_ai' => true]);
    (new WebFaq())->forceFill(['name' => 'Apakah bisa coba gratis?', 'descriptions' => '<p>Bisa, ada trial 14 hari.</p>', 'status' => 'active'])->save();
    $provider = new AiProvider(['name' => 'OpenAI', 'driver' => 'openai', 'model' => 'gpt-4o-mini']);
    $provider->forceFill(['api_key' => 'sk-test', 'is_active' => true])->save();
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Bisa! Ada trial 14 hari.']]]])]);

    chat(['message' => 'bisa coba gratis?', 'history' => [['role' => 'assistant', 'content' => 'Halo!']], 'visitor' => '1.2.3.4'])
        ->assertOk()->assertJsonPath('data.reply', 'Bisa! Ada trial 14 hari.');

    Http::assertSent(fn (HttpRequest $r) => str_contains(json_encode($r->data()), 'trial 14 hari') && str_contains(json_encode($r->data()), 'bisa coba gratis?'));
});

it('tanpa AI menjawab dari FAQ dengan sapaan bernama, paham bahasa chat, dan mengarahkan ke WhatsApp kalau tidak tahu', function () {
    (new WebFaq())->forceFill(['name' => 'Bagaimana cara daftar?', 'descriptions' => 'Klik Daftar lalu isi email.', 'status' => 'active'])->save();
    (new WebFaq())->forceFill(['name' => 'Berapa harga paket?', 'descriptions' => 'Mulai Rp429.000 untuk 6 bulan.', 'status' => 'active'])->save();
    // AI tersedia tapi live chat tidak memakai AI (default): tidak ada panggilan ke AI.
    (new AiProvider(['name' => 'OpenAI', 'driver' => 'openai', 'model' => 'gpt-4o-mini']))->forceFill(['api_key' => 'sk-test', 'is_active' => true])->save();
    Http::fake();

    chat(['message' => 'cara daftarnya gimana kak', 'name' => 'budi santoso'])->assertOk()
        ->assertJsonPath('data.reply', fn ($r) => str_contains($r, 'Klik Daftar lalu isi email.') && str_contains($r, 'Kak Budi'));
    chat(['message' => 'brp hrg nya'])->assertOk()->assertJsonPath('data.reply', fn ($r) => str_contains($r, 'Rp429.000'));
    chat(['message' => 'halo', 'name' => 'Sinta'])->assertOk()->assertJsonPath('data.reply', fn ($r) => str_starts_with($r, 'Halo Kak Sinta, selamat'));
    chat(['message' => 'makasih ya'])->assertOk()->assertJsonPath('data.reply', fn ($r) => str_starts_with($r, 'Sama-sama'));
    chat(['message' => 'cuaca hari ini?'])->assertOk()->assertJsonPath('data.reply', fn ($r) => str_contains($r, 'WhatsApp'));
    Http::assertNothingSent();
});

it('sebelum chat pengunjung mengisi nama, email, WhatsApp dan tersimpan sebagai Customer Website (tanpa data kembar)', function () {
    $start = fn (array $body) => test()->withHeaders(['X-API-KEY' => 'fe-key'])->postJson('/api/frontend/chat/start', $body);

    $start(['name' => 'Budi Santoso', 'email' => 'Budi@Mail.test', 'phone' => '0812-3456-7890'])->assertOk()->assertJsonPath('data.name', 'Budi Santoso');
    $start(['name' => 'Budi S', 'email' => 'budi@mail.test', 'phone' => '81234567890'])->assertOk();
    $start(['name' => 'X', 'email' => 'bukan-email', 'phone' => '123'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'phone']);

    $lead = Customer::forApp(Customer::APP_WEBSITE)->sole();
    expect($lead->only(['name', 'email', 'phone', 'source', 'branch_id']))
        ->toBe(['name' => 'Budi S', 'email' => 'budi@mail.test', 'phone' => '6281234567890', 'source' => 'live_chat', 'branch_id' => null]);

    // Hanya superadmin yang melihat pengunjung live chat; owner hanya customer perusahaannya.
    $this->actingAs(User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]))
        ->get(route('superadmin.contacts.index', ['app' => Customer::APP_WEBSITE]))->assertOk()->assertSee('Budi S');
    expect(Customer::accessibleBy(User::factory()->create())->count())->toBe(0);
});

it('wajib API key fe-konexa dan dibatasi per pengunjung', function () {
    chat(['message' => 'halo'], 'salah')->assertUnauthorized();
    foreach (range(1, 8) as $i) {
        chat(['message' => 'halo', 'visitor' => '9.9.9.9'])->assertOk();
    }
    chat(['message' => 'halo', 'visitor' => '9.9.9.9'])->assertStatus(429);
    chat(['message' => 'halo', 'visitor' => '8.8.8.8'])->assertOk();
});

it('superadmin mengatur live chat: catatan masuk ke AI, tidak bocor ke API publik, dan bisa dimatikan', function () {
    $admin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);
    $this->actingAs($admin)->get(route('superadmin.web.live-chat.edit'))->assertOk()->assertSee('Belum ada AI aktif');

    $this->actingAs($admin)->put(route('superadmin.web.live-chat.update'), [
        'chat_enabled' => '1', 'chat_ai' => '1', 'chat_greeting' => 'Halo kak!',
        'chat_questions' => "Harga?\n\nHarga?\nCara daftar?", 'chat_knowledge' => 'Promo kode HEMAT20.',
    ])->assertSessionHas('success');
    expect(WebSetting::current()->chat_questions)->toBe("Harga?\nCara daftar?");

    $this->withHeaders(['X-API-KEY' => 'fe-key'])->getJson('/api/frontend/web-setting')
        ->assertJsonPath('data.chat_greeting', 'Halo kak!')->assertJsonMissingPath('data.chat_knowledge');

    $provider = new AiProvider(['name' => 'OpenAI', 'driver' => 'openai', 'model' => 'gpt-4o-mini']);
    $provider->forceFill(['api_key' => 'sk-test', 'is_active' => true])->save();
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Pakai HEMAT20.']]]])]);
    $this->actingAs($admin)->postJson(route('superadmin.web.live-chat.test'), ['message' => 'ada promo?'])->assertJsonPath('reply', 'Pakai HEMAT20.');
    Http::assertSent(fn (HttpRequest $r) => str_contains(json_encode($r->data()), 'HEMAT20'));

    $this->actingAs($admin)->put(route('superadmin.web.live-chat.update'), ['chat_enabled' => '0']);
    chat(['message' => 'halo'])->assertForbidden();
});
