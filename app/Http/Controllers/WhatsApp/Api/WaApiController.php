<?php

namespace App\Http\Controllers\WhatsApp\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyWaApiKey;
use App\Models\BranchSubscription;
use App\Models\User;
use App\Models\WaApiKey;
use App\Services\Subscription\PlanLimits;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kirim WhatsApp dari aplikasi lain lewat device pemilik API key.
 * Alamat, header, body, dan jawaban sama dengan API teleios, jadi aplikasi customer cukup ganti API Host, Token, dan Secret Key.
 * Dokumentasi: docs/whatsapp-api.md.
 */
class WaApiController extends Controller
{
    public function __construct(private WaGateway $wa) {}

    /** POST /api/wa-api/v1/send-message {"to", "message", "ref"?} */
    public function send(Request $request): JsonResponse
    {
        $key = $this->key($request);
        $data = $request->validate([
            'to' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:10000'],
            'ref' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);
        $to = $this->recipient($data['to']);

        return $this->reply($to, $this->wa->sendText($key->branch_id, [
            'device_id' => $key->device_id,
            'to' => $to,
            'text' => $data['message'],
            'client_ref' => isset($data['ref']) ? 'api:'.$data['ref'] : null,
        ]));
    }

    /** POST /api/wa-api/v1/send-media (multipart: to, file, caption?, ref?) */
    public function sendMedia(Request $request): JsonResponse
    {
        $key = $this->key($request);
        $data = $request->validate([
            'to' => ['required', 'string', 'max:100'],
            'caption' => ['nullable', 'string', 'max:3000'],
            'file' => ['required', 'file', 'max:65536'],
            'ref' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ], ['file.max' => 'Ukuran file maksimal 64 MB.']);
        $to = $this->recipient($data['to']);

        return $this->reply($to, $this->wa->sendMedia($key->branch_id, [
            'device_id' => $key->device_id,
            'to' => $to,
            'text' => $data['caption'] ?? null,
            'client_ref' => isset($data['ref']) ? 'api:'.$data['ref'] : null,
        ], $request->file('file')));
    }

    /** GET /api/wa-api/v1/messages/{id}: queued, sent, failed, skipped. */
    public function status(Request $request, string $id): JsonResponse
    {
        $key = $this->key($request);
        $row = DB::table('wa_outbox')->where('device_id', $key->device_id)->where('id', $id)->first(['id', 'status', 'error', 'created_at', 'updated_at']);

        return $row ? response()->json(['success' => true, 'data' => $row]) : VerifyWaApiKey::error('Pesan tidak ditemukan.', 404);
    }

    /** GET /api/wa-api/v1/usage: paket, sisa kuota pesan bulan ini, jumlah request. Tidak memotong kuota. */
    public function usage(Request $request, PlanLimits $limits): JsonResponse
    {
        $key = $this->key($request);
        $subscription = BranchSubscription::where('branch_id', $key->branch_id)->where('app_key', 'whatsapp')->active()->first(['starts_at', 'ends_at']);

        return response()->json([
            'success' => true,
            'device_id' => $key->device_id,
            'package_active' => $subscription !== null,
            'package' => $subscription ? ['valid_from' => $subscription->starts_at->toIso8601String(), 'valid_until' => $subscription->ends_at->toIso8601String()] : null,
            'quota' => ['metric' => 'whatsapp_monthly', ...$limits->messageQuota($key->branch_id)],
            'requests' => [
                'today' => $this->requestCounts($key, now()->startOfDay()),
                'this_month' => $this->requestCounts($key, now()->startOfMonth()),
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function key(Request $request): WaApiKey
    {
        return $request->attributes->get('waApiKey');
    }

    /** Nomor biasa (0812.., 62812..) dirapikan ke 62..; JID lengkap (...@s.whatsapp.net / grup ...@g.us) dipakai apa adanya. */
    private function recipient(string $to): string
    {
        $to = trim($to);
        if (str_contains($to, '@')) {
            if (preg_match('/^\d{5,25}(-\d{5,15})?@(s\.whatsapp\.net|g\.us)$/', $to)) {
                return $to;
            }
        } elseif (strlen($phone = User::normalizePhone($to)) >= 10) {
            return $phone;
        }

        throw ValidationException::withMessages(['to' => 'Nomor tujuan tidak valid. Gunakan format 628xxxxxxxxxx atau ID grup ...@g.us.']);
    }

    private function reply(string $to, array $result): JsonResponse
    {
        if (! $result['success']) {
            return VerifyWaApiKey::error($result['message'], $result['status'] >= 400 ? $result['status'] : 502);
        }

        // Sama dengan teleios: 200 + status "sent". Pesan masuk antrean berjeda (anti-ban), status akhir lewat /messages/{id}.
        return response()->json([
            'success' => true,
            'status' => 'sent',
            'message' => ['id' => $result['data']['outbox_id'] ?? null, 'to' => $to, 'queued' => true],
        ]);
    }

    /** Jumlah request kirim per hasil, dari catatan wa_api_logs. */
    private function requestCounts(WaApiKey $key, \DateTimeInterface $since): array
    {
        $rows = $key->logs()->where('created_at', '>=', $since)->where('path', 'like', '%/send-%')
            ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        $sent = $rows->filter(fn ($n, $status) => $status < 300)->sum();
        $blocked = (int) ($rows[403] ?? 0);
        $limited = (int) ($rows[429] ?? 0);

        return ['sent' => (int) $sent, 'blocked_package' => $blocked, 'blocked_quota' => $limited,
            'failed' => (int) $rows->sum() - (int) $sent - $blocked - $limited, 'total' => (int) $rows->sum()];
    }
}
