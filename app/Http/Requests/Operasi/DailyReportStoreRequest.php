<?php

namespace App\Http\Requests\Operasi;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyReportStoreRequest extends FormRequest
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
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['array'],
            'rows.*.day' => ['required', 'integer', 'between:1,31'],
            'rows.*.kwh_produksi_stand_akhir' => ['nullable', 'numeric', 'min:0'],
            'rows.*.kwh_pakai_sendiri_stand_akhir' => ['nullable', 'numeric', 'min:0'],
            'rows.*.beban_puncak_pagi_kw' => ['nullable', 'numeric', 'min:0'],
            'rows.*.beban_puncak_malam_kw' => ['nullable', 'numeric', 'min:0'],
            'rows.*.pemakaian_pelumas_liter' => ['nullable', 'numeric', 'min:0'],
            'rows.*.flowmeter_hsd_stand_akhir' => ['nullable', 'numeric', 'min:0'],
            'rows.*.flowmeter_hsd_tambah_liter' => ['nullable', 'numeric', 'min:0'],
            'rows.*.flowmeter_mfo_stand_akhir' => ['nullable', 'numeric', 'min:0'],
            'rows.*.flowmeter_mfo_tambah_liter' => ['nullable', 'numeric', 'min:0'],
            'rows.*.air_pps_stand_akhir' => ['nullable', 'numeric', 'min:0'],
            'rows.*.air_softener_stand_akhir' => ['nullable', 'numeric', 'min:0'],
            'rows.*.catatan' => ['nullable', 'string', 'max:255'],
        ];
    }
}
