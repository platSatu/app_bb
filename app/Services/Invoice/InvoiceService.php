<?php

namespace App\Services\Invoice;

use App\Jobs\SendInvoiceWebhook;
use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceApiClient;
use App\Models\InvoiceBatch;
use App\Models\InvoiceCategory;
use App\Models\InvoiceSubscription;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\Customer\CustomerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Membuat & mengubah invoice (dashboard maupun API). Uangnya diurus InvoicePaymentService.
 * Pembuatan dikunci per branch supaya nomor/external_id tidak dobel saat request bersamaan.
 */
class InvoiceService
{
    public function __construct(private readonly CustomerService $customers)
    {
    }

    /** Periode baru: satu invoice untuk setiap customer yang berlangganan aktif di kategori. */
    public function createBatch(InvoiceCategory $category, array $data, User $actor): InvoiceBatch
    {
        $batch = DB::transaction(function () use ($category, $data, $actor) {
            $branch = UserBranch::whereKey($category->branch_id)->lockForUpdate()->firstOrFail();

            $batch = new InvoiceBatch($data);
            $batch->forceFill(['branch_id' => $category->branch_id, 'category_id' => $category->getKey(), 'status' => InvoiceBatch::STATUS_ACTIVE, 'created_by' => $actor->getKey()])->save();

            $subscriptions = $category->subscriptions()->active()->with('customer')->get();

            foreach ($subscriptions as $subscription) {
                $this->createForCustomer($branch, $batch, $subscription->customer, $subscription->amount ?? $batch->amount);
            }

            return $batch;
        });

        $this->emailBatch($batch);

        return $batch;
    }

    /** Tambah customer ke periode yang sudah ada. Customer yang sudah punya invoice aktif di periode ini dilewati. */
    public function addCustomers(InvoiceBatch $batch, array $customerIds): int
    {
        if ($batch->isCancelled()) {
            throw new RuntimeException('Periode ini sudah dibatalkan.');
        }

        $created = DB::transaction(function () use ($batch, $customerIds) {
            $branch = UserBranch::whereKey($batch->branch_id)->lockForUpdate()->firstOrFail();

            $existing = $batch->invoices()->where('status', '!=', Invoice::STATUS_CANCELLED)->whereNotNull('customer_id')->pluck('customer_id')->all();
            $overrides = InvoiceSubscription::where('category_id', $batch->category_id)->whereIn('customer_id', $customerIds)->pluck('amount', 'customer_id');
            $created = [];

            foreach (Customer::where('branch_id', $batch->branch_id)->forApp('invoices')->whereKey($customerIds)->whereNotIn('id', $existing)->get() as $customer) {
                $created[] = $this->createForCustomer($branch, $batch, $customer, $overrides[$customer->getKey()] ?? $batch->amount);
            }

            return $created;
        });

        foreach ($created as $invoice) {
            $this->email($invoice, 'issued');
        }

        return count($created);
    }

    /** Salin periode (mis. bulan berikutnya) dengan customer & nominal yang sama. */
    public function copyBatch(InvoiceBatch $source, array $data, User $actor): InvoiceBatch
    {
        $batch = DB::transaction(function () use ($source, $data, $actor) {
            $branch = UserBranch::whereKey($source->branch_id)->lockForUpdate()->firstOrFail();

            $batch = new InvoiceBatch(['name' => $data['name'], 'amount' => $source->amount, 'due_date' => $data['due_date'], 'use_late_fee' => $source->use_late_fee]);
            $batch->forceFill(['branch_id' => $source->branch_id, 'category_id' => $source->category_id, 'status' => InvoiceBatch::STATUS_ACTIVE, 'created_by' => $actor->getKey()])->save();

            $previous = $source->invoices()->with('customer')->where('status', '!=', Invoice::STATUS_CANCELLED)->whereNotNull('customer_id')->get();

            foreach ($previous as $invoice) {
                if ($invoice->customer) {
                    $this->createForCustomer($branch, $batch, $invoice->customer, $invoice->amount);
                }
            }

            return $batch;
        });

        $this->emailBatch($batch);

        return $batch;
    }

