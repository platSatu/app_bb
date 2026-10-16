<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Data pendaftar dari form publik. Bukan akun login; pemiliknya = company pemilik page. */
class PageSubmission extends Model
{
    use HasUuids;

    protected $fillable = [];

    public function page(): BelongsTo
    {
        return $this->belongsTo(PageCategory::class, 'page_category_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PageSubmissionAnswer::class)->orderBy('sort_order');
    }
}
