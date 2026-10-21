<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScheduleBranch;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Link pendaftaran portal per branch: satu untuk murid, satu untuk pengajar. */
class SchedulePortalLink extends Model
{
    use BelongsToScheduleBranch, HasUuids;

    public const TYPE_STUDENT = 'student';
    public const TYPE_TEACHER = 'teacher';

    public const TYPE_LABELS = [
        self::TYPE_STUDENT => 'Murid',
        self::TYPE_TEACHER => 'Pengajar',
    ];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function newToken(): string
    {
        return Str::lower(Str::random(32));
    }

    public function url(): string
    {
        return route('portal.join', $this->token);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
