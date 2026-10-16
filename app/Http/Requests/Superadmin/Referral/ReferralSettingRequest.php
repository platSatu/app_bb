<?php

namespace App\Http\Requests\Superadmin\Referral;

use Illuminate\Foundation\Http\FormRequest;

class ReferralSettingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'is_active' => ['boolean'],
            'reward_amount' => ['required', 'integer', 'min:0', 'max:10000000'],
            'min_deposit_amount' => ['required', 'integer', 'min:0', 'max:100000000'],
        ];
    }

    public function attributes(): array
    {
        return ['reward_amount' => 'nominal bonus', 'min_deposit_amount' => 'minimal top up'];
    }
}
