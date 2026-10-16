<?php

namespace App\Http\Requests\Superadmin\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? $this->user()->can('update', $target)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'handphone' => User::normalizePhone($this->input('handphone')),
        ]);
    }

    public function rules(): array
    {
        $target = $this->route('user');
        $isUpdate = $target instanceof User;
        $bySuperadmin = $this->user()->isSuperadmin();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($target)],
            'handphone' => ['required', 'regex:/^62[1-9][0-9]{8,12}$/', Rule::unique(User::class)->ignore($target)],
            'user_type' => $bySuperadmin ? ['required', Rule::in(array_keys(User::TYPE_LABELS))] : ['prohibited'],
            'role_id' => $bySuperadmin
                ? ['nullable', 'required_if:user_type,'.User::TYPE_STAFF, Rule::exists(Role::class, 'id')->where('scope', Role::SCOPE_PLATFORM)]
                : ['prohibited'],
            'status' => [$isUpdate ? 'required' : 'prohibited', Rule::in([User::STATUS_ACTIVE, User::STATUS_INACTIVE])],
            'password' => [$isUpdate ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'handphone.regex' => 'Nomor WhatsApp belum benar. Isi 9–13 digit tanpa awalan 0, contoh: 81234567890.',
            'handphone.unique' => 'Nomor WhatsApp ini sudah dipakai user lain.',
            'email.unique' => 'Email ini sudah dipakai user lain.',
            'role_id.required_if' => 'Pilih role untuk staff ini dulu ya.',
        ];
    }
}
