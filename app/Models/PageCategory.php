<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use App\Support\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PageCategory extends Model
{
    use HasActiveStatus, HasUuids;

    // branch_id, slug & QR diisi sistem setelah dicek kepemilikannya.
    protected $fillable = ['page_type_id', 'name', 'description', 'start_date', 'end_date', 'status'];

    protected $hidden = ['directory_qrcode'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'settings' => 'array'];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(PageType::class, 'page_type_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(UserBranch::class, 'branch_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(PageFormField::class)->orderBy('sort_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PageSubmission::class);
    }

    public function catalogOrders(): HasMany
    {
        return $this->hasMany(CatalogOrder::class, 'page_id');
    }

    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'page_id')->orderBy('sort_order');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'page_id');
    }

    public function ticketCategories(): HasMany
    {
        return $this->hasMany(TicketCategory::class, 'page_id')->orderBy('sort_order');
    }

    public function ticketOrders(): HasMany
    {
        return $this->hasMany(TicketOrder::class, 'page_id');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function template(): ?string
    {
        return $this->type?->template;
    }

    public function isTemplate(string $template): bool
    {
        return $this->template() === $template;
    }

    /** Pengaturan tab & tampilan publik dari config/page_templates.php. */
    public function templateConfig(?string $key = null): mixed
    {
        $config = config('page_templates.'.$this->template(), []);

        return $key ? ($config[$key] ?? null) : $config;
    }

    /**
     * Owner: hanya page miliknya. Tim platform: semua page, sesuai izin menu
     * superadmin.pages (lihat/kelola). Dipakai di semua sub-halaman page.
     */
    public function scopeAccessibleBy(Builder $query, User $user, string $ability = Menu::ABILITY_VIEW): Builder
    {
        if (! $user->isPlatformTeam()) {
            // Page di branch yang paket Pages-nya habis tidak bisa dibuka/diubah.
            return $query->ownedBy($user)->whereIn($this->qualifyColumn('branch_id'), \App\Services\Subscription\AppAccess::activeBranches('pages'));
        }

        return $user->canAccess('superadmin.pages', $ability) ? $query : $query->whereRaw('1 = 0');
    }

    /** Hanya page milik perusahaan user ini. */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('branch', fn (Builder $b) => $b->where('user_id', $user->getKey()));
    }

    public function url(): string
    {
        return route('page.show', $this->slug);
    }

    public function qrcodePath(): ?string
    {
        return $this->qrcode ? trim($this->directory_qrcode, '/').'/'.$this->qrcode : null;
    }

    public function qrcodeUrl(): ?string
    {
        return $this->qrcodePath() ? Storage::disk('public')->url($this->qrcodePath()) : null;
    }

    /** Bisa dibuka publik: aktif dan (kalau diisi) masih dalam rentang tanggal. */
    public function isOpen(): bool
    {
        return $this->isActive()
            && (! $this->start_date || $this->start_date->lte(today()))
            && (! $this->end_date || $this->end_date->gte(today()));
    }
}
