<?php

use App\Models\TicketSetting;

it('memberi biaya layanan tiket terbaru ke website, hanya dengan API key', function () {
    config(['services.frontend.key' => 'fe-key']);
    TicketSetting::current()->update(['fee_percent' => 1.5, 'free_allowance' => 750]);

    $this->getJson('/api/frontend/ticket-fee', ['X-API-KEY' => 'salah'])->assertUnauthorized();
    $this->getJson('/api/frontend/ticket-fee', ['X-API-KEY' => 'fe-key'])
        ->assertOk()
        ->assertExactJson(['data' => ['fee_percent' => 1.5, 'free_allowance' => 750]]);
});
