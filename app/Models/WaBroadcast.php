<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Message: Sekali Kirim / Berulang / Bertahap per Kontak. Setiap putaran kirim tercatat sebagai satu WaCampaign.
 *
 * Tujuan (target): customer_ids, group_ids (Group kontak), apps (semua customer aplikasi), phones, wa_groups (JID grup WhatsApp).
 * Bertahap: pesan ke-N (steps, urut position) dikirim pada starts_at + delay_days; runs_count = pesan yang sudah dikirim.
 */
class WaBroadcast extends Model
{
    use HasUuids;

    public const TYPES = ['once' => 'Sekali Kirim', 'recurring' => 'Berulang', 'drip' => 'Bertahap per Kontak'];

    public const SCHEDULES = ['now' => 'Kirim sekarang', 'at' => 'Pada waktu tertentu', 'daily' => 'Setiap hari', 'weekly' => 'Setiap minggu', 'monthly' => 'Setiap bulan', 'drip' => 'Bertahap'];

    /** Pilihan jadwal per jenis. */
    public const TYPE_SCHEDULES = ['once' => ['now', 'at'], 'recurring' => ['daily', 'weekly', 'monthly'], 'drip' => ['drip']];

    public const MAX_STEPS = 10;

    public const STATUSES = [
        'active' => ['Aktif', 'success'],
        'paused' => ['Dijeda', 'warning'],
        'done' => ['Selesai', 'secondary'],
        'cancelled' => ['Dibatalkan', 'danger'],
    ];

    protected $fillable = ['name', 'type', 'body', 'schedule'];

    protected function casts(): array
    {
        return ['target' => 'array', 'next_run_at' => 'datetime', 'last_run_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WaBroadcastStep::class, 'broadcast_id')->orderBy('position');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WaTemplate::class, 'wa_template_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WaDevice::class, 'device_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(WaCampaign::class, 'broadcast_id');
    }

    public function latestCampaign(): HasOne
    {
        return $this->hasOne(WaCampaign::class, 'broadcast_id')->latestOfMany();
    }

    public function isRepeating(): bool
    {
        return in_array($this->schedule, ['daily', 'weekly', 'monthly'], true);
    }

    /** Jadwal berikutnya setelah $from, selalu di masa depan; null bila sudah lewat tanggal berakhir. */
    public function nextAfter(CarbonInterface $from): ?CarbonInterface
    {
        if (! $this->isRepeating()) {
            return null;
        }
        $next = $from->copy();
        do {
            $next = match ($this->schedule) {
                'daily' => $next->addDay(),
                'weekly' => $next->addWeek(),
                'monthly' => $next->addMonthNoOverflow(),
            };
        } while ($next->isPast());

        return $this->ends_at && $next->gt($this->ends_at) ? null : $next;
    }

    /** Bertahap: waktu kirim pesan urutan ke-$index (0 = pertama), null bila tidak ada lagi. */
    public function stepDueAt(int $index): ?CarbonInterface
    {
        $step = $this->steps->values()->get($index);

        return $step && $this->starts_at ? $this->starts_at->copy()->addDays($step->delay_days) : null;
    }

    /** Ringkasan tujuan untuk daftar & detail. */
    public function targetSummary(): array
    {
        $t = $this->target ?? [];

        return array_filter([
            'Kontak' => count($t['customer_ids'] ?? []),
            'Group kontak' => count($t['group_ids'] ?? []),
            'Semua customer aplikasi' => count($t['apps'] ?? []),
            'Nomor' => count($t['phones'] ?? []),
            'Grup WhatsApp' => count($t['wa_groups'] ?? []),
        ]);
    }

    public function statusMeta(): array
    {
        return self::STATUSES[$this->status] ?? self::STATUSES['active'];
    }
}
