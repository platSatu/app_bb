<?php

namespace App\Http\Controllers\Superadmin\Tools;

use App\Http\Controllers\Controller;
use App\Models\ContentPublication;
use App\Models\SocialAccount;
use App\Models\SocialApp;
use App\Services\Social\Connectors\GoogleConnector;
use App\Services\Social\Connectors\MetaConnector;
use App\Services\Social\Connectors\TiktokConnector;
use App\Services\Social\SocialException;
use App\Services\Social\SocialManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Superadmin › Tools › Akun Sosmed: kredensial app developer (Meta, TikTok, Google) dan akun yang tersambung lewat OAuth.
 * State OAuth disimpan di session (pencegah CSRF) dan hanya berlaku 30 menit. Token tidak pernah ditampilkan.
 */
class SocialAccountController extends Controller
{
    private const STATE_TTL_MINUTES = 30;

    public function __construct(private readonly SocialManager $social) {}

    public function index(): View
    {
        return view('superadmin.tools.social', [
            'apps' => SocialApp::all()->keyBy('platform'),
            'accounts' => SocialAccount::withCount(['publications as active_count' => fn ($q) => $q->whereIn('status', ContentPublication::ACTIVE)])
                ->orderBy('platform')->orderBy('name')->get()->groupBy('platform'),
            'scopes' => ['meta' => MetaConnector::SCOPES, 'tiktok' => TiktokConnector::SCOPES, 'google' => GoogleConnector::SCOPES],
        ]);
    }

    public function saveApp(Request $request, string $provider): RedirectResponse
    {
        abort_unless(isset(SocialApp::PROVIDERS[$provider]), 404);
        $app = SocialApp::for($provider) ?? new SocialApp(['platform' => $provider]);

        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:200', 'regex:/^[A-Za-z0-9._\-]+$/'],
            // Saat ubah, kosong = rahasia lama tetap dipakai.
            'client_secret' => [$app->exists ? 'nullable' : 'required', 'string', 'max:255'],
            'graph_version' => [$provider === 'meta' ? 'nullable' : 'prohibited', 'regex:/^v\d{2}\.\d$/'],
            'config_id' => [$provider === 'meta' ? 'nullable' : 'prohibited', 'regex:/^\d{5,30}$/'],
        ], [], [
            'client_id' => SocialApp::PROVIDERS[$provider][1],
            'client_secret' => SocialApp::PROVIDERS[$provider][2],
            'config_id' => 'Configuration ID',
        ]);

        $app->fill(['client_id' => $data['client_id'], 'updated_by' => $request->user()->getKey()]);

        if (filled($data['client_secret'] ?? null)) {
            $app->client_secret = $data['client_secret'];
        }

        if ($provider === 'meta') {
            $app->meta = array_merge($app->meta ?? [], [
                'graph_version' => ($data['graph_version'] ?? null) ?: SocialApp::DEFAULT_GRAPH_VERSION,
                'config_id' => ($data['config_id'] ?? null) ?: null,
            ]);
        }

        $app->save();

        return back()->with('success', 'Kredensial '.SocialApp::label($provider).' disimpan.');
    }

    public function connect(Request $request, string $provider): RedirectResponse
    {
        abort_unless(isset(SocialApp::PROVIDERS[$provider]), 404);

        if (! $app = SocialApp::for($provider)) {
            return back()->with('error', 'Isi dulu '.SocialApp::PROVIDERS[$provider][1].' & '.SocialApp::PROVIDERS[$provider][2].' '.SocialApp::label($provider).'.');
        }

        $state = Str::random(40);
        $request->session()->put("social.oauth.{$state}", [
            'provider' => $provider,
            'user_id' => $request->user()->getKey(),
            'expires' => now()->addMinutes(self::STATE_TTL_MINUTES)->timestamp,
        ]);

        return redirect()->away($this->social->connector($provider)->authorizeUrl($app, $state));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(isset(SocialApp::PROVIDERS[$provider]), 404);
        $back = redirect()->route('superadmin.tools.social.index');
        $state = (string) $request->query('state');
        $pending = $state !== '' && strlen($state) === 40 ? $request->session()->pull("social.oauth.{$state}") : null;

        if (! $pending || ($pending['provider'] ?? null) !== $provider || ($pending['user_id'] ?? null) !== $request->user()->getKey()
            || ($pending['expires'] ?? 0) < now()->timestamp) {
            return $back->with('error', 'Sesi otorisasi tidak dikenali atau sudah kedaluwarsa. Ulangi dari tombol Sambungkan (buka bizbos dari domain yang sama dengan Callback URL).');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '' || strlen($code) > 2048) {
            return $back->with('error', 'Otorisasi '.SocialApp::label($provider).' dibatalkan atau ditolak.');
        }

        if (! $app = SocialApp::for($provider)) {
            return $back->with('error', 'Kredensial app sudah dihapus. Isi lagi lalu sambungkan ulang.');
        }

        try {
            $accounts = $this->social->connector($provider)->connect($app, $code, $request->user());
        } catch (SocialException $e) {
            return $back->with('error', 'Gagal menyambungkan: '.$e->getMessage());
        }

        return $back->with('success', 'Tersambung: '.collect($accounts)->map(fn ($a) => $a->platformLabel().' '.$a->displayName())->implode(', ').'.');
    }

    /** Cek token / perpanjang / perbarui data akun (TikTok: pilihan privasi terbaru). */
    public function refresh(SocialAccount $account): RedirectResponse
    {
        try {
            $this->social->refresh($account);
        } catch (SocialException $e) {
            return back()->with('error', $account->displayName().': '.$e->getMessage());
        }

        return back()->with('success', $account->platformLabel().' '.$account->displayName().' diperbarui.');
    }

    public function update(Request $request, SocialAccount $account): RedirectResponse
    {
        $account->forceFill(['is_default' => $request->boolean('is_default')])->save();

        return back()->with('success', $account->displayName().($account->is_default ? ' jadi' : ' tidak lagi jadi').' tujuan bawaan.');
    }

    public function destroy(SocialAccount $account): RedirectResponse
    {
        if ($account->publications()->whereIn('status', ContentPublication::ACTIVE)->exists()) {
            return back()->with('error', 'Akun ini masih punya kiriman terjadwal. Batalkan jadwalnya dulu.');
        }

        $name = $account->platformLabel().' '.$account->displayName();
        $account->delete();

        return back()->with('success', "{$name} diputus dari bizbos. Untuk mencabut izin sepenuhnya, hapus juga aksesnya di pengaturan akun platform tersebut.");
    }
}
