<?php

namespace App\Http\Controllers\WhatsApp\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WaApiKey;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Kirim WhatsApp dari aplikasi lain lewat device pemilik API key. Dokumentasi: halaman API Key device. */
class WaApiController extends Controller
{
    public function __construct(private WaGateway $wa) {}

    public function send(Request $request): JsonResponse
    {
        $key = $this->key($request);
        $data = $request->validate([
            'to' => ['required', 'string', 'max:20'],
            'text' => ['required', 'string', 'max:10000'],
            'ref' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);

        return $this->reply($this->wa->sendText($key->branch_id, [
            'device_id' => $key->device_id,
            'to' => $this->phone($data['to']),
            'text' => $data['text'],
            'client_ref' => isset($data['ref']) ? 'api:'.$data['ref'] : null,
        ]));
    }

    public function sendMedia(Request $request): JsonResponse
    {
        $key = $this->key($request);
        $data = $request->validate([
            'to' => ['required', 'string', 'max:20'],
            'caption' => ['nullable', 'string', 'max:3000'],
            'file' => ['required', 'file', 'max:65536'],
            'ref' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ]);

        return $this->reply($this->wa->sendMedia($key->branch_id, [
            'device_id' => $key->device_id,
            'to' => $this->phone($data['to']),
            'text' => $data['caption'] ?? null,
            'client_ref' => isset($data['ref']) ? 'api:'.$data['ref'] : null,
        ], $request->file('file')));
    }

    public function status(Request $request, string $id): JsonResponse
    {
        $key = $this->key($request);
        $row = DB::table('wa_outbox')->where('device_id', $key->device_id)->where('id', $id)->first(['id', 'status', 'error', 'created_at', 'updated_at']);
        abort_unless($row, 404);

        return response()->json(['success' => true, 'data' => $row]);
    }

    private function key(Request $request): WaApiKey
    {
        return $request->attributes->get('waApiKey');
    }

    private function phone(string $to): string
    {
        $phone = User::normalizePhone($to);
        abort_if(strlen($phone) < 10, 422, 'Nomor tujuan tidak valid. Gunakan format 628xxxxxxxxxx.');

        return $phone;
    }

    private function reply(array $result): JsonResponse
    {
        return response()->json(
            ['success' => $result['success'], 'message' => $result['success'] ? 'Pesan masuk antrean kirim.' : $result['message'], 'data' => $result['data']],
            $result['success'] ? 202 : ($result['status'] >= 400 ? $result['status'] : 502)
        );
    }
}
