<?php

namespace App\Http\Requests\Admin;

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
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
        $unit = $this->route('unit');
        $id = $unit instanceof Unit ? $unit->getKey() : null;

        return [
            'service_unit_id' => ['nullable', 'integer', 'exists:service_units,id'],
            'code' => ['required', 'string', 'max:32', Rule::unique('units', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(UnitType::class)],
            'status' => ['required', Rule::enum(UnitStatus::class)],
            'installed_capacity_mw' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'location' => ['nullable', 'string', 'max:160'],
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
            'service_unit_id' => 'unit layanan',
            'code' => 'kode',
            'name' => 'nama',
            'type' => 'tipe',
            'status' => 'status',
            'installed_capacity_mw' => 'kapasitas terpasang',
            'location' => 'lokasi',
            'is_active' => 'status aktif',
        ];
    }
}
