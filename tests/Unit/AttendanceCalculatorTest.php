<?php

namespace Tests\Unit;

use App\Enums\AttendanceCodeType;
use App\Models\AttendanceCode;
use App\Models\EmployeePresence;
use App\Models\WorkScheduleEntry;
use App\Services\Operator\AttendanceCalculator;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The Jadwal Shift is the plan; attendance comes only from the presensi
 * (absen masuk of the employee's account).
 */
class AttendanceCalculatorTest extends TestCase
{
    private function code(int $id, string $code, bool $present, AttendanceCodeType $type = AttendanceCodeType::Shift): AttendanceCode
    {
        $model = new AttendanceCode(['code' => $code, 'label' => $code, 'type' => $type, 'hitung_hadir' => $present]);
        $model->id = $id;

        return $model;
    }

    private function entry(int $employeeId, string $date, ?int $codeId): WorkScheduleEntry
    {
        return new WorkScheduleEntry(['employee_id' => $employeeId, 'work_date' => $date, 'attendance_code_id' => $codeId]);
    }

    private function presence(int $employeeId, string $date, ?int $lateMinutes = null): EmployeePresence
    {
        return new EmployeePresence(['employee_id' => $employeeId, 'work_date' => $date, 'late_minutes' => $lateMinutes]);
    }

    /**
     * @return Collection<int, AttendanceCode>
     */
    private function codes(): Collection
    {
        return new Collection([
            1 => $this->code(1, 'P', true),
            2 => $this->code(2, 'OFF', false),
            3 => $this->code(3, 'SKT', false, AttendanceCodeType::Absence),
        ]);
    }

    public function test_a_scheduled_shift_counts_as_hadir_only_with_an_absen_masuk(): void
    {
        $entries = new Collection([
            $this->entry(10, '2026-08-01', 1), // absen → hadir
            $this->entry(10, '2026-08-02', 1), // absen, late → terlambat
            $this->entry(10, '2026-08-03', 1), // no absen, past → tidak hadir
            $this->entry(10, '2026-08-04', 2), // OFF, no absen → nothing
            $this->entry(10, '2026-08-05', 3), // sakit → nothing
            $this->entry(10, '2026-08-10', 1), // today, no absen → menunggu
            $this->entry(10, '2026-08-11', 1), // future → nothing
            $this->entry(10, '2026-08-12', null), // blank cell, ignored
        ]);
        $presences = new Collection([
            $this->presence(10, '2026-08-01'),
            $this->presence(10, '2026-08-02', 12),
        ]);

        $summary = (new AttendanceCalculator)->summarise($entries, $this->codes(), $presences, '2026-08-10')['per_employee'][10];

        $this->assertSame(['P' => 5, 'OFF' => 1, 'SKT' => 1], $summary['counts']);
        $this->assertSame([
            1 => AttendanceCalculator::HADIR,
            2 => AttendanceCalculator::TERLAMBAT,
            3 => AttendanceCalculator::TIDAK_HADIR,
            10 => AttendanceCalculator::MENUNGGU,
        ], $summary['days']);
        $this->assertSame(2, $summary['hadir']);
        $this->assertSame(1, $summary['terlambat']);
        $this->assertSame(1, $summary['tidak_hadir']);
        // 3 working days due (1, 2, 3; today not yet), 2 attended.
        $this->assertSame(3, $summary['jadwal_kerja']);
        $this->assertEqualsWithDelta(0.6667, $summary['percent'], 0.0001);
    }

    public function test_a_generated_schedule_without_any_absen_is_not_attendance(): void
    {
        $entries = new Collection(array_map(fn (int $d): WorkScheduleEntry => $this->entry(5, sprintf('2026-08-%02d', $d), 1), range(1, 6)));

        $summary = (new AttendanceCalculator)->summarise($entries, $this->codes(), new Collection, '2026-08-31')['per_employee'][5];

        $this->assertSame(0, $summary['hadir']);
        $this->assertSame(6, $summary['tidak_hadir']);
        $this->assertSame(0.0, $summary['percent']);
    }

    public function test_an_absen_on_a_day_off_or_unscheduled_day_is_hadir_di_luar_jadwal(): void
    {
        $entries = new Collection([$this->entry(7, '2026-08-04', 2)]);
        $presences = new Collection([$this->presence(7, '2026-08-04'), $this->presence(7, '2026-08-09')]);

        $summary = (new AttendanceCalculator)->summarise($entries, $this->codes(), $presences, '2026-08-31')['per_employee'][7];

        $this->assertSame([4 => AttendanceCalculator::DI_LUAR_JADWAL, 9 => AttendanceCalculator::DI_LUAR_JADWAL], $summary['days']);
        $this->assertSame(2, $summary['hadir']);
        $this->assertSame(2, $summary['di_luar_jadwal']);
        $this->assertNull($summary['percent']);
    }
}
