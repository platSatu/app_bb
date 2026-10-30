<?php

namespace App\Services\WhatsApp;

use App\Models\Customer;
use App\Models\WaBroadcast;
use App\Models\WaCampaign;
use App\Services\Subscription\PlanLimits;
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

    public function __construct(private WaGateway $wa, private PlanLimits $limits) {}

    /**
     * Semua tujuan digabung: kontak pilihan, anggota Group kontak, semua customer aplikasi, nomor ketikan,
     * dan Grup WhatsApp (JID grup). Nomor yang berhenti berlangganan (STOP) dilewati; nomor dobel dikirimi sekali.
     *
     * @return Collection<int, array{phone: string, name: ?string}> phone = nomor 628xx atau JID grup
     */
    public function recipients(WaBroadcast $broadcast): Collection
    {
        $target = $broadcast->target ?? [];
        $groupIds = $target['group_ids'] ?? [];
        $apps = $target['apps'] ?? [];
        $customerIds = $target['customer_ids'] ?? [];

        // Satu query: hanya syarat yang dipilih yang ikut (tanpa subquery kosong).
        $contacts = ($groupIds || $apps || $customerIds)
            ? Customer::where('branch_id', $broadcast->branch_id)->whereNotNull('phone')
                ->where(fn ($q) => $q
                    ->when($customerIds, fn ($q) => $q->orWhereIn('id', $customerIds))
                    ->when($apps, fn ($q) => $q->orWhereIn('app_key', $apps))
                    ->when($groupIds, fn ($q) => $q->orWhereHas('groups', fn ($g) => $g->whereIn('contact_groups.id', $groupIds))))
                ->get(['name', 'phone'])
            : collect();

        $phones = $contacts->map(fn ($c) => ['phone' => (string) $c->phone, 'name' => $c->name])
            ->concat(collect($target['phones'] ?? [])->map(fn ($p) => ['phone' => (string) $p, 'name' => null]));

        $optOuts = DB::table('wa_optouts')->where('branch_id', $broadcast->branch_id)->pluck('phone')->flip();
        $groups = collect($target['wa_groups'] ?? [])->map(fn ($g) => ['phone' => (string) ($g['jid'] ?? ''), 'name' => null]);

        return $phones->reject(fn ($r) => isset($optOuts[$r['phone']]))
            ->concat($groups)
            ->reject(fn ($r) => $r['phone'] === '')
            ->unique('phone')->take(self::MAX_RECIPIENTS)->values();
    }

    /** Isi pesan yang dikirim pada putaran ini: pesan utama, atau pesan bertahap ke-(runs_count + 1). */
    private function content(WaBroadcast $broadcast): ?array
    {
        $source = $broadcast->type === 'drip' ? $broadcast->steps->values()->get($broadcast->runs_count) : $broadcast;
        if (! $source) {
            return null;
        }

        return [
            'body' => $source->body,
            'upload_id' => $source->upload_id,
            'upload_kind' => $source->upload_kind,
            'label' => $broadcast->type === 'drip' ? 'Pesan '.($broadcast->runs_count + 1) : null,
        ];
    }

    /** Jadwal putaran berikutnya setelah putaran ini; null = selesai. */
    private function nextRunAt(WaBroadcast $broadcast)
    {
        return $broadcast->type === 'drip'
            ? $broadcast->stepDueAt($broadcast->runs_count + 1)
            : $broadcast->nextAfter($broadcast->next_run_at);
    }

    /** Jalankan satu putaran bila sudah waktunya. Dipanggil dari scheduler & tombol "Kirim sekarang". */
    public function run(string $broadcastId): ?WaCampaign
    {
        $campaign = DB::transaction(function () use ($broadcastId) {
            $broadcast = WaBroadcast::whereKey($broadcastId)->lockForUpdate()->first();
            if (! $broadcast || $broadcast->status !== 'active' || ! $broadcast->next_run_at?->isPast()) {
                return null;
            }

            $content = $this->content($broadcast);
            $recipients = $content ? $this->recipients($broadcast) : collect();
            // Kuota pesan bulan ini dipakai di muka; kalau kurang, hanya sebanyak sisa kuota yang dikirim.
            $granted = $this->limits->takeMessages($broadcast->branch_id, $recipients->count(), partial: true);
            $skipped = $recipients->count() - $granted;
            $recipients = $recipients->take($granted);

            $next = $content ? $this->nextRunAt($broadcast) : null;
            $broadcast->forceFill([
                'last_run_at' => now(),
                'runs_count' => $broadcast->runs_count + 1,
                'next_run_at' => $next,
                'status' => $next ? 'active' : 'done',
                'last_error' => match (true) {
                    $skipped > 0 => "Kuota pesan WhatsApp bulan ini habis: {$skipped} penerima tidak dikirimi. Kuota kembali tanggal 1, atau upgrade paket.",
                    $recipients->isEmpty() => 'Tidak ada penerima (kontak tanpa nomor atau sudah berhenti berlangganan).',
                    default => null,
                },
            ])->save();

            return $recipients->isEmpty() ? null : $this->createCampaign($broadcast, $recipients, $content);
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

    private function createCampaign(WaBroadcast $broadcast, Collection $recipients, array $content): WaCampaign
    {
        $campaign = new WaCampaign();
        $campaign->forceFill([
            'id' => (string) Str::uuid(),
            'branch_id' => $broadcast->branch_id,
            'device_id' => $broadcast->device_id,
            'broadcast_id' => $broadcast->id,
            'name' => Str::limit(collect([$broadcast->name, $content['label'], now()->translatedFormat('d M Y H:i')])->filter()->implode(' · '), 150, ''),
            'status' => 'draft',
            'total' => $recipients->count(),
        ])->save();

        $priority = $recipients->count() <= self::DIRECT_LIMIT ? 0 : 10;
        $type = $content['upload_id'] ? $content['upload_kind'] : 'text';
        $now = now();

        foreach ($recipients->chunk(500) as $chunk) {
            DB::table('wa_outbox')->insert($chunk->map(fn ($r) => [
                'id' => (string) Str::uuid(),
                'branch_id' => $broadcast->branch_id,
                'device_id' => $broadcast->device_id,
                'campaign_id' => $campaign->id,
                'chat_jid' => $r['phone'],
                'type' => $type,
                'body' => $this->personalize($content['body'], $r['name']),
                'upload_id' => $content['upload_id'],
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
