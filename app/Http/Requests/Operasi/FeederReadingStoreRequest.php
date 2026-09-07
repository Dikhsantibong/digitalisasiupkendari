<?php

namespace App\Http\Requests\Operasi;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeederReadingStoreRequest extends FormRequest
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
            'readings.*.feeder_id' => [
                'required',
                'integer',
                Rule::exists('feeders', 'id')->where('unit_id', $this->input('unit_id')),
            ],
            'readings.*.day' => ['required', 'integer', 'between:1,31'],
            'readings.*.stand_akhir' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
