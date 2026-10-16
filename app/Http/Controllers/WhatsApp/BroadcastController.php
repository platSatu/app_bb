<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\ContactGroup;
use App\Models\Customer;
use App\Models\User;
use App\Models\WaBroadcast;
use App\Models\WaDevice;
use App\Services\WhatsApp\BroadcastService;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Settings > Message: sekali kirim & blast, langsung / terjadwal / berulang. */
class BroadcastController extends Controller
{
    use InWhatsAppArea;

    public function __construct(private BroadcastService $broadcasts) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.messages.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'broadcasts' => WaBroadcast::where('branch_id', $branch->id)
                ->with(['device:id,name', 'latestCampaign'])
                ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
                ->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.messages.create', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'type' => $request->query('type') === 'blast' ? 'blast' : 'once',
            'devices' => WaDevice::where('branch_id', $branch->id)->orderBy('name')->get(['id', 'name', 'status', 'phone']),
            'groups' => ContactGroup::where('branch_id', $branch->id)->withCount(['customers' => fn ($q) => $q->whereNotNull('phone')])->orderBy('name')->get(),
            'customers' => Customer::where('branch_id', $branch->id)->whereNotNull('phone')->orderBy('name')->limit(1000)->get(['id', 'name', 'phone']),
        ]);
    }

    public function store(Request $request, WaGateway $wa): RedirectResponse
    {
        $device = $this->device($request, (string) $request->input('device_id'));
        $request->merge(['phones' => collect(preg_split('/[\s,;]+/', (string) $request->input('phones'), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($p) => User::normalizePhone($p))->filter(fn ($p) => strlen($p) >= 10)->unique()->values()->all()]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(WaBroadcast::TYPES))],
            'body' => ['nullable', 'required_without:file', 'string', 'max:4000'],
            'file' => ['nullable', 'file', 'max:65536'],
            'group_ids' => ['nullable', 'required_if:type,blast', 'array'],
            'group_ids.*' => [Rule::exists('contact_groups', 'id')->where('branch_id', $device->branch_id)],
            'customer_ids' => ['nullable', 'array', 'max:500'],
            'customer_ids.*' => [Rule::exists('customers', 'id')->where('branch_id', $device->branch_id)],
            'phones' => ['array', 'max:500'],
            'schedule' => ['required', Rule::in(array_keys(WaBroadcast::SCHEDULES))],
            'send_at' => ['nullable', 'required_unless:schedule,now', 'date', 'after:now'],
        ], [
            'body.required_without' => 'Isi pesan atau lampirkan file.',
            'group_ids.required_if' => 'Pilih minimal satu Group untuk blast.',
            'send_at.required_unless' => 'Tentukan waktu kirim.',
            'send_at.after' => 'Waktu kirim harus setelah sekarang.',
            'file.max' => 'Ukuran file maksimal 64 MB.',
        ], ['name' => 'nama pesan', 'send_at' => 'waktu kirim']);

        if ($data['type'] === 'once' && empty($data['customer_ids']) && empty($data['phones'])) {
            return back()->withInput()->withErrors(['customer_ids' => 'Pilih kontak atau tulis nomor tujuan.']);
        }

        $upload = null;
        if ($request->hasFile('file')) {
            $result = $wa->upload($device->branch_id, $request->file('file'));
            if (! $result['success']) {
                return back()->withInput()->withErrors(['file' => $result['message']]);
            }
            $upload = $result['data'];
        }

        $broadcast = new WaBroadcast(['name' => $data['name'], 'type' => $data['type'], 'body' => $data['body'] ?? null, 'schedule' => $data['schedule']]);
        $broadcast->forceFill([
            'branch_id' => $device->branch_id,
            'device_id' => $device->id,
            'upload_id' => $upload['upload_id'] ?? null,
            'upload_kind' => $upload['kind'] ?? null,
            'upload_name' => $upload['name'] ?? null,
            'target' => $data['type'] === 'blast'
                ? ['group_ids' => array_values($data['group_ids'] ?? [])]
                : ['customer_ids' => array_values($data['customer_ids'] ?? []), 'phones' => $data['phones']],
            'next_run_at' => $data['schedule'] === 'now' ? now() : $data['send_at'],
            'status' => 'active',
            'created_by' => $request->user()->id,
        ])->save();

        if ($data['schedule'] === 'now') {
            $campaign = $this->broadcasts->run($broadcast->id);
            $message = $campaign ? "Pesan masuk antrean untuk {$campaign->total} penerima." : ($broadcast->fresh()->last_error ?? 'Pesan disimpan.');
        } else {
            $message = 'Pesan dijadwalkan '.$broadcast->next_run_at->translatedFormat('d M Y H:i').'.';
        }

        return redirect()->route('whatsapp.messages.show', $broadcast)->with('success', $message);
    }

    public function show(Request $request, string $broadcast): View
    {
        $model = $this->broadcast($request, $broadcast)->load('device:id,name,status');

        return view('whatsapp.messages.show', [
            'broadcast' => $model,
            'campaigns' => $model->campaigns()->latest()->limit(20)->get(),
            'recipients' => $model->status === 'active' ? $this->broadcasts->recipients($model)->count() : null,
            'groups' => ContactGroup::whereIn('id', $model->target['group_ids'] ?? [])->pluck('name'),
            'failures' => DB::table('wa_outbox')->whereIn('campaign_id', $model->campaigns()->select('id'))
                ->whereIn('status', ['failed', 'skipped'])->latest('updated_at')->limit(20)->get(['chat_jid', 'status', 'error', 'updated_at']),
        ]);
    }

    /** Progres untuk dimuat ulang otomatis selama masih mengirim. */
    public function progress(Request $request, string $broadcast): JsonResponse
    {
        $model = $this->broadcast($request, $broadcast);

        return response()->json(['campaigns' => $model->campaigns()->latest()->limit(20)->get(['id', 'status', 'total', 'sent', 'failed'])]);
    }

    public function pause(Request $request, string $broadcast): RedirectResponse
    {
        $this->broadcasts->pause($this->broadcast($request, $broadcast, ['active']));

        return back()->with('success', 'Pesan dijeda. Pengiriman yang sedang berjalan ikut berhenti sementara.');
    }

    public function resume(Request $request, string $broadcast): RedirectResponse
    {
        $this->broadcasts->resume($this->broadcast($request, $broadcast, ['paused']));

        return back()->with('success', 'Pesan dilanjutkan.');
    }

    public function cancel(Request $request, string $broadcast): RedirectResponse
    {
        $this->broadcasts->cancel($this->broadcast($request, $broadcast, ['active', 'paused']));

        return back()->with('success', 'Pesan dibatalkan. Antrean yang belum terkirim tidak akan dikirim.');
    }

    public function destroy(Request $request, string $broadcast): RedirectResponse
    {
        $model = $this->broadcast($request, $broadcast);
        if ($model->campaigns()->whereIn('status', ['draft', 'running', 'paused'])->exists()) {
            return back()->with('error', 'Pesan masih dikirim. Batalkan dulu sebelum dihapus.');
        }
        $branchId = $model->branch_id;
        $model->delete();

        return redirect()->route('whatsapp.messages.index', ['branch' => $branchId])->with('success', 'Pesan dan riwayat kirimnya dihapus.');
    }

    private function broadcast(Request $request, string $id, ?array $statuses = null): WaBroadcast
    {
        return WaBroadcast::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->findOrFail($id);
    }
}
