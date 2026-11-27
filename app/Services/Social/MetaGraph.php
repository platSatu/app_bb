<?php

namespace App\Services\Social;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Klien kecil Graph API Meta (Facebook Page & Instagram). Token dikirim di body/header, bukan di URL log.
 * Kode error 190 / 102 / 463 / 467 = token tidak berlaku -> SocialException(expired: true).
 */
class MetaGraph
{
    public const HOST = 'https://graph.facebook.com';

    private const EXPIRED_CODES = [102, 190, 463, 467];

    public function __construct(private readonly string $version) {}

    public function get(string $path, array $query, string $token, int $timeout = 30): array
    {
        return $this->send('get', $path, $query, $token, $timeout);
    }

    public function post(string $path, array $data, string $token, int $timeout = 60): array
    {
        return $this->send('post', $path, $data, $token, $timeout);
    }

    public function url(string $path): string
    {
        return self::HOST.'/'.$this->version.'/'.ltrim($path, '/');
    }

    private function send(string $method, string $path, array $data, string $token, int $timeout): array
    {
        try {
            $request = Http::timeout($timeout)->connectTimeout(15)->acceptJson()->withToken($token);
            $response = $method === 'get' ? $request->get($this->url($path), $data) : $request->asForm()->post($this->url($path), $data);
        } catch (Throwable $e) {
            report($e);

            throw new SocialException('Meta sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        return self::json($response);
    }

    /** Balasan Graph API -> array, atau SocialException dengan pesan Meta yang sudah dibersihkan. */
    public static function json(Response $response): array
    {
        $json = $response->json();

        if ($response->successful() && is_array($json) && ! isset($json['error'])) {
            return $json;
        }

        $error = is_array($json['error'] ?? null) ? $json['error'] : [];
        $code = (int) ($error['code'] ?? 0);
        $message = (string) ($error['error_user_msg'] ?? $error['message'] ?? ('HTTP '.$response->status()));
        report(new \RuntimeException('Meta Graph '.$response->status().' code '.$code.': '.mb_substr($message, 0, 300)));

        throw new SocialException('Meta: '.strip_tags($message), in_array($code, self::EXPIRED_CODES, true));
    }
}
