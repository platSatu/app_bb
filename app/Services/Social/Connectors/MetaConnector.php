<?php

namespace App\Services\Social\Connectors;

use App\Models\SocialAccount;
use App\Models\SocialApp;
use App\Models\User;
use App\Services\Social\Contracts\SocialConnector;
use App\Services\Social\MetaGraph;
use App\Services\Social\SocialException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Facebook Login untuk Facebook Page + Instagram professional (yang terhubung ke Page).
 * code -> user token -> long-lived user token -> Page access token per Page (tidak kedaluwarsa selama izin tidak dicabut).
 * Instagram memakai Page access token dari Page yang terhubung.
 */
class MetaConnector implements SocialConnector
{
    public const SCOPES = [
        'pages_show_list', 'pages_read_engagement', 'pages_manage_posts',
        'instagram_basic', 'instagram_content_publish', 'business_management',
    ];

    public function authorizeUrl(SocialApp $app, string $state): string
    {
        return 'https://www.facebook.com/'.$app->graphVersion().'/dialog/oauth?'.http_build_query([
            'client_id' => $app->client_id,
            'redirect_uri' => SocialApp::callbackUrl('meta'),
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', self::SCOPES),
        ]);
    }

    public function connect(SocialApp $app, string $code, User $user): array
    {
        $graph = new MetaGraph($app->graphVersion());

        $short = $this->token($graph, [
            'client_id' => $app->client_id, 'client_secret' => $app->client_secret,
            'redirect_uri' => SocialApp::callbackUrl('meta'), 'code' => $code,
        ]);
        $long = $this->token($graph, [
            'grant_type' => 'fb_exchange_token', 'client_id' => $app->client_id,
            'client_secret' => $app->client_secret, 'fb_exchange_token' => $short,
        ]);

        $pages = $graph->get('me/accounts', [
            'fields' => 'id,name,access_token,tasks,picture{url},instagram_business_account{id,username,name,profile_picture_url}',
            'limit' => 100,
        ], $long)['data'] ?? [];

        $accounts = [];

        foreach ((array) $pages as $page) {
            $tasks = (array) ($page['tasks'] ?? []);

            // Hanya Page yang boleh diposting oleh akun yang login.
            if (! is_array($page) || empty($page['id']) || empty($page['access_token']) || (! in_array('CREATE_CONTENT', $tasks, true) && ! in_array('MANAGE', $tasks, true))) {
                continue;
            }

            $accounts[] = SocialAccount::remember('facebook', (string) $page['id'], [
                'name' => (string) ($page['name'] ?? $page['id']),
                'avatar_url' => $page['picture']['data']['url'] ?? null,
                'access_token' => (string) $page['access_token'],
                'meta' => ['tasks' => array_values(array_filter($tasks, 'is_string'))],
            ], $user);

            $ig = $page['instagram_business_account'] ?? null;

            if (is_array($ig) && ! empty($ig['id'])) {
                $accounts[] = SocialAccount::remember('instagram', (string) $ig['id'], [
                    'name' => (string) (($ig['name'] ?? '') ?: ($ig['username'] ?? $ig['id'])),
                    'username' => $ig['username'] ?? null,
                    'avatar_url' => $ig['profile_picture_url'] ?? null,
                    'access_token' => (string) $page['access_token'],
                    'meta' => ['page_id' => (string) $page['id'], 'page_name' => (string) ($page['name'] ?? '')],
                ], $user);
            }
        }

        if ($accounts === []) {
            throw new SocialException('Tidak ada Halaman Facebook yang bisa diposting. Pastikan Anda admin Page-nya, lalu centang Page (dan akun Instagram-nya) di jendela izin Facebook.');
        }

        return $accounts;
    }

    /** Cek token masih berlaku & perbarui nama/foto. */
    public function refresh(SocialAccount $account, SocialApp $app): void
    {
        $graph = new MetaGraph($app->graphVersion());
        $fields = $account->platform === 'instagram' ? 'id,username,name,profile_picture_url' : 'id,name,picture{url}';
        $data = $graph->get($account->external_id, ['fields' => $fields], (string) $account->access_token);

        $account->forceFill([
            'name' => mb_substr(strip_tags((string) (($data['name'] ?? '') ?: ($data['username'] ?? $account->name))), 0, 200),
            'username' => isset($data['username']) ? mb_substr((string) $data['username'], 0, 200) : $account->username,
            'avatar_url' => ($url = $data['profile_picture_url'] ?? $data['picture']['data']['url'] ?? null) && str_starts_with((string) $url, 'https://') ? $url : $account->avatar_url,
            'status' => SocialAccount::STATUS_CONNECTED,
            'status_reason' => null,
        ])->save();
    }

    private function token(MetaGraph $graph, array $query): string
    {
        try {
            $response = Http::timeout(30)->acceptJson()->get($graph->url('oauth/access_token'), $query);
        } catch (Throwable $e) {
            report($e);

            throw new SocialException('Meta sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        $token = MetaGraph::json($response)['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new SocialException('Meta tidak mengirim access token.');
        }

        return $token;
    }
}
