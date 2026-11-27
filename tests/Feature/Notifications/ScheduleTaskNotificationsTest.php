<?php

namespace Tests\Feature\Notifications;

use App\Enums\RoleName;
use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\HarJadwalPatrolCheck;
use App\Models\K3JadwalOnCall;
use App\Models\Operasi5s5rJadwal;
use App\Models\OperasiFlmJadwal;
use App\Models\OperasiFlmMonitoring;
use App\Models\OperasiProgram5s5rItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleEntry;
use App\Services\Notifications\ScheduleReminders;
use App\Services\Notifications\ScheduleTaskTracker;
use App\Services\Operator\PresenceRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class ScheduleTaskNotificationsTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private User $koordinatorOps;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Poasia']);
        $this->koordinatorOps = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_5s5r_reminder_links_directly_to_field_input_repeats_periodically_and_stops_after_input(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $employee = Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $operator->id,
            'position' => 'Operator',
            'regu' => 'A',
            'is_active' => true,
        ]);

        Operasi5s5rJadwal::query()->create([
            'unit_id' => $this->unit->id,
            'year' => 2026,
            'month' => 10,
            'employee_id' => $employee->id,
            'shift' => 'A',
            'pelaksana' => $employee->name,
            'rencana' => [5],
            'realisasi' => [],
            'target' => 4,
        ]);

        // 06:35 WITA: initial reminder with direct input URL.
        $this->sendAt('2026-10-05 06:35');
        $this->assertSame(['jadwal-pribadi:operasi:5s-5r:2026-10-05'], $this->keysOf($operator));
        $first = $operator->notifications()->first()->data;
        $this->assertSame('Hari ini Anda terjadwal: 5S 5R', $first['title']);
        $this->assertStringStartsWith('/operasi/input/program-5s5r?', $first['url']);

        // Still pending in live tracker before input is saved.
        $pending = app(ScheduleTaskTracker::class)->forUser($operator, Carbon::parse('2026-10-05 07:00', PresenceRecorder::TIMEZONE));
        $this->assertCount(1, $pending);
        $this->assertFalse($pending[0]['completed']);

        // 08:35 & 10:35 WITA: repeats every 2 hours while uncompleted.
        $this->sendAt('2026-10-05 08:35');
        $this->sendAt('2026-10-05 10:35');
        $this->assertSame([
            'jadwal-pribadi:operasi:5s-5r:2026-10-05',
            'jadwal-pribadi:operasi:5s-5r:2026-10-05:r1',
            'jadwal-pribadi:operasi:5s-5r:2026-10-05:r2',
        ], $this->keysOf($operator));

        // Operator submits Program 5S 5R input for that day.
        OperasiProgram5s5rItem::query()->create([
            'unit_id' => $this->unit->id,
            'year' => 2026,
            'month' => 10,
            'day' => 5,
            'shift' => 'Pagi',
            'tanggal' => '2026-10-05',
            'minggu' => 1,
            'program' => 'ringkas',
            'kondisi_awal' => 'Baik',
            'membersihkan' => true,
            'progres' => '76-100',
            'kondisi_akhir' => 'Baik',
            'input_by' => $operator->id,
        ]);

        // Live tracker reflects completion; 12:35 WITA stops sending further reminders.
        $done = app(ScheduleTaskTracker::class)->forUser($operator, Carbon::parse('2026-10-05 12:00', PresenceRecorder::TIMEZONE));
        $this->assertTrue($done[0]['completed']);

        $this->sendAt('2026-10-05 12:35');
        $this->assertCount(3, $this->keysOf($operator));
    }

    public function test_shift_worker_only_receives_periodic_5s5r_reminders_within_their_shift_window(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $employee = Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $operator->id,
            'position' => 'Operator',
            'regu' => 'C',
            'is_active' => true,
        ]);

        $sore = AttendanceCode::factory()->create([
            'code' => 'S',
            'label' => 'Sore',
            'jam_mulai' => '16:00',
            'jam_selesai' => '22:00',
            'hitung_hadir' => true,
        ]);

        $schedule = WorkSchedule::query()->create([
            'unit_id' => $this->unit->id,
            'year' => 2026,
            'month' => 10,
        ]);

        WorkScheduleEntry::query()->create([
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-10-05',
            'attendance_code_id' => $sore->id,
            'regu' => 'C',
        ]);

        Operasi5s5rJadwal::query()->create([
            'unit_id' => $this->unit->id,
            'year' => 2026,
            'month' => 10,
            'employee_id' => $employee->id,
            'shift' => 'C',
            'pelaksana' => $employee->name,
            'rencana' => [5],
            'realisasi' => [],
            'target' => 4,
        ]);

        // Morning 07:00 WITA: off-shift -> no reminder yet.
        $this->sendAt('2026-10-05 07:00');
        $this->assertSame([], $this->keysOf($operator));

        // 15:35 WITA (within 30 min before 16:00 shift start) -> first reminder.
        $this->sendAt('2026-10-05 15:35');
        $this->assertSame(['jadwal-pribadi:operasi:5s-5r:2026-10-05'], $this->keysOf($operator));

        // 17:35 WITA (+2 hours while still uncompleted) -> periodic reminder r1.
        $this->sendAt('2026-10-05 17:35');
        $this->assertSame([
            'jadwal-pribadi:operasi:5s-5r:2026-10-05',
            'jadwal-pribadi:operasi:5s-5r:2026-10-05:r1',
        ], $this->keysOf($operator));
    }

    public function test_flm_regu_cell_notifies_every_member_of_that_regu_and_stops_when_monitoring_is_recorded(): void
    {
        $opB1 = $this->userWithRole(RoleName::Operator, $this->unit);
        $opB2 = $this->userWithRole(RoleName::Operator, $this->unit);
        $opA = $this->userWithRole(RoleName::Operator, $this->unit);

        Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $opB1->id,
            'name' => 'Leader Regu B',
            'position' => 'Operator',
            'regu' => 'B',
            'is_shift_leader' => true,
            'is_active' => true,
        ]);
        Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $opB2->id,
            'name' => 'Anggota Regu B',
            'position' => 'Operator',
            'regu' => 'B',
            'is_shift_leader' => false,
            'is_active' => true,
        ]);
        Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $opA->id,
            'name' => 'Operator Regu A',
            'position' => 'Operator',
            'regu' => 'A',
            'is_active' => true,
        ]);

        OperasiFlmJadwal::query()->create([
            'unit_id' => $this->unit->id,
            'year' => 2026,
            'month' => 10,
            'section' => 'rutin',
            'shift' => '',
            'label' => '08:00 sd 15:00',
            'row_type' => 'shift',
            'days' => ['5' => 'B'],
            'target' => 0,
            'sort_order' => 1,
        ]);

        $this->sendAt('2026-10-05 06:35');

        $this->assertSame(['jadwal-pribadi:operasi:flm:2026-10-05'], $this->keysOf($opB1));
        $this->assertSame(['jadwal-pribadi:operasi:flm:2026-10-05'], $this->keysOf($opB2));
        $this->assertSame([], $this->keysOf($opA), 'Regu A must not receive Regu B FLM reminder.');

        $notifData = $opB2->notifications()->first()->data;
        $this->assertSame('Hari ini Regu B terjadwal: FLM', $notifData['title']);
        $this->assertStringContainsString('Leader Shift: Leader Regu B', $notifData['body']);
        $this->assertStringStartsWith('/operasi/input/monitoring-flm?', $notifData['url']);

        // Record FLM Monitoring by Regu B.
        OperasiFlmMonitoring::query()->create([
            'unit_id' => $this->unit->id,
            'year' => 2026,
            'month' => 10,
            'no_urut' => 1,
            'mesin' => 'Mesin #1',
            'tanggal' => '2026-10-05',
            'masalah' => 'Rembesan oli',
            'kondisi_awal' => ['bersihkan'],
            'status' => 'open',
            'reported_by' => $opB2->id,
            'reporter_shift' => 'Pagi · Regu B',
            'input_by' => $opB2->id,
        ]);

        // Repeat at 08:35 WITA should skip both Regu B members now that FLM is recorded.
        $this->sendAt('2026-10-05 08:35');
        $this->assertCount(1, $this->keysOf($opB1));
        $this->assertCount(1, $this->keysOf($opB2));
    }

    public function test_saving_today_flm_or_5s5r_schedule_immediately_notifies_assigned_users_without_duplicates_on_resave(): void
    {
        $now = Carbon::parse('2026-10-05 10:00', PresenceRecorder::TIMEZONE)->utc();
        Carbon::setTestNow($now);

        $opB = $this->userWithRole(RoleName::Operator, $this->unit);
        $empB = Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $opB->id,
            'name' => 'Operator Regu B',
            'position' => 'Operator',
            'regu' => 'B',
            'is_active' => true,
        ]);

        $flmPayload = [
            'unit_id' => $this->unit->id,
            'month' => 10,
            'year' => 2026,
            'rows' => [
                [
                    'section' => 'rutin',
                    'shift' => '',
                    'label' => '08:00 sd 15:00',
                    'row_type' => 'shift',
                    'days' => ['5' => 'B'],
                    'target' => 0,
                ],
            ],
        ];

        $this->actingAs($this->koordinatorOps)->post('/operasi/jadwal/flm', $flmPayload)->assertRedirect();

        $this->assertCount(1, $opB->fresh()->notifications);
        $this->assertSame('Jadwal baru Regu B hari ini: FLM', $opB->fresh()->notifications->first()->data['title']);
        $this->assertStringStartsWith('/operasi/input/monitoring-flm?', $opB->fresh()->notifications->first()->data['url']);

        // Re-saving identical schedule for today does not re-notify.
        $this->actingAs($this->koordinatorOps)->post('/operasi/jadwal/flm', $flmPayload)->assertRedirect();
        $this->assertCount(1, $opB->fresh()->notifications);

        // Saving 5S5R for today notifies the assigned employee immediately.
        $this->actingAs($this->koordinatorOps)->post('/operasi/jadwal/program-5s-5r', [
            'unit_id' => $this->unit->id,
            'month' => 10,
            'year' => 2026,
            'rows' => [
                [
                    'employee_id' => $empB->id,
                    'shift' => 'B',
                    'pelaksana' => $empB->name,
                    'rencana' => [5],
                    'realisasi' => [],
                    'target' => 4,
                ],
            ],
        ])->assertRedirect();

        $titles = $opB->fresh()->notifications->pluck('data.title')->all();
        $this->assertCount(2, $titles);
        $this->assertContains('Jadwal baru hari ini: 5S 5R', $titles);
    }

    public function test_har_patrol_check_links_harmes_directly_to_field_sheet(): void
    {
        $harmes = $this->userWithRole(RoleName::Harmes, $this->unit);
        $employee = Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $harmes->id,
            'position' => 'Harmes',
            'is_active' => true,
        ]);

        HarJadwalPatrolCheck::query()->create([
            'unit_id' => $this->unit->id,
            'employee_id' => $employee->id,
            'year' => 2026,
            'month' => 10,
            'rencana' => ['5' => '1'],
            'realisasi' => [],
        ]);

        $this->sendAt('2026-10-05 06:35');

        $this->assertSame(['jadwal-pribadi:har:patrol-check:2026-10-05'], $this->keysOf($harmes));
        $data = $harmes->notifications()->first()->data;
        $this->assertStringStartsWith('/har/input/lembar/patrol-check-pemeliharaan?', $data['url']);
    }

    public function test_k3_on_call_uses_cutoff_period_and_skips_off_codes(): void
    {
        $koordinatorK3 = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);
        $staff = $this->userWithRole(RoleName::StafK3, $this->unit);
        $empOn = Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $koordinatorK3->id,
            'position' => 'Koordinator K3',
            'is_active' => true,
        ]);
        $empOff = Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $staff->id,
            'position' => 'Staf K3',
            'is_active' => true,
        ]);

        // Date 2026-10-20 falls inside cut-off period year=2026, month=11 (16 Oct - 15 Nov).
        K3JadwalOnCall::query()->create([
            'unit_id' => $this->unit->id,
            'year' => 2026,
            'month' => 11,
            'employee_id' => $empOn->id,
            'nama' => $empOn->name,
            'schedule' => ['2026-10-20' => 'ON'],
        ]);
        K3JadwalOnCall::query()->create([
            'unit_id' => $this->unit->id,
            'year' => 2026,
            'month' => 11,
            'employee_id' => $empOff->id,
            'nama' => $empOff->name,
            'schedule' => ['2026-10-20' => 'OFF'],
        ]);

        $this->sendAt('2026-10-20 06:35');

        $this->assertContains('jadwal-pribadi:k3:on-call:2026-10-20', $this->keysOf($koordinatorK3));
        $this->assertSame([], $this->keysOf($staff));

        $trackerItems = app(ScheduleTaskTracker::class)->forUser($koordinatorK3, Carbon::parse('2026-10-20 07:00', PresenceRecorder::TIMEZONE));
        $this->assertCount(1, $trackerItems);
        $this->assertFalse($trackerItems[0]['requires_input']);
    }

    public function test_dashboard_shares_live_schedule_tasks_for_signed_in_operator(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:00', PresenceRecorder::TIMEZONE)->utc());

        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $employee = Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $operator->id,
            'position' => 'Operator',
            'regu' => 'A',
            'is_active' => true,
        ]);

        Operasi5s5rJadwal::query()->create([
            'unit_id' => $this->unit->id,
            'year' => 2026,
            'month' => 10,
            'employee_id' => $employee->id,
            'shift' => 'A',
            'pelaksana' => $employee->name,
            'rencana' => [5],
            'realisasi' => [],
            'target' => 4,
        ]);

        $this->actingAs($operator)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('scheduleTasks', 1)
                ->where('scheduleTasks.0.table_key', 'operasi:5s-5r')
                ->where('scheduleTasks.0.completed', false)
            );
    }

    private function sendAt(string $wita): void
    {
        $now = Carbon::parse($wita, PresenceRecorder::TIMEZONE)->utc();
        Carbon::setTestNow($now);

        app(ScheduleReminders::class)->run($now);
    }

    /**
     * @return list<string>
     */
    private function keysOf(User $user): array
    {
        return $user->notifications()->get()->map(fn ($n): string => $n->data['key'])->sort()->values()->all();
    }
}