    /** @return array{0: Invoice, 1: bool} [invoice, baru dibuat?]. external_id yang sama = invoice yang sama (idempoten). */
    public function createFromApi(InvoiceApiClient $client, array $data, int $expiryMinutes): array
    {
        return DB::transaction(function () use ($client, $data, $expiryMinutes) {
            $branch = UserBranch::whereKey($client->branch_id)->lockForUpdate()->firstOrFail();

            if ($existing = Invoice::where('api_client_id', $client->getKey())->where('external_id', $data['external_id'])->first()) {
                return [$existing, false];
            }

            $customer = filled($data['customer']['email'] ?? null) || filled($data['customer']['phone'] ?? null)
                ? $this->customers->resolve($branch, 'invoices', $data['customer']['name'], $data['customer']['phone'] ?? null, $data['customer']['email'] ?? null, 'Invoice API')
                : null;

            $invoice = $this->newInvoice($branch, 'INV', [
                'api_client_id' => $client->getKey(),
                'source' => Invoice::SOURCE_API,
                'external_id' => $data['external_id'],
                'customer_id' => $customer?->getKey(),
                'description' => $data['description'],
                'customer_name' => $data['customer']['name'],
                'customer_email' => $data['customer']['email'] ?? null,
                'customer_phone' => $customer?->phone ?? ($data['customer']['phone'] ?? null),
                'amount' => $data['amount'],
                'due_date' => $data['due_date'] ?? null,
                'expires_at' => now()->addMinutes($data['expiry_minutes'] ?? $expiryMinutes),
                'return_url' => $data['return_url'] ?? null,
            ]);

            return [$invoice, true];
        });
    }

    /** Tagihan pendaftaran Google Form (satu per pendaftar). Lunas -> QR registrasi ulang dikirim lewat WhatsApp. */
    public function createForForm(UserBranch $branch, string $description, string $name, string $phone, int $amount): Invoice
    {
        return $this->newInvoice($branch, 'REG', [
            'source' => Invoice::SOURCE_FORM,
            'description' => Str::limit($description, 250, ''),
            'customer_name' => Str::limit($name, 150, ''),
            'customer_phone' => $phone,
            'amount' => $amount,
        ]);
    }

    public function cancel(Invoice $invoice, string $reason): void
    {
        $cancelled = DB::transaction(function () use ($invoice, $reason) {
            $locked = Invoice::whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isUnpaid()) {
                throw new RuntimeException('Hanya invoice yang belum dibayar yang bisa dibatalkan.');
            }

            $locked->forceFill(['status' => Invoice::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => Str::limit($reason, 250, '')])->save();

            return $locked;
        });

        SendInvoiceWebhook::for($cancelled, 'invoice.cancelled');
    }

    public function cancelBatch(InvoiceBatch $batch, string $reason): int
    {
        $count = 0;

        foreach ($batch->invoices()->where('status', Invoice::STATUS_UNPAID)->get() as $invoice) {
            $this->cancel($invoice, $reason);
            $count++;
        }

        $batch->forceFill(['status' => InvoiceBatch::STATUS_CANCELLED])->save();

        return $count;
    }

    /** Link bayar lama langsung tidak berlaku (mis. link tersebar ke orang lain). */
    public function regenerateToken(Invoice $invoice): void
    {
        $invoice->forceFill(['token' => $this->unique('token', fn () => Str::random(48))])->save();
    }

    /** Email gagal tidak boleh menggagalkan proses utama. $kind: issued | reminder | paid. */
    public function email(Invoice $invoice, string $kind): bool
    {
        if (! $invoice->customer_email) {
            return false;
        }

        try {
            Mail::to($invoice->customer_email)->queue(new InvoiceMail($invoice->getKey(), $kind));

            if ($kind === 'issued') {
                $invoice->forceFill(['emailed_at' => now()])->save();
            }

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private function emailBatch(InvoiceBatch $batch): void
    {
        if ($batch->category?->email_enabled) {
            $batch->invoices()->whereNotNull('customer_email')->each(fn (Invoice $invoice) => $this->email($invoice, 'issued'));
        }
    }

    private function createForCustomer(UserBranch $branch, InvoiceBatch $batch, Customer $customer, int $amount): Invoice
    {
        $category = $batch->category;

        return $this->newInvoice($branch, $category->prefix, [
            'category_id' => $category->getKey(),
            'batch_id' => $batch->getKey(),
            'customer_id' => $customer->getKey(),
            'source' => Invoice::SOURCE_DASHBOARD,
            'description' => Str::limit($batch->name, 250, ''),
            'customer_name' => $customer->displayName(),
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'amount' => $amount,
            'apply_late_fee' => $batch->use_late_fee && $category->hasLateFee(),
            'due_date' => $batch->due_date,
        ]);
    }

    private function newInvoice(UserBranch $branch, string $prefix, array $attributes): Invoice
    {
        $invoice = new Invoice();
        $invoice->forceFill([
            'branch_id' => $branch->getKey(),
            'company_id' => $branch->company_id,
            'number' => $this->unique('number', fn () => strtoupper($prefix).'-'.now()->format('ym').strtoupper(Str::random(6))),
            'token' => $this->unique('token', fn () => Str::random(48)),
            'status' => Invoice::STATUS_UNPAID,
            ...$attributes,
        ])->save();

        return $invoice;
    }

    private function unique(string $column, callable $make): string
    {
        do {
            $value = $make();
        } while (Invoice::where($column, $value)->exists());

        return $value;
    }
}
