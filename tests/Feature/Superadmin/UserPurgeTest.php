<?php

use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaChat;
use App\Models\WaDevice;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Owner dengan company, branch, page, WhatsApp (device + chat), riwayat saldo (sudah 0), dan sesi login. */
function purgeOwner(): array
{
    $branch = appBranch(['pages', 'whatsapp']);
    $owner = User::find($branch->user_id);
    $type = PageType::firstOrCreate(['name' => 'Landing'], ['template' => PageType::TEMPLATE_LANDING, 'status' => 'active']);
    tap((new PageCategory())->forceFill(['branch_id' => $branch->id, 'page_type_id' => $type->id, 'name' => 'Promo', 'slug' => 'promo-'.Str::lower(Str::random(5)), 'status' => 'active']))->save();
    $device = tap((new WaDevice())->forceFill(['branch_id' => $branch->id, 'name' => 'CS', 'phone' => '62812'.random_int(1000000, 9999999), 'status' => 'connected']))->save();
    tap((new WaChat())->forceFill(['branch_id' => $branch->id, 'device_id' => $device->id, 'chat_jid' => Str::random(8).'@s.whatsapp.net', 'unread_count' => 1]))->save();

    $wallets = app(WalletService::class);
    $wallets->credit($owner, 50000, WalletTransaction::TYPE_DEPOSIT, 'purge-in:'.$owner->id);
    $wallets->debit($owner, 50000, WalletTransaction::TYPE_PURCHASE, 'purge-out:'.$owner->id);
    DB::table('sessions')->insert(['id' => 'sesi-'.$owner->id, 'user_id' => $owner->id, 'payload' => '', 'last_activity' => time()]);

    return [$owner, $branch, $device];
}

beforeEach(function () {
    $this->superadmin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);
});

it('hapus user = semua datanya ikut terhapus, data user lain tetap utuh', function () {
    [$owner, $branch] = purgeOwner();
    [$other, $otherBranch, $otherDevice] = purgeOwner();
    // Chat milik owner lain yang pernah di-assign ke user yang dihapus: tetap ada, assign-nya dikosongkan.
    $sharedChat = WaChat::where('device_id', $otherDevice->id)->first();
    $sharedChat->forceFill(['assigned_user_id' => $owner->id])->save();

    $this->actingAs($this->superadmin)
        ->delete(route('superadmin.users.destroy', $owner), ['confirm_email' => strtoupper($owner->email), 'password' => 'password'])
        ->assertRedirect(route('superadmin.users.index'))->assertSessionHas('success');

    expect(User::find($owner->id))->toBeNull()
        ->and(UserBranch::find($branch->id))->toBeNull()
        ->and(DB::table('user_companies')->where('user_id', $owner->id)->exists())->toBeFalse()
        ->and(DB::table('page_categories')->where('branch_id', $branch->id)->exists())->toBeFalse()
        ->and(DB::table('wa_devices')->where('branch_id', $branch->id)->exists())->toBeFalse()
        ->and(DB::table('wa_chats')->where('branch_id', $branch->id)->exists())->toBeFalse()
        ->and(DB::table('branch_subscriptions')->where('branch_id', $branch->id)->exists())->toBeFalse()
        ->and(DB::table('wallets')->where('user_id', $owner->id)->exists())->toBeFalse()
        ->and(DB::table('wallet_transactions')->where('user_id', $owner->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $owner->id)->exists())->toBeFalse();

    // Data owner lain tidak tersentuh.
    expect(User::find($other->id))->not->toBeNull()
        ->and(DB::table('page_categories')->where('branch_id', $otherBranch->id)->count())->toBe(1)
        ->and(DB::table('wallet_transactions')->where('user_id', $other->id)->count())->toBe(2)
        ->and($sharedChat->fresh()->assigned_user_id)->toBeNull();
});

it('tidak menghapus kalau email konfirmasi salah, password admin salah, atau masih ada saldo', function () {
    [$owner] = purgeOwner();
    $this->actingAs($this->superadmin);

    $this->delete(route('superadmin.users.destroy', $owner), ['confirm_email' => 'salah@mail.test', 'password' => 'password'])->assertSessionHas('error');
    expect(User::find($owner->id))->not->toBeNull();

    $this->delete(route('superadmin.users.destroy', $owner), ['confirm_email' => $owner->email, 'password' => 'salah'])->assertSessionHasErrors('password');
    expect(User::find($owner->id))->not->toBeNull();

    app(WalletService::class)->credit($owner, 25000, WalletTransaction::TYPE_DEPOSIT, 'purge-sisa:'.$owner->id);
    $this->delete(route('superadmin.users.destroy', $owner), ['confirm_email' => $owner->email, 'password' => 'password'])
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'masih ada saldo Rp 25.000'));
    expect(User::find($owner->id))->not->toBeNull();
});
