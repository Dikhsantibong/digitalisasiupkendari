<?php

namespace App\Http\Requests\Operasi;

use App\Enums\TankFuelType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class FuelReceiptStoreRequest extends FormRequest
{
    /**
     * Authorisation is handled in the controller (permission + unit scope).
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
            'report_date' => ['required', 'date'],
            'fuel_type' => ['required', new Enum(TankFuelType::class)],
            'supplier' => ['nullable', 'string', 'max:150'],
            'do_number' => ['nullable', 'string', 'max:100'],
            'unloading_date' => ['nullable', 'date'],
            'volume_liter' => ['required', 'numeric', 'min:0'],
            'calorie_value' => ['nullable', 'numeric', 'min:0'],
            'price_per_liter' => ['nullable', 'numeric', 'min:0'],
            'transport_cost' => ['nullable', 'numeric', 'min:0'],
            'surveyor_cost' => ['nullable', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'report_date' => 'tanggal',
            'fuel_type' => 'jenis BBM',
            'volume_liter' => 'volume',
        ];
    }
}
