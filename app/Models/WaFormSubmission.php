<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu kiriman form yang diterima webhook, beserta hasil kirim WhatsApp-nya. */
class WaFormSubmission extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(WaFormIntegration::class, 'integration_id');
    }
}
