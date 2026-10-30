<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Link pendaftaran customer Invoice, satu per branch. Token acak 40 karakter = satu-satunya kunci. */
class InvoiceRegisterLink extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(UserBranch::class, 'branch_id');
    }

    public static function newToken(): string
    {
        return Str::lower(Str::random(40));
    }

    public function url(): string
    {
        return route('bills.register', $this->token);
    }
}
