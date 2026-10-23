<?php

namespace App\Http\Controllers\WhatsApp\Api;

use App\Http\Controllers\Controller;
use App\Models\WaFormIntegration;
use App\Services\WhatsApp\FormRelay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Penerima kiriman Google Form (Apps Script). Token di URL adalah kuncinya.
 * Selalu menjawab 200 untuk token yang sah supaya Apps Script tidak mengulang-ulang;
 * hasil gagal tercatat di riwayat integrasi.
 */
class FormWebhookController extends Controller
{
    private const MAX_BYTES = 65536;

    public function __invoke(Request $request, string $token, FormRelay $relay): JsonResponse
    {
        $integration = WaFormIntegration::with('device:id,branch_id')->where('webhook_token', $token)->first();

        if (! $integration) {
            return response()->json(['success' => false, 'message' => 'Webhook tidak ditemukan. Salin ulang URL dari menu Third Party.'], 404);
        }
        if (! $integration->is_active) {
            return response()->json(['success' => false, 'message' => 'Integrasi ini sedang dinonaktifkan pemiliknya.']);
        }
        if (strlen($request->getContent()) > self::MAX_BYTES) {
            return response()->json(['success' => false, 'message' => 'Data form terlalu besar (maks 64 KB).'], 413);
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            $payload = $request->post();
        }

        $result = $relay->handle($integration, $payload);

        return response()->json(['success' => in_array($result['status'], ['sent', 'duplicate'], true), ...$result]);
    }
}
