<?php

namespace App\Services\WhatsApp;

use App\Models\Customer;
use App\Models\WaBroadcast;
use App\Models\WaCampaign;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menjalankan Message: membuat satu campaign + baris antrean per penerima, lalu go_bizbos mengirimnya
 * dengan jeda anti-ban. Penerima dihitung ulang setiap putaran (anggota Group bisa berubah).
 */
class BroadcastService
{
    public const MAX_RECIPIENTS = 5000;

    /** Sampai 3 penerima dianggap pesan biasa (langsung), selebihnya ikut antrean blast yang dijeda. */
    private const DIRECT_LIMIT = 3;

    public function __construct(private WaGateway $wa) {}

    /** @return Collection<int, array{phone: string, name: ?string}> */
    public function recipients(WaBroadcast $broadcast): Collection
    {
        $target = $broadcast->target ?? [];
        $customers = Customer::where('branch_id', $broadcast->branch_id)->whereNotNull('phone');

        $list = $broadcast->type === 'blast'
            ? $customers->whereHas('groups', fn ($q) => $q->whereIn('contact_groups.id', $target['group_ids'] ?? []))->get(['name', 'phone'])
            : $customers->whereIn('id', $target['customer_ids'] ?? [])->get(['name', 'phone'])
                ->concat(collect($target['phones'] ?? [])->map(fn ($p) => (object) ['phone' => $p, 'name' => null]));

        $optOuts = DB::table('wa_optouts')->where('branch_id', $broadcast->branch_id)->pluck('phone')->flip();

        return $list->map(fn ($c) => ['phone' => (string) $c->phone, 'name' => $c->name])
            ->reject(fn ($r) => $r['phone'] === '' || isset($optOuts[$r['phone']]))
            ->unique('phone')->take(self::MAX_RECIPIENTS)->values();
    }

    /** Jalankan satu putaran bila sudah waktunya. Dipanggil dari scheduler & tombol "Kirim sekarang". */
    public function run(string $broadcastId): ?WaCampaign
    {
        $campaign = DB::transaction(function () use ($broadcastId) {
            $broadcast = WaBroadcast::whereKey($broadcastId)->lockForUpdate()->first();
            if (! $broadcast || $broadcast->status !== 'active' || ! $broadcast->next_run_at?->isPast()) {
                return null;
            }

            $recipients = $this->recipients($broadcast);
            $broadcast->forceFill([
                'last_run_at' => now(),
                'runs_count' => $broadcast->runs_count + 1,
                'next_run_at' => $broadcast->nextAfter($broadcast->next_run_at),
                'status' => $broadcast->isRepeating() ? 'active' : 'done',
                'last_error' => $recipients->isEmpty() ? 'Tidak ada penerima (kontak tanpa nomor atau sudah berhenti berlangganan).' : null,
            ])->save();

            return $recipients->isEmpty() ? null : $this->createCampaign($broadcast, $recipients);
        });

        if ($campaign) {
            $this->start($campaign);
        }

        return $campaign;
    }

    /** Minta go_bizbos mulai mengirim. Bila gagal, campaign tetap draft dan dicoba lagi oleh scheduler. */
    public function start(WaCampaign $campaign): bool
    {
        return $this->wa->campaign($campaign->branch_id, $campaign->id, 'start')['success'];
    }

    public function pause(WaBroadcast $broadcast): void
    {
        $broadcast->forceFill(['status' => 'paused'])->save();
        $this->eachLive($broadcast, ['running'], 'pause');
    }

    public function resume(WaBroadcast $broadcast): void
    {
        $broadcast->forceFill([
            'status' => 'active',
            'next_run_at' => $broadcast->next_run_at && $broadcast->next_run_at->isPast() && $broadcast->isRepeating() ? $broadcast->nextAfter($broadcast->next_run_at) : $broadcast->next_run_at,
        ])->save();
        $this->eachLive($broadcast, ['paused'], 'start');
    }

    public function cancel(WaBroadcast $broadcast): void
    {
        $broadcast->forceFill(['status' => 'cancelled', 'next_run_at' => null])->save();

        foreach ($broadcast->campaigns()->whereIn('status', ['draft', 'running', 'paused'])->get() as $campaign) {
            if (! $this->wa->campaign($campaign->branch_id, $campaign->id, 'cancel')['success']) {
                // go_bizbos sedang tidak bisa dihubungi: batalkan langsung di database (go_bizbos membaca tabel yang sama).
                DB::table('wa_outbox')->where('campaign_id', $campaign->id)->where('status', 'queued')->update(['status' => 'cancelled', 'error' => 'Blast dibatalkan.', 'updated_at' => now()]);
                $campaign->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
            }
        }
    }

    private function eachLive(WaBroadcast $broadcast, array $statuses, string $action): void
    {
        foreach ($broadcast->campaigns()->whereIn('status', $statuses)->get() as $campaign) {
            $this->wa->campaign($campaign->branch_id, $campaign->id, $action);
        }
    }

    private function createCampaign(WaBroadcast $broadcast, Collection $recipients): WaCampaign
    {
        $campaign = new WaCampaign();
        $campaign->forceFill([
            'id' => (string) Str::uuid(),
            'branch_id' => $broadcast->branch_id,
            'device_id' => $broadcast->device_id,
            'broadcast_id' => $broadcast->id,
            'name' => Str::limit($broadcast->name.' · '.now()->translatedFormat('d M Y H:i'), 150, ''),
            'status' => 'draft',
            'total' => $recipients->count(),
        ])->save();

        $priority = $recipients->count() <= self::DIRECT_LIMIT ? 0 : 10;
        $type = $broadcast->upload_id ? $broadcast->upload_kind : 'text';
        $now = now();

        foreach ($recipients->chunk(500) as $chunk) {
            DB::table('wa_outbox')->insert($chunk->map(fn ($r) => [
                'id' => (string) Str::uuid(),
                'branch_id' => $broadcast->branch_id,
                'device_id' => $broadcast->device_id,
                'campaign_id' => $campaign->id,
                'chat_jid' => $r['phone'],
                'type' => $type,
                'body' => $this->personalize($broadcast->body, $r['name']),
                'upload_id' => $broadcast->upload_id,
                'priority' => $priority,
                'status' => 'queued',
                'attempts' => 0,
                'available_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        }

        return $campaign;
    }

    /** {nama} diganti nama kontak, atau "Kak" bila namanya belum ada. */
    public function personalize(?string $body, ?string $name): ?string
    {
        return $body === null ? null : str_ireplace('{nama}', trim((string) $name) ?: 'Kak', $body);
    }
}
