<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasUuids;

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const KIND_TEXT = 'text';

    public const KIND_IMAGE = 'image';

    public const KIND_VIDEO = 'video';

    public const KIND_AUDIO = 'audio';

    public const MEDIA_KINDS = [self::KIND_IMAGE, self::KIND_VIDEO, self::KIND_AUDIO];

    public const STATUS_DONE = 'done';

    public const STATUS_STOPPED = 'stopped';

    /** Media menunggu diproses antrean. */
    public const STATUS_PENDING = 'pending';

    /** Media sedang dibuat (sudah diambil worker / video sedang dirender di Classai). */
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['role', 'kind', 'content', 'media', 'options', 'model', 'input_tokens', 'output_tokens', 'duration_ms', 'status'];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer', 'output_tokens' => 'integer', 'duration_ms' => 'integer',
            'media' => 'array', 'options' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function isWaiting(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    /** Data untuk tampilan chat (tanpa path file asli; file dibuka lewat route yang dicek pemiliknya). */
    public function toChatArray(): array
    {
        $options = (array) $this->options;

        return [
            'id' => $this->getKey(),
            'role' => $this->role,
            'kind' => $this->kind ?: self::KIND_TEXT,
            'content' => $this->content,
            'model' => $this->model,
            'status' => $this->status,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'duration_ms' => $this->duration_ms,
            'cost_rp' => isset($options['cost_micro_rp']) ? round($options['cost_micro_rp'] / 1_000_000) : null,
            'details' => array_values(array_filter([
                $options['aspect_ratio'] ?? null, $options['resolution'] ?? null,
                isset($options['seconds']) ? $options['seconds'].' detik' : null,
                $options['voice_name'] ?? null, $options['style'] ?? null,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'media' => collect((array) $this->media)->values()->map(fn ($file, $i) => [
                'url' => route('superadmin.ai-assistant.file', [$this, $i]),
                'download' => route('superadmin.ai-assistant.file', [$this, $i, 'download' => 1]),
                'mime' => $file['mime'] ?? null,
            ])->all(),
        ];
    }
}
