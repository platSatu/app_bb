<?php

use App\Mail\InvoicePaidOwnerMail;
use App\Models\EmailLog;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserCompany;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use App\Services\Invoice\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

// Pakai antrean database sungguhan (bukan sync) supaya alur antrean -> terkirim/gagal teruji.
beforeEach(function () {
    config(['queue.default' => 'database']);
    $this->superadmin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);
});

function workEmailQueue(): void
{
    Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1]);
}

function emailOwnerInvoice(User $owner): Invoice
{
    $company = tap((new UserCompany())->forceFill(['user_id' => $owner->id, 'name' => 'Yayasan Email', 'slug' => 'email-'.Str::lower(Str::random(4))]))->save();
    $branch = tap((new UserBranch())->forceFill(['user_id' => $owner->id, 'company_id' => $company->id, 'name' => 'Cabang Email', 'status' => 'active']))->save();

    return tap((new Invoice())->forceFill([
        'branch_id' => $branch->id, 'company_id' => $company->id, 'source' => 'dashboard',
        'number' => 'INV-'.Str::upper(Str::random(8)), 'token' => Str::random(48), 'description' => 'SPP Januari',
        'customer_name' => 'Budi', 'amount' => 100000, 'status' => 'paid',
        'paid_amount' => 100000, 'fee' => 2000, 'net_amount' => 98000, 'paid_at' => now(),
    ]))->save();
}

it('email owner invoice lunas tercatat dari antrean sampai terkirim', function () {
    $owner = User::factory()->create(['email' => 'owner@mail.test']);
    $invoice = emailOwnerInvoice($owner);

    app(InvoiceService::class)->notifyOwner($invoice, 'paid', 100000);

    $log = EmailLog::sole();
    expect($log->status)->toBe(EmailLog::STATUS_QUEUED)
        ->and($log->type)->toBe(InvoicePaidOwnerMail::class)
        ->and($log->recipient)->toBe('owner@mail.test')
        ->and($log->job_uuid)->not->toBeNull();

    workEmailQueue();

    $log->refresh();
    expect($log->status)->toBe(EmailLog::STATUS_SENT)
        ->and($log->subject)->toContain('Invoice lunas '.$invoice->number)
        ->and($log->sent_at)->not->toBeNull();
});

it('email gagal tercatat beserta alasannya dan bisa dikirim ulang oleh superadmin', function () {
    // Invoice tidak ada -> email gagal dibuat saat dikirim worker.
    Mail::to('salah@mail.test')->queue(new InvoicePaidOwnerMail((string) Str::uuid(), 'paid', 1000));
    workEmailQueue();

    $log = EmailLog::sole();
    expect($log->status)->toBe(EmailLog::STATUS_FAILED)->and($log->error)->not->toBeEmpty();

    $this->actingAs($this->superadmin)->get(route('superadmin.emails.index'))
        ->assertOk()
        ->assertSee(['salah@mail.test', 'Invoice lunas (owner)', 'Gagal', 'Kirim Ulang']);

    $this->post(route('superadmin.emails.resend', $log))->assertSessionHas('success');

    expect($log->fresh()->status)->toBe(EmailLog::STATUS_QUEUED)
        ->and(DB::table('jobs')->count())->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('email terkirim tidak bisa dikirim ulang dan menu khusus superadmin', function () {
    $log = EmailLog::create(['type' => InvoicePaidOwnerMail::class, 'recipient' => 'a@mail.test', 'status' => EmailLog::STATUS_SENT]);

    $this->actingAs($this->superadmin)->post(route('superadmin.emails.resend', $log))->assertSessionHas('error');
    $this->actingAs(User::factory()->create())->get(route('superadmin.emails.index'))->assertForbidden();
});

it('verifikasi email dan reset password dikirim lewat antrean', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();
    $user->sendPasswordResetNotification('token-uji');

    Notification::assertSentTo($user, QueuedVerifyEmail::class);
    Notification::assertSentTo($user, QueuedResetPassword::class, fn ($n) => $n->token === 'token-uji');
    expect(new QueuedVerifyEmail())->toBeInstanceOf(ShouldQueue::class)
        ->and(new QueuedResetPassword('x'))->toBeInstanceOf(ShouldQueue::class);
});
