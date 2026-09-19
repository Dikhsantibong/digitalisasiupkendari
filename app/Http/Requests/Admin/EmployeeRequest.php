<?php

namespace App\Http\Requests\Admin;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EmployeeRequest extends FormRequest
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
        $employee = $this->route('employee');
        $id = $employee instanceof Employee ? $employee->getKey() : null;

        return [
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'service_unit_id' => ['nullable', 'integer', 'exists:service_units,id'],
            'name' => ['required', 'string', 'max:120'],
            'nip' => ['nullable', 'string', 'max:64', Rule::unique('employees', 'nip')->ignore($id)],
            'position' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($id)],
            'is_active' => ['required', 'boolean'],
            'signature' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'signature_base64' => ['nullable', 'string'],
            'remove_signature' => ['nullable', 'boolean'],
        ];
    }

    /**
     * A report-signer jabatan (Project Leader, Office, Koordinator, PIC PDM,
     * TL, Manager UL) may have only one active holder per unit.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $key = Employee::signerAttributes(
                $this->input('position'),
                $this->filled('unit_id') ? $this->integer('unit_id') : null,
                $this->filled('service_unit_id') ? $this->integer('service_unit_id') : null,
                $this->boolean('is_active'),
            )['singleton_key'];
            if ($key === null) {
                return;
            }

            $employee = $this->route('employee');
            $holder = Employee::query()->where('singleton_key', $key)
                ->when($employee instanceof Employee, fn ($query) => $query->whereKeyNot($employee->getKey()))
                ->first();

            if ($holder !== null) {
                $validator->errors()->add('position', "Jabatan {$this->input('position')} di unit ini sudah dipegang pegawai aktif {$holder->name}. Nonaktifkan pegawai tersebut terlebih dahulu.");
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedAttributes(): array
    {
        return Arr::except($this->validated(), ['signature', 'signature_base64', 'remove_signature']);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'unit_id' => 'unit pembangkit',
            'service_unit_id' => 'unit layanan',
            'name' => 'nama',
            'nip' => 'NID/NIP',
            'position' => 'jabatan',
            'user_id' => 'akun pengguna',
            'is_active' => 'status aktif',
            'signature' => 'tanda tangan',
        ];
    }
}
