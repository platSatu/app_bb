<?php

use App\Models\BranchSubscription;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\Ticket;
use App\Models\TicketCardLog;
use App\Models\TicketCategory;
use App\Models\TicketOrder;
use App\Models\TicketScanner;
use App\Models\TicketSession;
use App\Models\TicketSessionAttendance;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserCompany;
use App\Services\Ticket\TicketOrderService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Mail::fake());

function ticketEvent(array $card = ['enabled' => true], int $quota = 10, int $price = 0): array
{
    $owner = User::factory()->create();
    $company = (new UserCompany())->forceFill(['user_id' => $owner->id, 'name' => 'Komunitas Maju', 'slug' => 'kom-'.Str::lower(Str::random(6))]);
    $company->save();
    $branch = (new UserBranch())->forceFill(['user_id' => $owner->id, 'company_id' => $company->id, 'name' => 'Pusat', 'status' => 'active']);
    $branch->save();
    (new BranchSubscription())->forceFill(['branch_id' => $branch->id, 'app_key' => 'events', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()])->save();
    $type = PageType::create(['name' => 'Tiket '.Str::random(4), 'template' => PageType::TEMPLATE_TICKET, 'status' => 'active']);
    $page = (new PageCategory())->forceFill([
        'branch_id' => $branch->id, 'page_type_id' => $type->id, 'name' => 'Seminar Digital', 'slug' => 'seminar-'.Str::lower(Str::random(6)),
        'status' => 'active', 'start_date' => today(), 'settings' => ['ticket' => ['card' => $card]],
    ]);
    $page->save();
    $category = new TicketCategory(['name' => 'Reguler', 'price' => $price, 'quota' => $quota, 'max_per_order' => 5, 'status' => 'active']);
    $category->page_id = $page->id;
    $category->save();
    $vip = new TicketCategory(['name' => 'VIP', 'price' => $price, 'quota' => $quota, 'max_per_order' => 5, 'status' => 'active']);
    $vip->page_id = $page->id;
    $vip->save();

    return [$owner, $page->fresh(['type', 'fields', 'branch']), $category, $vip];
}

function buyFree(PageCategory $page, TicketCategory $category, string $who, int $qty = 1): TicketOrder
{
    return app(TicketOrderService::class)->create($page, [$category->id => $qty], [
        'name' => 'Peserta '.$who, 'email' => $who.'@mail.test', 'phone' => '0812'.str_pad((string) crc32($who) % 100000000, 8, '0', STR_PAD_LEFT),
    ], [], '127.0.0.1', 'test');
}

function device(PageCategory $page, string $type): TicketScanner
{
    $scanner = new TicketScanner(['name' => 'Perangkat '.$type, 'status' => 'active']);
    $scanner->forceFill(['page_id' => $page->id, 'type' => $type, 'token' => Str::random(48), 'pin_hash' => Hash::make('123456')])->save();

    return $scanner;
}

function asDevice(TicketScanner $scanner)
{
    return test()->withSession(['ticket_scanner.'.$scanner->id => sha1($scanner->pin_hash)]);
}

it('tidak pernah menjual lebih dari kuota dan memberi pesan yang jelas saat habis', function () {
    [, $page, $category] = ticketEvent(quota: 3);

    foreach (['a', 'b', 'c'] as $who) {
        expect(buyFree($page, $category, $who)->status)->toBe(TicketOrder::STATUS_PAID);
    }

    expect(fn () => buyFree($page, $category, 'd'))->toThrow(ValidationException::class, 'Habis');

    $category->refresh();
    expect($category->sold)->toBe(3)->and($category->reserved)->toBe(0)
        ->and(Ticket::where('page_id', $page->id)->count())->toBe(3)
        ->and(TicketOrder::count())->toBe(3);
});

it('menolak tiket gratis kedua untuk orang yang sama', function () {
    [, $page, $category] = ticketEvent();
    buyFree($page, $category, 'sama');

    expect(fn () => buyFree($page, $category, 'sama'))->toThrow(ValidationException::class, 'sudah pernah mendaftar');
});

it('memeriksa ulang harga saat memotong kuota', function () {
    [, $page, $category] = ticketEvent();
    $service = app(TicketOrderService::class);
    $method = new ReflectionMethod($service, 'holdQuota');
    $stale = $category->replicate();
    $stale->id = $category->id;
    $stale->price = 5000; // harga di form sudah berubah

    $order = buyFree($page, $category, 'x');

    expect(fn () => $method->invoke($service, $stale, 1, $order))->toThrow(ValidationException::class, 'Harga tiket');
    expect($category->fresh()->reserved)->toBe(0);
});

