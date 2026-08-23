<?php

namespace App\Http\Requests\Admin;

use App\Enums\PermissionName;
use App\Enums\RoleScope;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    /**
     * Authorisation is handled by the controller's policy checks.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');
        $id = $role instanceof Role ? $role->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'name')->ignore($id)],
            'display_name' => ['required', 'string', 'max:120'],
            'scope' => ['required', Rule::enum(RoleScope::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionName::values())],
        ];
    }

    /**
     * A system role's identity is part of the application's structure; only its
     * presentation and permissions may change.
     */
    protected function prepareForValidation(): void
    {
        $role = $this->route('role');

        if ($role instanceof Role && $role->is_system) {
            $this->merge([
                'name' => $role->name,
                'scope' => $role->scope->value,
            ]);

            return;
        }

        if ($this->has('name')) {
            $this->merge(['name' => Str::snake(Str::lower((string) $this->input('name')))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Nama sistem role hanya boleh berisi huruf kecil, angka, dan garis bawah.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama sistem',
            'display_name' => 'nama tampilan',
            'scope' => 'cakupan',
            'description' => 'deskripsi',
            'permissions' => 'permission',
        ];
    }
}
