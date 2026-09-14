<?php

namespace App\Services\Operator;

use App\Enums\ScheduleGroupType;
use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Unit;
use App\Models\WorkSchedule;
use App\Services\Operasi\DocumentGridBuilder;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;

/**
 * Assembles the Operator attendance/schedule report document: the report payload
 * (header, calendar grid of shift codes per employee, recap & attendance %,
 * per-code totals), the rich-text body, the spreadsheet grid, and the letterhead
 * — mirroring the other report-document builders.
 */
class AbsensiDocumentBuilder
{
    public function __construct(
        private readonly AttendanceCalculator $calculator,
        private readonly DocumentGridBuilder $grids,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Unit $unit, int $month, int $year, ScheduleGroupType $groupType): array
    {
        $unit->loadMissing('serviceUnit:id,name');

        $codes = AttendanceCode::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get();

        $employees = Employee::query()
            ->where('unit_id', $unit->id)->where('is_active', true)
            ->when(
                $groupType === ScheduleGroupType::Shift,
                fn ($q) => $q->whereNotNull('regu'),
                fn ($q) => $q->whereNull('regu'),
            )
            ->orderByRaw('regu is null, regu')->orderBy('name')
            ->get(['id', 'name', 'nip', 'position', 'regu']);

        $schedule = WorkSchedule::query()->with('entries')
            ->where('unit_id', $unit->id)->where('year', $year)
            ->where('month', $month)->where('group_type', $groupType->value)->first();

        $entries = $schedule?->entries ?? collect();
        $codeById = $codes->keyBy('id');

        $cells = [];
        foreach ($entries as $entry) {
            if ($entry->attendance_code_id === null) {
                continue;
            }
            $code = $codeById->get($entry->attendance_code_id)?->code;
            if ($code !== null) {
                $cells[$entry->employee_id][(int) $entry->work_date->day] = $code;
            }
        }

        $recap = $this->calculator->summarise($entries, $codeById);

        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $holidayDates = Holiday::query()->whereYear('date', $year)->whereMonth('date', $month)->get(['date']);

        $days = collect(range(1, $daysInMonth))->map(function (int $day) use ($year, $month, $holidayDates): array {
            $date = Carbon::create($year, $month, $day);

            return [
                'day' => $day,
                'dow' => ['M', 'S', 'S', 'R', 'K', 'J', 'S'][$date->dayOfWeek],
                'is_weekend' => $date->isSunday(),
                'is_holiday' => $holidayDates->contains(fn ($h): bool => $h->date->day === $day),
            ];
        })->all();

        $periodLabel = Indonesian::monthName($month).' '.$year;

        return [
            'unit' => ['name' => $unit->name, 'service_unit' => $unit->serviceUnit?->name],
            'period' => ['label' => $groupType->label().' · '.$periodLabel],
            'period_label' => $periodLabel,
            'group_type' => $groupType->value,
            'group_label' => $groupType->label(),
            'month' => $month,
            'year' => $year,
            'days' => $days,
            'employees' => $employees->map(fn (Employee $e): array => [
                'id' => $e->id,
                'nip' => $e->nip,
                'name' => $e->name,
                'regu' => $e->regu,
                'cells' => $cells[$e->id] ?? [],
                'recap' => $recap['per_employee'][$e->id]['counts'] ?? [],
                'percent' => $recap['per_employee'][$e->id]['percent'] ?? null,
            ])->all(),
            'codes' => $codes->map(fn (AttendanceCode $c): array => ['code' => $c->code, 'label' => $c->label])->all(),
            'totals' => $recap['totals'],
            'document_number' => sprintf('ABS-OPS/%s/%02d-%d/%s', $unit->code ?? $unit->id, $month, $year, $groupType->value),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function bodyHtml(array $data): string
    {
        return View::make('operator.laporan.absensi-body', ['report' => $data])->render();
    }

    public function contentStyles(): string
    {
        return View::make('operator.laporan.styles')->render();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function letterhead(array $data): string
    {
        return View::make('operasi.laporan.partials.letterhead', [
            'report' => $data,
            'title' => 'Laporan Absensi & Jadwal Kerja Shift',
        ])->render();
    }

    /**
     * Excel grid: NIP | Nama | Regu | day 1..N | code totals | % Hadir.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function grid(array $data): array
    {
        $days = $data['days'];
        $codes = $data['codes'];
        $cols = 3 + count($days) + count($codes) + 1;

        $header = [$this->c('NIP', true, 'c'), $this->c('Nama', true, 'c'), $this->c('Regu', true, 'c')];
        foreach ($days as $d) {
            $header[] = $this->c((string) $d['day'], true, 'c');
        }
        foreach ($codes as $c) {
            $header[] = $this->c($c['code'], true, 'c');
        }
        $header[] = $this->c('% Hadir', true, 'c');

        $rows = [$header];
        foreach ($data['employees'] as $e) {
            $cells = [$this->c((string) ($e['nip'] ?? ''), false, 'l'), $this->c($e['name'], false, 'l'), $this->c((string) ($e['regu'] ?? ''), false, 'c')];
            foreach ($days as $d) {
                $cells[] = $this->c((string) ($e['cells'][$d['day']] ?? ''), false, 'c');
            }
            foreach ($codes as $c) {
                $cells[] = $this->c((string) ($e['recap'][$c['code']] ?? ''), false, 'c');
            }
            $cells[] = $this->c($e['percent'] !== null ? round($e['percent'] * 100).'%' : '', false, 'c');
            $rows[] = $cells;
        }

        return [
            'name' => 'Absensi',
            'cols' => $cols,
            'col_widths' => array_merge([90, 160, 45], array_fill(0, count($days), 26), array_fill(0, count($codes), 30), [55]),
            'merges' => [],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $grid
     */
    public function gridToHtml(array $grid): string
    {
        return $this->grids->gridToHtml($grid);
    }

    /**
     * @return array{t: string, b?: bool, a?: string}
     */
    private function c(string $text, bool $bold = false, string $align = 'l'): array
    {
        $cell = ['t' => $text];
        if ($bold) {
            $cell['b'] = true;
        }
        if ($align !== 'l') {
            $cell['a'] = $align;
        }

        return $cell;
    }
}
