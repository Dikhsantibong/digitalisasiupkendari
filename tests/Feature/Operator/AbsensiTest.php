<?php

namespace Tests\Feature\Operator;

use App\Enums\RoleName;
use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\ShiftPattern;
use App\Models\Unit;
use App\Models\WorkSchedule;
use Database\Seeders\AttendanceCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class AbsensiTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(AttendanceCodeSeeder::class);
    }

    private function shiftEmployee(Unit $unit, string $regu = 'A'): Employee
    {
        return Employee::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'regu' => $regu, 'position' => 'Operator']);
    }

    private function nonShiftEmployee(Unit $unit, string $position = 'Project Leader'): Employee
    {
        return Employee::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'regu' => null, 'position' => $position]);
    }

    /**
     * The code stored for an employee on a date, looked up via the schedule of the given group.
     */
    private function codeOn(Unit $unit, Employee $employee, string $date, string $group = 'shift'): ?string
    {
        $schedule = WorkSchedule::query()->where('unit_id', $unit->id)->where('group_type', $group)
            ->where('year', (int) substr($date, 0, 4))->where('month', (int) substr($date, 5, 2))->first();

        $entry = $schedule?->entries()->where('employee_id', $employee->id)->get()
            ->first(fn ($e) => $e->work_date->toDateString() === $date);

        return $entry?->attendance_code_id === null ? null : AttendanceCode::query()->find($entry->attendance_code_id)?->code;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function target(Unit $unit, array $extra = []): array
    {
        return ['unit_id' => $unit->id, 'year' => 2026, 'month' => 8, ...$extra];
    }

    public function test_a_role_without_absensi_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, Unit::factory()->create()))
            ->get(route('operator.absensi.index'))
            ->assertForbidden();
    }

    public function test_the_sheet_shows_operators_as_shift_and_leaders_as_non_shift(): void
    {
        $unit = Unit::factory()->create();
        $operator = $this->shiftEmployee($unit);
        $leader = $this->nonShiftEmployee($unit, 'Project Leader');
        $koordinator = $this->nonShiftEmployee($unit, 'Koordinator Operasi');
        // Staff outside the Non Shift jabatan list stay off the sheet.
        $this->nonShiftEmployee($unit, 'Staf Operasi');

        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $unit))
            ->get(route('operator.absensi.index', $this->target($unit)))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operator/absensi')
                ->where('can_write', true)
                ->where('period_label', 'Agustus 2026')
                ->has('days', 31)
                ->where('days.0.name', 'Sabtu')
                ->where('days.0.is_weekend', true)
                ->has('codes', 8)
                ->where('sections.0.key', 'shift')
                ->has('sections.0.employees', 1)
                ->where('sections.0.employees.0.id', $operator->id)
                ->where('sections.1.key', 'non_shift')
                ->has('sections.1.employees', 2)
                // Project Leader is listed before the Koordinator.
                ->where('sections.1.employees.0.id', $leader->id)
                ->where('sections.1.employees.1.id', $koordinator->id)
                ->has('patterns', 4));
    }

    public function test_the_operator_can_view_but_not_write(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operator.absensi.index', $this->target($unit)))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('operator.absensi.store'), $this->target($unit, ['cells' => []]))
            ->assertForbidden();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('operator.absensi.generate'), $this->target($unit))
            ->assertForbidden();
    }

    public function test_saved_cells_land_in_the_schedule_of_each_employees_roster(): void
    {
        $unit = Unit::factory()->create();
        $operator = $this->shiftEmployee($unit);
        $leader = $this->nonShiftEmployee($unit);
        $outsider = $this->nonShiftEmployee($unit, 'Staf Operasi');

        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $unit))
            ->post(route('operator.absensi.store'), $this->target($unit, [
                'cells' => [
                    ['employee_id' => $operator->id, 'day' => 1, 'code' => 'P'],
                    ['employee_id' => $operator->id, 'day' => 2, 'code' => 'OFF'],
                    ['employee_id' => $leader->id, 'day' => 3, 'code' => 'P'],
                    ['employee_id' => $outsider->id, 'day' => 3, 'code' => 'P'],
                ],
            ]))
            ->assertRedirect();

        $this->assertSame('P', $this->codeOn($unit, $operator, '2026-08-01'));
        $this->assertSame('OFF', $this->codeOn($unit, $operator, '2026-08-02'));
        $this->assertSame('P', $this->codeOn($unit, $leader, '2026-08-03', 'non_shift'));
        $this->assertNull($this->codeOn($unit, $outsider, '2026-08-03', 'non_shift'));

        $shift = WorkSchedule::query()->where('unit_id', $unit->id)->where('group_type', 'shift')->first();
        $this->assertSame('A', $shift->entries()->first()->regu);
        $this->assertSame(2, $shift->entries()->count());
    }

    public function test_a_blank_or_unknown_code_clears_the_cell(): void
    {
        $unit = Unit::factory()->create();
        $employee = $this->shiftEmployee($unit);
        $user = $this->userWithRole(RoleName::ProjectLeaderOperasi, $unit);

        $this->actingAs($user)->post(route('operator.absensi.store'), $this->target($unit, [
            'cells' => [['employee_id' => $employee->id, 'day' => 1, 'code' => 'P'], ['employee_id' => $employee->id, 'day' => 2, 'code' => 'S']],
        ]));

        $this->actingAs($user)->post(route('operator.absensi.store'), $this->target($unit, [
            'cells' => [['employee_id' => $employee->id, 'day' => 1, 'code' => null], ['employee_id' => $employee->id, 'day' => 2, 'code' => 'ZZZ']],
        ]))->assertRedirect();

        $this->assertNull($this->codeOn($unit, $employee, '2026-08-01'));
        $this->assertNull($this->codeOn($unit, $employee, '2026-08-02'));
    }

    public function test_generate_fills_shift_by_regu_and_non_shift_by_office_hours(): void
    {
        $unit = Unit::factory()->create();
        $reguA = $this->shiftEmployee($unit, 'A');
        $reguB = $this->shiftEmployee($unit, 'B');
        $leader = $this->nonShiftEmployee($unit);
        Holiday::factory()->create(['year' => 2026, 'date' => '2026-08-17', 'description' => 'Hari Kemerdekaan']);

        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $unit))
            ->post(route('operator.absensi.generate'), $this->target($unit))
            ->assertRedirect();

        // Default cycle OFF,OFF,S,S,P,P,M,M: regu A starts at OFF, regu B two steps behind at M.
        $this->assertSame('OFF', $this->codeOn($unit, $reguA, '2026-08-01'));
        $this->assertSame('S', $this->codeOn($unit, $reguA, '2026-08-03'));
        $this->assertSame('OFF', $this->codeOn($unit, $reguA, '2026-08-09'));
        $this->assertSame('M', $this->codeOn($unit, $reguB, '2026-08-01'));

        // Non Shift: Saturday OFF, Monday P, national holiday OFF.
        $this->assertSame('OFF', $this->codeOn($unit, $leader, '2026-08-01', 'non_shift'));
        $this->assertSame('P', $this->codeOn($unit, $leader, '2026-08-03', 'non_shift'));
        $this->assertSame('OFF', $this->codeOn($unit, $leader, '2026-08-17', 'non_shift'));

        $this->assertSame(31, WorkSchedule::query()->where('unit_id', $unit->id)->where('group_type', 'shift')->first()
            ->entries()->where('employee_id', $reguA->id)->count());
    }

    public function test_generate_continues_the_rotation_from_the_previous_month(): void
    {
        $unit = Unit::factory()->create();
        $employee = $this->shiftEmployee($unit, 'A');
        $user = $this->userWithRole(RoleName::ProjectLeaderOperasi, $unit);

        // July ends on P,P, so August 1 must carry on with M.
        $this->actingAs($user)->post(route('operator.absensi.store'), [
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 7,
            'cells' => [['employee_id' => $employee->id, 'day' => 30, 'code' => 'P'], ['employee_id' => $employee->id, 'day' => 31, 'code' => 'P']],
        ]);

        $this->actingAs($user)->post(route('operator.absensi.generate'), $this->target($unit))->assertRedirect();

        $this->assertSame('M', $this->codeOn($unit, $employee, '2026-08-01'));
        $this->assertSame('M', $this->codeOn($unit, $employee, '2026-08-02'));
        $this->assertSame('OFF', $this->codeOn($unit, $employee, '2026-08-03'));
    }

    public function test_generate_keeps_absence_codes_and_follows_the_unit_pattern(): void
    {
        $unit = Unit::factory()->create();
        $employee = $this->shiftEmployee($unit, 'A');
        ShiftPattern::factory()->forUnit($unit)->create(['regu' => 'A', 'sequence' => 'P,S,M,OFF']);
        $user = $this->userWithRole(RoleName::ProjectLeaderOperasi, $unit);

        $this->actingAs($user)->post(route('operator.absensi.store'), $this->target($unit, [
            'cells' => [['employee_id' => $employee->id, 'day' => 5, 'code' => 'SKT']],
        ]));

        $this->actingAs($user)->post(route('operator.absensi.generate'), $this->target($unit))->assertRedirect();

        $this->assertSame('P', $this->codeOn($unit, $employee, '2026-08-01'));
        $this->assertSame('OFF', $this->codeOn($unit, $employee, '2026-08-04'));
        $this->assertSame('SKT', $this->codeOn($unit, $employee, '2026-08-05'));
        $this->assertNotNull(WorkSchedule::query()->where('unit_id', $unit->id)->where('group_type', 'shift')->first()->generated_at);
    }

    public function test_a_user_cannot_write_a_schedule_for_a_unit_they_cannot_access(): void
    {
        $ownUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $employee = $this->shiftEmployee($otherUnit);

        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $ownUnit))
            ->post(route('operator.absensi.store'), $this->target($otherUnit, [
                'cells' => [['employee_id' => $employee->id, 'day' => 1, 'code' => 'P']],
            ]))
            ->assertForbidden();
    }
}
