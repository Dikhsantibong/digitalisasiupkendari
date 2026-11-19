<?php

namespace App\Http\Requests\Admin;

use App\Enums\UnitContract;
use App\Enums\UnitStatus;
use App\Enums\UnitTlStructure;
use App\Enums\UnitType;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
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
            'contract' => ['nullable', Rule::enum(UnitContract::class)],
            'status' => ['required', Rule::enum(UnitStatus::class)],
            'installed_capacity_mw' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'location' => ['nullable', 'string', 'max:160'],
            'is_active' => ['required', 'boolean'],
            'operator_pengusahaan' => ['sometimes', 'boolean'],
            'tl_structure' => ['sometimes', Rule::enum(UnitTlStructure::class)],
            // Adding a unit: make its accounts (Akun Unit, Project Leader, TL, Koordinator & Office).
            'create_accounts' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedAttributes(): array
    {
        $validated = Arr::except($this->validated(), ['create_accounts']);
        $validated['code'] = Str::upper($validated['code']);
        // A new unit without a choice: containerized by its name, otherwise TL Ophar.
        if (! $this->route('unit') instanceof Unit && ! isset($validated['tl_structure'])) {
            $validated['tl_structure'] = UnitTlStructure::suggestFor($validated['name'])->value;
        }
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
            'contract' => 'kontrak',
            'status' => 'status',
            'installed_capacity_mw' => 'kapasitas terpasang',
            'location' => 'lokasi',
            'is_active' => 'status aktif',
            'operator_pengusahaan' => 'input operasi oleh operator',
            'tl_structure' => 'struktur team leader',
            'create_accounts' => 'buat akun unit',
        ];
    }
}
