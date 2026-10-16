<?php

namespace App\Http\Requests\Superadmin\Page;

use App\Http\Requests\Page\Category\PageCategoryRequest;
use App\Models\UserBranch;
use Illuminate\Validation\Rule;

/** Sama dengan form user, tapi superadmin boleh memilih branch milik siapa pun. */
class PageRequest extends PageCategoryRequest
{
    protected function branchQuery()
    {
        return UserBranch::query();
    }

    protected function branchRule()
    {
        return Rule::exists(UserBranch::class, 'id');
    }

    public function messages(): array
    {
        return [...parent::messages(), 'branch_id.exists' => 'Branch tidak ditemukan.'];
    }
}
