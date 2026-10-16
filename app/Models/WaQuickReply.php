<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WaQuickReply extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'shortcut', 'body'];
}
