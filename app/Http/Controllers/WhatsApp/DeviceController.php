<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\WaDevice;
use App\Services\WhatsApp\DeviceHealth;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Device & Inbox: daftar nomor per branch, hubungkan (QR / kode pairing), kesehatan, putuskan. */
class DeviceController extends Controller
{
    use InWhatsAppArea;

    public function __construct(private WaGateway $wa) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.devices.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'devices' => WaDevice::with('branch:id,name')->where('branch_id', $branch->id)->orderBy('created_at')->get(),
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $branch = $this->currentBranch($request);

        return response()->json([
            'devices' => WaDevice::with('branch:id,name')->where('branch_id', $branch?->id)->orderBy('created_at')->get()->map->toClient(),
            'realtime_url' => $branch ? $this->wa->realtimeUrl($branch->id) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', Rule::in($this->ownerBranches($request)->pluck('id'))],
            'name' => ['required', 'string', 'max:100', Rule::unique('wa_devices')->where('branch_id', $request->input('branch_id'))],
        ], ['name.unique' => 'Nama perangkat ini sudah dipakai di branch tersebut.'], ['name' => 'nama perangkat']);

        $device = new WaDevice(['name' => $data['name']]);
        $device->forceFill(['branch_id' => $data['branch_id'], 'status' => 'logged_out'])->save();

        return redirect()->route('whatsapp.devices.index', ['branch' => $data['branch_id'], 'connect' => $device->id])
            ->with('success', 'Perangkat ditambahkan. Lanjutkan dengan menghubungkan nomor WhatsApp Anda.');
    }

    public function update(Request $request, string $device): RedirectResponse
    {
        $model = $this->device($request, $device);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('wa_devices')->where('branch_id', $model->branch_id)->ignore($model->id)],
            'daily_cap' => ['nullable', 'integer', 'min:0', 'max:5000'],
        ], ['name.unique' => 'Nama perangkat ini sudah dipakai di branch tersebut.'], ['name' => 'nama perangkat', 'daily_cap' => 'batas blast harian']);

        $model->fill(['name' => $data['name'], 'daily_cap' => (int) ($data['daily_cap'] ?? 0)])->save();

        return back()->with('success', 'Perangkat diperbarui.');
    }

    public function destroy(Request $request, string $device): RedirectResponse
    {
        $model = $this->device($request, $device);

        if ($model->isLinked()) {
            $result = $this->wa->logout($model->branch_id, $model->id);
            if (! $result['success'] && $result['status'] !== 404) {
                return back()->with('error', 'Perangkat belum bisa dihapus: '.$result['message']);
            }
        }

        $model->delete();

        return back()->with('success', 'Perangkat beserta riwayat chatnya sudah dihapus.');
    }

    public function connect(Request $request, string $device): JsonResponse
    {
        $model = $this->device($request, $device);
        $phone = $request->validate(['phone' => ['nullable', 'string', 'max:20']])['phone'] ?? null;
        $phone = $phone ? \App\Models\User::normalizePhone($phone) : null;

        return $this->gatewayJson($this->wa->connect($model->branch_id, $model->id, $phone));
    }

    public function status(Request $request, string $device): JsonResponse
    {
        $model = $this->device($request, $device);

        return $this->gatewayJson($this->wa->status($model->branch_id, $model->id));
    }

    public function logout(Request $request, string $device): JsonResponse
    {
        $model = $this->device($request, $device);

        return $this->gatewayJson($this->wa->logout($model->branch_id, $model->id));
    }

    public function health(Request $request, string $device, DeviceHealth $health): JsonResponse
    {
        return response()->json(['health' => $health->for($this->device($request, $device))]);
    }
}
