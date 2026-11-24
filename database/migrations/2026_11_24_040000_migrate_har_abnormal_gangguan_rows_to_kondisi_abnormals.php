<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unify Pemeliharaan (HAR) "Laporan Kondisi Abnormal dan Gangguan Pembangkit"
     * with Operasi's `kondisi_abnormals` table so both divisions share the exact
     * same records and format.
     */
    public function up(): void
    {
        if (! Schema::hasTable('har_tabel_rows') || ! Schema::hasTable('kondisi_abnormals')) {
            return;
        }

        $harRows = DB::table('har_tabel_rows')
            ->where('tabel', 'abnormal-gangguan')
            ->orderBy('unit_id')
            ->orderBy('year')
            ->orderBy('month')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($harRows as $row) {
            $data = is_string($row->data) ? (json_decode($row->data, true) ?: []) : (array) $row->data;
            $uraian = trim((string) ($data['uraian'] ?? ''));
            if ($uraian === '') {
                continue;
            }

            $jenisHar = trim((string) ($data['jenis'] ?? ''));
            $fullUraian = $jenisHar !== '' && ! str_contains($uraian, $jenisHar)
                ? "{$uraian} ({$jenisHar})"
                : $uraian;

            $year = (int) $row->year;
            $month = (int) $row->month;
            $tanggal = ! empty($data['tanggal'])
                ? (string) $data['tanggal']
                : Carbon::create($year, $month, 1)->toDateString();

            $isGangguan = ! empty($data['gangguan']) && empty($data['abnormal']) ? 1 : 0;
            $isAbnormal = $isGangguan === 1 ? 0 : 1;
            $durasiAbnormal = $isAbnormal === 1 ? (float) ($data['durasi_abnormal'] ?? 0) : 0;
            $durasiGangguan = $isGangguan === 1 ? (float) ($data['durasi_gangguan'] ?? 0) : 0;

            $alreadyExists = DB::table('kondisi_abnormals')
                ->where('unit_id', $row->unit_id)
                ->where('year', $year)
                ->where('month', $month)
                ->where('uraian_kondisi', $fullUraian)
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            $nextNo = (int) DB::table('kondisi_abnormals')
                ->where('unit_id', $row->unit_id)
                ->where('year', $year)
                ->where('month', $month)
                ->max('no_urut') + 1;

            DB::table('kondisi_abnormals')->insert([
                'unit_id' => $row->unit_id,
                'year' => $year,
                'month' => $month,
                'no_urut' => $nextNo,
                'uraian_kondisi' => $fullUraian,
                'tanggal' => $tanggal,
                'is_abnormal' => $isAbnormal,
                'durasi_abnormal' => $durasiAbnormal,
                'is_gangguan' => $isGangguan,
                'durasi_gangguan' => $durasiGangguan,
                'input_by' => $row->input_by,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }

        DB::table('har_tabel_rows')->where('tabel', 'abnormal-gangguan')->delete();
    }

    public function down(): void
    {
        // Shared records remain in kondisi_abnormals.
    }
};
