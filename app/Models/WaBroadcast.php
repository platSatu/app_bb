<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Message: pesan sekali kirim / blast. Setiap putaran kirim tercatat sebagai satu WaCampaign. */
class WaBroadcast extends Model
{
    use HasUuids;

    public const TYPES = ['once' => 'Sekali kirim', 'blast' => 'Blast'];

    public const SCHEDULES = ['now' => 'Kirim sekarang', 'at' => 'Pada waktu tertentu', 'daily' => 'Setiap hari', 'weekly' => 'Setiap minggu', 'monthly' => 'Setiap bulan'];

    public const STATUSES = [
        'active' => ['Aktif', 'success'],
        'paused' => ['Dijeda', 'warning'],
        'done' => ['Selesai', 'secondary'],
        'cancelled' => ['Dibatalkan', 'danger'],
    ];

    protected $fillable = ['name', 'type', 'body', 'schedule'];

    protected function casts(): array
    {
        return ['target' => 'array', 'next_run_at' => 'datetime', 'last_run_at' => 'datetime'];
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

    /** Jadwal berikutnya setelah $from, selalu di masa depan. */
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

        return $next;
    }

    public function statusMeta(): array
    {
        return self::STATUSES[$this->status] ?? self::STATUSES['active'];
    }
}
