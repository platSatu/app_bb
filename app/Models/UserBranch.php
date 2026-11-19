<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserBranch extends Model
{
    use HasUuids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    /**
     * Notifikasi WhatsApp di Settings › Branch (bawaan mati), isinya sama dengan email.
     * Notifikasi Jadwal disimpan di kolom yang sama tapi diatur di Schedules › Notifikasi (App\Support\ScheduleNotice).
     */
    public const WA_NOTIFY = [
        'invoice' => ['Invoice', 'Tagihan baru, pengingat jatuh tempo & bukti lunas ke customer.'],
        'ticket' => ['Tiket', 'E-ticket ke pembeli setelah pembayaran berhasil.'],
    ];

    /** Label aplikasi per awalan nama tabel, untuk pesan "masih menyimpan data ..."; tabel lain memakai namanya sendiri. */
    private const DATA_LABELS = [
        'schedule_' => 'Jadwal', 'invoice' => 'Invoice', 'wa_' => 'WhatsApp', 'ticket' => 'Tiket', 'catalog' => 'Katalog',
        'customer' => 'Buku Kontak', 'page' => 'Halaman', 'quiz' => 'Kuis', 'branch_subscription' => 'Paket langganan',
    ];

    /** Tabel yang dibuat otomatis oleh sistem (bukan data owner), tidak menghalangi hapus branch. */
    private const DELETE_IGNORED = ['schedule_portal_links'];

    // user_id, company_id & slug diisi sistem, tidak pernah dari form. wa_notify diubah lewat form sakelar sendiri.
    protected $fillable = ['name', 'phone', 'handphone', 'address', 'city', 'description', 'status'];

    protected function casts(): array
    {
        return ['wa_notify' => 'array', 'place_qr_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (UserBranch $branch) {
            $branch->slug ??= static::uniqueSlug($branch->company_id, $branch->name);
        });
    }

    public static function uniqueSlug(string $companyId, string $name): string
    {
        $base = Str::slug($name) ?: 'branch';
        $slug = $base;

        while (static::where('company_id', $companyId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(UserCompany::class, 'company_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Sakelar notifikasi WhatsApp $kind nyala dan paket WhatsApp branch ini masih aktif. */
    public function waNotifies(string $kind): bool
    {
        return ! empty($this->wa_notify[$kind]) && app(\App\Services\Subscription\AppAccess::class)->branchActive($this->getKey(), 'whatsapp');
    }

    /** Isi QR tempat les (ditempel di lokasi, discan pengajar untuk absen). Bertanda tangan & berversi: Ganti QR membuat yang lama tidak berlaku. */
    public function placeQr(): string
    {
        $version = (int) ($this->place_qr_version ?: 1);

        return 'BBP.'.$this->getKey().'.'.$version.'.'.self::placeSignature($this->getKey(), $version);
    }

    /** Id branch dari isi QR tempat; null kalau palsu atau sudah diganti. */
    public static function idFromPlaceQr(string $code): ?string
    {
        if (! preg_match('/^BBP\.([0-9a-f-]{36})\.(\d{1,9})\.([0-9a-f]{16})$/', trim($code), $m)
            || ! hash_equals(self::placeSignature($m[1], (int) $m[2]), $m[3])) {
            return null;
        }

        return self::whereKey($m[1])->where('place_qr_version', (int) $m[2])->exists() ? $m[1] : null;
    }

    private static function placeSignature(string $id, int $version): string
    {
        return substr(hash_hmac('sha256', "schedule-place|{$id}|{$version}", (string) config('app.key')), 0, 16);
    }

    /**
     * Alasan branch ini belum boleh dihapus, atau null kalau aman. Hapus branch ikut menghapus (cascade) semua data
     * yang menempel padanya, jadi branch yang masih menyimpan data cukup dibuat Tidak Aktif.
     * Dibaca dari foreign key ke tabel branch, sehingga tabel baru otomatis ikut terlindungi tanpa didaftarkan.
     */
    public function deleteBlockedReason(): ?string
    {
        $db = $this->getConnection();
        $schema = $db->getSchemaBuilder();
        $labels = [];

        foreach (array_diff($schema->getTableListing($schema->getCurrentSchemaName(), false), self::DELETE_IGNORED) as $table) {
            foreach ($schema->getForeignKeys($table) as $key) {
                if ($key['foreign_table'] === $this->getTable() && strtolower((string) $key['on_delete']) === 'cascade'
                    && $db->table($table)->where($key['columns'][0], $this->getKey())->exists()) {
                    $labels[] = collect(self::DATA_LABELS)->first(fn ($label, $prefix) => str_starts_with($table, $prefix)) ?? Str::headline($table);
                }
            }
        }

        return $labels
            ? 'Branch '.$this->name.' masih menyimpan data '.implode(', ', array_unique($labels)).'. Supaya data ini tidak ikut terhapus, ubah status branch menjadi Tidak Aktif saja.'
            : null;
    }

    public function scopeSearch(Builder $query, ?string $keyword): Builder
    {
        $keyword = trim((string) $keyword);

        return $query->when($keyword !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
            ->where('name', 'like', "%{$keyword}%")
            ->orWhere('city', 'like', "%{$keyword}%")));
    }
}
