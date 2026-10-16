<?php

namespace App\Http\Controllers\WhatsApp;

use App\Events\WaWebhookReceived;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Webhook dari go_bizbos: device.status, message.new, message.status, campaign.status.
 * Signature = hex HMAC-SHA256(webhook_secret, timestamp + "." + body).
 */
class WaWebhookController extends Controller
{
    private const TOLERANCE_SECONDS = 300;

    public function __invoke(Request $request): JsonResponse
    {
        $timestamp = (string) $request->header('X-Bizbos-Timestamp');
        $signature = strtolower((string) $request->header('X-Bizbos-Signature'));
        $body = $request->getContent();
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, (string) config('services.wa.webhook_secret'));

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS || ! hash_equals($expected, $signature)) {
            return response()->json(['success' => false, 'message' => 'Signature tidak cocok.'], 401);
        }

        $payload = json_decode($body, true);
        if (! is_array($payload) || ! isset($payload['event'], $payload['branch_id'])) {
            return response()->json(['success' => false, 'message' => 'Data tidak valid.'], 422);
        }

        // Go mengirim ulang bila tidak dapat balasan; delivery yang sama cukup diproses sekali.
        $key = 'wa-webhook:'.$request->header('X-Bizbos-Delivery');
        if (Cache::has($key)) {
            return response()->json(['success' => true]);
        }

        event(new WaWebhookReceived($payload['event'], $payload['branch_id'], $payload['data'] ?? []));
        Cache::put($key, true, now()->addDay());

        return response()->json(['success' => true]);
    }
}
