<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Kredensial app developer untuk Tools › Akun Sosmed, diisi superadmin. Satu baris per penyedia:
 *  - meta:   Meta App (Facebook Login) untuk Facebook Page + Instagram professional.
 *  - tiktok: TikTok for Developers (Login Kit + Content Posting API).
 *  - google: Google Cloud OAuth client (YouTube Data API v3).
 */
class SocialApp extends Model
{
    use HasUuids;

    /** penyedia => [label, label ID, label rahasia, platform akun yang dihasilkan] */
    public const PROVIDERS = [
        'meta' => ['Meta (Instagram & Facebook)', 'App ID', 'App Secret', ['instagram', 'facebook']],
        'tiktok' => ['TikTok', 'Client Key', 'Client Secret', ['tiktok']],
        'google' => ['Google (YouTube)', 'Client ID', 'Client Secret', ['youtube']],
    ];

    public const DEFAULT_GRAPH_VERSION = 'v26.0';

    protected $fillable = ['platform', 'client_id', 'client_secret', 'meta', 'updated_by'];

    protected $hidden = ['client_secret'];

    protected function casts(): array
    {
        return ['client_secret' => 'encrypted', 'meta' => 'array'];
    }

    public static function for(string $provider): ?self
    {
        return static::where('platform', $provider)->first();
    }

    /** Penyedia untuk satu platform akun (instagram -> meta). */
    public static function providerOf(string $platform): ?string
    {
        foreach (self::PROVIDERS as $provider => $row) {
            if (in_array($platform, $row[3], true)) {
                return $provider;
            }
        }

        return null;
    }

    public static function label(string $provider): string
    {
        return self::PROVIDERS[$provider][0] ?? ucfirst($provider);
    }

    /** Alamat callback OAuth yang didaftarkan di console developer masing-masing. */
    public static function callbackUrl(string $provider): string
    {
        return route('superadmin.tools.social.callback', $provider);
    }

    public function graphVersion(): string
    {
        $version = (string) (($this->meta ?? [])['graph_version'] ?? '');

        return preg_match('/^v\d{2}\.\d$/', $version) ? $version : self::DEFAULT_GRAPH_VERSION;
    }

    /** Rahasia disamarkan untuk tampilan: abcd••••wxyz. */
    public function maskedSecret(): string
    {
        $secret = (string) $this->client_secret;

        return strlen($secret) <= 8 ? str_repeat('•', strlen($secret)) : substr($secret, 0, 4).str_repeat('•', 8).substr($secret, -4);
    }
}
