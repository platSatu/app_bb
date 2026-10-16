<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Menu bertingkat: setiap node berisi pesan + pilihan jawaban (angka/teks) menuju node lain. */
class WaChatbotFlow extends Model
{
    use HasUuids;

    public const MATCH_TYPES = ['exact' => 'Sama persis', 'contains' => 'Mengandung kata kunci'];

    protected $fillable = ['name', 'match_type', 'keywords', 'nodes', 'end_message', 'handover_message', 'timeout_minutes', 'is_active'];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'nodes' => 'array', 'is_active' => 'boolean'];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WaDevice::class, 'device_id');
    }

    public function node(string $id): ?array
    {
        return collect($this->nodes)->firstWhere('id', $id);
    }
}
