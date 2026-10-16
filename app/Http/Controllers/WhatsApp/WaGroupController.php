<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\ContactGroup;
use App\Models\WaDevice;
use App\Services\Contact\ContactBookService;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Phone Book > Group WhatsApp: grup yang diikuti nomor, anggotanya bisa disalin ke Group kontak. */
class WaGroupController extends Controller
{
    use InWhatsAppArea;

    public function __construct(private WaGateway $wa) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        $devices = WaDevice::where('branch_id', $branch->id)->orderBy('name')->get();
        $device = $devices->firstWhere('id', $request->query('device')) ?? $devices->firstWhere('status', 'connected') ?? $devices->first();
        $result = null;

        if ($device?->isConnected()) {
            $key = "wa-groups:{$device->id}";
            if ($request->boolean('refresh')) {
                Cache::forget($key);
            }
            // Daftar grup disimpan 5 menit supaya tidak sering bertanya ke WhatsApp.
            $result = Cache::get($key) ?? tap($this->wa->groups($branch->id, $device->id), fn ($r) => $r['success'] && Cache::put($key, $r, 300));
        }

        return view('whatsapp.wa-groups.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'devices' => $devices,
            'device' => $device,
            'result' => $result,
            'contactGroups' => ContactGroup::where('branch_id', $branch->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function import(Request $request, ContactBookService $book): RedirectResponse
    {
        $device = $this->device($request, (string) $request->input('device_id'));
        $data = $request->validate([
            'jid' => ['required', 'regex:/^[0-9-]{10,40}@g\.us$/'],
            'group_id' => ['nullable', 'required_without:new_group', Rule::exists('contact_groups', 'id')->where('branch_id', $device->branch_id)],
            'new_group' => ['nullable', 'required_without:group_id', 'string', 'max:100', Rule::unique('contact_groups', 'name')->where('branch_id', $device->branch_id)],
        ], ['group_id.required_without' => 'Pilih group tujuan atau isi nama group baru.', 'new_group.unique' => 'Nama group ini sudah ada, pilih dari daftar saja.']);

        $result = $this->wa->groupParticipants($device->branch_id, $device->id, $data['jid']);
        if (! $result['success']) {
            return back()->with('error', $result['message']);
        }

        $rows = collect($result['data']['participants'] ?? [])->filter(fn ($p) => filled($p['phone']))
            ->map(fn ($p) => ['name' => $p['name'] ?: null, 'phone' => $p['phone'], 'email' => null])->values()->all();

        $group = ! empty($data['group_id']) ? ContactGroup::find($data['group_id']) : tap(new ContactGroup(['name' => $data['new_group']]), fn ($g) => $g->forceFill(['branch_id' => $device->branch_id])->save());
        $saved = $book->addMany($device->branch, array_slice($rows, 0, ContactBookService::MAX_ROWS), $group, 'whatsapp_group')['saved'];
        $hidden = count($result['data']['participants'] ?? []) - count($rows);

        return redirect()->route('whatsapp.groups.show', $group)
            ->with('success', "{$saved} anggota grup \"{$result['data']['name']}\" masuk ke group {$group->name}.".($hidden > 0 ? " {$hidden} anggota dilewati karena nomornya disembunyikan WhatsApp." : ''));
    }
}