it('kiosk: cek nama dulu, cetak sekali, lalu otomatis registrasi ulang', function () {
    [, $page, $category] = ticketEvent();
    $ticket = buyFree($page, $category, 'kiosk')->tickets()->first();
    $kiosk = device($page, TicketScanner::TYPE_KIOSK);

    asDevice($kiosk)->get(route('scan.show', $kiosk->token))->assertOk()->assertSee('Halo, selamat datang!');

    // Langkah 1: belum mengubah apa pun.
    asDevice($kiosk)->postJson(route('scan.redeem', $kiosk->token), ['code' => $ticket->token])
        ->assertOk()->assertJson(['status' => 'ready', 'ticket' => ['name' => 'Peserta kiosk']])->assertJsonPath('preview', fn ($html) => str_contains($html, 'data-idc'));
    expect($ticket->fresh()->hasCard())->toBeFalse()->and($ticket->fresh()->isCheckedIn())->toBeFalse();

    // Langkah 2: cetak.
    $print = asDevice($kiosk)->postJson(route('scan.redeem', $kiosk->token), ['code' => $ticket->token, 'confirm' => true])
        ->assertOk()->assertJson(['status' => 'ok'])->json('print');
    expect($print)->toContain('@page { size: 54mm 85.6mm')->toContain('data:image/png;base64');

    $fresh = $ticket->fresh();
    expect($fresh->card_token)->toHaveLength(48)->not->toBe($fresh->token)
        ->and($fresh->isCheckedIn())->toBeTrue()->and($fresh->checked_in_scanner_id)->toBe($kiosk->id)
        ->and(TicketCardLog::where('ticket_id', $ticket->id)->where('action', 'print')->where('source', 'kiosk')->count())->toBe(1);

    // Tidak bisa cetak dua kali di kiosk.
    asDevice($kiosk)->postJson(route('scan.redeem', $kiosk->token), ['code' => $ticket->token, 'confirm' => true])
        ->assertJson(['status' => 'already']);
    // QR ID card ditolak dengan pesan yang ramah.
    asDevice($kiosk)->postJson(route('scan.redeem', $kiosk->token), ['code' => $fresh->card_token])->assertJson(['status' => 'wrong_card']);
    // Kode booking yang diketik tidak diterima kiosk (mencegah cetak kartu orang lain).
    asDevice($kiosk)->postJson(route('scan.redeem', $kiosk->token), ['code' => $ticket->booking_code])->assertJson(['status' => 'not_found']);
    expect($fresh->fresh()->card_print_count)->toBe(1);
});

it('kiosk menolak e-ticket dari event lain dan saat cetak belum dibuka', function () {
    [, $page, $category] = ticketEvent(['enabled' => false]);
    [, $other, $otherCategory] = ticketEvent();
    $foreign = buyFree($other, $otherCategory, 'luar')->tickets()->first();
    $own = buyFree($page, $category, 'dalam')->tickets()->first();
    $kiosk = device($page, TicketScanner::TYPE_KIOSK);

    asDevice($kiosk)->postJson(route('scan.redeem', $kiosk->token), ['code' => $own->token])->assertJson(['status' => 'disabled']);

    $page->forceFill(['settings' => ['ticket' => ['card' => ['enabled' => true]]]])->save();
    asDevice($kiosk)->postJson(route('scan.redeem', $kiosk->token), ['code' => $foreign->token, 'confirm' => true])->assertJson(['status' => 'not_found']);
    expect($foreign->fresh()->hasCard())->toBeFalse();
});

it('link kiosk butuh PIN dan tidak bisa dipakai untuk endpoint lain', function () {
    [, $page, $category] = ticketEvent();
    $ticket = buyFree($page, $category, 'pin')->tickets()->first();
    $kiosk = device($page, TicketScanner::TYPE_KIOSK);

    $this->postJson(route('scan.redeem', $kiosk->token), ['code' => $ticket->token])->assertStatus(401);
    asDevice($kiosk)->postJson(route('scan.check', $kiosk->token), ['code' => $ticket->token])->assertNotFound();
    asDevice($kiosk)->postJson(route('scan.card', $kiosk->token), ['number' => $ticket->number])->assertNotFound();
});

