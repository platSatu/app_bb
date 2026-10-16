<?php

namespace App\Http\Requests\Superadmin\Branch;

use App\Http\Requests\Settings\Branch\BranchRequest as SettingsBranchRequest;
use App\Models\UserBranch;
use App\Models\UserCompany;
use App\Support\Menu;
use Illuminate\Validation\Rule;

class BranchRequest extends SettingsBranchRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccess('superadmin.branches', Menu::ABILITY_MANAGE);
    }

    public function rules(): array
    {
        $isCreate = ! $this->route('branch') instanceof UserBranch;

        return [
            ...parent::rules(),
            'company_id' => $isCreate ? ['required', Rule::exists(UserCompany::class, 'id')] : ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'company_id' => 'perusahaan'];
    }
}
