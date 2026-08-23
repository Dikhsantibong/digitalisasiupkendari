<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules;

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
            'name' => ['required', 'string', 'max:120'],
            'employee_id' => ['nullable', 'string', 'max:32', 'unique:users,employee_id'],
            'email' => ['required', 'string', 'email', 'max:160', 'unique:users,email'],
            'position' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['required', 'boolean'],
            'password' => $this->passwordRules(),
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
