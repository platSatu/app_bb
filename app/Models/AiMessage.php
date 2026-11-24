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

    public const STATUS_DONE = 'done';

    public const STATUS_STOPPED = 'stopped';

    protected $fillable = ['role', 'content', 'model', 'input_tokens', 'output_tokens', 'duration_ms', 'status'];

    protected function casts(): array
    {
        return ['input_tokens' => 'integer', 'output_tokens' => 'integer', 'duration_ms' => 'integer'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
