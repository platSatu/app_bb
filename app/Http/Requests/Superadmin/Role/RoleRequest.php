<?php

namespace App\Http\Requests\Superadmin\Role;

use App\Models\Role;
use App\Support\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperadmin();
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique(Role::class)->where('scope', Role::SCOPE_PLATFORM)->ignore($this->route('role')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*.key' => ['required', 'string', Rule::in(Menu::grantable('superadmin')->keys()->all())],
            'permissions.*.ability' => ['nullable', Rule::in([Menu::ABILITY_VIEW, Menu::ABILITY_MANAGE])],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Nama role ini sudah ada. Pakai nama lain ya.'];
    }

    /** Hanya menu yang memang boleh diberikan ke staff, sisanya dibuang. */
    public function permissions(): array
    {
        return collect($this->validated('permissions', []))
            ->filter(fn (array $permission) => filled($permission['ability'] ?? null))
            ->mapWithKeys(fn (array $permission) => [$permission['key'] => $permission['ability']])
            ->all();
    }
}
