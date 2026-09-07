<?php

namespace App\Http\Requests\Operasi;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StarStopStoreRequest extends FormRequest
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
            'engine_id' => [
                'required',
                'integer',
                Rule::exists('machines', 'id')->where('unit_id', $this->input('unit_id')),
            ],
            'status_code_id' => ['required', 'integer', 'exists:unit_status_codes,id'],
            'report_date' => ['required', 'date'],
            'start_datetime' => ['required', 'date'],
            'stop_datetime' => ['required', 'date', 'after_or_equal:start_datetime'],
            'operator_name' => ['nullable', 'string', 'max:120'],
            'dispatcher_name' => ['nullable', 'string', 'max:120'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'engine_id' => 'mesin',
            'status_code_id' => 'kode status',
            'report_date' => 'tanggal',
            'start_datetime' => 'waktu start',
            'stop_datetime' => 'waktu stop',
            'operator_name' => 'operator',
            'dispatcher_name' => 'dispatcher',
        ];
    }
}
