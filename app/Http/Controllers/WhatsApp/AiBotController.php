<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\WaAiBot;
use App\Models\WaAiLog;
use App\Models\WaDevice;
use App\Services\WhatsApp\Bot\AiResponder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Settings > AI Bot: satu bot per device, memakai API key AI milik user sendiri. */
class AiBotController extends Controller
{
    use InWhatsAppArea;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        $devices = WaDevice::where('branch_id', $branch->id)->orderBy('name')->get();
        $bots = WaAiBot::whereIn('device_id', $devices->pluck('id'))->get()->keyBy('device_id');

        return view('whatsapp.ai-bots.index', ['branches' => $this->ownerBranches($request), 'branch' => $branch, 'devices' => $devices, 'bots' => $bots]);
    }

    public function edit(Request $request, string $device): View
    {
        $model = $this->device($request, $device);
        $bot = WaAiBot::firstWhere('device_id', $model->id) ?? new WaAiBot(['driver' => 'openai', 'model' => WaAiBot::DRIVERS['openai'][2], 'max_tokens' => 400, 'handover_minutes' => 120, 'is_active' => true]);

        return view('whatsapp.ai-bots.form', [
            'device' => $model,
            'bot' => $bot,
            'logs' => $bot->exists ? WaAiLog::where('bot_id', $bot->id)->latest('id')->limit(15)->get() : collect(),
        ]);
    }

    public function update(Request $request, string $device): RedirectResponse
    {
        $model = $this->device($request, $device);
        $bot = WaAiBot::firstWhere('device_id', $model->id) ?? new WaAiBot();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'driver' => ['required', Rule::in(array_keys(WaAiBot::DRIVERS))],
            'model' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\/-]+$/'],
            'api_key' => [$bot->exists ? 'nullable' : 'required', 'string', 'max:300'],
            'system_prompt' => ['nullable', 'string', 'max:3000'],
            'knowledge' => ['nullable', 'string', 'max:30000'],
            'max_tokens' => ['required', 'integer', 'min:50', 'max:2000'],
            'handover_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
        ], ['model.regex' => 'Nama model tidak valid.'], ['api_key' => 'API key', 'knowledge' => 'informasi bisnis']);

        $bot->fill(collect($data)->except('api_key')->all() + ['is_active' => $request->boolean('is_active')]);
        $bot->forceFill(['branch_id' => $model->branch_id, 'device_id' => $model->id]);
        if (filled($data['api_key'] ?? null)) {
            $bot->api_key = trim($data['api_key']);
        }
        $bot->save();

        return back()->with('success', 'AI Bot disimpan.'.($bot->is_active ? ' Bot menjawab pesan yang tidak ditangani flow & auto reply.' : ''));
    }

    public function test(Request $request, string $device, AiResponder $ai): RedirectResponse
    {
        $bot = WaAiBot::where('device_id', $this->device($request, $device)->id)->firstOrFail();

        try {
            return back()->with('ai_test', $ai->test($bot))->with('success', 'API key berfungsi, berikut contoh jawaban AI.');
        } catch (Throwable $e) {
            return back()->with('error', 'Tes gagal: '.$e->getMessage());
        }
    }

    public function destroy(Request $request, string $device): RedirectResponse
    {
        $model = $this->device($request, $device);
        WaAiBot::where('device_id', $model->id)->delete();

        return redirect()->route('whatsapp.ai-bots.index', ['branch' => $model->branch_id])->with('success', 'AI Bot dihapus beserta API key-nya.');
    }
}
