<?php

namespace Tests\Feature\Notifications;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\HarDailyMeeting;
use App\Models\HarJadwalPiketOnCall;
use App\Models\Operasi5s5rJadwal;
use App\Models\OperasiBlackstartJadwal;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Notifications\ScheduleReminders;
use App\Services\Operator\PresenceRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class ScheduleRemindersTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private User $koordinator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Uji']);
        $this->koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_koordinator_gets_todays_operasi_digest_once_from_their_digest_time(): void
    {
        $this->plan5s5r($this->unit, [5, 6], 'Regu A');

        $this->sendAt('2026-10-05 06:00');
        $this->assertSame([], $this->keysOf($this->koordinator), 'Not before 06:30.');

        $this->sendAt('2026-10-05 06:35');
        $this->sendAt('2026-10-05 07:00');

        $this->assertSame(['jadwal:operasi:'.$this->unit->id.':2026-10-05'], $this->keysOf($this->koordinator));
        $data = $this->koordinator->notifications()->first()->data;
        $this->assertSame('Jadwal Operasi hari ini · PLTD Uji', $data['title']);
        $this->assertStringContainsString('5S 5R: Regu A', $data['body']);
        $this->assertSame('operasi', $data['module']);
        $this->assertStringStartsWith('/operasi/jadwal/program-5s-5r?', $data['url']);
    }

    public function test_nothing_is_sent_on_a_day_without_jadwal(): void
    {
        $this->plan5s5r($this->unit, [6], 'Regu A');

        $this->sendAt('2026-10-05 07:00');

        $this->assertSame([], $this->keysOf($this->koordinator));
    }

    public function test_the_digest_follows_the_users_own_time_and_switch(): void
    {
        $this->plan5s5r($this->unit, [5], 'Regu A');
        $this->koordinator->forceFill(['notification_settings' => ['disabled' => [], 'digest_time' => '08:00']])->save();

        $this->sendAt('2026-10-05 07:00');
        $this->assertSame([], $this->keysOf($this->koordinator));

        $this->sendAt('2026-10-05 08:05');
        $this->assertCount(1, $this->keysOf($this->koordinator));

        $other = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $other->forceFill(['notification_settings' => ['disabled' => ['jadwal']]])->save();
        $this->sendAt('2026-10-05 09:00');
        $this->assertSame([], $this->keysOf($other));
    }

    public function test_only_accounts_that_run_the_jadwal_at_that_unit_receive_it(): void
    {
        $this->plan5s5r($this->unit, [5], 'Regu A');

        $otherUnit = Unit::factory()->create(['is_active' => true]);
        $koordinatorElsewhere = $this->userWithRole(RoleName::KoordinatorOperasi, $otherUnit);
        $tlOperasi = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $koordinatorHar = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $serviceUnit = ServiceUnit::factory()->create();
        $this->unit->update(['service_unit_id' => $serviceUnit->id]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);

        $this->sendAt('2026-10-05 07:00');

        $this->assertCount(1, $this->keysOf($this->koordinator));
        foreach ([$koordinatorElsewhere, $tlOperasi, $koordinatorHar, $manager, $superAdmin] as $user) {
            $this->assertSame([], $this->keysOf($user), "{$user->email} must not receive the Operasi digest.");
        }
    }

    public function test_a_personal_duty_reaches_the_employees_own_account(): void
    {
        $harmes = $this->userWithRole(RoleName::Harmes, $this->unit);
        $employee = Employee::factory()->create(['unit_id' => $this->unit->id, 'user_id' => $harmes->id, 'position' => 'Operator', 'is_active' => true]);
        HarJadwalPiketOnCall::query()->create([
            'unit_id' => $this->unit->id, 'year' => 2026, 'month' => 10, 'employee_id' => $employee->id,
            'nama' => $employee->name, 'kategori' => 'HARMES', 'target' => 15, 'piket' => [5, 12],
        ]);
        $koordinatorHar = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);

        $this->sendAt('2026-10-05 07:00');

        $this->assertSame(['jadwal-pribadi:har:piket-on-call:2026-10-05'], $this->keysOf($harmes));
        $personal = $harmes->notifications()->first()->data;
        $this->assertSame('Hari ini Anda terjadwal: Piket On Call', $personal['title']);
        $this->assertNull($personal['url'], 'Harmes cannot open the Jadwal Pemeliharaan page, so no link.');

        $this->assertSame(['jadwal:har:'.$this->unit->id.':2026-10-05'], $this->keysOf($koordinatorHar));
    }

    public function test_yearly_week_plans_remind_on_the_first_day_of_that_week(): void
    {
        OperasiBlackstartJadwal::query()->create(['unit_id' => $this->unit->id, 'year' => 2026, 'uraian' => 'Uji blackstart', 'rencana' => ['10-2']]);

        $this->sendAt('2026-10-01 07:00');
        $this->assertSame([], $this->keysOf($this->koordinator));

        $this->sendAt('2026-10-08 07:00');
        $this->assertSame(['jadwal:operasi:'.$this->unit->id.':2026-10-08'], $this->keysOf($this->koordinator));
        $this->assertStringContainsString('Blackstart: Uji blackstart', $this->koordinator->notifications()->first()->data['body']);
    }

    public function test_the_daily_meeting_is_part_of_the_pemeliharaan_digest(): void
    {
        $koordinatorHar = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        HarDailyMeeting::factory()->create(['unit_id' => $this->unit->id, 'tanggal' => '2026-10-05', 'year' => 2026, 'month' => 10, 'acara' => 'Rapat koordinasi', 'waktu' => '09:00', 'tempat' => 'Ruang rapat']);

        $this->sendAt('2026-10-05 07:00');

        $body = $koordinatorHar->notifications()->first()->data['body'];
        $this->assertStringContainsString('Daily Meeting: Rapat koordinasi · 09:00 · Ruang rapat', $body);
    }

    public function test_no_today_digest_in_the_evening(): void
    {
        $this->plan5s5r($this->unit, [5], 'Regu A');

        $this->sendAt('2026-10-05 18:30');

        $this->assertSame([], $this->keysOf($this->koordinator));
    }

    public function test_cell_values_read_lists_and_maps(): void
    {
        $this->assertSame('', ScheduleReminders::cellValue([1, 5, 9], 5));
        $this->assertSame('', ScheduleReminders::cellValue(['10-2'], '10-2'));
        $this->assertNull(ScheduleReminders::cellValue([1, 9], 5));
        $this->assertSame('P1', ScheduleReminders::cellValue(['5' => 'P1'], 5));
        $this->assertNull(ScheduleReminders::cellValue(['5' => ''], 5));
        $this->assertNull(ScheduleReminders::cellValue(['5' => 'O'], 5, ['R', 'D']));
        $this->assertSame('D', ScheduleReminders::cellValue(['5' => 'D'], 5, ['R', 'D']));
        $this->assertNull(ScheduleReminders::cellValue(null, 5));
    }

    /**
     * @param  list<int>  $days
     */
    private function plan5s5r(Unit $unit, array $days, string $pelaksana): void
    {
        Operasi5s5rJadwal::query()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 10, 'pelaksana' => $pelaksana, 'rencana' => $days, 'realisasi' => [], 'target' => 4,
        ]);
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
