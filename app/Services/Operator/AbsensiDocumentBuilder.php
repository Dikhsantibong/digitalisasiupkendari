<?php

namespace App\Services\Operator;

use App\Enums\ScheduleGroupType;
use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Unit;
use App\Models\WorkSchedule;
use App\Support\Indonesian;
use Illuminate\Support\Carbon;

/**
 * Assembles the monthly attendance payload of one roster (header, calendar of
 * shift codes per employee, recap & attendance %, per-code totals). Consumed by
 * the Laporan Operasi, which embeds the Kerja Shift roster.
 */
class AbsensiDocumentBuilder
{
    public function __construct(
        private readonly AttendanceCalculator $calculator,
        private readonly AttendanceRoster $roster,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Unit $unit, int $month, int $year, ScheduleGroupType $groupType): array
    {
        $unit->loadMissing('serviceUnit:id,name');

        $codes = AttendanceCode::query()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get();

        $employees = $this->roster->employees($unit, $groupType);

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
}
