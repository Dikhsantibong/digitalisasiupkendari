<?php

namespace App\Http\Requests\Operasi;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],

            // Ikhtisar Sentral summary
            'summary' => ['nullable', 'array'],
            'summary.kwh_dibangkit' => ['nullable', 'numeric'],
            'summary.kwh_pemakaian_sendiri' => ['nullable', 'numeric'],
            'summary.kwh_disalurkan' => ['nullable', 'numeric'],
            'summary.beban_puncak_pagi_kw' => ['nullable', 'numeric'],
            'summary.beban_puncak_malam_kw' => ['nullable', 'numeric'],
            'summary.jam_jalan_perhari' => ['nullable', 'numeric'],

            // Ikhtisar Sentral per Mesin
            'mesins' => ['nullable', 'array'],
            'mesins.*.engine_id' => ['required', 'integer', 'exists:machines,id'],
            'mesins.*.kwh_dibangkit' => ['nullable', 'numeric'],
            'mesins.*.jam_jalan' => ['nullable', 'numeric'],
            'mesins.*.pemakaian_hsd' => ['nullable', 'numeric'],
            'mesins.*.pemakaian_mfo' => ['nullable', 'numeric'],
            'mesins.*.sfc' => ['nullable', 'numeric'],
            'mesins.*.t_kalor' => ['nullable', 'numeric'],
            'mesins.*.slc' => ['nullable', 'numeric'],
            'mesins.*.pemakaian_pelumas' => ['nullable', 'array'],
            'mesins.*.bbm' => ['nullable', 'array'],
            'mesins.*.bbm.*' => ['nullable', 'numeric', 'min:0'],

            // Ikhtisar Persediaan Bahan Bakar & Pelumas
            'inventory' => ['nullable', 'array'],
            'inventory.persediaan_awal' => ['nullable', 'array'],
            'inventory.penerimaan' => ['nullable', 'array'],
            'inventory.penerimaan_sewa_smp' => ['nullable', 'array'],
            'inventory.pemakaian_non_operasi' => ['nullable', 'array'],
            'inventory.pengiriman' => ['nullable', 'array'],

            // Legacy rows support for backwards compatibility
            'engine_id' => ['nullable', 'integer'],
            'rows' => ['nullable', 'array'],
        ];
    }
}
