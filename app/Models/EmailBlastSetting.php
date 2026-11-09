<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Akun email pengirim Tools › Email Blast (terpisah dari MAIL_* di .env). Satu baris saja. */
class EmailBlastSetting extends Model
{
    use HasUuids;

    protected $fillable = ['from_name', 'from_address', 'host', 'username', 'encryption', 'smtp_port', 'imap_port', 'daily_limit', 'updated_by'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'smtp_port' => 'integer',
            'imap_port' => 'integer',
            'daily_limit' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([])->refresh();
    }

    public function isConfigured(): bool
    {
        return filled($this->host) && filled($this->username) && filled($this->password) && filled($this->from_address);
    }
}
