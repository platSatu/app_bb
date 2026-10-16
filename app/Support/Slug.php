<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Slug
{
    /** Slug unik di dalam $scope (mis. produk satu page). $ignoreId untuk baris yang sedang diedit. */
    public static function unique(Builder $scope, string $name, string $fallback, ?string $ignoreId = null, int $max = 100): string
    {
        $base = Str::limit(Str::slug($name) ?: $fallback, $max - 6, '');
        $slug = $base;

        while ((clone $scope)->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
