<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Services\Operator\AttendanceRoster;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'regu' => ['nullable', Rule::in(AttendanceRoster::REGU)],
            'is_shift_leader' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_shift_leader')) {
                return;
            }

            if (! $this->filled('regu')) {
                $validator->errors()->add('is_shift_leader', 'Leader Shift harus memilih Regu Shift (A–D).');
            }
        }];
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
            'regu' => 'regu shift',
            'is_shift_leader' => 'leader shift',
            'is_active' => 'status aktif',
            'password' => 'kata sandi',
        ];
    }
}
