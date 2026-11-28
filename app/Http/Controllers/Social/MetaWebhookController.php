<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMetaWebhook;
use App\Models\SocialApp;
use App\Models\SocialInboxSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook Meta untuk Tools › Inbox (Callback URL: /api/webhooks/meta).
 *  GET  = verifikasi saat didaftarkan di dashboard Meta (hub.verify_token harus sama dengan token di Tools › Inbox).
 *  POST = event DM & komentar. Wajib lolos X-Hub-Signature-256 (HMAC-SHA256 body dengan App Secret); diproses di antrean.
 */
class MetaWebhookController extends Controller
{
    private const MAX_BODY_BYTES = 1024 * 1024;

    public function verify(Request $request): Response
    {
        $token = (string) SocialInboxSetting::current()->webhook_verify_token;

        if ($request->query('hub_mode') === 'subscribe' && $token !== '' && hash_equals($token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request): Response
    {
        $body = $request->getContent();
        $secret = (string) SocialApp::for('meta')?->client_secret;
        $signature = strtolower((string) $request->header('X-Hub-Signature-256'));

        if ($secret === '' || strlen($body) > self::MAX_BODY_BYTES
            || ! hash_equals('sha256='.hash_hmac('sha256', $body, $secret), $signature)) {
            return response('Invalid signature', 401);
        }

        $payload = json_decode($body, true);

        if (is_array($payload) && in_array($payload['object'] ?? null, ['page', 'instagram'], true)) {
            ProcessMetaWebhook::dispatch($payload);
        }

        // Meta mengirim ulang kalau tidak mendapat 200; pesan dobel dicegah oleh ID pesan unik.
        return response('EVENT_RECEIVED', 200);
    }
}
