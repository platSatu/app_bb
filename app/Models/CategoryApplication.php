<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryApplication extends Model
{
    use HasActiveStatus, HasUuids;

    // slug & image diisi sistem.
    protected $fillable = ['name', 'description', 'status'];

    protected static function booted(): void
    {
        static::creating(function (CategoryApplication $category) {
            $category->slug ??= static::uniqueSlug($category->name);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'aplikasi';
        $slug = $base;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
