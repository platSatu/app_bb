<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\SchedulePortalLink;
use App\Models\SchedulePortalRequest;
use App\Models\UserBranch;
use App\Services\Schedule\SchedulePortalService;
use App\Services\Subscription\AppAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Owner: link pendaftaran portal per branch & persetujuan pendaftar (murid/orang tua dan pengajar). */
class PortalAdminController extends Controller
{
    public function __construct(private readonly SchedulePortalService $portal)
    {
    }

    public function requests(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $branches = UserBranch::where('user_id', $user->getKey())->whereIn('id', AppAccess::activeBranches('schedules'))->orderBy('name')->get(['id', 'name']);

        if ($branches->isEmpty()) {
            return redirect()->route('settings.branches.index')->with('info', 'Buat branch dulu ya, pendaftar portal tercatat per branch.');
        }

        $status = in_array($request->query('status'), array_keys(SchedulePortalRequest::STATUS_LABELS), true) ? $request->query('status') : SchedulePortalRequest::STATUS_PENDING;
        $base = SchedulePortalRequest::accessibleBy($user)
            ->when($request->query('branch'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type));

        $items = (clone $base)->with('branch:id,name')->where('status', $status)
            ->when(trim((string) $request->query('search')), fn ($q, $search) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")->orWhere('guardian_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        if ($status === SchedulePortalRequest::STATUS_PENDING) {
            $items->getCollection()->each(fn ($item) => $item->setRelation('matches', $this->portal->matches($item)));
        }

        return view('schedule.portal.requests', [
            'items' => $items,
            'status' => $status,
            'branches' => $branches,
            'counts' => (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function approve(Request $request, string $portalRequest): RedirectResponse
    {
        $item = $this->find($request, $portalRequest);
        $memberId = $request->validate(['member_id' => ['required', 'string', 'max:36']], ['member_id.required' => 'Pilih mau dihubungkan ke data yang mana.'])['member_id'];

        try {
            $member = $this->portal->approve($item, $memberId === 'new' ? null : $memberId, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $next = $item->isStudent() ? 'Masukkan ke Jadwal Rutin supaya jadwalnya muncul di portal.' : 'Atur grade yang diajar di menu Pengajar.';

        return back()->with('success', "{$member->name} sudah disetujui dan bisa masuk ke portal. {$next}");
    }

    public function reject(Request $request, string $portalRequest): RedirectResponse
    {
        $item = $this->find($request, $portalRequest);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null;

        try {
            $this->portal->reject($item, $reason, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Pendaftaran {$item->name} ditolak.");
    }

    public function links(Request $request): View|RedirectResponse
    {
        $branches = UserBranch::where('user_id', $request->user()->getKey())->whereIn('id', AppAccess::activeBranches('schedules'))->orderBy('name')->get(['id', 'name', 'status']);

        if ($branches->isEmpty()) {
            return redirect()->route('settings.branches.index')->with('info', 'Buat branch dulu ya, link pendaftaran dibuat per branch.');
        }

        return view('schedule.portal.links', [
            'branches' => $branches,
            'links' => $this->portal->linksFor($branches),
            'pending' => SchedulePortalRequest::accessibleBy($request->user())->where('status', SchedulePortalRequest::STATUS_PENDING)->count(),
        ]);
    }

    public function toggle(Request $request, string $link): RedirectResponse
    {
        $model = $this->findLink($request, $link);
        $model->forceFill(['is_active' => ! $model->is_active])->save();

        return back()->with('success', "Link daftar {$model->typeLabel()} ".($model->is_active ? 'dibuka kembali.' : 'ditutup. Orang yang membukanya akan melihat pesan bahwa pendaftaran sedang ditutup.'));
    }

    public function regenerate(Request $request, string $link): RedirectResponse
    {
        $model = $this->findLink($request, $link);
        $model->forceFill(['token' => SchedulePortalLink::newToken()])->save();

        return back()->with('success', "Link daftar {$model->typeLabel()} sudah diganti. Link lama tidak bisa dipakai lagi.");
    }

    private function find(Request $request, string $id): SchedulePortalRequest
    {
        return SchedulePortalRequest::accessibleBy($request->user())->findOrFail($id);
    }

    private function findLink(Request $request, string $id): SchedulePortalLink
    {
        return SchedulePortalLink::accessibleBy($request->user())->findOrFail($id);
    }
}
