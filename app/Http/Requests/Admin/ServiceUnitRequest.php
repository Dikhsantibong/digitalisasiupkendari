<?php

namespace App\Http\Requests\Admin;

use App\Models\ServiceUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceUnitRequest extends FormRequest
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
        $serviceUnit = $this->route('service_unit');
        $id = $serviceUnit instanceof ServiceUnit ? $serviceUnit->getKey() : null;

        return [
            'code' => ['required', 'string', 'max:32', Rule::unique('service_units', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedAttributes(): array
    {
        $validated = $this->validated();
        $validated['code'] = Str::upper($validated['code']);
        $validated['slug'] = Str::slug($validated['name']);

        return $validated;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'name' => 'nama',
            'description' => 'deskripsi',
            'is_active' => 'status aktif',
        ];
    }
}
