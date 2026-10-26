<?php

namespace App\Http\Controllers\Invoice\Api;

use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\DisbursementRecipient;
use App\Models\InvoiceApiClient;
use App\Services\Payment\DuitkuDisbursementClient;
use App\Services\Withdrawal\DisbursementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * REST API Disbursement untuk website user: daftar bank, rekening penerima, dan kirim dana dari saldo
 * branch milik API key. Harus diaktifkan pemilik akun (menu Disbursement › Pengaturan & API).
 */
class DisbursementApiController extends Controller
{
    private const ID_PATTERN = 'regex:/^[A-Za-z0-9._:\-]+$/';

    public function __construct(private readonly DisbursementService $service)
    {
    }

    public function banks(Request $request): JsonResponse
    {
        $this->client($request);

        try {
            $banks = DuitkuDisbursementClient::make()->banks();
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 503);
        }

        return response()->json(['success' => true, 'data' => collect($banks)->map(fn ($b) => ['bank_code' => $b['bankCode'], 'bank_name' => $b['bankName']])->values()]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $data = $request->validate([
            'external_id' => ['nullable', 'string', 'max:100', self::ID_PATTERN],
            'bank_code' => ['required', 'string', 'max:10'],
            'account_number' => ['required', 'string', 'regex:/^[0-9\s\-]{5,30}$/'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'label' => ['nullable', 'string', 'max:150'],
        ]);

        try {
            $recipient = $this->service->saveRecipient($client->branch, $data, Disbursement::SOURCE_API);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return response()->json(['success' => true, 'data' => $recipient->toApi()]);
    }

    public function showAccount(Request $request, string $account): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->findRecipient($this->client($request), $account)->toApi()]);
    }

    public function store(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:100', self::ID_PATTERN],
            'payout_account_id' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $recipient = $this->findRecipient($client, $data['payout_account_id']);

        try {
            [$disbursement, $created] = $this->service->send($recipient, (int) $data['amount'], $data['description'] ?? null, Disbursement::SOURCE_API, $data['external_id'], $client);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), $e->getCode() === 409 ? 409 : 422);
        }

        return response()->json(['success' => true, 'created' => $created, 'data' => $disbursement->load('recipient')->toApi()], $created ? 201 : 200);
    }

    public function show(Request $request, string $payout): JsonResponse
    {
        $client = $this->client($request);
        $model = Disbursement::with('recipient')->where('branch_id', $client->branch_id)
            ->where(fn ($q) => $q->where('external_id', $payout)->when($this->isUuid($payout), fn ($w) => $w->orWhere('id', $payout)))
            ->firstOr(fn () => abort($this->error('Disbursement tidak ditemukan.', 404)));

        return response()->json(['success' => true, 'data' => $model->toApi()]);
    }

    /** {account} boleh id bizbos atau external_id milik Anda. */
    private function findRecipient(InvoiceApiClient $client, string $id): DisbursementRecipient
    {
        return DisbursementRecipient::where('branch_id', $client->branch_id)
            ->where(fn ($q) => $q->where('external_id', $id)->when($this->isUuid($id), fn ($w) => $w->orWhere('id', $id)))
            ->firstOr(fn () => abort($this->error('Rekening penerima tidak ditemukan.', 404)));
    }

    private function client(Request $request): InvoiceApiClient
    {
        $client = $request->attributes->get('invoiceClient');

        abort_unless($client->payout_enabled, $this->error('Disbursement lewat API belum diaktifkan. Aktifkan di menu Disbursement › Pengaturan & API.', 403));

        return $client;
    }

    private function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f-]{36}$/i', $value);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
