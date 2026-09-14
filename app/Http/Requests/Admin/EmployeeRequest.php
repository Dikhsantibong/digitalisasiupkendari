<?php

namespace App\Http\Requests\Admin;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'is_active' => ['required', 'boolean'],
            'signature' => ['nullable', 'file', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'signature_base64' => ['nullable', 'string'],
            'remove_signature' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedAttributes(): array
    {
        return \Illuminate\Support\Arr::except($this->validated(), ['signature', 'signature_base64', 'remove_signature']);
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
            'is_active' => 'status aktif',
            'signature' => 'tanda tangan',
        ];
    }
}
