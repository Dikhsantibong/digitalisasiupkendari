<?php

namespace App\Http\Requests\Admin;

use App\Enums\FuelType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class MachineRequest extends FormRequest
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
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:120'],
            'fuel_type' => ['nullable', new Enum(FuelType::class)],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'capacity_kw' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'is_active' => ['required', 'boolean'],
            'lubricant_type_ids' => ['array'],
            'lubricant_type_ids.*' => [
                'integer',
                Rule::exists('lubricant_types', 'id')->where('unit_id', $this->input('unit_id')),
            ],
        ];
    }

    /**
     * The machine's own attributes, without the lubricant pivot payload.
     *
     * @return array<string, mixed>
     */
    public function validatedAttributes(): array
    {
        return collect($this->validated())
            ->except('lubricant_type_ids')
            ->all();
    }

    /**
     * The lubricant type ids selected for the machine.
     *
     * @return list<int>
     */
    public function lubricantTypeIds(): array
    {
        return collect($this->validated('lubricant_type_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'unit_id' => 'unit pembangkit',
            'name' => 'nama mesin',
            'type' => 'tipe',
            'fuel_type' => 'jenis bahan bakar',
            'serial_number' => 'serial number',
            'capacity_kw' => 'kapasitas',
            'is_active' => 'status aktif',
            'lubricant_type_ids' => 'jenis pelumas',
        ];
    }
}
