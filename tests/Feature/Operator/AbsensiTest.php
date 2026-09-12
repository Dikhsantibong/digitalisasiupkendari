<?php

namespace Tests\Feature\Operator;

use App\Enums\RoleName;
use App\Models\AttendanceCode;
use App\Models\Employee;
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
        return Employee::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'regu' => $regu]);
    }

    public function test_a_role_without_absensi_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, Unit::factory()->create()))
            ->get(route('operator.absensi.index'))
            ->assertForbidden();
    }

    public function test_the_project_leader_sees_the_schedule_grid(): void
    {
        $unit = Unit::factory()->create();
        $this->shiftEmployee($unit);

        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $unit))
            ->get(route('operator.absensi.index', [
                'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'group_type' => 'shift',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operator/absensi')
                ->where('can_write', true)
                ->has('employees', 1)
                ->has('days', 31)
                ->has('codes', 8));
    }

    public function test_the_operator_can_view_but_not_write(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operator.absensi.index', [
                'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'group_type' => 'shift',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('operator.absensi.store'), [
                'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'group_type' => 'shift', 'cells' => [],
            ])
            ->assertForbidden();
    }

    public function test_the_project_leader_saves_cells(): void
    {
        $unit = Unit::factory()->create();
        $employee = $this->shiftEmployee($unit);
        $pagi = AttendanceCode::query()->where('code', 'P')->first();

        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $unit))
            ->post(route('operator.absensi.store'), [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'group_type' => 'shift',
                'cells' => [
                    ['employee_id' => $employee->id, 'day' => 1, 'code' => 'P'],
                    ['employee_id' => $employee->id, 'day' => 2, 'code' => 'OFF'],
                ],
            ])
            ->assertRedirect();

        $schedule = WorkSchedule::query()->where('unit_id', $unit->id)
            ->where('year', 2026)->where('month', 8)->where('group_type', 'shift')->first();
        $this->assertNotNull($schedule);

        $entries = $schedule->entries()->get();
        $this->assertCount(2, $entries);

        $first = $entries->first(fn ($e) => $e->work_date->toDateString() === '2026-08-01');
        $this->assertNotNull($first);
        $this->assertSame($pagi->id, $first->attendance_code_id);
        $this->assertSame('A', $first->regu);
    }

    public function test_unknown_codes_are_cleared_on_save(): void
    {
        $unit = Unit::factory()->create();
        $employee = $this->shiftEmployee($unit);

        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $unit))
            ->post(route('operator.absensi.store'), [
                'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'group_type' => 'shift',
                'cells' => [['employee_id' => $employee->id, 'day' => 1, 'code' => 'ZZZ']],
            ])
            ->assertRedirect();

        $schedule = WorkSchedule::query()->where('unit_id', $unit->id)->first();
        $this->assertNull($schedule->entries()->first()->attendance_code_id);
    }

    public function test_generate_fills_the_month_from_the_regu_pattern(): void
    {
        $unit = Unit::factory()->create();
        $employee = $this->shiftEmployee($unit, 'A');
        ShiftPattern::factory()->forUnit($unit)->create(['regu' => 'A', 'sequence' => 'P,S,M,OFF']);

        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $unit))
            ->post(route('operator.absensi.generate'), [
                'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'group_type' => 'shift', 'start_day' => 1,
            ])
            ->assertRedirect();

        $schedule = WorkSchedule::query()->where('unit_id', $unit->id)->first();
        $this->assertNotNull($schedule->generated_at);
        // A 31-day month fully filled for the one shift employee.
        $this->assertSame(31, $schedule->entries()->where('employee_id', $employee->id)->count());

        $pagi = AttendanceCode::query()->where('code', 'P')->first();
        $day1 = $schedule->entries()->where('employee_id', $employee->id)->get()
            ->first(fn ($e) => $e->work_date->toDateString() === '2026-08-01');
        $this->assertSame($pagi->id, $day1->attendance_code_id);
    }

    public function test_a_user_cannot_write_a_schedule_for_a_unit_they_cannot_access(): void
    {
        $ownUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();
        $employee = $this->shiftEmployee($otherUnit);

        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $ownUnit))
            ->post(route('operator.absensi.store'), [
                'unit_id' => $otherUnit->id, 'year' => 2026, 'month' => 8, 'group_type' => 'shift',
                'cells' => [['employee_id' => $employee->id, 'day' => 1, 'code' => 'P']],
            ])
            ->assertForbidden();
    }
}
