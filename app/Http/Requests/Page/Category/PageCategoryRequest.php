<?php

namespace App\Http\Requests\Page\Category;

use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\UserBranch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => $this->filled('slug') ? Str::slug((string) $this->input('slug')) : null]);
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
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.exists' => 'Branch tidak ditemukan di perusahaan Anda.',
            'page_type_id.exists' => 'Kategori page ini tidak tersedia.',
            'slug.unique' => 'Link ini sudah dipakai, coba yang lain.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ];
    }

    public function attributes(): array
    {
        return [
            'branch_id' => 'branch',
            'page_type_id' => 'kategori page',
            'name' => 'nama page',
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

    /** User biasa: hanya branch miliknya sendiri. Superadmin meng-override ini. */
    protected function branchQuery()
    {
        return UserBranch::where('user_id', $this->user()->getKey())->whereIn('id', \App\Services\Subscription\AppAccess::activeBranches('pages'));
    }

    protected function branchRule()
    {
        return Rule::exists(UserBranch::class, 'id')->where('user_id', $this->user()->getKey())
            ->whereIn('id', \App\Services\Subscription\AppAccess::activeBranches('pages')->pluck('branch_id')->all());
    }

    /** Kategori aktif, atau kategori lama page ini (supaya edit tidak gagal saat kategorinya dinonaktifkan). */
    protected function pageTypeRule()
    {
        $page = $this->route('page');
        $current = $page ? PageCategory::whereKey($page instanceof PageCategory ? $page->getKey() : $page)->value('page_type_id') : null;

        return Rule::exists(PageType::class, 'id')->where(fn ($q) => $q->where(fn ($w) => $w
            ->where('status', PageType::STATUS_ACTIVE)
            ->when($current, fn ($o) => $o->orWhere('id', $current))));
    }
}
