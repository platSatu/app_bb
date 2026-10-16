<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogCategory extends Model
{
    use HasActiveStatus, HasUuids;

    protected $fillable = ['name', 'status'];

    public function products(): HasMany
    {
        return $this->hasMany(CatalogProduct::class, 'category_id');
    }
}
