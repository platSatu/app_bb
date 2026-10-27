<?php

namespace App\Http\Controllers\Superadmin\Activity;

use App\Http\Controllers\Controller;
use App\Models\FrontendVisitorLog;
use App\Models\UserActivityLog;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Settings > Aktivitas & Pengunjung: tab Pengunjung Website (bizbos.id) & tab Aktivitas Pengguna (app.bizbos). */
class ActivityController extends Controller
{
    public function visitors(Request $request): View
    {
        $from = ($request->date('date_from') ?? now()->subDays(29))->startOfDay();
        $until = ($request->date('date_to') ?? now())->endOfDay();
        $visits = fn () => FrontendVisitorLog::query()->whereBetween('visited_at', [$from, $until])
            ->where(fn ($q) => $q->whereNull('device_type')->orWhere('device_type', '!=', 'bot'));

        $total = $visits()->toBase()->selectRaw('COUNT(DISTINCT visitor_id) AS visitors, COUNT(*) AS views')->first();
        $daily = $visits()->toBase()->selectRaw('DATE(visited_at) AS day, COUNT(DISTINCT visitor_id) AS visitors, COUNT(*) AS views')
            ->groupBy('day')->get()->keyBy('day');

        return view('superadmin.activity.visitors', [
            'from' => $from,
            'until' => $until,
            'total' => ['visitors' => (int) $total->visitors, 'views' => (int) $total->views],
            'today' => FrontendVisitorLog::query()->where('visited_at', '>=', today())->distinct()->count('visitor_id'),
            'bots' => FrontendVisitorLog::query()->whereBetween('visited_at', [$from, $until])->where('device_type', 'bot')->count(),
            'daily' => collect(CarbonPeriod::create($from, $until->copy()->startOfDay()))->map(fn ($day) => [
                'label' => $day->translatedFormat('d M'),
                'visitors' => (int) ($daily[$day->toDateString()]->visitors ?? 0),
                'views' => (int) ($daily[$day->toDateString()]->views ?? 0),
            ]),
            'pages' => $visits()->toBase()->selectRaw('path, COUNT(*) AS views, COUNT(DISTINCT visitor_id) AS visitors')
                ->groupBy('path')->orderByDesc('views')->limit(10)->get(),
            'devices' => $visits()->toBase()->selectRaw('device_type, COUNT(DISTINCT visitor_id) AS visitors')
                ->groupBy('device_type')->orderByDesc('visitors')->pluck('visitors', 'device_type'),
            'sources' => $this->sources($visits()->toBase()->selectRaw('referrer, COUNT(DISTINCT visitor_id) AS visitors')
                ->groupBy('referrer')->orderByDesc('visitors')->limit(500)->pluck('visitors', 'referrer')),
        ]);
    }

    public function users(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $logs = UserActivityLog::query()
            ->with('user:id,name,email')
            ->ofType($request->query('type'))
            ->when($request->query('user'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($search !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u->search($search)))
            ->when($request->date('date_from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('date_to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $lastWeek = UserActivityLog::query()->where('created_at', '>=', now()->subDays(7));

        return view('superadmin.activity.users', [
            'logs' => $logs,
            'activeToday' => UserActivityLog::query()->where('created_at', '>=', today())->distinct()->count('user_id'),
            'activeWeek' => (clone $lastWeek)->distinct()->count('user_id'),
            'actionsToday' => UserActivityLog::query()->where('created_at', '>=', today())->count(),
            'topMenus' => (clone $lastWeek)->where('method', 'GET')->whereNotNull('route_name')->toBase()
                ->selectRaw('route_name, COUNT(*) AS total')->groupBy('route_name')->orderByDesc('total')->limit(30)->get()
                ->groupBy(fn ($row) => UserActivityLog::labelFor($row->route_name))
                ->map(fn (Collection $rows) => $rows->sum('total'))->sortDesc()->take(8),
        ]);
    }

    /**
     * Asal pengunjung dari referrer: Langsung, Google, Instagram, dst. Perpindahan antar halaman bizbos sendiri tidak dihitung.
     *
     * @param  Collection<string, int>  $referrers
     */
    private function sources(Collection $referrers): Collection
    {
        $known = ['google' => 'Google', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'fb.' => 'Facebook', 'tiktok' => 'TikTok',
            'whatsapp' => 'WhatsApp', 'wa.me' => 'WhatsApp', 'youtube' => 'YouTube', 'bing' => 'Bing', 'linkedin' => 'LinkedIn', 't.co' => 'X / Twitter'];

        return $referrers->reduce(function (Collection $carry, int $visitors, ?string $referrer) use ($known) {
            $host = strtolower((string) parse_url((string) $referrer, PHP_URL_HOST));

            if ($host !== '' && str_contains($host, 'bizbos')) {
                return $carry;
            }

            $label = $host === '' ? 'Langsung / tidak diketahui' : (collect($known)->first(fn ($name, $needle) => str_contains($host, $needle)) ?? $host);

            return $carry->put($label, $carry->get($label, 0) + $visitors);
        }, collect())->sortDesc()->take(8);
    }
}
