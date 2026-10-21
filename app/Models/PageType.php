<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Jenis page yang disiapkan superadmin (Landing Page, Katalog, Tiket, Quiz, ...). */
class PageType extends Model
{
    use HasActiveStatus, HasUuids;

    public const TEMPLATE_LANDING = 'landing';
    public const TEMPLATE_CATALOG = 'catalog';
    public const TEMPLATE_TICKET = 'ticket';
    public const TEMPLATE_QUIZ = 'quiz';
    public const TEMPLATE_BIOLINK = 'biolink';

    public const TEMPLATES = [
        self::TEMPLATE_LANDING => 'Landing Page',
        self::TEMPLATE_CATALOG => 'Catalog Online',
        self::TEMPLATE_TICKET => 'Ticket',
        self::TEMPLATE_QUIZ => 'Quiz',
        self::TEMPLATE_BIOLINK => 'Bio Link',
    ];

    protected $fillable = ['name', 'template', 'description', 'status'];

    protected static function booted(): void
    {
        static::creating(function (PageType $type) {
            $base = Str::slug($type->name) ?: 'page';
            $type->slug ??= static::where('slug', $base)->exists() ? $base.'-'.Str::lower(Str::random(5)) : $base;
        });
    }

    public function pages(): HasMany
    {
        return $this->hasMany(PageCategory::class);
    }

    public function templateLabel(): string
    {
        return self::TEMPLATES[$this->template] ?? $this->template;
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
