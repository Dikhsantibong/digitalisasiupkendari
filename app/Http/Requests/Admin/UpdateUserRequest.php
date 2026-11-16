<?php

namespace App\Http\Requests\Admin;

use App\Models\Employee;
use App\Models\User;
use App\Services\Operator\AttendanceRoster;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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
            'regu' => ['nullable', Rule::in(AttendanceRoster::REGU)],
            'is_shift_leader' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
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

                return;
            }

            if (! $this->boolean('is_active')) {
                return;
            }

            $target = $this->route('user');
            if (! $target instanceof User) {
                return;
            }

            $employee = $target->employee
                ?? ($target->employee_id ? Employee::query()->where('nip', $target->employee_id)->first() : null);
            $unitId = $employee?->unit_id ?? $target->roleAssignments()->whereNotNull('unit_id')->value('unit_id');

            if ($unitId === null) {
                return;
            }

            $leader = Employee::query()
                ->where('unit_id', $unitId)
                ->where('regu', $this->input('regu'))
                ->where('is_active', true)
                ->where('is_shift_leader', true)
                ->when($employee !== null, fn ($query) => $query->whereKeyNot($employee->getKey()))
                ->first();

            if ($leader !== null) {
                $validator->errors()->add('is_shift_leader', "Regu {$this->input('regu')} di unit ini sudah punya Leader Shift: {$leader->name}.");
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
