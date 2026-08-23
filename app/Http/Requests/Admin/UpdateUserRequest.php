<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
        $target = $this->route('user');
        $id = $target instanceof User ? $target->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'employee_id' => ['nullable', 'string', 'max:32', Rule::unique('users', 'employee_id')->ignore($id)],
            'email' => ['required', 'string', 'email', 'max:160', Rule::unique('users', 'email')->ignore($id)],
            'position' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'employee_id' => 'NIP',
            'email' => 'email',
            'position' => 'jabatan',
            'phone' => 'nomor telepon',
            'is_active' => 'status aktif',
            'password' => 'kata sandi',
        ];
    }
}
