<?php

use App\Models\FrontendVisitorLog;
use App\Models\User;
use App\Models\UserActivityLog;

beforeEach(function () {
    $this->superadmin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);
    $this->user = User::factory()->create();
});

it('mencatat halaman yang dibuka dan aksi pengguna, tanpa polling JSON', function () {
    $this->actingAs($this->user)->get(route('dashboard'));
    $this->actingAs($this->user)->getJson(route('dashboard'));
    $this->actingAs($this->user)->post(route('logout'));

    $logs = UserActivityLog::where('user_id', $this->user->id)->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->route_name)->toBe('dashboard')->and($logs[0]->verb())->toBe('Membuka')->and($logs[0]->path)->toBe('/dashboard')
        ->and($logs[1]->route_name)->toBe('logout')->and($logs[1]->verb())->toBe('Keluar');
});

it('tamu tidak dicatat', function () {
    $this->get(route('login'));

    expect(UserActivityLog::count())->toBe(0);
});

it('tab Aktivitas Pengguna memakai nama menu sidebar dan bisa difilter per pengguna', function () {
    UserActivityLog::create(['user_id' => $this->user->id, 'route_name' => 'whatsapp.auto-replies.index', 'method' => 'GET', 'path' => '/whatsapp/auto-replies', 'status' => 200, 'created_at' => now()]);
    UserActivityLog::create(['user_id' => $this->superadmin->id, 'route_name' => 'superadmin.packages.update', 'method' => 'PUT', 'path' => '/superadmin/packages/x', 'status' => 302, 'created_at' => now()]);

    $this->actingAs($this->superadmin)->get(route('superadmin.activity.users'))->assertOk()
        ->assertSee('WhatsApp › Settings › Auto Reply')->assertSee('Mengubah')->assertSee($this->user->email);

    $this->actingAs($this->superadmin)->get(route('superadmin.activity.users', ['user' => $this->user->id]))->assertOk()
        ->assertSee('Auto Reply')->assertDontSee('/superadmin/packages/x');
});

it('tab Pengunjung Website menghitung pengunjung unik tanpa bot', function () {
    $visit = fn (string $visitor, string $path, string $ua = 'Mozilla/5.0 (iPhone) Mobile') => FrontendVisitorLog::create([
        'visitor_id' => $visitor, 'ip_address' => '1.2.3.4', 'user_agent' => $ua, 'device_type' => str_contains($ua, 'bot') ? 'bot' : 'mobile',
        'path' => $path, 'referrer' => 'https://www.google.com/', 'visited_at' => now(),
    ]);
    $visit('a', '/');
    $visit('a', '/tiket');
    $visit('b', '/tiket');
    $visit('c', '/', 'Googlebot/2.1');

    $this->actingAs($this->superadmin)->get(route('superadmin.activity.visitors'))->assertOk()
        ->assertSee('Pengunjung Website')->assertSee('/tiket')->assertSee('Google')
        ->assertSee('1 kunjungan bot')
        ->assertViewHas('total', ['visitors' => 2, 'views' => 3]);
});

it('hanya superadmin yang bisa membuka menu ini', function () {
    $this->actingAs($this->user)->get(route('superadmin.activity.visitors'))->assertForbidden();
    $this->actingAs($this->user)->get(route('superadmin.activity.users'))->assertForbidden();
});

it('riwayat lebih dari 180 hari dihapus otomatis', function () {
    $old = UserActivityLog::create(['user_id' => $this->user->id, 'method' => 'GET', 'path' => '/a', 'status' => 200, 'created_at' => now()->subDays(181)]);
    $new = UserActivityLog::create(['user_id' => $this->user->id, 'method' => 'GET', 'path' => '/b', 'status' => 200, 'created_at' => now()->subDays(10)]);

    $this->artisan('model:prune', ['--model' => [UserActivityLog::class]])->assertSuccessful();

    expect(UserActivityLog::find($old->id))->toBeNull()->and(UserActivityLog::find($new->id))->not->toBeNull();
});
