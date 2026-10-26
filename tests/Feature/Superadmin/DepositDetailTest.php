<?php

use App\Models\User;
use App\Models\Wallet;

beforeEach(function () {
    $this->superadmin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);
});

it('kotak ringkasan deposit bisa diklik ke rinciannya dengan filter yang sama', function () {
    // assertSee meng-escape teks, jadi & di URL dicocokkan dengan &amp; di HTML.
    $this->actingAs($this->superadmin)
        ->get(route('superadmin.deposits.index', ['search' => 'budi', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31']))
        ->assertOk()
        ->assertSee(route('superadmin.deposits.index', ['search' => 'budi', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'status' => \App\Models\Deposit::STATUS_SUCCESS]))
        ->assertSee(route('superadmin.deposits.balances', ['search' => 'budi', 'date_to' => '2026-10-31']))
        ->assertSee(route('superadmin.deposits.history', ['search' => 'budi', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'type' => \App\Models\WalletTransaction::TYPE_PURCHASE]));
});

it('rincian sisa saldo: hanya user bersaldo, terbesar di atas, bisa dicari', function () {
    $wallet = fn (string $name, int $balance) => tap((new Wallet())->forceFill(['user_id' => User::factory()->create(['name' => $name])->id, 'balance' => $balance]))->save();
    $wallet('Ani Kecil', 10000);
    $wallet('Budi Besar', 500000);
    $wallet('Citra Kosong', 0);

    $this->actingAs($this->superadmin)->get(route('superadmin.deposits.balances'))
        ->assertOk()
        ->assertSeeInOrder(['Budi Besar', 'Rp 500.000', 'Ani Kecil', 'Rp 10.000'])
        ->assertDontSee('Citra Kosong');

    $this->get(route('superadmin.deposits.balances', ['search' => 'ani']))
        ->assertOk()->assertSee('Ani Kecil')->assertDontSee('Budi Besar');
});
