<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CatalogProductImage extends Model
{
    use HasActiveStatus, HasUuids;

    protected $fillable = ['name', 'description', 'status'];

    public function url(): string
    {
        return Storage::disk('public')->url($this->image);
    }

    public function thumbnailUrl(): string
    {
        return Storage::disk('public')->url($this->thumbnail);
    }
}
