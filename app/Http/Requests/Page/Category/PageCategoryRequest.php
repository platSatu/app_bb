<?php

namespace App\Http\Requests\Page\Category;

use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\TicketSetting;
use App\Models\UserBranch;
use App\Services\Subscription\AppAccess;
use App\Support\Apps;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : null,
            'ga_id' => $this->filled('ga_id') ? strtoupper(trim((string) $this->input('ga_id'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', $this->branchRule()],
            'page_type_id' => ['required', $this->pageTypeRule()],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'min:3', 'max:100', Rule::unique(PageCategory::class, 'slug')->ignore($this->route('page'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(array_keys(PageCategory::STATUS_LABELS))],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'og_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_banner' => ['boolean'],
            'remove_og_image' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'ga_id' => ['nullable', 'regex:'.PageCategory::GA_PATTERN],
            // Page tiket wajib memilih siapa yang menanggung biaya layanan.
            'ticket_fee_payer' => [
                Rule::requiredIf(fn () => PageType::whereKey((string) $this->input('page_type_id'))->value('template') === PageType::TEMPLATE_TICKET),
                'nullable', Rule::in(array_keys(TicketSetting::PAYER_LABELS)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.exists' => 'Branch tidak ditemukan di perusahaan Anda.',
            'page_type_id.exists' => 'Kategori page ini tidak tersedia.',
            'slug.unique' => 'Link ini sudah dipakai, coba yang lain.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'ticket_fee_payer.required' => 'Pilih dulu siapa yang menanggung biaya layanan tiket.',
            'ga_id.regex' => 'Measurement ID Google Analytics diawali G-, contoh G-AB12CD34EF.',
        ];
    }

    public function attributes(): array
    {
        return [
            'branch_id' => 'branch',
            'page_type_id' => 'kategori page',
            'name' => 'nama page',
            'banner' => 'banner',
            'og_image' => 'gambar share',
            'meta_title' => 'judul SEO',
            'meta_description' => 'deskripsi SEO',
            'slug' => 'link',
            'description' => 'deskripsi',
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal selesai',
        ];
    }

    public function branch(): UserBranch
    {
        return $this->branchQuery()->findOrFail($this->validated('branch_id'));
    }

    /** 'events' (menu Event) atau 'pages' (menu Pages), dibaca dari nama route. */
    protected function area(): string
    {
        return Apps::pageArea($this->route()?->getName());
    }

    /** User biasa: hanya branch miliknya sendiri yang paket layanan area ini aktif. Superadmin meng-override ini. */
    protected function branchQuery()
    {
        return UserBranch::where('user_id', $this->user()->getKey())->whereIn('id', AppAccess::activeBranches($this->area()));
    }

    protected function branchRule()
    {
        return Rule::exists(UserBranch::class, 'id')->where('user_id', $this->user()->getKey())
            ->whereIn('id', AppAccess::activeBranches($this->area())->pluck('branch_id')->all());
    }

    /**
     * Kategori aktif, atau kategori lama page ini (supaya edit tidak gagal saat kategorinya dinonaktifkan).
     * Selalu milik area ini: menu Event hanya kategori bertemplate tiket, menu Pages hanya sisanya.
     */
    protected function pageTypeRule()
    {
        $page = $this->route('page');
        $current = $page ? PageCategory::whereKey($page instanceof PageCategory ? $page->getKey() : $page)->value('page_type_id') : null;
        $events = $this->area() === Apps::EVENTS;

        return Rule::exists(PageType::class, 'id')->where(fn ($q) => $q
            ->when($events, fn ($t) => $t->whereIn('template', Apps::EVENT_TEMPLATES), fn ($t) => $t->whereNotIn('template', Apps::EVENT_TEMPLATES))
            ->where(fn ($w) => $w
                ->where('status', PageType::STATUS_ACTIVE)
                ->when($current, fn ($o) => $o->orWhere('id', $current))));
    }
}
