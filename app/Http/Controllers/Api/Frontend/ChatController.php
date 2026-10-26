<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebSetting;
use App\Services\Customer\CustomerService;
use App\Services\Web\SiteAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Live chat bizbos.id (dipanggil server fe-konexa dengan X-API-KEY, bukan langsung dari browser).
 * start(): pengunjung mengisi nama, email, WhatsApp dulu -> disimpan sebagai Customer "Website".
 * __invoke(): jawab pertanyaan. Dibatasi per pengunjung (IP dikirim fe-konexa).
 */
class ChatController extends Controller
{
    private const PER_VISITOR_PER_MINUTE = 8;

    public function start(Request $request, CustomerService $customers): JsonResponse
    {
        if (WebSetting::current()->chat_enabled === false) {
            return response()->json(['message' => 'Live chat sedang tidak aktif.'], 403);
        }

        $request->merge(['phone' => User::normalizePhone($request->input('phone'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[\pL\s.\'-]+$/u'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'regex:/^62[1-9][0-9]{8,12}$/'],
        ], [
            'name.regex' => 'Nama hanya boleh berisi huruf.',
            'phone.regex' => 'Nomor WhatsApp belum benar, contoh: 81234567890.',
        ], ['name' => 'nama', 'phone' => 'nomor WhatsApp']);

        $customer = $customers->resolveWebsiteLead($data['name'], $data['phone'], $data['email']);

        return response()->json(['data' => ['name' => $customer->name]]);
    }

    public function __invoke(Request $request, SiteAssistant $assistant): JsonResponse
    {
        // Dimatikan dari Settings > Live Chat: jangan habiskan kuota AI walau ada yang memanggil langsung.
        if (WebSetting::current()->chat_enabled === false) {
            return response()->json(['message' => 'Live chat sedang tidak aktif.'], 403);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:500'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2000'],
            'visitor' => ['nullable', 'string', 'max:64'],
        ]);

        $key = 'site-chat:'.sha1($data['visitor'] ?? $request->ip());
        if (RateLimiter::tooManyAttempts($key, self::PER_VISITOR_PER_MINUTE)) {
            return response()->json(['message' => 'Pelan-pelan ya, coba kirim lagi dalam '.RateLimiter::availableIn($key).' detik.'], 429);
        }
        RateLimiter::hit($key, 60);

        return response()->json(['data' => [
            'reply' => $assistant->reply(trim($data['message']), $data['history'] ?? [], $data['name'] ?? null),
            'contact' => $assistant->contact(),
        ]]);
    }
}
