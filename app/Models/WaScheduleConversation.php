<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Posisi satu nomor WhatsApp di percakapan bot Jadwal (satu baris per nomor per branch).
 * Hanya dibuat oleh sistem (pengingat terkirim / pengajuan masuk ke admin), jadi bot Jadwal tidak pernah
 * menyela chat biasa: tanpa baris yang masih berlaku, pesan masuk diteruskan ke chatbot flow / auto reply / AI.
 * context hanya berisi id & pilihan yang sudah dihitung sistem, tidak pernah isi dari pengirim selain alasan.
 */
class WaScheduleConversation extends Model
{
    use HasUuids;

    public const ROLE_STUDENT = 'student';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TEACHER = 'teacher';

    // Langkah murid / orang tua.
    public const STEP_CONFIRM = 'confirm';
    public const STEP_REASON = 'reason';
    public const STEP_RESCHEDULE_ASK = 'reschedule_ask';
    public const STEP_SLOT = 'slot';

    // Langkah admin.
    public const STEP_REVIEW = 'review';
    public const STEP_REJECT_REASON = 'reject_reason';

    // Langkah pengajar.
    public const STEP_RECAP = 'recap';
    public const STEP_RECAP_PICK = 'recap_pick';
    public const STEP_ONLINE_START = 'online_start';
    public const STEP_ATTEND = 'attend';
    public const STEP_ATTEND_PICK = 'attend_pick';
    public const STEP_ATTEND_REASON = 'attend_reason';
    public const STEP_ATTEND_CONFIRM = 'attend_confirm';

    protected $fillable = [];

    protected function casts(): array
    {
        return ['context' => 'array', 'expires_at' => 'datetime', 'invalid_count' => 'integer'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(UserBranch::class, 'branch_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function context(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }
}
