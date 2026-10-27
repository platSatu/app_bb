<?php

namespace App\Models;

use App\Support\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Satu halaman yang dibuka / aksi yang dilakukan pengguna yang login. Lihat migration-nya. */
class UserActivityLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /** Riwayat lebih lama dari ini dihapus otomatis (routes/console.php). */
    public const KEEP_DAYS = 180;

    public const TYPE_VIEW = 'view';
    public const TYPE_ACTION = 'action';

    public const TYPE_LABELS = [self::TYPE_VIEW => 'Membuka halaman', self::TYPE_ACTION => 'Aksi (simpan/ubah/hapus)'];

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return match ($type) {
            self::TYPE_VIEW => $query->where('method', 'GET'),
            self::TYPE_ACTION => $query->where('method', '!=', 'GET'),
            default => $query,
        };
    }

    /** "Membuka", "Menyimpan", "Mengubah", "Menghapus", atau "Menjalankan" untuk aksi lain. */
    public function verb(): string
    {
        $action = Str::afterLast((string) $this->route_name, '.');

        return match (true) {
            $this->method === 'GET' => 'Membuka',
            $this->route_name === 'login' => 'Masuk',
            $this->route_name === 'logout' => 'Keluar',
            $this->method === 'DELETE' || $action === 'destroy' => 'Menghapus',
            in_array($this->method, ['PUT', 'PATCH'], true) || $action === 'update' => 'Mengubah',
            $action === 'store' => 'Menyimpan',
            default => 'Menjalankan',
        };
    }

    /** Nama menu dari sidebar (config/menu.php), mis. "WhatsApp › Auto Reply"; selain menu: nama route yang dirapikan. */
    public function menuLabel(): string
    {
        return static::labelFor($this->route_name);
    }

    public static function labelFor(?string $routeName): string
    {
        if (! $routeName) {
            return '-';
        }

        static $cache = [];

        if (! isset($cache[$routeName])) {
            // Sama seperti Menu::isActive(): pola berawalan '!' mengecualikan.
            $item = Menu::items()->first(function (array $item) use ($routeName) {
                [$exclude, $include] = collect((array) $item['active'])->partition(fn (string $p) => str_starts_with($p, '!'));

                return Str::is($include->all(), $routeName) && ! Str::is($exclude->map(fn ($p) => substr($p, 1))->all(), $routeName);
            });
            $cache[$routeName] = $item
                ? $item['group'].' › '.$item['label']
                : Str::of($routeName)->replace(['.', '-', '_'], ' ')->headline()->toString();
        }

        return $cache[$routeName];
    }
}
