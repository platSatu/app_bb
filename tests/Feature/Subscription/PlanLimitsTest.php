<?php

use App\Exceptions\PlanLimitException;
use App\Models\BranchSubscription;
use App\Models\CategoryApplication;
use App\Models\Package;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\SubscriptionHistory;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaDevice;
use App\Services\Invoice\InvoiceService;
use App\Services\Page\PageCategoryService;
use App\Services\Subscription\PlanLimits;
use App\Services\Subscription\SubscriptionService;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Branch dengan semua layanan aktif dan batas tertentu per layanan (null = tanpa batas tercatat / langganan lama). */
function planBranch(array $limits = []): UserBranch
{
    $branch = appBranch(['pages', 'invoices', 'whatsapp']);
    foreach ($limits as $app => $value) {
        BranchSubscription::where('branch_id', $branch->id)->where('app_key', $app)->update(['limits' => json_encode($value)]);
    }

    return $branch;
}

function planPackage(array $apps, array $limits): Package
{
    $category = CategoryApplication::create(['name' => 'Kat '.Str::random(4), 'status' => 'active']);

    return Package::create(['category_application_id' => $category->id, 'name' => 'Paket', 'price' => 0, 'duration' => 30, 'apps' => $apps, 'limits' => $limits, 'status' => 'active']);
}

function planPage(UserBranch $branch, PageType $type, string $name = 'Acara'): PageCategory
{
    return app(PageCategoryService::class)->save(new PageCategory(), $branch, ['page_type_id' => $type->id, 'name' => $name.' '.Str::random(4), 'status' => 'active']);
}

it('batas paket disalin ke branch saat aktif, dan perpanjangan sebelum habis memakai yang lebih longgar', function () {
    $branch = planBranch();
    $owner = User::find($branch->user_id);
    $type = PageType::create(['name' => 'Tiket', 'template' => PageType::TEMPLATE_TICKET, 'status' => 'active']);
    $small = planPackage(['pages', 'whatsapp'], ['pages' => [$type->id => 1], 'whatsapp' => ['monthly' => 100]]);
    $big = planPackage(['pages', 'whatsapp'], ['pages' => [$type->id => 3], 'whatsapp' => []]);

    $service = app(SubscriptionService::class);
    $service->extend($branch, 'pages', 30, SubscriptionHistory::SOURCE_MANUAL, $owner, $small->id);
    $service->extend($branch, 'whatsapp', 30, SubscriptionHistory::SOURCE_MANUAL, $owner, $small->id);
    $limits = app(PlanLimits::class);
    expect($limits->limit($branch->id, 'pages', $type->id))->toBe(1)->and($limits->limit($branch->id, 'whatsapp', 'monthly'))->toBe(100);

    // Perpanjang dengan paket yang lebih besar: page jadi 3, WhatsApp jadi tanpa batas.
    $service->extend($branch, 'pages', 30, SubscriptionHistory::SOURCE_MANUAL, $owner, $big->id);
    $service->extend($branch, 'whatsapp', 30, SubscriptionHistory::SOURCE_MANUAL, $owner, $big->id);
    expect($limits->limit($branch->id, 'pages', $type->id))->toBe(3)->and($limits->limit($branch->id, 'whatsapp', 'monthly'))->toBeNull();

    // Perpanjang lagi dengan paket kecil sebelum habis: tidak turun kelas.
    $service->extend($branch, 'pages', 30, SubscriptionHistory::SOURCE_MANUAL, $owner, $small->id);
    expect($limits->limit($branch->id, 'pages', $type->id))->toBe(3);
});

