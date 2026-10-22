<?php

namespace Tests\Feature\Operator;

use App\Enums\AttendanceRequestType;
use App\Enums\RoleName;
use App\Models\AttendanceCode;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\EmployeePresence;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSchedule;
use Database\Seeders\AttendanceCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Resume Statistik: kehadiran against the Jadwal Shift, tukar shift and the
 * work saved per operator, with the day-by-day realisasi shift — every
 * operator for the supervisors, only their own for an operator.
 */
class ResumeStatistikTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private User $userA;

    private Employee $operatorA;

    private User $userB;

    private Employee $operatorB;

    /** @var array{unit_id: int, month: int, year: int} */
    private array $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(AttendanceCodeSeeder::class);
        $this->travelTo('2026-08-20 09:00:00');

        $this->unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Uji']);
        $this->period = ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026];
        [$this->userA, $this->operatorA] = $this->operator('Andi', 'A');
        [$this->userB, $this->operatorB] = $this->operator('Budi', 'B');
    }

    public function test_an_operator_sees_their_own_kehadiran_and_work(): void
    {
        // Jadwal: three working shifts (one attended on time, one late, one missed), an OFF and a cuti.
        $this->schedule($this->operatorA, ['2026-08-03' => 'P', '2026-08-04' => 'S', '2026-08-05' => 'M', '2026-08-06' => 'OFF', '2026-08-07' => 'C']);
        $this->presence($this->operatorA, '2026-08-03', '07:55', '16:05');
        $this->presence($this->operatorA, '2026-08-04', '16:20', null, late: 20);

        // Work saved under the operator's account.
        $this->travelTo('2026-08-03 10:15:00');
        $this->actingAs($this->userA)->post(route('operasi.input.flm-monitoring.store'), $this->period + ['rows' => [
            ['mesin' => 'Pompa BBM', 'tanggal' => '2026-08-03', 'masalah' => 'Bocor', 'status' => 'open'],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->userA)->post(route('operasi.input.kondisi-abnormal.store'), $this->period + ['rows' => [
            ['no_urut' => 1, 'uraian_kondisi' => 'Exhaust tinggi', 'tanggal' => '2026-08-03', 'is_abnormal' => 1, 'durasi_abnormal' => 1],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->travelTo('2026-08-20 09:00:00');

        // An approved tukar shift: A worked for B on the 10th.
        AttendanceRequest::factory()->approved()->create([
            'unit_id' => $this->unit->id, 'employee_id' => $this->operatorB->id, 'substitute_employee_id' => $this->operatorA->id,
            'type' => AttendanceRequestType::TukarShift, 'start_date' => '2026-08-10', 'end_date' => '2026-08-10',
            'applied' => [
                ['employee_id' => $this->operatorB->id, 'date' => '2026-08-10', 'from' => 'P', 'to' => 'OFF'],
                ['employee_id' => $this->operatorA->id, 'date' => '2026-08-10', 'from' => 'OFF', 'to' => 'P'],
            ],
        ]);

        $this->actingAs($this->userA)
            // An operator cannot open a colleague's resume.
            ->get(route('operator.resume.index', $this->period + ['employee_id' => $this->operatorB->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operator/resume/index')
                ->where('sees_all', false)
                ->has('employees', 1)
                ->where('employees.0.name', 'Andi')
                ->where('selected.id', $this->operatorA->id)
                ->where('employees.0.kehadiran.jadwal_kerja', 3)
                ->where('employees.0.kehadiran.hadir', 2)
                ->where('employees.0.kehadiran.terlambat', 1)
                ->where('employees.0.kehadiran.tidak_hadir', 1)
                ->where('employees.0.kehadiran.shift', ['P' => 1, 'S' => 1, 'M' => 1, 'OFF' => 1])
                ->where('employees.0.kehadiran.absen.C', 1)
                ->where('employees.0.kehadiran.percent', fn ($percent): bool => abs((float) $percent - 2 / 3) < 0.001)
                ->where('employees.0.tukar_shift', ['menggantikan' => 1, 'digantikan' => 0])
                ->where('employees.0.pekerjaan.flm', 1)
                ->where('employees.0.pekerjaan.kondisi_abnormal', 1)
                ->where('employees.0.pekerjaan.unsafe', 0)
                ->where('employees.0.pekerjaan.aktivitas', 2)
                // Day by day: the 3rd attended with the saves of that day, the 4th late, the 5th missed.
                ->has('days', 31)
                ->where('days.2.code', 'P')
                ->where('days.2.status', 'hadir')
                ->where('days.2.check_in', '07:55')
                ->where('days.2.check_out', '16:05')
                ->where('days.2.aktivitas.0.time', '18:15')
                ->where('days.2.aktivitas', fn ($activities): bool => count($activities) === 2 && str_contains($activities[0]['description'], 'Monitoring FLM'))
                ->where('days.3.status', 'terlambat')
                ->where('days.3.late_minutes', 20)
                ->where('days.4.status', 'tidak_hadir')
                ->where('days.5.code', 'OFF')
                ->where('days.5.status', null)
                ->where('days.6.code', 'C'),
            );
    }

    public function test_the_supervisors_see_every_operator_and_pick_one(): void
    {
        $this->schedule($this->operatorB, ['2026-08-03' => 'P']);
        $this->presence($this->operatorB, '2026-08-03', '08:00', '16:00');
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);

        $this->actingAs($koordinator)->get(route('operator.resume.index', $this->period + ['employee_id' => $this->operatorB->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sees_all', true)
                ->has('employees', 2)
                ->where('selected.id', $this->operatorB->id)
                ->where('selected.kehadiran.hadir', 1)
                ->where('days.2.status', 'hadir'));

        // TL Operasi (read-only) also sees every operator; without a choice the first one is shown.
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit))->get(route('operator.resume.index', $this->period))
            ->assertInertia(fn ($page) => $page->has('employees', 2)->where('selected.id', fn ($id): bool => in_array($id, [$this->operatorA->id, $this->operatorB->id], true)));
    }

    public function test_access_is_limited_to_the_operator_module_and_the_unit(): void
    {
        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $this->unit))
            ->get(route('operator.resume.index', $this->period))
            ->assertForbidden();

        // An operator account not linked to an employee has no resume.
        $this->actingAs($this->userWithRole(RoleName::Operator, $this->unit))
            ->get(route('operator.resume.index', $this->period))
            ->assertForbidden();

        // Another unit's supervisor asking for this unit gets their own unit: this unit's operators stay hidden.
        $otherUnit = Unit::factory()->create(['is_active' => true]);
        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $otherUnit))
            ->get(route('operator.resume.index', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('unit.id', $otherUnit->id)->has('employees', 0)->where('days', []));
    }

    /**
     * @return array{0: User, 1: Employee}
     */
    private function operator(string $name, string $regu): array
    {
        $user = $this->userWithRole(RoleName::Operator, $this->unit);
        $employee = Employee::factory()->create(['unit_id' => $this->unit->id, 'is_active' => true, 'name' => $name, 'regu' => $regu, 'position' => 'Operator', 'user_id' => $user->id]);

        return [$user, $employee];
    }

    /**
     * @param  array<string, string>  $codes  Y-m-d => code
     */
    private function schedule(Employee $employee, array $codes): void
    {
        $schedule = WorkSchedule::query()->firstOrCreate(['unit_id' => $this->unit->id, 'year' => 2026, 'month' => 8, 'group_type' => 'shift']);

        foreach ($codes as $date => $code) {
            $schedule->entries()->create([
                'employee_id' => $employee->id, 'work_date' => $date, 'regu' => $employee->regu,
                'attendance_code_id' => AttendanceCode::query()->where('code', $code)->value('id'),
            ]);
        }
    }

    private function presence(Employee $employee, string $date, string $in, ?string $out, int $late = 0): void
    {
        $at = fn (string $time): Carbon => Carbon::parse("{$date} {$time}", 'Asia/Makassar')->utc();

        EmployeePresence::factory()->create([
            'unit_id' => $this->unit->id, 'employee_id' => $employee->id, 'user_id' => $employee->user_id, 'work_date' => $date,
            'check_in_at' => $at($in), 'check_out_at' => $out !== null ? $at($out) : null, 'late_minutes' => $late,
        ]);
    }
}
