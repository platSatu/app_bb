<?php

namespace App\Services\Social\Connectors;

use App\Models\SocialAccount;
use App\Models\SocialApp;
use App\Models\User;
use App\Services\Social\Contracts\SocialConnector;
use App\Services\Social\SocialException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google OAuth (akses offline) untuk YouTube Data API v3: unggah video (Shorts) ke channel milik akun yang login.
 * Access token ±1 jam, diperpanjang otomatis dengan refresh token sebelum dipakai.
 */
class GoogleConnector implements SocialConnector
{
    // youtube.force-ssl: baca & balas komentar di Tools › Inbox.
    public const SCOPES = ['https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube.readonly', 'https://www.googleapis.com/auth/youtube.force-ssl'];

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function authorizeUrl(SocialApp $app, string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $app->client_id,
            'redirect_uri' => SocialApp::callbackUrl('google'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    public function connect(SocialApp $app, string $code, User $user): array
    {
        $token = $this->token([
            'code' => $code, 'client_id' => $app->client_id, 'client_secret' => $app->client_secret,
            'redirect_uri' => SocialApp::callbackUrl('google'), 'grant_type' => 'authorization_code',
        ]);

        if (! str_contains((string) ($token['scope'] ?? ''), 'youtube.upload')) {
            throw new SocialException('Izin unggah YouTube tidak diberikan. Sambungkan ulang dan centang semua izin.');
        }

        $channels = $this->channels((string) $token['access_token']);

        if ($channels === []) {
            throw new SocialException('Akun Google ini belum punya channel YouTube. Buat channel dulu di YouTube.');
        }

        return array_map(fn (array $channel) => SocialAccount::remember('youtube', (string) $channel['id'], [
            'name' => (string) ($channel['snippet']['title'] ?? $channel['id']),
            'username' => isset($channel['snippet']['customUrl']) ? ltrim((string) $channel['snippet']['customUrl'], '@') : null,
            'avatar_url' => $channel['snippet']['thumbnails']['default']['url'] ?? null,
            'access_token' => (string) $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
        ], $user), $channels);
    }

    public function refresh(SocialAccount $account, SocialApp $app): void
    {
        if (blank($account->refresh_token)) {
            throw new SocialException('Refresh token YouTube tidak ada. Sambungkan ulang akunnya.', true);
        }

        $token = $this->token([
            'client_id' => $app->client_id, 'client_secret' => $app->client_secret,
            'refresh_token' => (string) $account->refresh_token, 'grant_type' => 'refresh_token',
        ]);

        $account->forceFill([
            'access_token' => (string) $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
            'status' => SocialAccount::STATUS_CONNECTED,
            'status_reason' => null,
        ])->save();
    }

    /** @return list<array<string, mixed>> */
    private function channels(string $accessToken): array
    {
        try {
            $response = Http::timeout(30)->acceptJson()->withToken($accessToken)
                ->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet', 'mine' => 'true']);
        } catch (Throwable $e) {
            report($e);

            throw new SocialException('YouTube sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        if ($response->failed()) {
            throw new SocialException('YouTube: '.strip_tags((string) ($response->json('error.message') ?? 'gagal membaca channel (HTTP '.$response->status().').')));
        }

        return array_values(array_filter((array) $response->json('items'), fn ($c) => is_array($c) && ! empty($c['id'])));
    }

    private function token(array $form): array
    {
        try {
            $response = Http::timeout(30)->acceptJson()->asForm()->post(self::TOKEN_URL, $form);
        } catch (Throwable $e) {
            report($e);

            throw new SocialException('Google sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || empty($json['access_token'])) {
            $error = is_array($json) ? (string) ($json['error'] ?? '') : '';
            report(new \RuntimeException('Google token HTTP '.$response->status().': '.$error));

            // invalid_grant = izin dicabut / refresh token kedaluwarsa (mis. app masih "Testing": 7 hari).
            throw new SocialException($error === 'invalid_grant'
                ? 'Izin Google sudah tidak berlaku (dicabut atau kedaluwarsa). Sambungkan ulang akun YouTube.'
                : 'Google menolak otorisasi'.($error !== '' ? ': '.strip_tags($error) : '.'), $error === 'invalid_grant');
        }

        return $json;
    }
}
