<?php

namespace App\Jobs;

use App\Models\Disbursement;
use App\Models\Invoice;
use App\Models\InvoiceApiClient;
use App\Models\InvoiceWebhookLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Kabar ke server website user (invoice.paid / invoice.expired / invoice.cancelled / payout.* / ping).
 * Ditandatangani dengan webhook secret, dicoba ulang bertahap, setiap percobaan dicatat.
 * URL hanya https & bukan alamat jaringan internal (kecuali APP_ENV=local untuk uji coba).
 */
class SendInvoiceWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function __construct(public string $clientId, public ?string $invoiceId, public string $event, public ?string $disbursementId = null)
    {
        $this->afterCommit();
    }

    public static function for(?Invoice $invoice, string $event): void
    {
        if ($invoice?->api_client_id && InvoiceApiClient::whereKey($invoice->api_client_id)->whereNotNull('webhook_url')->exists()) {
            dispatch(new self($invoice->api_client_id, $invoice->getKey(), $event));
        }
    }

    /** Hanya kiriman lewat API (punya api_client_id) yang dikabari. */
    public static function forDisbursement(Disbursement $disbursement, string $event): void
    {
        if ($disbursement->api_client_id && InvoiceApiClient::whereKey($disbursement->api_client_id)->whereNotNull('webhook_url')->exists()) {
            dispatch(new self($disbursement->api_client_id, null, $event, $disbursement->getKey()));
        }
    }

    public function backoff(): array
    {
        return [60, 300, 1800, 7200, 21600];
    }

    public function handle(): void
    {
        $client = InvoiceApiClient::find($this->clientId);
        $invoice = $this->invoiceId ? Invoice::find($this->invoiceId) : null;
        $disbursement = $this->disbursementId ? Disbursement::with('recipient')->find($this->disbursementId) : null;

        if (! $client?->webhook_url || ($this->invoiceId && ! $invoice) || ($this->disbursementId && ! $disbursement)) {
            return;
        }

        $body = json_encode([
            'id' => (string) Str::uuid(),
            'event' => $this->event,
            'created_at' => now()->toIso8601String(),
            'data' => $invoice?->toApi() ?? $disbursement?->toApi() ?? ['message' => 'Webhook bizbos berhasil tersambung.'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $timestamp = (string) time();
        $status = null;
        $error = null;
        $reply = null;

        try {
            [$host, $port, $ip] = self::resolveSafely($client->webhook_url);

            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]]])
                ->withHeaders([
                    'X-Bizbos-Event' => $this->event,
                    'X-Bizbos-Timestamp' => $timestamp,
                    'X-Bizbos-Signature' => hash_hmac('sha256', $timestamp.'.'.$body, $client->webhook_secret),
                    'User-Agent' => 'bizbos-webhook/1.0',
                ])
                ->withBody($body, 'application/json')
                ->post($client->webhook_url);

            $status = $response->status();
            $reply = Str::limit($response->body(), 1000);
            $error = $response->successful() ? null : 'Server Anda menjawab HTTP '.$status;
        } catch (Throwable $e) {
            $error = Str::limit($e->getMessage(), 480);
        }

        $log = new InvoiceWebhookLog();
        $log->forceFill([
            'api_client_id' => $client->getKey(),
            'invoice_id' => $invoice?->getKey(),
            'disbursement_id' => $disbursement?->getKey(),
            'event' => $this->event,
            'attempt' => $this->attempts(),
            'http_status' => $status,
            'payload' => $body,
            'response_body' => $reply,
            'error' => $error,
        ])->save();

        if ($error) {
            throw new RuntimeException('Webhook gagal: '.$error);
        }
    }

    /** @return array{0: string, 1: int, 2: string} */
    public static function resolveSafely(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';
        $local = app()->isLocal();

        if (! in_array($scheme, $local ? ['https', 'http'] : ['https'], true) || $host === '') {
            throw new RuntimeException('URL webhook harus diawali https://');
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : (gethostbyname($host) ?: $host);

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new RuntimeException('Domain webhook tidak ditemukan.');
        }

        if (! $local && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('URL webhook tidak boleh mengarah ke jaringan internal.');
        }

        return [$host, (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80)), $ip];
    }
}
