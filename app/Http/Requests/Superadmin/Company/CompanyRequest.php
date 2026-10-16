<?php

namespace App\Http\Requests\Superadmin\Company;

use App\Http\Requests\Settings\Company\CompanyRequest as SettingsCompanyRequest;
use App\Models\User;
use App\Models\UserCompany;
use App\Support\Menu;
use Illuminate\Validation\Rule;

class CompanyRequest extends SettingsCompanyRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->canAccess('superadmin.companies', Menu::ABILITY_MANAGE);
    }

    public function rules(): array
    {
        $isCreate = ! $this->route('company') instanceof UserCompany;

        return [
            ...parent::rules(),
            'user_id' => $isCreate
                ? ['required', Rule::exists(User::class, 'id'), Rule::unique(UserCompany::class, 'user_id')]
                : ['prohibited'],
            'status' => ['required', Rule::in([UserCompany::STATUS_ACTIVE, UserCompany::STATUS_INACTIVE])],
            'limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'user_id.unique' => 'User ini sudah punya perusahaan. Satu user hanya bisa punya satu perusahaan.'];
    }

    public function attributes(): array
    {
        return [...parent::attributes(), 'user_id' => 'pemilik'];
    }
}