it('absensi: hanya QR ID card, hanya di jam sesi, dan tercatat sekali', function () {
    [, $page, $category, $vip] = ticketEvent();
    $ticket = buyFree($page, $category, 'kelas')->tickets()->first();
    $vipTicket = buyFree($page, $vip, 'vip')->tickets()->first();
    $kiosk = device($page, TicketScanner::TYPE_KIOSK);
    asDevice($kiosk)->postJson(route('scan.redeem', $kiosk->token), ['code' => $ticket->token, 'confirm' => true]);
    asDevice($kiosk)->postJson(route('scan.redeem', $kiosk->token), ['code' => $vipTicket->token, 'confirm' => true]);
    $card = $ticket->fresh()->card_token;

    $session = new TicketSession(['name' => 'Kelas Reguler', 'starts_at' => now()->addMinutes(10), 'ends_at' => now()->addHours(2), 'open_before_minutes' => 30, 'late_minutes' => 15, 'category_ids' => [$category->id], 'status' => 'active']);
    $session->page_id = $page->id;
    $session->save();
    $staff = device($page, TicketScanner::TYPE_ATTENDANCE);
    $scan = fn ($code, $extra = []) => asDevice($staff)->postJson(route('scan.check', $staff->token), ['code' => $code, 'session_id' => $session->id, ...$extra]);

    asDevice($staff)->get(route('scan.show', $staff->token))->assertOk()->assertSee('Kelas Reguler');

    $scan($ticket->token)->assertJson(['status' => 'wrong_card']);
    $scan($vipTicket->fresh()->card_token)->assertJson(['status' => 'not_allowed']);
    $scan($card)->assertJson(['status' => 'ok', 'stats' => ['attended' => 1, 'eligible' => 1]]);
    $scan($card)->assertJson(['status' => 'already']);
    expect(TicketSessionAttendance::where('session_id', $session->id)->count())->toBe(1);

    // Kode booking: wajib konfirmasi nama dulu.
    $session2 = $session->replicate();
    $session2->name = 'Kelas Kedua';
    $session2->save();
    $scan2 = fn ($code, $extra = []) => asDevice($staff)->postJson(route('scan.check', $staff->token), ['code' => $code, 'session_id' => $session2->id, ...$extra]);
    $scan2($ticket->booking_code)->assertJson(['status' => 'confirm']);
    expect(TicketSessionAttendance::where('session_id', $session2->id)->count())->toBe(0);
    $scan2($ticket->booking_code, ['confirm' => true])->assertJson(['status' => 'ok']);

    // Jendela waktu: belum dibuka & sudah ditutup (batas terlambat 15 menit setelah mulai).
    $this->travelTo(now()->subHour());
    $scan($card)->assertJson(['status' => 'closed'])->assertJsonPath('message', fn ($m) => str_contains($m, 'baru dibuka'));
    $this->travelBack();
    $this->travelTo(now()->addMinutes(26));
    $scan2($ticket->booking_code, ['confirm' => true])->assertJson(['status' => 'closed'])->assertJsonPath('message', fn ($m) => str_contains($m, 'sudah ditutup'));
    $this->travelBack();

    // Sesi dari event lain tidak bisa dipakai.
    [, $other] = ticketEvent();
    $foreign = new TicketSession(['name' => 'Asing', 'starts_at' => now(), 'ends_at' => now()->addHour(), 'open_before_minutes' => 30, 'status' => 'active']);
    $foreign->page_id = $other->id;
    $foreign->save();
    asDevice($staff)->postJson(route('scan.check', $staff->token), ['code' => $card, 'session_id' => $foreign->id])->assertJson(['status' => 'not_found']);
});

it('cetak ulang dengan QR baru membuat kartu lama tidak berlaku', function () {
    [$owner, $page, $category] = ticketEvent();
    $ticket = buyFree($page, $category, 'hilang')->tickets()->first();

    // Admin cetak pertama (tanpa registrasi ulang, karena orangnya belum tentu hadir).
    $this->actingAs($owner)->post(route('events.ticket.cards.print', [$page, $ticket]))->assertOk()->assertSee('window.print', false);
    $old = $ticket->fresh()->card_token;
    expect($old)->not->toBeNull()->and($ticket->fresh()->isCheckedIn())->toBeFalse();

    $this->actingAs($owner)->post(route('events.ticket.cards.print', [$page, $ticket]), ['replace' => 1])->assertOk();
    $new = $ticket->fresh();
    expect($new->card_token)->not->toBe($old)->and($new->card_print_count)->toBe(2)
        ->and(TicketCardLog::where('ticket_id', $ticket->id)->pluck('action')->all())->toEqualCanonicalizing(['print', 'replace']);

    $session = new TicketSession(['name' => 'Kelas', 'starts_at' => now(), 'ends_at' => now()->addHour(), 'open_before_minutes' => 30, 'status' => 'active']);
    $session->page_id = $page->id;
    $session->save();
    $staff = device($page, TicketScanner::TYPE_ATTENDANCE);
    asDevice($staff)->postJson(route('scan.check', $staff->token), ['code' => $old, 'session_id' => $session->id])->assertJson(['status' => 'not_found']);
    asDevice($staff)->postJson(route('scan.check', $staff->token), ['code' => $new->card_token, 'session_id' => $session->id])->assertJson(['status' => 'ok']);
});

