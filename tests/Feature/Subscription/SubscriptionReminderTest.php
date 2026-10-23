<?php

use App\Mail\SubscriptionReminderMail;
use App\Models\BranchSubscription;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserCompany;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function ownerBranch(User $owner, string $name): UserBranch
{
    $company = UserCompany::firstWhere('user_id', $owner->id)
        ?? tap((new UserCompany())->forceFill(['user_id' => $owner->id, 'name' => 'PT '.$name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4))]))->save();
    $branch = (new UserBranch())->forceFill(['user_id' => $owner->id, 'company_id' => $company->id, 'name' => $name, 'status' => 'active']);
    $branch->save();

    return $branch;
}

function subscribe(UserBranch $branch, string $app, $endsAt): BranchSubscription
{
    $subscription = (new BranchSubscription())->forceFill(['branch_id' => $branch->id, 'app_key' => $app, 'starts_at' => now()->subMonth(), 'ends_at' => $endsAt]);
    $subscription->save();

    return $subscription;
}

beforeEach(fn () => $this->travelTo(now()->setTime(8, 10)));

it('mengirim pengingat di H-7, H-4, H-1 dan hari H saja, digabung per owner', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $pusat = ownerBranch($owner, 'Pusat');
    subscribe($pusat, 'invoices', today()->addDays(7)->setTime(14, 0));
    subscribe($pusat, 'pages', today()->addDays(7)->setTime(9, 0));
    subscribe(ownerBranch($owner, 'Cabang 2'), 'schedules', today()->addDays(4)->setTime(10, 0));
    subscribe($pusat, 'whatsapp', today()->addDays(5));
    subscribe(ownerBranch(User::factory()->create(), 'Lain'), 'pages', today()->setTime(23, 0));

    $this->artisan('subscriptions:remind')->assertSuccessful();

    Mail::assertQueued(SubscriptionReminderMail::class, 3);
    Mail::assertQueued(SubscriptionReminderMail::class, fn ($m) => $m->hasTo($owner->email) && $m->days === 7 && count($m->subscriptionIds) === 2);
    Mail::assertQueued(SubscriptionReminderMail::class, fn ($m) => $m->hasTo($owner->email) && $m->days === 4);
    Mail::assertQueued(SubscriptionReminderMail::class, fn ($m) => $m->days === 0);
});

it('tidak mengirim dua kali di hari yang sama, dan siklus baru setelah diperpanjang', function () {
    Mail::fake();
    $subscription = subscribe(ownerBranch(User::factory()->create(), 'Pusat'), 'invoices', today()->addDay()->setTime(12, 0));

    $this->artisan('subscriptions:remind');
    $this->artisan('subscriptions:remind');
    Mail::assertQueued(SubscriptionReminderMail::class, 1);

    // Diperpanjang 30 hari: hari ke-23 dari sekarang jadi H-7 untuk tanggal berakhir yang baru.
    $subscription->forceFill(['ends_at' => $subscription->ends_at->addDays(30)])->save();
    $this->travelTo(now()->addDays(24));
    $this->artisan('subscriptions:remind');
    Mail::assertQueued(SubscriptionReminderMail::class, fn ($m) => $m->days === 7);
});

it('isi email menyebut layanan, cabang, dan tombol perpanjang', function () {
    $owner = User::factory()->create(['name' => 'Natan']);
    $subscription = subscribe(ownerBranch($owner, 'Pusat'), 'invoices', today()->setTime(20, 0));

    $html = (new SubscriptionReminderMail($owner->id, 0, [$subscription->id]))->render();

    expect($html)->toContain('Natan')->toContain('Tagihan')->toContain('Pusat')->toContain('Perpanjang Paket')->toContain(route('profile.packages'));
});
