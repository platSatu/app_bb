<?php

namespace App\Http\Requests\Superadmin\Referral;

use Illuminate\Foundation\Http\FormRequest;

class ReferralSettingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'is_active' => ['boolean'],
            'default_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'hold_days' => ['required', 'integer', 'min:0', 'max:365'],
            'active_months' => ['required', 'integer', 'min:1', 'max:120'],
        ];
    }

    public function attributes(): array
    {
        return ['default_percent' => 'persen default', 'hold_days' => 'lama ditahan', 'active_months' => 'lama berlaku'];
    }
}
