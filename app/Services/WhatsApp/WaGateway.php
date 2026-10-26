<?php

namespace App\Services\WhatsApp;

use App\Services\Subscription\PlanLimits;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Klien ke service Go (go_bizbos). Setiap request ditandatangani dan membawa branch_id,
 * jadi Go hanya melayani data milik branch tersebut.
 * Semua method mengembalikan ['success' => bool, 'status' => int, 'message' => string, 'data' => array].
 */
class WaGateway
{
    public function connect(string $branchId, string $deviceId, ?string $phone = null): array
    {
        if ($denied = $this->inactive($branchId)) {
            return $denied;
        }

        return $this->json('POST', "/v1/devices/{$deviceId}/connect", $branchId, ['phone' => $phone], 30);
    }

    public function status(string $branchId, string $deviceId): array
    {
        return $this->json('GET', "/v1/devices/{$deviceId}/status", $branchId);
    }

    public function logout(string $branchId, string $deviceId): array
    {
        return $this->json('POST', "/v1/devices/{$deviceId}/logout", $branchId);
    }

    public function sendText(string $branchId, array $data): array
    {
        if ($denied = $this->inactive($branchId) ?? $this->quota($branchId)) {
            return $denied;
        }

        return $this->settle($branchId, $this->json('POST', '/v1/messages', $branchId, $data));
    }

    public function sendMedia(string $branchId, array $data, UploadedFile $file): array
    {
        if ($denied = $this->inactive($branchId) ?? $this->quota($branchId)) {
            return $denied;
        }

        return $this->settle($branchId, $this->multipart('/v1/messages/media', $branchId, $data, $file));
    }

    public function sendPoll(string $branchId, array $data): array
    {
        if ($denied = $this->inactive($branchId) ?? $this->quota($branchId)) {
            return $denied;
        }

        return $this->settle($branchId, $this->json('POST', '/v1/messages/poll', $branchId, $data));
    }

    public function upload(string $branchId, UploadedFile $file): array
    {
        return $this->multipart('/v1/uploads', $branchId, [], $file);
    }

    public function markRead(string $branchId, string $chatId): array
    {
        return $this->json('POST', "/v1/chats/{$chatId}/read", $branchId);
    }

    public function subscribePresence(string $branchId, string $chatId): array
    {
        return $this->json('POST', "/v1/chats/{$chatId}/presence", $branchId);
    }

    public function groups(string $branchId, string $deviceId): array
    {
        return $this->json('GET', "/v1/devices/{$deviceId}/groups", $branchId, [], 40);
    }

    public function groupParticipants(string $branchId, string $deviceId, string $groupJid): array
    {
        return $this->json('GET', "/v1/devices/{$deviceId}/groups/{$groupJid}", $branchId, [], 30);
    }

    /** $action: start | pause | cancel */
    public function campaign(string $branchId, string $campaignId, string $action): array
    {
        // Batal/jeda tetap boleh supaya kiriman bisa dihentikan; mulai/lanjut butuh paket aktif.
        if (in_array($action, ['start', 'resume'], true) && ($denied = $this->inactive($branchId))) {
            return $denied;
        }

        return $this->json('POST', "/v1/campaigns/{$campaignId}/{$action}", $branchId);
    }

