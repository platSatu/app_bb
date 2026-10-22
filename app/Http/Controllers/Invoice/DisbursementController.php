<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Invoice\Concerns\InInvoiceArea;
use App\Models\Disbursement;
use App\Models\DisbursementRecipient;
use App\Models\DisbursementSetting;
use App\Models\InvoiceApiClient;
use App\Models\UserBranch;
use App\Services\Payment\DuitkuDisbursementClient;
use App\Services\Wallet\WalletService;
use App\Services\Withdrawal\DisbursementService;
use App\Support\Menu;
use App\Support\XlsxExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Menu Disbursement (owner): Riwayat, Rekening Penerima, Pengaturan & API. Data selalu per branch miliknya. */
class DisbursementController extends Controller
{
    use InInvoiceArea;

    public function __construct(private readonly DisbursementService $service)
    {
    }

    public function index(Request $request, WalletService $wallets): View|RedirectResponse|StreamedResponse
    {
        if (! ($branches = $this->ownerBranches($request))->count()) {
            return $this->branchRequired();
        }

        $branch = $this->branch($request, $branches);
        $search = trim((string) $request->query('search'));
        $query = Disbursement::accessibleBy($request->user())->where('branch_id', $branch->id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$search}%")
                ->orWhere('external_id', 'like', "%{$search}%")->orWhere('account_name', 'like', "%{$search}%")))
            ->when($request->date('date_from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('date_to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()))
            ->latest();

        if ($request->boolean('export')) {
            return XlsxExport::download('disbursement-'.now()->format('Ymd-His'), ['Referensi', 'External ID', 'Sumber', 'Penerima', 'Bank', 'Rekening', 'Jumlah', 'Biaya', 'Diterima', 'Status', 'Keterangan', 'Dibuat'],
                $query->limit(5000)->get()->map(fn (Disbursement $d) => [
                    $d->reference, (string) $d->external_id, $d->source, $d->account_name, $d->bank_name, '••••'.$d->account_last4,
                    $d->amount, $d->fee, $d->net_amount, $d->statusLabel(), (string) $d->description, $d->created_at->format('Y-m-d H:i'),
                ]));
        }

        // Saldo pemilik branch (yang dipotong saat kirim) dan bagian yang sudah settlement.
        $summary = $wallets->summary($branch->user ?? $request->user());

        return view('invoice.disbursements.index', [
            ...$this->page($request, 'history', $branches, $branch),
            'disbursements' => $query->with('recipient:id,label')->paginate(20)->withQueryString(),
            'recipients' => DisbursementRecipient::where('branch_id', $branch->id)->where('status', DisbursementRecipient::STATUS_ACTIVE)->orderBy('label')->orderBy('account_name')->get(),
            'balance' => $summary['balance'],
            'sendable' => $summary['available'],
            'sentThisMonth' => (int) Disbursement::where('branch_id', $branch->id)->where('status', Disbursement::STATUS_SUCCESS)->where('created_at', '>=', now()->startOfMonth())->sum('amount'),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', Rule::in($this->ownerBranches($request)->pluck('id'))],
            'recipient_id' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['recipient_id' => 'rekening penerima', 'amount' => 'jumlah']);

        $recipient = DisbursementRecipient::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->where('branch_id', $data['branch_id'])->findOrFail($data['recipient_id']);

        try {
            [$disbursement] = $this->service->send($recipient, (int) $data['amount'], $data['description'] ?? null, Disbursement::SOURCE_DASHBOARD, null, null, $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Disbursement {$disbursement->reference} diproses. Saldo ".$this->rupiah($disbursement->amount)." sudah ditahan; kalau gagal, otomatis kembali.");
    }

    public function check(Request $request, string $disbursement): RedirectResponse
    {
        $model = Disbursement::accessibleBy($request->user())->findOrFail($disbursement);

        return back()->with('info', "{$model->reference}: ".$this->service->checkStatus($model));
    }

    public function recipients(Request $request): View|RedirectResponse
    {
        if (! ($branches = $this->ownerBranches($request))->count()) {
            return $this->branchRequired();
        }

        $branch = $this->branch($request, $branches);
        [$banks, $banksError] = $this->banks();

        return view('invoice.disbursements.recipients', [
            ...$this->page($request, 'recipients', $branches, $branch),
            'recipients' => DisbursementRecipient::accessibleBy($request->user())->where('branch_id', $branch->id)
                ->when(trim((string) $request->query('search')), fn ($q, $s) => $q->where(fn ($w) => $w->where('account_name', 'like', "%{$s}%")->orWhere('label', 'like', "%{$s}%")->orWhere('external_id', 'like', "%{$s}%")))
                ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")->latest()->paginate(20)->withQueryString(),
            'banks' => $banks,
            'banksError' => $banksError,
        ]);
    }

    public function storeRecipient(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', Rule::in($this->ownerBranches($request)->pluck('id'))],
            'bank_code' => ['required', 'string', 'max:10'],
            'account_number' => ['required', 'string', 'regex:/^[0-9\s\-]{5,30}$/'],
            'label' => ['nullable', 'string', 'max:150'],
        ], ['account_number.regex' => 'Nomor rekening hanya angka.'], ['bank_code' => 'bank', 'account_number' => 'nomor rekening', 'label' => 'label']);

        try {
            $recipient = $this->service->saveRecipient(UserBranch::findOrFail($data['branch_id']), $data, Disbursement::SOURCE_DASHBOARD, $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Rekening {$recipient->masked()} a.n. {$recipient->account_name} tersimpan dan siap dipakai.");
    }

    public function toggleRecipient(Request $request, string $recipient): RedirectResponse
    {
        $model = DisbursementRecipient::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($recipient);
        $model->forceFill(['status' => $model->isActive() ? DisbursementRecipient::STATUS_INACTIVE : DisbursementRecipient::STATUS_ACTIVE])->save();

        return back()->with('success', "Rekening {$model->masked()} ".($model->isActive() ? 'diaktifkan.' : 'dinonaktifkan, tidak bisa menerima dana sampai diaktifkan lagi.'));
    }

    public function settings(Request $request): View|RedirectResponse
    {
        if (! ($branches = $this->ownerBranches($request))->count()) {
            return $this->branchRequired();
        }

        $branch = $this->branch($request, $branches);

        return view('invoice.disbursements.settings', [
            ...$this->page($request, 'settings', $branches, $branch),
            'client' => InvoiceApiClient::accessibleBy($request->user())->where('branch_id', $branch->id)->first(),
        ]);
    }

    public function updateSettings(Request $request, string $client): RedirectResponse
    {
        $model = InvoiceApiClient::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($client);
        $data = $request->validate([
            'payout_max_amount' => ['nullable', 'integer', 'min:10000'],
            'payout_daily_limit' => ['nullable', 'integer', 'min:10000'],
        ], [], ['payout_max_amount' => 'batas per transaksi', 'payout_daily_limit' => 'batas per hari']);

        $model->forceFill([...$data, 'payout_enabled' => $request->boolean('payout_enabled')])->save();

        return back()->with('success', 'Pengaturan disbursement API disimpan.');
    }

    private function branch(Request $request, $branches): UserBranch
    {
        return $branches->firstWhere('id', $request->query('branch')) ?? $branches->first();
    }

    private function page(Request $request, string $tab, $branches, UserBranch $branch): array
    {
        $setting = DisbursementSetting::current();

        return ['tab' => $tab, 'branches' => $branches, 'branch' => $branch, 'setting' => $setting, 'available' => $setting->is_active && $setting->isConfigured()];
    }

    /** @return array{0: array, 1: ?string} */
    private function banks(): array
    {
        try {
            return [DuitkuDisbursementClient::make()->banks(), null];
        } catch (Throwable $e) {
            report($e);

            return [[], 'Daftar bank sedang tidak bisa dimuat. Coba muat ulang halaman beberapa saat lagi.'];
        }
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
