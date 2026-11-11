<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasActiveStatus, HasUuids;

    /** Durasi 0 = selamanya (tanpa masa aktif), mis. Pages & Event gratis. */
    public const LIFETIME = 0;

    protected $fillable = ['category_application_id', 'name', 'price', 'description', 'duration', 'apps', 'limits', 'is_trial', 'is_featured', 'status'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'duration' => 'integer', 'apps' => 'array', 'limits' => 'array', 'is_trial' => 'boolean', 'is_featured' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryApplication::class, 'category_application_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function isLifetime(): bool
    {
        return (int) $this->duration === self::LIFETIME;
    }

    /** "Selamanya" atau "N hari". */
    public function durationLabel(): string
    {
        return $this->isLifetime() ? 'Selamanya' : $this->duration.' hari';
    }

    /**
     * Deskripsi siap tampil. {biaya_tiket} & {tiket_gratis} diganti angka dari Biaya Layanan Tiket (superadmin),
     * jadi kartu paket Event selalu sama dengan pengaturan tiket yang berlaku.
     */
    public function describe(): string
    {
        $text = (string) $this->description;

        if (! str_contains($text, '{')) {
            return $text;
        }

        $ticket = once(fn () => TicketSetting::current());

        return strtr($text, [
            '{biaya_tiket}' => rtrim(rtrim(number_format((float) $ticket->fee_percent, 2, ',', '.'), '0'), ',').'%',
            '{tiket_gratis}' => number_format((int) $ticket->free_allowance, 0, ',', '.'),
        ]);
    }

    /** Deskripsi ditulis satu fitur per baris, ditampilkan sebagai list. */
    public function features(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $this->describe()))));
    }

    /** Batas satu layanan (lihat App\Services\Subscription\PlanLimits). Kosong = tanpa batas. */
    public function limitsFor(string $app): array
    {
        return \App\Services\Subscription\PlanLimits::clean($this->limits[$app] ?? []);
    }

    /** Nama layanan yang dibuka paket ini. */
    public function appLabels(): array
    {
        return array_map(fn ($key) => \App\Support\Apps::label($key), $this->apps ?? []);
    }
}
