<?php

namespace Tests\Feature\Operator;

use App\Enums\RoleName;
use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\EmployeePresence;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Operator\PresenceRecorder;
use Database\Seeders\AttendanceCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PresensiTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    /** Office pin used by every test (PLTD Poasia area). */
    private const OFFICE = ['latitude' => -3.9778, 'longitude' => 122.5150];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(AttendanceCodeSeeder::class);
    }

    /**
     * Put the employee on a Jadwal Shift code for a date.
     */
    private function schedule(Employee $employee, string $date, string $code): void
    {
        $schedule = WorkSchedule::query()->firstOrCreate([
            'unit_id' => $employee->unit_id,
            'year' => (int) substr($date, 0, 4),
            'month' => (int) substr($date, 5, 2),
            'group_type' => 'shift',
        ]);

        $schedule->entries()->create([
            'employee_id' => $employee->id,
            'work_date' => $date,
            'attendance_code_id' => AttendanceCode::query()->where('code', $code)->value('id'),
            'regu' => $employee->regu,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * An operator whose account is linked to an employee of a unit with an office pin.
     *
     * @return array{0: User, 1: Employee, 2: Unit}
     */
    private function operator(bool $withLocation = true, RoleName $role = RoleName::Operator): array
    {
        $unit = Unit::factory()->create($withLocation ? [...self::OFFICE, 'attendance_radius_m' => 300] : []);
        $user = $this->userWithRole($role, $unit);
        $employee = Employee::factory()->create(['unit_id' => $unit->id, 'user_id' => $user->id, 'is_active' => true, 'regu' => 'A', 'position' => 'Operator']);

        return [$user, $employee, $unit];
    }

    /**
     * A position roughly $metres north of the office (1° latitude ≈ 111 195 m).
     * Tests not about the schedule carry a note, since an unscheduled absen needs one.
     *
     * @return array{latitude: float, longitude: float, accuracy: int, note?: string}
     */
    private function metresNorth(int $metres, ?string $note = 'uji'): array
    {
        $position = ['latitude' => self::OFFICE['latitude'] + $metres / 111_195, 'longitude' => self::OFFICE['longitude'], 'accuracy' => 8];

        return $note === null ? $position : [...$position, 'note' => $note];
    }

    public function test_distance_is_measured_with_the_haversine_formula(): void
    {
        $north = $this->metresNorth(250);

        $distance = PresenceRecorder::distanceMeters(self::OFFICE['latitude'], self::OFFICE['longitude'], $north['latitude'], $north['longitude']);

        $this->assertEqualsWithDelta(250, $distance, 1);
    }

    public function test_a_role_without_presensi_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, Unit::factory()->create()))
            ->get(route('operator.presensi.index'))
            ->assertForbidden();
    }

    public function test_the_page_shows_the_office_and_todays_state(): void
    {
        [$user] = $this->operator();

        $this->actingAs($user)
            ->get(route('operator.presensi.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operator/presensi')
                ->where('office.radius_m', 300)
                ->where('office.latitude', self::OFFICE['latitude'])
                ->where('today', null)
                ->where('open', null));
    }

    public function test_the_operator_checks_in_and_out_within_the_radius(): void
    {
        Carbon::setTestNow('2026-09-25 00:05:00'); // 08:05 WITA
        [$user, $employee, $unit] = $this->operator();

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(120))
            ->assertRedirect()->assertSessionHasNoErrors();

        $presence = EmployeePresence::query()->where('employee_id', $employee->id)->sole();
        $this->assertSame('2026-09-25', $presence->work_date->toDateString());
        $this->assertSame($unit->id, $presence->unit_id);
        $this->assertEqualsWithDelta(120, $presence->check_in_distance_m, 2);
        $this->assertNull($presence->check_out_at);

        Carbon::setTestNow('2026-09-25 08:10:00'); // 16:10 WITA
        $this->actingAs($user)->post(route('operator.presensi.check-out'), $this->metresNorth(40))
            ->assertRedirect()->assertSessionHasNoErrors();

        $presence->refresh();
        $this->assertNotNull($presence->check_out_at);
        $this->assertEqualsWithDelta(40, $presence->check_out_distance_m, 2);
    }

    public function test_a_check_in_outside_the_radius_is_rejected(): void
    {
        [$user] = $this->operator();

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(450))
            ->assertSessionHasErrors('location');

        $this->assertSame(0, EmployeePresence::query()->count());
    }

    public function test_the_radius_follows_the_unit_setting(): void
    {
        [$user, , $unit] = $this->operator();
        $unit->update(['attendance_radius_m' => 500]);

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(450))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, EmployeePresence::query()->count());
    }

    public function test_only_one_check_in_per_day(): void
    {
        Carbon::setTestNow('2026-09-25 00:05:00');
        [$user] = $this->operator();

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(10));
        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(10))
            ->assertSessionHasErrors('presence');

        Carbon::setTestNow('2026-09-25 08:00:00');
        $this->actingAs($user)->post(route('operator.presensi.check-out'), $this->metresNorth(10));
        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(10))
            ->assertSessionHasErrors('presence');

        $this->assertSame(1, EmployeePresence::query()->count());
    }

    public function test_check_out_needs_an_open_check_in(): void
    {
        [$user] = $this->operator();

        $this->actingAs($user)->post(route('operator.presensi.check-out'), $this->metresNorth(10))
            ->assertSessionHasErrors('presence');
    }

    public function test_a_sore_shift_checks_out_after_midnight_on_the_same_record(): void
    {
        Carbon::setTestNow('2026-09-25 07:55:00'); // 15:55 WITA
        [$user, $employee] = $this->operator();

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(10));

        Carbon::setTestNow('2026-09-25 16:10:00'); // 00:10 WITA next day
        $this->actingAs($user)->post(route('operator.presensi.check-out'), $this->metresNorth(10))
            ->assertSessionHasNoErrors();

        $presence = EmployeePresence::query()->where('employee_id', $employee->id)->sole();
        $this->assertSame('2026-09-25', $presence->work_date->toDateString());
        $this->assertNotNull($presence->check_out_at);
    }

    public function test_presence_is_refused_when_the_unit_has_no_office_location(): void
    {
        [$user] = $this->operator(withLocation: false);

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(10))
            ->assertSessionHasErrors('presence');
    }

    public function test_an_account_without_an_employee_record_cannot_check_in(): void
    {
        $user = $this->userWithRole(RoleName::Operator, Unit::factory()->create(self::OFFICE));

        $this->actingAs($user)->get(route('operator.presensi.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('employee', null));

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(10))
            ->assertSessionHasErrors('presence');
    }

    public function test_the_page_shows_the_accounts_shift_for_today(): void
    {
        Carbon::setTestNow('2026-09-25 00:05:00'); // 08:05 WITA
        [$user, $employee] = $this->operator();
        $employee->update(['is_shift_leader' => true]);
        $this->schedule($employee, '2026-09-25', 'P');

        $this->actingAs($user)->get(route('operator.presensi.index'))
            ->assertInertia(fn ($page) => $page
                ->where('employee.regu', 'A')
                ->where('employee.is_shift_leader', true)
                ->where('shift.work_date', '2026-09-25')
                ->where('shift.code', 'P')
                ->where('shift.is_working', true));
    }

    public function test_a_scheduled_shift_needs_no_note_and_lateness_is_recorded(): void
    {
        Carbon::setTestNow('2026-09-25 00:12:00'); // 08:12 WITA, Pagi starts 08:00
        [$user, $employee] = $this->operator();
        $this->schedule($employee, '2026-09-25', 'P');

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(20, note: null))
            ->assertSessionHasNoErrors();

        $presence = EmployeePresence::query()->where('employee_id', $employee->id)->sole();
        $this->assertSame('P', $presence->shift_code);
        $this->assertSame(12, $presence->late_minutes);
        $this->assertNull($presence->check_in_note);
    }

    public function test_arriving_before_the_shift_starts_is_on_time(): void
    {
        Carbon::setTestNow('2026-09-25 07:45:00'); // 15:45 WITA, Sore starts 16:00
        [$user, $employee] = $this->operator();
        $this->schedule($employee, '2026-09-25', 'S');

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(20, note: null));

        $this->assertSame(0, EmployeePresence::query()->where('employee_id', $employee->id)->sole()->late_minutes);
    }

    public function test_an_absen_on_an_off_day_requires_a_note(): void
    {
        Carbon::setTestNow('2026-09-25 00:05:00');
        [$user, $employee] = $this->operator();
        $this->schedule($employee, '2026-09-25', 'OFF');

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(20, note: null))
            ->assertSessionHasErrors('note');
        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(20, note: '  '))
            ->assertSessionHasErrors('note');
        $this->assertSame(0, EmployeePresence::query()->count());

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(20, note: 'Ganti shift dengan Rian (Regu B)'))
            ->assertSessionHasNoErrors();

        $presence = EmployeePresence::query()->where('employee_id', $employee->id)->sole();
        $this->assertSame('OFF', $presence->shift_code);
        $this->assertNull($presence->late_minutes);
        $this->assertSame('Ganti shift dengan Rian (Regu B)', $presence->check_in_note);
    }

    public function test_an_unscheduled_day_also_requires_a_note(): void
    {
        [$user] = $this->operator();

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(20, note: null))
            ->assertSessionHasErrors('note');
    }

    public function test_an_evening_absen_before_a_malam_shift_belongs_to_the_next_day(): void
    {
        Carbon::setTestNow('2026-09-25 15:40:00'); // 23:40 WITA
        [$user, $employee] = $this->operator();
        $this->schedule($employee, '2026-09-26', 'M');

        $this->actingAs($user)->post(route('operator.presensi.check-in'), $this->metresNorth(20, note: null))
            ->assertSessionHasNoErrors();

        $presence = EmployeePresence::query()->where('employee_id', $employee->id)->sole();
        $this->assertSame('2026-09-26', $presence->work_date->toDateString());
        $this->assertSame('M', $presence->shift_code);
        $this->assertSame(0, $presence->late_minutes);
    }

    public function test_the_position_is_validated(): void
    {
        [$user] = $this->operator();

        $this->actingAs($user)->post(route('operator.presensi.check-in'), ['latitude' => 120, 'longitude' => 'x'])
            ->assertSessionHasErrors(['latitude', 'longitude']);
    }
}
