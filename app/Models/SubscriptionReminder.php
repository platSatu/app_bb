<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Pengingat masa aktif yang sudah dikirim, supaya tidak terkirim dua kali. */
class SubscriptionReminder extends Model
{
    use HasUuids;

    protected $fillable = [];
}
