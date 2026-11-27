<?php

namespace App\Services\Social\Connectors;

use App\Models\SocialAccount;
use App\Models\SocialApp;
use App\Models\User;
use App\Services\Social\Contracts\SocialConnector;
use App\Services\Social\SocialException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * TikTok Login Kit (web) + Content Posting API. Access token berlaku ±24 jam, refresh token ±365 hari;
 * diperpanjang otomatis oleh social:refresh-tokens. creator_info (pilihan privasi, batas durasi) disimpan di meta akun.
 */
class TiktokConnector implements SocialConnector
{
    public const API = 'https://open.tiktokapis.com/v2';

    public const SCOPES = ['user.info.basic', 'video.publish', 'video.upload'];

    public function authorizeUrl(SocialApp $app, string $state): string
    {
        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
            'client_key' => $app->client_id,
            'scope' => implode(',', self::SCOPES),
            'response_type' => 'code',
            'redirect_uri' => SocialApp::callbackUrl('tiktok'),
            'state' => $state,
        ]);
    }

    public function connect(SocialApp $app, string $code, User $user): array
    {
        $token = $this->token([
            'client_key' => $app->client_id, 'client_secret' => $app->client_secret, 'code' => $code,
            'grant_type' => 'authorization_code', 'redirect_uri' => SocialApp::callbackUrl('tiktok'),
        ]);

        $granted = explode(',', (string) ($token['scope'] ?? ''));

        if (! in_array('video.publish', $granted, true)) {
            throw new SocialException('Izin "video.publish" tidak diberikan. Sambungkan ulang dan setujui semua izin.');
        }

        $info = self::api('get', '/user/info/', (string) $token['access_token'], ['fields' => 'open_id,avatar_url,display_name'])['user'] ?? [];
        $creator = self::creatorInfo((string) $token['access_token']);
        $openId = (string) ($token['open_id'] ?? $info['open_id'] ?? '');

        if ($openId === '' || strlen($openId) > 100) {
            throw new SocialException('TikTok tidak mengirim ID akun. Coba sambungkan lagi.');
        }

        return [SocialAccount::remember('tiktok', $openId, [
            'name' => (string) (($info['display_name'] ?? '') ?: ($creator['creator_nickname'] ?? 'TikTok')),
            'username' => $creator['creator_username'] ?? null,
            'avatar_url' => $info['avatar_url'] ?? $creator['creator_avatar_url'] ?? null,
            'access_token' => (string) $token['access_token'],
            'refresh_token' => (string) ($token['refresh_token'] ?? ''),
            'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 86400)),
            'refresh_expires_at' => now()->addSeconds((int) ($token['refresh_expires_in'] ?? 31536000)),
            'meta' => self::creatorMeta($creator),
        ], $user)];
    }

    public function refresh(SocialAccount $account, SocialApp $app): void
    {
        if (blank($account->refresh_token) || ($account->refresh_expires_at && $account->refresh_expires_at->isPast())) {
            throw new SocialException('Refresh token TikTok sudah habis. Sambungkan ulang akunnya.', true);
        }

        $token = $this->token([
            'client_key' => $app->client_id, 'client_secret' => $app->client_secret,
            'grant_type' => 'refresh_token', 'refresh_token' => (string) $account->refresh_token,
        ]);

        $account->forceFill([
            'access_token' => (string) $token['access_token'],
            'refresh_token' => (string) (($token['refresh_token'] ?? '') ?: $account->refresh_token),
            'token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 86400)),
            'refresh_expires_at' => isset($token['refresh_expires_in']) ? now()->addSeconds((int) $token['refresh_expires_in']) : $account->refresh_expires_at,
            'status' => SocialAccount::STATUS_CONNECTED,
            'status_reason' => null,
        ])->save();

        // Pilihan privasi bisa berubah (mis. akun dijadikan privat): ikut diperbarui, tapi gagal di sini tidak membatalkan perpanjangan.
        try {
            $account->forceFill(['meta' => array_merge($account->meta ?? [], self::creatorMeta(self::creatorInfo((string) $account->access_token)))])->save();
        } catch (SocialException) {
        }
    }

    /** @return array<string, mixed> data creator_info (privacy_level_options, max_video_post_duration_sec, ...) */
    public static function creatorInfo(string $accessToken): array
    {
        return self::api('post', '/post/publish/creator_info/query/', $accessToken, []);
    }

    public static function creatorMeta(array $creator): array
    {
        return [
            'privacy_level_options' => array_values(array_filter((array) ($creator['privacy_level_options'] ?? []), 'is_string')),
            'max_video_post_duration_sec' => (int) ($creator['max_video_post_duration_sec'] ?? 0) ?: null,
            'comment_disabled' => (bool) ($creator['comment_disabled'] ?? false),
            'duet_disabled' => (bool) ($creator['duet_disabled'] ?? false),
            'stitch_disabled' => (bool) ($creator['stitch_disabled'] ?? false),
            'creator_info_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Panggil Content Posting / Display API. Balasan TikTok: {data: {...}, error: {code, message, log_id}}; code "ok" = berhasil.
     *
     * @return array<string, mixed> isi "data"
     */
    public static function api(string $method, string $path, string $accessToken, array $body, int $timeout = 30): array
    {
        try {
            $request = Http::timeout($timeout)->connectTimeout(15)->acceptJson()->withToken($accessToken);
            $response = $method === 'get'
                ? $request->get(self::API.$path, $body)
                : $request->withBody(json_encode((object) $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'application/json; charset=UTF-8')->post(self::API.$path);
        } catch (Throwable $e) {
            report($e);

            throw new SocialException('TikTok sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        return self::data($response);
    }

    private static function data(Response $response): array
    {
        $json = $response->json();
        $code = is_array($json) ? (string) ($json['error']['code'] ?? '') : '';

        if ($response->successful() && $code === 'ok') {
            return (array) ($json['data'] ?? []);
        }

        $message = is_array($json) ? (string) ($json['error']['message'] ?? '') : '';
        report(new \RuntimeException('TikTok HTTP '.$response->status().' '.$code.': '.mb_substr($message, 0, 300)));

        throw new SocialException('TikTok: '.match ($code) {
            'access_token_invalid', 'scope_not_authorized' => 'izin akun tidak berlaku lagi, sambungkan ulang.',
            'unaudited_client_can_only_post_to_private_accounts' => 'app TikTok belum lolos audit, jadi hanya bisa posting ke akun privat (Hanya saya).',
            'spam_risk_too_many_posts' => 'batas posting harian akun ini tercapai. Coba besok.',
            'spam_risk_user_banned_from_posting' => 'akun ini sedang diblokir TikTok untuk posting.',
            'privacy_level_option_mismatch' => 'pilihan privasi tidak sesuai akun. Perbarui akun di Tools › Akun Sosmed lalu jadwalkan ulang.',
            'url_ownership_unverified' => 'domain link media belum diverifikasi di TikTok for Developers (URL properties).',
            'rate_limit_exceeded' => 'terlalu banyak permintaan. Dicoba lagi nanti.',
            default => strip_tags($message !== '' ? $message : 'gagal (HTTP '.$response->status().').'),
        }, in_array($code, ['access_token_invalid', 'scope_not_authorized'], true));
    }

    /** Token endpoint memakai form-urlencoded dan bentuk balasan sendiri (tanpa bungkus data/error). */
    private function token(array $form): array
    {
        try {
            $response = Http::timeout(30)->acceptJson()->asForm()->post(self::API.'/oauth/token/', $form);
        } catch (Throwable $e) {
            report($e);

            throw new SocialException('TikTok sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || empty($json['access_token'])) {
            $error = is_array($json) ? (string) ($json['error_description'] ?? $json['error'] ?? '') : '';
            report(new \RuntimeException('TikTok token HTTP '.$response->status().': '.mb_substr($error, 0, 300)));

            throw new SocialException('TikTok menolak otorisasi'.($error !== '' ? ': '.strip_tags($error) : '.'), ($form['grant_type'] ?? '') === 'refresh_token');
        }

        return $json;
    }
}
