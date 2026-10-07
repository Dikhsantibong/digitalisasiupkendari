<?php

namespace App\Services\Operasi;

use App\Enums\TugJenis;
use App\Models\Machine;
use App\Models\OperasiTug;
use App\Models\Unit;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;

/**
 * TUG 9 — Rekap Bon Pemakaian Energi Primer of one machine for one month.
 * The daily amounts are read from the month's sheet (Pemakaian Pelumas or
 * Pemakaian Bahan Bakar, never stored twice); only the header (nomor,
 * pekerjaan, SPK, cost center, kode perkiraan, tanggal) is kept per machine in
 * operasi_tugs. A new month starts from the previous TUG of the same machine
 * and jenis, except its nomor.
 *
 * @phpstan-type Column array{key: string, name: string, code: string|null, unit_label: string}
 */
abstract class TugDocument
{
    abstract public function jenis(): TugJenis;

    /**
     * The material columns of this machine's TUG.
     *
     * @return list<Column>
     */
    abstract protected function columns(Unit $unit, Machine $machine): array;

    /**
     * The amount of a column on a day, from the month's sheet.
     *
     * @return array{readings: callable(string, int): float, has_sheet: bool}
     */
    abstract protected function source(Unit $unit, Machine $machine, int $month, int $year): array;

    /**
     * @return array{
     *     jenis: string,
     *     machine: array{id: int, name: string, serial_number: string|null},
     *     period_label: string,
     *     header: array{nomor: string|null, pekerjaan: string, no_spk: string|null, cost_center: string|null, kode_perkiraan: string|null, tanggal_dokumen: string|null},
     *     header_saved: bool,
     *     columns: list<Column>,
     *     days: list<array{day: int, date: string, values: array<string, float>, total: float}>,
     *     totals: array<string, float>,
     *     grand_total: float,
     *     has_sheet: bool,
     *     signatories: array{manager: string|null, tl_operasi: string|null}
     * }
     */
    public function build(Unit $unit, Machine $machine, int $month, int $year): array
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $columns = $this->columns($unit, $machine);
        $source = $this->source($unit, $machine, $month, $year);

        $days = [];
        $totals = array_fill_keys(array_column($columns, 'key'), 0.0);
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $values = [];
            foreach ($columns as $column) {
                $value = round($source['readings']($column['key'], $day), 2);
                $values[$column['key']] = $value;
                $totals[$column['key']] += $value;
            }

            $days[] = [
                'day' => $day,
                'date' => Carbon::create($year, $month, $day)->toDateString(),
                'values' => $values,
                'total' => round(array_sum($values), 2),
            ];
        }

        $totals = array_map(fn (float $total): float => round($total, 2), $totals);
        [$header, $saved] = $this->header($machine, $month, $year);

        return [
            'jenis' => $this->jenis()->value,
            'machine' => ['id' => $machine->id, 'name' => $machine->name, 'serial_number' => $machine->serial_number],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'header' => $header,
            'header_saved' => $saved,
            'columns' => $columns,
            'days' => $days,
            'totals' => $totals,
            'grand_total' => round(array_sum($totals), 2),
            'has_sheet' => $source['has_sheet'],
            'signatories' => [
                'manager' => $unit->manager()?->name,
                'tl_operasi' => $unit->employees()->where('is_active', true)->where('position', 'like', '%Team Leader Operasi%')->orderBy('id')->value('name'),
            ],
        ];
    }

    /**
     * The saved header of the TUG, or one carried over from the machine's
     * previous TUG of the same jenis (pekerjaan, cost center, kode perkiraan)
     * dated the first day of the following month.
     *
     * @return array{0: array{nomor: string|null, pekerjaan: string, no_spk: string|null, cost_center: string|null, kode_perkiraan: string|null, tanggal_dokumen: string|null}, 1: bool}
     */
    private function header(Machine $machine, int $month, int $year): array
    {
        $tugs = OperasiTug::query()->where('machine_id', $machine->id)->where('jenis', $this->jenis());
        $tug = (clone $tugs)->where('month', $month)->where('year', $year)->first();

        if ($tug !== null) {
            return [[
                'nomor' => $tug->nomor,
                'pekerjaan' => $tug->pekerjaan,
                'no_spk' => $tug->no_spk,
                'cost_center' => $tug->cost_center,
                'kode_perkiraan' => $tug->kode_perkiraan,
                'tanggal_dokumen' => $tug->tanggal_dokumen?->toDateString(),
            ], true];
        }

        $previous = $tugs
            ->where(fn ($query) => $query->where('year', '<', $year)->orWhere(fn ($q) => $q->where('year', $year)->where('month', '<', $month)))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        return [[
            'nomor' => null,
            'pekerjaan' => $previous->pekerjaan ?? 'RUTIN',
            'no_spk' => null,
            'cost_center' => $previous?->cost_center,
            'kode_perkiraan' => $previous?->kode_perkiraan,
            'tanggal_dokumen' => Carbon::create($year, $month, 1)->addMonth()->toDateString(),
        ], false];
    }
}
