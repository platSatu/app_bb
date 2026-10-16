<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DisbursementSetting extends Model
{
    use HasUuids;

    protected $fillable = ['mode', 'sandbox_user_id', 'sandbox_email', 'production_user_id', 'production_email', 'withdrawal_fee', 'min_amount'];

    protected $hidden = ['sandbox_secret_key', 'production_secret_key'];

    protected function casts(): array
    {
        return [
            'sandbox_secret_key' => 'encrypted',
            'production_secret_key' => 'encrypted',
            'withdrawal_fee' => 'integer',
            'min_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['mode' => 'sandbox']);
    }

    public function isSandbox(): bool
    {
        return $this->mode !== 'production';
    }

    public function credential(string $field): ?string
    {
        return $this->{($this->isSandbox() ? 'sandbox_' : 'production_').$field};
    }

    public function isConfigured(): bool
    {
        return filled($this->credential('user_id')) && filled($this->credential('email')) && filled($this->credential('secret_key'));
    }
}