it('petugas registrasi ulang menerima QR ID card dan bisa mencetak ulang', function () {
    [, $page, $category] = ticketEvent();
    $ticket = buyFree($page, $category, 'pintu')->tickets()->first();
    $gate = device($page, TicketScanner::TYPE_CHECKIN);

    asDevice($gate)->get(route('scan.show', $gate->token))->assertOk()->assertSee('Registrasi Ulang');
    asDevice($gate)->postJson(route('scan.check', $gate->token), ['code' => $ticket->token])->assertJson(['status' => 'ok', 'ticket' => ['has_card' => false]]);

    $print = asDevice($gate)->postJson(route('scan.card', $gate->token), ['number' => $ticket->number])->assertJson(['status' => 'ok'])->json('print');
    expect($print)->toContain('Peserta pintu');
    $card = $ticket->fresh()->card_token;

    asDevice($gate)->postJson(route('scan.check', $gate->token), ['code' => $card])->assertJson(['status' => 'already', 'ticket' => ['has_card' => true]]);
    expect(TicketCardLog::where('ticket_id', $ticket->id)->where('source', 'petugas')->count())->toBe(1);
});

it('halaman admin ID card, sesi, dan daftar hadir bisa dibuka & disimpan', function () {
    [$owner, $page, $category] = ticketEvent(['enabled' => false]);
    buyFree($page, $category, 'admin');

    $this->actingAs($owner)->get(route('events.ticket.cards.index', $page))->assertOk()->assertSee('Pengaturan Cetak')->assertSee('Peserta admin');
    $this->actingAs($owner)->put(route('events.ticket.cards.update', $page), ['enabled' => 1, 'size' => 'a5', 'orientation' => 'landscape', 'label' => 'delegasi', 'show_category' => 1])
        ->assertRedirect()->assertSessionHas('success');
    expect($page->fresh()->setting('ticket.card'))->toMatchArray(['enabled' => true, 'size' => 'a5', 'orientation' => 'landscape', 'label' => 'DELEGASI', 'hole' => false]);

    $this->actingAs($owner)->post(route('events.ticket.sessions.store', $page), [
        '_form' => 'session-new', 'name' => 'Kelas Pagi', 'starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'ends_at' => now()->addDay()->subHour()->format('Y-m-d\TH:i'),
        'open_before_minutes' => 30, 'status' => 'active',
    ])->assertSessionHasErrorsIn('session-new', 'ends_at');

    $this->actingAs($owner)->post(route('events.ticket.sessions.store', $page), [
        '_form' => 'session-new', 'name' => 'Kelas Pagi', 'starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d\TH:i'),
        'open_before_minutes' => 30, 'late_minutes' => 15, 'category_ids' => [$category->id], 'status' => 'active',
    ])->assertSessionHasNoErrors();
    $session = TicketSession::where('page_id', $page->id)->firstOrFail();
    expect($session->category_ids)->toBe([$category->id]);

    $this->actingAs($owner)->get(route('events.ticket.sessions.index', $page))->assertOk()->assertSee('Kelas Pagi')->assertSee('Scan');
    $this->actingAs($owner)->get(route('events.ticket.sessions.show', [$page, $session, 'tab' => 'absent']))->assertOk()->assertSee('Peserta admin');
    $this->actingAs($owner)->get(route('events.ticket.sessions.show', [$page, $session, 'export' => 1]))->assertOk()->assertDownload();
    $this->actingAs($owner)->get(route('events.ticket.scanners.index', $page))->assertOk();

    $this->actingAs($owner)->post(route('events.ticket.scanners.store', $page), ['name' => 'Kiosk Lobby', 'type' => 'kiosk'])->assertSessionHas('scanner_pin');
    expect(TicketScanner::where('page_id', $page->id)->where('type', 'kiosk')->exists())->toBeTrue();

    // Sesi lain tidak bisa diakses lewat page orang lain.
    [$stranger] = ticketEvent();
    $this->actingAs($stranger)->get(route('events.ticket.sessions.show', [$page, $session]))->assertNotFound();
});

it('sesi yang sudah ada absensinya tidak bisa dihapus', function () {
    [$owner, $page, $category] = ticketEvent();
    $ticket = buyFree($page, $category, 'hapus')->tickets()->first();
    $session = new TicketSession(['name' => 'Kelas', 'starts_at' => now(), 'ends_at' => now()->addHour(), 'open_before_minutes' => 30, 'status' => 'active']);
    $session->page_id = $page->id;
    $session->save();
    (new TicketSessionAttendance())->forceFill(['page_id' => $page->id, 'session_id' => $session->id, 'ticket_id' => $ticket->id, 'scanned_at' => now()])->save();

    $this->actingAs($owner)->delete(route('events.ticket.sessions.destroy', [$page, $session]))->assertSessionHas('error');
    expect($session->fresh())->not->toBeNull();
});
