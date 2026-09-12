<?php

namespace Tests\Unit;

use App\Enums\AttendanceCodeType;
use App\Models\AttendanceCode;
use App\Models\WorkScheduleEntry;
use App\Services\Operator\AttendanceCalculator;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class AttendanceCalculatorTest extends TestCase
{
    private function code(int $id, string $code, bool $present, AttendanceCodeType $type = AttendanceCodeType::Shift): AttendanceCode
    {
        $model = new AttendanceCode(['code' => $code, 'label' => $code, 'type' => $type, 'hitung_hadir' => $present]);
        $model->id = $id;

        return $model;
    }

    private function entry(int $employeeId, ?int $codeId): WorkScheduleEntry
    {
        return new WorkScheduleEntry(['employee_id' => $employeeId, 'attendance_code_id' => $codeId]);
    }

    public function test_it_counts_codes_and_derives_the_attendance_percentage(): void
    {
        $codes = new Collection([
            1 => $this->code(1, 'P', true),
            2 => $this->code(2, 'OFF', false),
            3 => $this->code(3, 'SKT', false, AttendanceCodeType::Absence),
        ]);

        // 6 worked (P) + 2 OFF = 8 scheduled → 6/8 = 0.75. One SKT lowers it.
        $entries = new Collection([
            ...array_fill(0, 6, $this->entry(10, 1)),
            $this->entry(10, 2),
            $this->entry(10, 2),
            $this->entry(10, 3),
            $this->entry(10, null), // blank day, ignored
        ]);

        $result = (new AttendanceCalculator)->summarise($entries, $codes);

        $summary = $result['per_employee'][10];
        $this->assertSame(6, $summary['counts']['P']);
        $this->assertSame(2, $summary['counts']['OFF']);
        $this->assertSame(1, $summary['counts']['SKT']);
        $this->assertSame(6, $summary['present']);
        $this->assertSame(9, $summary['scheduled']);
        $this->assertEqualsWithDelta(0.6667, $summary['percent'], 0.0001);
        $this->assertSame(6, $result['totals']['P']);
    }

    public function test_a_clean_rotation_lands_at_seventy_five_percent(): void
    {
        $codes = new Collection([
            1 => $this->code(1, 'P', true),
            2 => $this->code(2, 'OFF', false),
        ]);

        $entries = new Collection([
            ...array_fill(0, 6, $this->entry(5, 1)),
            ...array_fill(0, 2, $this->entry(5, 2)),
        ]);

        $result = (new AttendanceCalculator)->summarise($entries, $codes);

        $this->assertSame(0.75, $result['per_employee'][5]['percent']);
    }
}
