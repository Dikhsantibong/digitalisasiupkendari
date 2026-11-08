<?php

namespace App\Http\Requests\Admin;

use App\Enums\RoleName;
use App\Enums\RoleScope;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RoleAssignmentRequest extends FormRequest
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
        return [
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'service_unit_id' => ['nullable', 'integer', 'exists:service_units,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
        ];
    }

    /**
     * The scope required by an assignment is dictated by the role, so validate
     * the pair rather than the fields in isolation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $role = Role::query()->find($this->input('role_id'));

            if ($role === null) {
                return;
            }

            if ($role->scope === RoleScope::Unit && $this->input('unit_id') === null) {
                $validator->errors()->add('unit_id', 'Role ini harus ditugaskan pada satu unit pembangkit.');
            }

            if ($role->scope === RoleScope::ServiceUnit && $this->input('service_unit_id') === null) {
                $validator->errors()->add('service_unit_id', 'Role ini harus ditugaskan pada satu unit layanan.');
            }

            // Admin UL goes to the UL of the account's own unit(s): an Akun Unit sees the units of its UL.
            if ($role->name === RoleName::AdminUl->value && $this->input('service_unit_id') !== null) {
                $user = $this->route('user');
                $ownUls = $user instanceof User
                    ? $user->roleAssignments()->whereNotNull('unit_id')->with('unit:id,service_unit_id')->get()->pluck('unit.service_unit_id')->filter()->unique()->values()->all()
                    : [];

                if ($ownUls === []) {
                    $validator->errors()->add('role_id', 'Admin UL hanya untuk akun yang sudah ditugaskan pada unit pembangkit di bawah satu UL (cek UL unit tersebut di Master Unit).');
                } elseif (! in_array((int) $this->input('service_unit_id'), array_map('intval', $ownUls), true)) {
                    $validator->errors()->add('service_unit_id', 'Pilih Unit Layanan tempat unit bawaan akun ini berada.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'role_id' => 'role',
            'service_unit_id' => 'unit layanan',
            'unit_id' => 'unit pembangkit',
        ];
    }
}
