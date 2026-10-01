<?php

namespace Tests\Feature\Notifications;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\EmployeePresence;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleEntry;
use App\Services\Notifications\AttendanceReminders;
use App\Services\Operator\PresenceRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class AttendanceRemindersTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private User $operator;

    private Employee $employee;

    private AttendanceCode $pagi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->unit = Unit::factory()->create(['is_active' => true]);
        $this->operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $this->employee = Employee::factory()->create(['unit_id' => $this->unit->id, 'user_id' => $this->operator->id, 'position' => 'Operator', 'is_active' => true]);
        $this->pagi = AttendanceCode::factory()->create(['code' => 'P', 'label' => 'Pagi', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'hitung_hadir' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_reminds_before_the_shift_once(): void
    {
        $this->schedule('2026-10-05', $this->pagi);

        $this->sendAt('2026-10-05 07:35');
        $this->sendAt('2026-10-05 07:45');

        $this->assertSame(['absen-segera:2026-10-05'], $this->keysOf($this->operator));
        $this->assertStringContainsString('Shift Pagi (08:00–16:00)', $this->operator->notifications()->first()->data['title']);
        $this->assertSame('/operator/presensi', $this->operator->notifications()->first()->data['url']);
    }

    public function test_it_reminds_when_the_shift_started_without_absen_masuk(): void
    {
        $this->schedule('2026-10-05', $this->pagi);

        $this->sendAt('2026-10-05 08:10');
        $this->assertSame([], $this->keysOf($this->operator), 'No reminder in the first 15 minutes.');

        $this->sendAt('2026-10-05 08:20');
        $this->assertSame(['absen-masuk:2026-10-05'], $this->keysOf($this->operator));
    }

    public function test_no_absen_masuk_reminder_once_checked_in(): void
    {
        $this->schedule('2026-10-05', $this->pagi);
        $this->checkIn('2026-10-05');

        $this->sendAt('2026-10-05 07:40');
        $this->sendAt('2026-10-05 08:30');

        $this->assertSame([], $this->keysOf($this->operator));
    }

    public function test_it_reminds_to_check_out_after_the_shift(): void
    {
        $this->schedule('2026-10-05', $this->pagi);
        $this->checkIn('2026-10-05');

        $this->sendAt('2026-10-05 16:05');
        $this->assertSame([], $this->keysOf($this->operator));

        $this->sendAt('2026-10-05 16:20');
        $this->assertSame(['absen-pulang:2026-10-05'], $this->keysOf($this->operator));
    }

    public function test_it_tells_tomorrows_shift_in_the_evening(): void
    {
        $this->schedule('2026-10-06', $this->pagi);

        $this->sendAt('2026-10-05 18:30');
        $this->assertSame([], $this->keysOf($this->operator));

        $this->sendAt('2026-10-05 19:05');
        $this->assertSame(['shift-besok:2026-10-06'], $this->keysOf($this->operator));
    }

    public function test_a_shift_ending_at_midnight_ends_the_next_day(): void
    {
        $sore = AttendanceCode::factory()->create(['code' => 'S', 'label' => 'Sore', 'jam_mulai' => '16:00', 'jam_selesai' => '24:00', 'hitung_hadir' => true]);
        $this->schedule('2026-10-05', $sore);
        $this->checkIn('2026-10-05');

        $this->sendAt('2026-10-06 00:20');

        $this->assertSame(['absen-pulang:2026-10-05'], $this->keysOf($this->operator));
    }

    public function test_off_days_and_codes_without_a_start_time_get_nothing(): void
    {
        $off = AttendanceCode::factory()->create(['code' => 'OFF', 'label' => 'Libur', 'jam_mulai' => null, 'jam_selesai' => null, 'hitung_hadir' => false]);
        $this->schedule('2026-10-05', $off);

        $this->sendAt('2026-10-05 08:30');

        $this->assertSame([], $this->keysOf($this->operator));
    }

    public function test_the_user_can_switch_absen_reminders_off(): void
    {
        $this->schedule('2026-10-05', $this->pagi);
        $this->operator->forceFill(['notification_settings' => ['disabled' => ['absensi']]])->save();

        $this->sendAt('2026-10-05 08:30');

        $this->assertSame([], $this->keysOf($this->operator));
    }

    public function test_a_role_without_the_permission_gets_nothing(): void
    {
        $this->schedule('2026-10-05', $this->pagi);
        $role = Role::query()->where('name', RoleName::Operator->value)->firstOrFail();
        $role->permissions()->detach($role->permissions()->where('name', PermissionName::NotifikasiAbsensi->value)->pluck('permissions.id'));
        $this->operator->forgetAccessCache();

        $this->sendAt('2026-10-05 08:30');

        $this->assertSame([], $this->keysOf($this->operator->fresh()));
    }

    public function test_an_account_that_cannot_take_the_absen_gets_nothing(): void
    {
        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $role = Role::query()->where('name', RoleName::TeamLeaderOperasi->value)->firstOrFail();
        $role->permissions()->detach($role->permissions()->where('name', PermissionName::OperatorPresensi->value)->pluck('permissions.id'));
        $tl->forgetAccessCache();
        $employee = Employee::factory()->create(['unit_id' => $this->unit->id, 'user_id' => $tl->id, 'position' => 'Operator', 'is_active' => true]);
        $this->schedule('2026-10-05', $this->pagi, $employee);

        $this->sendAt('2026-10-05 08:30');

        $this->assertSame([], $this->keysOf($tl->fresh()));
    }

    public function test_reminders_go_only_to_the_scheduled_employees_account(): void
    {
        $colleague = $this->userWithRole(RoleName::Operator, $this->unit);
        $this->schedule('2026-10-05', $this->pagi);

        $this->sendAt('2026-10-05 08:30');

        $this->assertSame(['absen-masuk:2026-10-05'], $this->keysOf($this->operator));
        $this->assertSame([], $this->keysOf($colleague));
    }

    private function schedule(string $date, AttendanceCode $code, ?Employee $employee = null): void
    {
        $carbon = Carbon::parse($date);
        $schedule = WorkSchedule::query()->firstOrCreate(
            ['unit_id' => $this->unit->id, 'year' => $carbon->year, 'month' => $carbon->month, 'group_type' => 'shift'],
        );

        WorkScheduleEntry::factory()->create([
            'work_schedule_id' => $schedule->id,
            'employee_id' => ($employee ?? $this->employee)->id,
            'work_date' => $date,
            'attendance_code_id' => $code->id,
        ]);
    }

    private function checkIn(string $date): void
    {
        EmployeePresence::factory()->create([
            'unit_id' => $this->unit->id,
            'employee_id' => $this->employee->id,
            'user_id' => $this->operator->id,
            'work_date' => $date,
            'check_in_at' => Carbon::parse("{$date} 07:55", PresenceRecorder::TIMEZONE)->utc(),
        ]);
    }

    private function sendAt(string $wita): void
    {
        $now = Carbon::parse($wita, PresenceRecorder::TIMEZONE)->utc();
        Carbon::setTestNow($now);

        app(AttendanceReminders::class)->run($now);
    }

    /**
     * @return list<string>
     */
    private function keysOf(User $user): array
    {
        return $user->notifications()->get()->map(fn ($n): string => $n->data['key'])->sort()->values()->all();
    }
}
