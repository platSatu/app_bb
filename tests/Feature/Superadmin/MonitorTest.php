<?php

use App\Http\Controllers\Superadmin\Monitor\MonitorController;
use App\Mail\TicketOrderPaid;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->superadmin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);
});

function mailPayload(): string
{
    $mail = new \Illuminate\Mail\SendQueuedMailable(new TicketOrderPaid('order-1'));

    return json_encode(['uuid' => (string) Str::uuid(), 'displayName' => TicketOrderPaid::class, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
        'maxTries' => null, 'timeout' => null, 'data' => ['commandName' => get_class($mail), 'command' => serialize($mail)]]);
}

it('menampilkan antrean yang tertahan, job gagal, dan status jadwal', function () {
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => mailPayload(), 'attempts' => 0, 'reserved_at' => null,
        'available_at' => now()->subMinutes(10)->getTimestamp(), 'created_at' => now()->subMinutes(10)->getTimestamp()]);
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => mailPayload(),
        'exception' => "Symfony\\Component\\Mailer\\Exception\\TransportException: Connection refused\n#0 stack", 'failed_at' => now()]);
    Cache::forever(MonitorController::HEARTBEAT, now()->subMinute()->toIso8601String());

    $this->actingAs($this->superadmin)->get(route('superadmin.monitor.index'))->assertOk()
        ->assertSee('Antrean &amp; Tugas', false)->assertSee('Tertahan')->assertSee('Email: TicketOrderPaid')
        ->assertSee('Connection refused')->assertSee('Berjalan');
});

it('job gagal bisa dicoba lagi atau dihapus, job antrean bisa dibuang', function () {
    $uuid = (string) Str::uuid();
    DB::table('failed_jobs')->insert(['uuid' => $uuid, 'connection' => 'database', 'queue' => 'default', 'payload' => mailPayload(), 'exception' => 'x', 'failed_at' => now()]);

    $this->actingAs($this->superadmin)->post(route('superadmin.monitor.retry'), ['uuid' => $uuid])->assertSessionHas('success');
    expect(DB::table('failed_jobs')->count())->toBe(0)->and(DB::table('jobs')->count())->toBe(1);

    $this->delete(route('superadmin.monitor.jobs.destroy', DB::table('jobs')->value('id')))->assertSessionHas('success');
    expect(DB::table('jobs')->count())->toBe(0);

    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => mailPayload(), 'exception' => 'x', 'failed_at' => now()]);
    $this->delete(route('superadmin.monitor.forget'))->assertSessionHas('success');
    expect(DB::table('failed_jobs')->count())->toBe(0);
});

it('pembayaran tertahan terhitung dan bisa diproses sekarang', function () {
    $owner = User::factory()->create();
    (new Deposit())->forceFill(['user_id' => $owner->id, 'reference_number' => 'DEP-1', 'amount' => 50000, 'status' => Deposit::STATUS_PENDING,
        'expires_at' => now()->subHour()])->save();

    $this->actingAs($this->superadmin)->get(route('superadmin.monitor.index'))->assertSee('1 transaksi');
    $this->post(route('superadmin.monitor.process'))->assertSessionHas('success');
    expect(Deposit::first()->status)->toBe(Deposit::STATUS_EXPIRED);
});

it('hanya superadmin', function () {
    $this->actingAs(User::factory()->create())->get(route('superadmin.monitor.index'))->assertForbidden();
});
