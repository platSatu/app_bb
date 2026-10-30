<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\ContactGroup;
use App\Models\Customer;
use App\Models\User;
use App\Models\WaBroadcast;
use App\Models\WaBroadcastStep;
use App\Models\WaDevice;
use App\Models\WaTemplate;
use App\Services\WhatsApp\BroadcastService;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Settings > Message: Sekali Kirim, Berulang, dan Bertahap per Kontak (seperti Pesan Terjadwal di Konexa). */
class BroadcastController extends Controller
{
    use InWhatsAppArea;

    public function __construct(private BroadcastService $broadcasts, private WaGateway $wa) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        $broadcasts = WaBroadcast::where('branch_id', $branch->id)
            ->with('device:id,name')
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->latest()->paginate(20)->withQueryString();

        return view('whatsapp.messages.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'broadcasts' => $broadcasts,
            'stats' => $this->stats($broadcasts->pluck('id')->all()),
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
            'type' => array_key_exists($request->query('type'), WaBroadcast::TYPES) ? $request->query('type') : 'once',
            'devices' => WaDevice::where('branch_id', $branch->id)->orderBy('name')->get(['id', 'name', 'status', 'phone']),
            'templates' => WaTemplate::where('branch_id', $branch->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'header', 'body', 'footer', 'link', 'file_name']),
            'groups' => ContactGroup::where('branch_id', $branch->id)->withCount(['customers' => fn ($q) => $q->whereNotNull('phone')])->orderBy('name')->get(),
            // Penerima boleh dari customer aplikasi mana pun di branch ini.
            'customers' => Customer::where('branch_id', $branch->id)->whereNotNull('phone')->orderBy('app_key')->orderBy('name')->limit(1000)->get(['id', 'app_key', 'name', 'phone']),
            'appCounts' => Customer::where('branch_id', $branch->id)->whereNotNull('phone')->selectRaw('app_key, count(*) as total')->groupBy('app_key')->pluck('total', 'app_key'),
        ]);
    }

    /** Daftar Grup WhatsApp yang diikuti device, untuk tab "Grup WhatsApp" di form. */
    public function waGroups(Request $request, string $device): JsonResponse
    {
        $model = $this->device($request, $device);
        if (! $model->isConnected()) {
            return response()->json(['success' => false, 'message' => 'Device belum terhubung ke WhatsApp.', 'groups' => []]);
        }
        $result = $this->cachedGroups($model, $request->boolean('refresh'));

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
            'groups' => collect($result['data']['groups'] ?? [])->map(fn ($g) => ['jid' => $g['jid'], 'name' => ($g['name'] ?? '') ?: '(tanpa nama)', 'size' => $g['participants'] ?? null, 'announce' => (bool) ($g['announce'] ?? false), 'is_admin' => (bool) ($g['is_admin'] ?? false)])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $device = $this->device($request, (string) $request->input('device_id'));
        $data = $this->validated($request, $device);

        $broadcast = DB::transaction(function () use ($request, $device, $data) {
            $broadcast = new WaBroadcast(['name' => $data['name'], 'type' => $data['type'], 'schedule' => $data['schedule']]);
            [$startsAt, $endsAt, $firstRun] = $this->timing($data);

            $fill = [
                'branch_id' => $device->branch_id,
                'device_id' => $device->id,
                'target' => $this->target($device, $data),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'next_run_at' => $firstRun,
                'status' => 'active',
                'created_by' => $request->user()->id,
            ];
            if ($data['type'] !== 'drip') {
                $fill += $this->content($device, $data['use_template'] ?? false, $data['wa_template_id'] ?? null, $data['body'] ?? null, $request->file('file'), 'file');
            }
            $broadcast->forceFill($fill)->save();

            if ($data['type'] === 'drip') {
                foreach (array_values($data['steps']) as $i => $step) {
                    $row = new WaBroadcastStep(['position' => $i + 1, 'delay_days' => (int) $step['delay_days'], 'body' => null]);
                    $field = "steps.{$step['_index']}.file"; // indeks asli di form (urutan disusun ulang per hari kirim)
                    $row->forceFill(['broadcast_id' => $broadcast->id] + $this->content($device, (bool) ($step['use_template'] ?? false), $step['wa_template_id'] ?? null, $step['body'] ?? null, $request->file($field), $field))->save();
                }
                $broadcast->forceFill(['next_run_at' => $broadcast->load('steps')->stepDueAt(0)])->save();
            }

            return $broadcast;
        });

        if ($broadcast->schedule === 'now') {
            $campaign = $this->broadcasts->run($broadcast->id);
            $message = $campaign ? "Pesan masuk antrean untuk {$campaign->total} tujuan." : ($broadcast->fresh()->last_error ?? 'Pesan disimpan.');
        } else {
            $message = 'Pesan dijadwalkan, kirim pertama '.$broadcast->next_run_at->translatedFormat('d M Y H:i').'.';
        }

        return redirect()->route('whatsapp.messages.show', $broadcast)->with('success', $message);
    }

    public function show(Request $request, string $broadcast): View
    {
        $model = $this->broadcast($request, $broadcast)->load(['device:id,name,status', 'steps', 'template:id,name']);
        $target = $model->target ?? [];

        return view('whatsapp.messages.show', [
            'broadcast' => $model,
            'stats' => $this->stats([$model->id])[$model->id] ?? null,
            'campaigns' => $model->campaigns()->latest()->limit(20)->get(),
            'recipients' => $model->status === 'active' ? $this->broadcasts->recipients($model)->count() : null,
            'targets' => collect()
                ->concat(ContactGroup::whereIn('id', $target['group_ids'] ?? [])->pluck('name')->map(fn ($n) => 'Group kontak: '.$n))
                ->concat(collect($target['apps'] ?? [])->map(fn ($app) => 'Semua customer '.(Customer::APPS[$app] ?? $app)))
                ->concat(collect($target['wa_groups'] ?? [])->map(fn ($g) => 'Grup WhatsApp: '.($g['name'] ?? $g['jid'])))
                ->when(count($target['customer_ids'] ?? []), fn ($c) => $c->push(count($target['customer_ids']).' kontak'))
                ->when(count($target['phones'] ?? []), fn ($c) => $c->push(count($target['phones']).' nomor')),
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

    /**
     * Delivered / Read / Failed per Message, dihitung dari semua putaran kirimnya.
     * Delivered termasuk yang sudah dibaca. Grup WhatsApp dihitung satu pesan.
     *
     * @return array<string, object{total:int, sent:int, delivered:int, read:int, failed:int}>
     */
    private function stats(array $broadcastIds): array
    {
        if (! $broadcastIds) {
            return [];
        }

        return DB::table('wa_outbox as o')
            ->join('wa_campaigns as c', 'c.id', '=', 'o.campaign_id')
            ->leftJoin('wa_messages as m', 'm.id', '=', 'o.message_id')
            ->whereIn('c.broadcast_id', $broadcastIds)
            ->groupBy('c.broadcast_id')
            ->selectRaw("c.broadcast_id,
                count(*) as total,
                sum(case when o.status = 'sent' then 1 else 0 end) as sent,
                sum(case when m.status in ('delivered', 'read', 'played') then 1 else 0 end) as delivered,
                sum(case when m.status in ('read', 'played') then 1 else 0 end) as `read`,
                sum(case when o.status in ('failed', 'skipped') then 1 else 0 end) as failed")
            ->get()->keyBy('broadcast_id')->all();
    }

    private function validated(Request $request, WaDevice $device): array
    {
        $branchId = $device->branch_id;
        $request->merge([
            'phones' => collect(preg_split('/[\s,;]+/', (string) $request->input('phones'), -1, PREG_SPLIT_NO_EMPTY))
                ->map(fn ($p) => User::normalizePhone($p))->filter(fn ($p) => strlen($p) >= 10)->unique()->values()->all(),
            'use_template' => $request->boolean('use_template'),
        ]);
        $type = (string) $request->input('type');
        $template = Rule::exists('wa_templates', 'id')->where('branch_id', $branchId)->where('is_active', true);

        $rules = [
            'type' => ['required', Rule::in(array_keys(WaBroadcast::TYPES))],
            'name' => ['required', 'string', 'max:150'],
            'schedule' => ['required', Rule::in(WaBroadcast::TYPE_SCHEDULES[$type] ?? [])],
            'customer_ids' => ['nullable', 'array', 'max:1000'],
            'customer_ids.*' => [Rule::exists('customers', 'id')->where('branch_id', $branchId)],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => [Rule::exists('contact_groups', 'id')->where('branch_id', $branchId)],
            'apps' => ['nullable', 'array'],
            'apps.*' => [Rule::in(array_keys(Customer::APPS))],
            'phones' => ['array', 'max:1000'],
            'wa_groups' => ['nullable', 'array', 'max:100'],
            'wa_groups.*' => ['string', 'regex:/^[0-9-]{10,40}@g\.us$/'],
        ];

        if ($type === 'once') {
            $rules += [
                'send_at' => ['nullable', 'required_if:schedule,at', 'date', 'after:now'],
            ];
        } else {
            $rules += [
                'start_date' => ['required', 'date', 'after_or_equal:today'],
                'send_time' => ['required', 'date_format:H:i'],
            ];
        }
        if ($type === 'recurring') {
            $rules['end_date'] = ['nullable', 'date', 'after_or_equal:start_date'];
        }

        if ($type === 'drip') {
            $rules += [
                'steps' => ['required', 'array', 'min:1', 'max:'.WaBroadcast::MAX_STEPS],
                'steps.*.delay_days' => ['required', 'integer', 'min:0', 'max:365', 'distinct'],
                'steps.*.use_template' => ['nullable', 'boolean'],
                'steps.*.wa_template_id' => ['nullable', 'required_if:steps.*.use_template,1', $template],
                'steps.*.body' => ['nullable', 'string', 'max:4000'],
                'steps.*.file' => ['nullable', 'file', 'max:65536'],
            ];
        } else {
            $rules += [
                'use_template' => ['boolean'],
                'wa_template_id' => ['nullable', 'required_if:use_template,true', $template],
                'body' => ['nullable', 'string', 'max:4000'],
                'file' => ['nullable', 'file', 'max:65536'],
            ];
        }

        $data = $request->validate($rules, [
            'send_at.required_if' => 'Tentukan waktu kirim.',
            'send_at.after' => 'Waktu kirim harus setelah sekarang.',
            'start_date.after_or_equal' => 'Tanggal mulai tidak boleh sebelum hari ini.',
            'end_date.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
            'wa_template_id.required_if' => 'Pilih template.',
            'steps.*.wa_template_id.required_if' => 'Pilih template untuk pesan ini.',
            'steps.*.delay_days.distinct' => 'Setiap pesan harus dikirim di hari yang berbeda.',
            'file.max' => 'Ukuran file maksimal 64 MB.',
            'steps.*.file.max' => 'Ukuran file maksimal 64 MB.',
            'wa_groups.*.regex' => 'Grup WhatsApp tidak valid.',
        ], ['name' => 'nama pesan', 'send_at' => 'waktu kirim', 'start_date' => 'tanggal mulai', 'end_date' => 'tanggal berakhir', 'send_time' => 'jam kirim', 'steps' => 'pesan bertahap']);

        $errors = [];
        if (empty($data['customer_ids']) && empty($data['group_ids']) && empty($data['apps']) && empty($data['phones']) && empty($data['wa_groups'])) {
            $errors['recipients'] = 'Pilih minimal satu tujuan: kontak, nomor, atau Grup WhatsApp.';
        }
        if ($type !== 'drip' && empty($data['use_template']) && blank($data['body'] ?? null) && ! $request->hasFile('file')) {
            $errors['body'] = 'Isi pesan atau lampirkan file.';
        }
        foreach ($type === 'drip' ? $data['steps'] : [] as $i => $step) {
            if (empty($step['use_template']) && blank($step['body'] ?? null) && ! $request->hasFile("steps.{$i}.file")) {
                $errors["steps.{$i}.body"] = 'Isi pesan atau lampirkan file.';
            }
        }
        if ($type !== 'once' && Carbon::parse($data['start_date'].' '.$data['send_time'])->isPast() && ! ($type === 'drip' && collect($data['steps'])->min('delay_days') > 0)) {
            $errors['send_time'] = 'Jam kirim hari ini sudah lewat, pilih jam berikutnya atau mulai besok.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // Bertahap: urutkan pesan berdasarkan hari kirimnya.
        if ($type === 'drip') {
            $data['steps'] = collect($data['steps'])->map(fn ($s, $i) => $s + ['_index' => $i])->sortBy('delay_days')->values()->all();
        }

        return $data;
    }

    /** [starts_at, ends_at, kirim pertama] */
    private function timing(array $data): array
    {
        if ($data['type'] === 'once') {
            $at = $data['schedule'] === 'now' ? now() : Carbon::parse($data['send_at']);

            return [$at, null, $at];
        }

        $start = Carbon::parse($data['start_date'].' '.$data['send_time']);
        $end = ! empty($data['end_date']) ? Carbon::parse($data['end_date'])->endOfDay() : null;

        return [$start, $end, $start];
    }

    private function target(WaDevice $device, array $data): array
    {
        $names = collect($this->cachedGroups($device)['data']['groups'] ?? [])->pluck('name', 'jid');

        return [
            'customer_ids' => array_values($data['customer_ids'] ?? []),
            'group_ids' => array_values($data['group_ids'] ?? []),
            'apps' => array_values($data['apps'] ?? []),
            'phones' => $data['phones'] ?? [],
            'wa_groups' => collect($data['wa_groups'] ?? [])->unique()->map(fn ($jid) => ['jid' => $jid, 'name' => $names[$jid] ?? null])->values()->all(),
        ];
    }

    /**
     * Isi pesan siap simpan. Template disalin saat disimpan (teks + file), jadi mengubah template
     * belakangan tidak mengubah pesan yang sudah dijadwalkan.
     */
    private function content(WaDevice $device, bool $useTemplate, ?string $templateId, ?string $body, ?UploadedFile $file, string $field): array
    {
        $template = $useTemplate ? WaTemplate::where('branch_id', $device->branch_id)->find($templateId) : null;
        if ($template) {
            $body = $template->toText();
            $file = $template->file_path && Storage::disk(WaTemplate::DISK)->exists($template->file_path)
                ? new UploadedFile(Storage::disk(WaTemplate::DISK)->path($template->file_path), $template->file_name ?: 'lampiran', $template->file_mime, null, true)
                : null;
        }

        $upload = null;
        if ($file) {
            $result = $this->wa->upload($device->branch_id, $file);
            if (! $result['success']) {
                throw ValidationException::withMessages([$field => $result['message']]);
            }
            $upload = $result['data'];
        }

        return [
            'body' => filled($body) ? $body : null,
            'wa_template_id' => $template?->id,
            'upload_id' => $upload['upload_id'] ?? null,
            'upload_kind' => $upload['kind'] ?? null,
            'upload_name' => $upload['name'] ?? null,
        ];
    }

    /** Daftar grup disimpan 5 menit supaya tidak sering bertanya ke WhatsApp (sama dengan menu Group WhatsApp). */
    private function cachedGroups(WaDevice $device, bool $refresh = false): array
    {
        $key = "wa-groups:{$device->id}";
        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::get($key) ?? tap($this->wa->groups($device->branch_id, $device->id), fn ($r) => $r['success'] && Cache::put($key, $r, 300));
    }

    private function broadcast(Request $request, string $id, ?array $statuses = null): WaBroadcast
    {
        return WaBroadcast::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->findOrFail($id);
    }
}
