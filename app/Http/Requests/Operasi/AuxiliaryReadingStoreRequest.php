<?php

namespace App\Http\Requests\Operasi;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuxiliaryReadingStoreRequest extends FormRequest
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
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'readings' => ['array'],
            'readings.*.auxiliary_source_id' => [
                'required',
                'integer',
                Rule::exists('auxiliary_sources', 'id')->where('unit_id', $this->input('unit_id')),
            ],
            'readings.*.day' => ['required', 'integer', 'between:1,31'],
            'readings.*.stand_kwh_akhir' => ['nullable', 'numeric', 'min:0'],
            'readings.*.stand_bbm_akhir' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
