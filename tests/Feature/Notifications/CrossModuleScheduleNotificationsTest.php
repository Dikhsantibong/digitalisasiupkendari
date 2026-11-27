<?php

namespace Tests\Feature\Notifications;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\HarDailyMeeting;
use App\Models\HarJadwalMeetingPemeliharaan;
use App\Models\HarLembarRow;
use App\Models\K3ActivityPlan;
use App\Models\K3ActivityType;
use App\Models\K3PatrolCheckJadwal;
use App\Models\K3PekerjaanRutin;
use App\Models\LogistikJadwalRow;
use App\Models\PdmFormDocument;
use App\Models\PdmJadwal5s5r;
use App\Models\PdmJadwalMeeting;
use App\Models\Unit;
use App\Models\User;
use App\Services\Notifications\ScheduleReminders;
use App\Services\Notifications\ScheduleTaskTracker;
use App\Services\Operator\PresenceRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class CrossModuleScheduleNotificationsTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Bau-Bau']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_logistik_5s5r_and_shift_track_employee_assignments_notify_and_complete(): void
    {
        $now = Carbon::parse('2026-10-05 07:00', PresenceRecorder::TIMEZONE)->utc();
        Carbon::setTestNow($now);

        $actor = $this->userWithRole(RoleName::KoordinatorLogistik, $this->unit);
        $assignee = $this->userWithRole(RoleName::KoordinatorLogistik, $this->unit);
        $employee = Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $assignee->id,
            'position' => 'Officer Logistik',
            'is_active' => true,
        ]);

        $this->actingAs($actor)->post(route('logistik.jadwal.sheet.store', ['jadwal' => '5s5r']), [
            'unit_id' => $this->unit->id,
            'month' => 10,
            'year' => 2026,
            'rows' => [[
                'section' => null,
                'employee_id' => $employee->id,
                'nama' => $employee->name,
                'pic' => '',
                'days' => ['5' => 'R'],
                'target' => 1,
                'keterangan' => '',
                'evidence' => [],
            ]],
        ])->assertRedirect();

        $saved = LogistikJadwalRow::query()
            ->where('unit_id', $this->unit->id)
            ->where('jadwal', '5s5r')
            ->firstOrFail();
        $this->assertSame($employee->id, $saved->employee_id);
        $this->assertHasKeyPrefix($assignee, "jadwal-baru:logistik:5s5r:{$this->unit->id}:2026-10-05:");

        $tracker = app(ScheduleTaskTracker::class);
        $tasks = $tracker->forUser($assignee, $now);
        $this->assertCount(1, $tasks);
        $this->assertSame('logistik:5s5r', $tasks[0]['table_key']);
        $this->assertTrue($tasks[0]['requires_input']);
        $this->assertFalse($tasks[0]['completed']);
        $this->assertStringStartsWith('/logistik/input/lembar/inspeksi-5s5r?', (string) $tasks[0]['url']);

        // Repeating reminder fires while pending.
        $this->sendAt('2026-10-05 07:00');
        $this->sendAt('2026-10-05 09:05');
        $this->assertContains('jadwal-pribadi:logistik:jadwal-pelaksanaan-5s5r-logistik-gudang:2026-10-05', $this->keysOf($assignee));
        $this->assertContains('jadwal-pribadi:logistik:jadwal-pelaksanaan-5s5r-logistik-gudang:2026-10-05:r1', $this->keysOf($assignee));

        // Saving Inspeksi 5S5R input marks the task completed and stops further repeats.
        LogistikJadwalRow::query()->create([
            'unit_id' => $this->unit->id,
            'jadwal' => 'inspeksi-5s5r',
            'year' => 2026,
            'month' => 10,
            'nama' => 'Area Gudang',
            'days' => ['1' => '1'],
            'sort_order' => 0,
            'input_by' => $assignee->id,
        ]);

        $done = $tracker->forUser($assignee, Carbon::parse('2026-10-05 11:05', PresenceRecorder::TIMEZONE));
        $this->assertTrue($done[0]['completed']);
        $countBefore = count($this->keysOf($assignee));
        $this->sendAt('2026-10-05 11:05');
        $this->assertCount($countBefore, $this->keysOf($assignee));

        // Shift Logistik: code 'P' triggers standing duty (requires_input = false); 'OF' does not.
        $offUser = $this->userWithRole(RoleName::KoordinatorLogistik, $this->unit);
        $offEmployee = Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $offUser->id,
            'is_active' => true,
        ]);

        LogistikJadwalRow::query()->create([
            'unit_id' => $this->unit->id,
            'jadwal' => 'shift',
            'year' => 2026,
            'month' => 10,
            'employee_id' => $employee->id,
            'nama' => $employee->name,
            'days' => ['5' => 'P'],
            'sort_order' => 0,
        ]);
        LogistikJadwalRow::query()->create([
            'unit_id' => $this->unit->id,
            'jadwal' => 'shift',
            'year' => 2026,
            'month' => 10,
            'employee_id' => $offEmployee->id,
            'nama' => $offEmployee->name,
            'days' => ['5' => 'OF'],
            'sort_order' => 1,
        ]);

        $shiftDuties = collect($tracker->forUser($assignee, $now))->where('table_key', 'logistik:shift')->values();
        $this->assertCount(1, $shiftDuties);
        $this->assertFalse($shiftDuties[0]['requires_input']);
        $this->assertSame([], $tracker->forUser($offUser, $now));
    }

    public function test_pdm_5s5r_and_meeting_assign_employees_notify_and_complete(): void
    {
        $now = Carbon::parse('2026-10-05 07:00', PresenceRecorder::TIMEZONE)->utc();
        Carbon::setTestNow($now);

        $actor = $this->userWithRole(RoleName::KoordinatorPdm, $this->unit);
        $assignee = $this->userWithRole(RoleName::KoordinatorPdm, $this->unit);
        $employee = Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $assignee->id,
            'position' => 'Analis PdM',
            'is_active' => true,
        ]);

        $this->actingAs($actor)->post('/pdm/jadwal/program-5s-5r', [
            'unit_id' => $this->unit->id,
            'month' => 10,
            'year' => 2026,
            'rows' => [[
                'no_urut' => 1,
                'employee_id' => $employee->id,
                'uraian' => 'RINGKAS (SEIRI)',
                'rencana' => [5],
                'realisasi' => [],
                'target' => 1,
            ]],
        ])->assertRedirect();

        $this->actingAs($actor)->post('/pdm/jadwal/meeting', [
            'unit_id' => $this->unit->id,
            'month' => 10,
            'year' => 2026,
            'rows' => [[
                'no_urut' => 1,
                'employee_id' => $employee->id,
                'uraian' => 'OFFICER PdM & Matlev',
                'rencana' => [5],
                'realisasi' => [],
                'target' => 1,
            ]],
        ])->assertRedirect();

        $this->assertSame($employee->id, PdmJadwal5s5r::query()->firstOrFail()->employee_id);
        $meetingRow = PdmJadwalMeeting::query()->firstOrFail();
        $this->assertSame($employee->id, $meetingRow->employee_id);

        $this->assertHasKeyPrefix($assignee, "jadwal-baru:pdm:5s-5r:{$this->unit->id}:2026-10-05:");
        $this->assertHasKeyPrefix($assignee, "jadwal-baru:pdm:meeting:{$this->unit->id}:2026-10-05:");

        $tracker = app(ScheduleTaskTracker::class);
        $tasks = collect($tracker->forUser($assignee, $now))->keyBy('table_key');
        $this->assertFalse($tasks['pdm:5s-5r']['completed']);
        $this->assertStringStartsWith('/pdm/input/forms/checklist-5s5r?', (string) $tasks['pdm:5s-5r']['url']);
        $this->assertFalse($tasks['pdm:meeting']['completed']);

        PdmFormDocument::query()->create([
            'unit_id' => $this->unit->id,
            'form' => 'checklist-5s5r',
            'year' => 2026,
            'month' => 10,
            'subject' => '',
            'header' => ['tanggal' => '2026-10-05'],
            'input_by' => $assignee->id,
        ]);
        $meetingRow->update(['realisasi' => [5]]);

        $done = collect($tracker->forUser($assignee, $now))->keyBy('table_key');
        $this->assertTrue($done['pdm:5s-5r']['completed']);
        $this->assertTrue($done['pdm:meeting']['completed']);
    }

    public function test_k3_patrol_check_pekerjaan_rutin_and_time_frame_assign_notify_and_complete(): void
    {
        $now = Carbon::parse('2026-10-05 07:00', PresenceRecorder::TIMEZONE)->utc();
        Carbon::setTestNow($now);

        $actor = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);
        $assignee = $this->userWithRole(RoleName::StafK3, $this->unit);
        $employee = Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $assignee->id,
            'position' => 'Petugas K3L',
            'is_active' => true,
        ]);
        $activityType = K3ActivityType::factory()->forUnit($this->unit)->create(['name' => 'Inspeksi Hydrant']);

        $this->actingAs($actor)->post(route('k3.jadwal.patrol-check.store'), [
            'unit_id' => $this->unit->id,
            'month' => 10,
            'year' => 2026,
            'rows' => [[
                'id' => null,
                'no_urut' => 1,
                'employee_id' => $employee->id,
                'uraian' => 'Patrol Area Oil Trap',
                'rencana' => [5],
                'realisasi' => [],
                'bobot_sla' => 10,
                'keterangan' => '',
            ]],
        ])->assertRedirect();

        $this->actingAs($actor)->post(route('k3.jadwal.pekerjaan-rutin.store'), [
            'unit_id' => $this->unit->id,
            'month' => 10,
            'year' => 2026,
            'rows' => [[
                'id' => null,
                'no_urut' => 1,
                'employee_id' => $employee->id,
                'uraian' => 'Cek APAR Gudang',
                'rencana' => [5],
                'realisasi' => [],
                'paraf' => 'Empty',
                'sort_order' => 0,
            ]],
        ])->assertRedirect();

        $this->actingAs($actor)->post(route('k3.input.time-frame.store'), [
            'unit_id' => $this->unit->id,
            'month' => 10,
            'year' => 2026,
            'rows' => [[
                'activity_type_id' => $activityType->id,
                'employee_id' => $employee->id,
                'pic' => $employee->name,
                'keterangan' => '',
                'rencana' => [5],
                'realisasi' => [],
            ]],
        ])->assertRedirect();

        $patrol = K3PatrolCheckJadwal::query()->firstOrFail();
        $rutin = K3PekerjaanRutin::query()->firstOrFail();
        $plan = K3ActivityPlan::query()->firstOrFail();
        $this->assertSame($employee->id, $patrol->employee_id);
        $this->assertSame($employee->id, $rutin->employee_id);
        $this->assertSame($employee->id, $plan->employee_id);

        $this->assertHasKeyPrefix($assignee, "jadwal-baru:k3:patrol-check:{$this->unit->id}:2026-10-05:");
        $this->assertHasKeyPrefix($assignee, "jadwal-baru:k3:pekerjaan-rutin:{$this->unit->id}:2026-10-05:");
        $this->assertHasKeyPrefix($assignee, "jadwal-baru:k3:time-frame:{$this->unit->id}:2026-10-05:");

        $tracker = app(ScheduleTaskTracker::class);
        $pending = collect($tracker->forUser($assignee, $now))->keyBy('table_key');
        $this->assertFalse($pending['k3:patrol-check']['completed']);
        $this->assertFalse($pending['k3:pekerjaan-rutin']['completed']);
        $this->assertFalse($pending['k3:time-frame']['completed']);

        $patrol->update(['realisasi' => [5]]);
        $rutin->update(['realisasi' => [5]]);
        $plan->update(['real_days' => [5]]);

        $done = collect($tracker->forUser($assignee, $now))->keyBy('table_key');
        $this->assertTrue($done['k3:patrol-check']['completed']);
        $this->assertTrue($done['k3:pekerjaan-rutin']['completed']);
        $this->assertTrue($done['k3:time-frame']['completed']);
    }

    public function test_har_meeting_pemeliharaan_notifies_and_weekly_tools_and_blackstart_enter_digest(): void
    {
        $now = Carbon::parse('2026-10-05 07:00', PresenceRecorder::TIMEZONE)->utc();
        Carbon::setTestNow($now);

        $actor = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $assignee = $this->userWithRole(RoleName::Harmes, $this->unit);
        $employee = Employee::factory()->create([
            'unit_id' => $this->unit->id,
            'user_id' => $assignee->id,
            'position' => 'Harmes',
            'is_active' => true,
        ]);

        $this->actingAs($actor)->post(route('har.jadwal.meeting-pemeliharaan.store'), [
            'unit_id' => $this->unit->id,
            'month' => 10,
            'year' => 2026,
            'rows' => [[
                'id' => null,
                'employee_id' => $employee->id,
                'uraian' => 'Meeting Pemeliharaan Mingguan',
                'target' => 4,
                'rencana' => ['5' => 1],
                'realisasi' => [],
                'keterangan' => '',
            ]],
        ])->assertRedirect();

        $this->assertSame($employee->id, HarJadwalMeetingPemeliharaan::query()->firstOrFail()->employee_id);
        $this->assertHasKeyPrefix($assignee, "jadwal-baru:har:meeting-pemeliharaan:{$this->unit->id}:2026-10-05:");

        $tracker = app(ScheduleTaskTracker::class);
        $pending = $tracker->forUser($assignee, $now);
        $this->assertCount(1, $pending);
        $this->assertSame('har:meeting-pemeliharaan', $pending[0]['table_key']);
        $this->assertFalse($pending[0]['completed']);
        $this->assertStringStartsWith('/har/formulir/daily-meeting?', (string) $pending[0]['url']);

        HarDailyMeeting::query()->create([
            'unit_id' => $this->unit->id,
            'year' => 2026,
            'month' => 10,
            'tanggal' => '2026-10-05',
            'acara' => 'Meeting Evaluasi HAR',
            'peserta' => [],
            'eviden' => [],
            'absensi_dibuka' => true,
            'input_by' => $assignee->id,
        ]);

        $done = $tracker->forUser($assignee, $now);
        $this->assertTrue($done[0]['completed']);

        // Inventarisasi Tools (w_week) and Blackstart (mw_week) plan entries fire on week starts (1, 8, 15, 22).
        HarLembarRow::query()->create([
            'unit_id' => $this->unit->id,
            'lembar' => 'inventarisasi-tools',
            'year' => 2026,
            'month' => 10,
            'subject' => '',
            'sort_order' => 0,
            'fields' => ['uraian' => 'Kunci Shock Set'],
            'cells' => ['main' => ['w1' => '1']],
        ]);
        HarLembarRow::query()->create([
            'unit_id' => $this->unit->id,
            'lembar' => 'pemeriksaan-blackstart',
            'year' => 2026,
            'month' => 0,
            'subject' => '',
            'sort_order' => 0,
            'fields' => ['uraian' => 'ENGINE DIESEL GENSET (EDG)'],
            'cells' => ['rencana' => ['m10w1' => '1']],
        ]);

        $reminders = app(ScheduleReminders::class);
        $weekStartPlan = $reminders->plan(Carbon::parse('2026-10-01', PresenceRecorder::TIMEZONE));
        $harLabels = collect($weekStartPlan['units']['har'][$this->unit->id] ?? [])->pluck('label')->all();
        $this->assertContains('Inventarisasi Tools & Material', $harLabels);
        $this->assertContains('Pemeriksaan Blackstart', $harLabels);

        $midWeekPlan = $reminders->plan(Carbon::parse('2026-10-05', PresenceRecorder::TIMEZONE));
        $midHarLabels = collect($midWeekPlan['units']['har'][$this->unit->id] ?? [])->pluck('label')->all();
        $this->assertNotContains('Inventarisasi Tools & Material', $midHarLabels);
        $this->assertNotContains('Pemeriksaan Blackstart', $midHarLabels);
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

    private function assertHasKeyPrefix(User $user, string $prefix): void
    {
        $keys = $this->keysOf($user);
        $matched = collect($keys)->contains(fn (string $key): bool => str_starts_with($key, $prefix));

        $this->assertTrue($matched, "Expected notification key starting with [{$prefix}] in: ".json_encode($keys));
    }
}