    /** Token singkat untuk browser: scope "sse" (resource = device_id, opsional), "media" (id pesan), "avatar" (id chat). */
    public function token(string $branchId, string $scope, ?string $resource = null, int $ttl = 600): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode(array_filter([
            'b' => $branchId,
            's' => $scope,
            'r' => $resource,
            'exp' => time() + min($ttl, 900),
        ]))), '+/', '-_'), '=');

        return $payload.'.'.hash_hmac('sha256', $payload, (string) config('services.wa.access_secret'));
    }

    public function realtimeUrl(string $branchId, ?string $deviceId = null): string
    {
        return $this->publicUrl('/realtime?token='.$this->token($branchId, 'sse', $deviceId, 120));
    }

    public function mediaUrl(string $branchId, string $messageId): string
    {
        return $this->publicUrl("/media/{$messageId}?token=".$this->token($branchId, 'media', $messageId));
    }

    public function avatarUrl(string $branchId, string $chatId): string
    {
        return $this->publicUrl("/avatar/{$chatId}?token=".$this->token($branchId, 'avatar', $chatId));
    }

    /** Paket WhatsApp branch habis: kirim pesan & tautkan nomor ditolak (cara jawabnya sama dengan error lain). */
    private function inactive(string $branchId): ?array
    {
        return app(\App\Services\Subscription\AppAccess::class)->branchActive($branchId, 'whatsapp') ? null
            : ['success' => false, 'status' => 403, 'message' => 'Paket WhatsApp untuk branch ini belum aktif atau sudah habis. Aktifkan paket dulu ya.', 'data' => []];
    }

    /** Kuota pesan WhatsApp bulan ini (PlanLimits): dipakai 1 sebelum kirim, dikembalikan kalau go_bizbos menolak. */
    private function quota(string $branchId): ?array
    {
        $limits = app(PlanLimits::class);

        return $limits->takeMessages($branchId, 1) ? null
            : ['success' => false, 'status' => 429, 'message' => $limits->messagesExhaustedMessage($branchId), 'data' => []];
    }

    private function settle(string $branchId, array $result): array
    {
        if (! $result['success']) {
            app(PlanLimits::class)->refundMessages($branchId, 1);
        }

        return $result;
    }

    private function publicUrl(string $path): string
    {
        return rtrim((string) config('services.wa.public_url'), '/').$path;
    }

    private function json(string $method, string $path, string $branchId, array $data = [], int $timeout = 20): array
    {
        $body = $method === 'GET' ? '' : json_encode((object) array_filter($data, fn ($v) => $v !== null && $v !== ''));

        return $this->send(fn () => $this->client($method, $path, $branchId, hash('sha256', $body))
            ->timeout($timeout)
            ->withBody($body, 'application/json')
            ->send($method, $this->url($path)));
    }

    private function multipart(string $path, string $branchId, array $data, UploadedFile $file): array
    {
        $boundary = Str::random(32);
        $body = '';
        foreach (array_filter($data, fn ($v) => $v !== null && $v !== '') as $name => $value) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        }
        $filename = str_replace(['"', "\r", "\n", '\\'], '', $file->getClientOriginalName()) ?: 'file';
        $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"{$filename}\"\r\nContent-Type: application/octet-stream\r\n\r\n";
        $body .= $file->getContent()."\r\n--{$boundary}--\r\n";

        return $this->send(fn () => $this->client('POST', $path, $branchId, hash('sha256', $body))
            ->timeout(120)
            ->withBody($body, "multipart/form-data; boundary={$boundary}")
            ->send('POST', $this->url($path)));
    }

    private function send(callable $request): array
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            Log::warning('go_bizbos tidak bisa dihubungi: '.$e->getMessage());

            return ['success' => false, 'status' => 503, 'message' => 'Layanan WhatsApp sedang tidak bisa dihubungi. Coba lagi beberapa saat lagi.', 'data' => []];
        }

        $json = $response->json() ?? [];

        return [
            'success' => $response->successful() && ($json['success'] ?? false),
            'status' => $response->status(),
            'message' => $json['message'] ?? $json['data']['message'] ?? ($response->successful() ? 'Berhasil.' : 'Terjadi kesalahan pada layanan WhatsApp.'),
            'data' => $json['data'] ?? [],
        ];
    }

    private function client(string $method, string $path, string $branchId, string $bodyHash): PendingRequest
    {
        $ts = (string) time();
        $nonce = Str::random(32);
        $signature = hash_hmac('sha256', implode("\n", [$ts, $nonce, $method, $path, $branchId, $bodyHash]), (string) config('services.wa.api_secret'));

        return Http::acceptJson()->withHeaders([
            'X-Bizbos-Timestamp' => $ts,
            'X-Bizbos-Nonce' => $nonce,
            'X-Bizbos-Branch' => $branchId,
            'X-Bizbos-Signature' => $signature,
        ]);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.wa.url'), '/').$path;
    }
}
