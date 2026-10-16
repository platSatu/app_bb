<?php

namespace App\Http\Requests\Superadmin\Page;

use App\Http\Requests\Superadmin\CategoryApplication\CategoryApplicationRequest;
use App\Models\PageType;
use Illuminate\Validation\Rule;

/** Field-nya sama dengan Kategori Aplikasi, ditambah template. */
class PageTypeRequest extends CategoryApplicationRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'template' => ['required', Rule::in(array_keys(PageType::TEMPLATES))]];
    }
}
