<?php

namespace App\Http\Controllers\WhatsApp\Concerns;

use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaChat;
use App\Models\WaDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Menu WhatsApp hanya untuk owner: semua data dibatasi ke branch miliknya.
 * Tim platform tidak membuka inbox tenant.
 */
trait InWhatsAppArea
{
    protected function ownerBranches(Request $request): Collection
    {
        return once(fn () => UserBranch::where('user_id', $request->user()->getKey())->orderBy('name')->get(['id', 'name', 'status', 'company_id', 'user_id']));
    }

    /** Branch terpilih dari ?branch=, default branch pertama. */
    protected function currentBranch(Request $request): ?UserBranch
    {
        $branches = $this->ownerBranches($request);

        return $branches->firstWhere('id', $request->input('branch')) ?? $branches->first();
    }

    protected function device(Request $request, string $id): WaDevice
    {
        return WaDevice::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))->findOrFail($id);
    }

    protected function findChat(WaDevice $device, string $id): WaChat
    {
        return $device->chats()->findOrFail($id);
    }

    /** Siapa saja yang bisa memegang chat. Sekarang owner branch; nanti ditambah tim per perusahaan. */
    protected function assignableUsers(WaDevice $device): Collection
    {
        return User::whereKey($device->branch->user_id)->get(['id', 'name']);
    }

    protected function branchRequired(): RedirectResponse
    {
        return redirect()->route('settings.branches.index')
            ->with('info', 'Buat branch dulu ya, setiap nomor WhatsApp dan kontak tercatat di salah satu branch Anda.');
    }

    /** "harga, promo; ongkir" -> ['harga', 'promo', 'ongkir'] */
    protected function keywordList(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/[,;\n]+/', (string) $value);

        return collect($items)->map(fn ($k) => trim((string) $k))->filter()->unique()->values()->all();
    }

    /** Jawaban seragam dari WaGateway ke browser. */
    protected function gatewayJson(array $result): JsonResponse
    {
        return response()->json($result, $result['success'] ? 200 : ($result['status'] >= 400 ? $result['status'] : 502));
    }
}