it('pages: tidak bisa membuat page melebihi batas per kategori, termasuk lewat pindah kategori', function () {
    Storage::fake('public');
    $ticket = PageType::create(['name' => 'Tiket', 'template' => PageType::TEMPLATE_TICKET, 'status' => 'active']);
    $landing = PageType::create(['name' => 'Landing', 'template' => PageType::TEMPLATE_LANDING, 'status' => 'active']);
    $branch = planBranch(['pages' => [$ticket->id => 1, $landing->id => 1]]);

    planPage($branch, $ticket);
    expect(fn () => planPage($branch, $ticket))->toThrow(PlanLimitException::class, 'hanya bisa membuat 1 page kategori Tiket');

    $page = planPage($branch, $landing);
    // Mengubah page landing menjadi tiket juga dihitung.
    expect(fn () => app(PageCategoryService::class)->save($page->fresh(), $branch, ['page_type_id' => $ticket->id, 'name' => $page->name, 'status' => 'active']))
        ->toThrow(PlanLimitException::class);
    // Mengedit page yang sudah ada tanpa pindah kategori tetap boleh.
    app(PageCategoryService::class)->save($page->fresh(), $branch, ['page_type_id' => $landing->id, 'name' => 'Nama baru', 'status' => 'active']);

    expect(PageCategory::where('branch_id', $branch->id)->count())->toBe(2);
});

it('invoice: kuota bulanan berlaku untuk semua sumber, dan API mendapat JSON 429', function () {
    $branch = planBranch(['invoices' => ['monthly' => 2]]);
    $create = fn () => DB::transaction(fn () => app(InvoiceService::class)->createForForm($branch, 'Daftar', 'Budi', '6281234567890', 50000));

    $create();
    $create();
    expect($create)->toThrow(PlanLimitException::class, 'Kuota invoice bulan ini sudah habis (2 dari 2)');

    // Bulan berikutnya kuota kembali.
    $this->travelTo(now()->addMonthNoOverflow()->startOfMonth()->addHour());
    $create();

    $response = (new PlanLimitException('habis'))->render(Request::create('/api/v1/invoices', 'POST'));
    expect($response->getStatusCode())->toBe(429)->and($response->getData(true))->toMatchArray(['success' => false, 'code' => 'plan_limit']);
});

it('whatsapp: semua pesan keluar dihitung per bulan, gagal kirim mengembalikan kuota', function () {
    $branch = planBranch(['whatsapp' => ['monthly' => 2]]);
    $device = tap((new WaDevice())->forceFill(['branch_id' => $branch->id, 'name' => 'CS', 'phone' => '6281200000000', 'status' => 'connected']))->save();
    $send = fn () => app(WaGateway::class)->sendText($branch->id, ['device_id' => $device->id, 'to' => '6281234567890', 'text' => 'Halo']);

    Http::fakeSequence('*/v1/messages')->push(['success' => false, 'message' => 'gagal'], 500)->push(['success' => true])->push(['success' => true]);

    expect($send()['success'])->toBeFalse();   // gagal di go_bizbos: kuota dikembalikan
    expect($send()['success'])->toBeTrue();
    expect($send()['success'])->toBeTrue();
    $denied = $send();

    expect($denied)->toMatchArray(['success' => false, 'status' => 429])
        ->and($denied['message'])->toContain('Kuota pesan WhatsApp bulan ini sudah habis');
    Http::assertSentCount(3);
});

it('whatsapp: broadcast hanya mengambil sisa kuota, dan kuota tidak bisa terlewati', function () {
    $branch = planBranch(['whatsapp' => ['monthly' => 5]]);
    $limits = app(PlanLimits::class);

    expect($limits->takeMessages($branch->id, 3))->toBe(3)
        ->and($limits->takeMessages($branch->id, 10, partial: true))->toBe(2)   // broadcast: sisa 2 saja
        ->and($limits->takeMessages($branch->id, 1))->toBe(0)
        ->and($limits->summary($branch->id)['whatsapp'][0])->toBe('Pesan bulan ini: 5 / 5');
});

it('langganan lama tanpa batas tercatat tetap tanpa batas', function () {
    $branch = planBranch();
    $limits = app(PlanLimits::class);

    expect($limits->takeMessages($branch->id, 10000))->toBe(10000)
        ->and($limits->limit($branch->id, 'invoices', 'monthly'))->toBeNull()
        ->and($limits->summary($branch->id))->toBe([]);
});
