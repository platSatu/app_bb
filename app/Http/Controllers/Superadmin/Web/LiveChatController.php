<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\WebSetting;
use App\Services\Web\SiteAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Settings > Live Chat: pengaturan widget tanya-jawab di header bizbos.id.
 * Disimpan di WebSetting (ikut terkirim ke fe-konexa lewat API web-setting, kecuali chat_knowledge).
 */
class LiveChatController extends Controller
{
    public function edit(): View
    {
        return view('superadmin.web.live-chat.edit', [
            'setting' => WebSetting::current(),
            'provider' => AiProvider::active(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chat_enabled' => ['required', 'boolean'],
            'chat_ai' => ['nullable', 'boolean'],
            'chat_greeting' => ['nullable', 'string', 'max:300'],
            'chat_questions' => ['nullable', 'string', 'max:1000'],
            'chat_knowledge' => ['nullable', 'string', 'max:6000'],
        ]);

        // Maks 6 pertanyaan cepat, masing-masing pendek, supaya tetap muat di layar HP.
        $data['chat_questions'] = collect(preg_split('/\R/', (string) ($data['chat_questions'] ?? '')))
            ->map(fn ($q) => mb_substr(trim($q), 0, 60))->filter()->unique()->take(6)->implode("\n") ?: null;

        WebSetting::current()->update([...$data, 'chat_ai' => $request->boolean('chat_ai')]);
        Cache::forget(SiteAssistant::CACHE_KEY);

        return back()->with('success', 'Pengaturan live chat disimpan.');
    }

    /** Uji jawaban asisten langsung dari halaman pengaturan. */
    public function test(Request $request, SiteAssistant $assistant): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:500']]);

        return response()->json(['reply' => $assistant->reply(trim($data['message']))]);
    }
}
