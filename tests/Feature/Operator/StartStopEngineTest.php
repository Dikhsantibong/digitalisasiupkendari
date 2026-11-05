<?php

namespace Tests\Feature\Operator;

use App\Enums\MesinHarianJenis;
use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\OperasiMesinHarian;
use App\Models\OperatorStartStop;
use App\Models\Unit;
use App\Models\User;
use App\Services\Operasi\MesinHarianSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Start Stop Engine (modul Operator): the runs of each mesin per tanggal,
 * saved as one sheet a month; the gangguan stops are Jumlah Kali Gangguan.
 */
class StartStopEngineTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private Machine $mak1;

    private Machine $mak2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $this->unit = Unit::factory()->create(['is_active' => true]);
        $this->mak1 = Machine::factory()->create(['unit_id' => $this->unit->id, 'name' => 'MAK #1', 'is_active' => true]);
        $this->mak2 = Machine::factory()->create(['unit_id' => $this->unit->id, 'name' => 'MAK #2', 'is_active' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sync(User $user, array $data): TestResponse
    {
        return $this->actingAs($user)->post(route('operator.start-stop.sync'), ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026, ...$data]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function runRow(array $extra = []): array
    {
        return ['tanggal' => '2026-09-05', 'engine_id' => $this->mak1->id, 'start_time' => '08:00', 'cbg_on' => '08:05', 'cbg_off' => '15:55', 'stop_time' => '16:00', 'penyebab_stop' => 'normal', 'keterangan' => '', ...$extra];
    }

    public function test_the_operator_adds_edits_and_deletes_runs_in_one_save(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);

        $this->sync($operator, ['rows' => [
            $this->runRow(),
            $this->runRow(['start_time' => '18:00', 'cbg_on' => '18:03', 'cbg_off' => '', 'stop_time' => '19:10', 'penyebab_stop' => 'gangguan', 'keterangan' => 'Trip LO pressure']),
            $this->runRow(['engine_id' => $this->mak2->id, 'start_time' => '22:00', 'stop_time' => '02:00']),
            // A blank row is skipped.
            ['tanggal' => '2026-09-05', 'engine_id' => null, 'start_time' => '', 'cbg_on' => '', 'cbg_off' => '', 'stop_time' => '', 'penyebab_stop' => 'normal'],
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, OperatorStartStop::query()->count());
        $trip = OperatorStartStop::query()->where('penyebab_stop', 'gangguan')->sole();
        $this->assertSame(['18:00', null, '19:10', 'Trip LO pressure', $operator->id], [$trip->start_time, $trip->cbg_off, $trip->stop_time, $trip->keterangan, $trip->input_by]);

        $this->actingAs($operator)->get(route('operator.start-stop.index', ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operator/start-stop/index')
                ->has('rows', 3)
                ->has('machines', 2)
                ->where('rows.0.start_time', '08:00')
                ->where('can_write', true));

        [$first, $removed] = OperatorStartStop::query()->orderBy('id')->take(2)->get()->all();
        $this->sync($operator, [
            'rows' => [['id' => $first->id] + $this->runRow(['stop_time' => '17:00'])],
            'deleted_ids' => [$removed->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame('17:00', $first->fresh()->stop_time);
        $this->assertModelMissing($removed);
        $this->assertSame(2, OperatorStartStop::query()->count());
    }

    public function test_the_runs_are_validated_and_guarded(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $otherMachine = Machine::factory()->create(['is_active' => true]);

        $this->sync($operator, ['rows' => [$this->runRow(['tanggal' => '2026-10-01'])]])->assertSessionHasErrors('rows.0.tanggal');
        $this->sync($operator, ['rows' => [$this->runRow(['engine_id' => $otherMachine->id])]])->assertSessionHasErrors('rows.0.engine_id');
        $this->sync($operator, ['rows' => [$this->runRow(['engine_id' => null])]])->assertSessionHasErrors('rows.0.engine_id');
        $this->sync($operator, ['rows' => [$this->runRow(['start_time' => '25:00'])]])->assertSessionHasErrors('rows.0.start_time');
        $this->sync($operator, ['rows' => [$this->runRow(['penyebab_stop' => 'rusak'])]])->assertSessionHasErrors('rows.0.penyebab_stop');
        $this->assertSame(0, OperatorStartStop::query()->count());

        // View-only roles and other units' operators cannot save.
        $this->sync($this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit), ['rows' => [$this->runRow()]])->assertForbidden();
        $this->sync($this->userWithRole(RoleName::Operator, Unit::factory()->create()), ['rows' => [$this->runRow()]])->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit))
            ->get(route('operator.start-stop.index', ['unit_id' => $this->unit->id]))
            ->assertInertia(fn ($page) => $page->where('can_write', false));
    }

    public function test_gangguan_stops_are_the_jumlah_kali_gangguan(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        // A day typed into the old sheet without any Start Stop entry is kept.
        OperasiMesinHarian::query()->create([
            'unit_id' => $this->unit->id, 'jenis' => MesinHarianJenis::KaliGangguan, 'month' => 9, 'year' => 2026,
            'readings' => [$this->mak2->id => ['20' => 2], $this->mak1->id => ['5' => 9]],
        ]);

        $this->sync($operator, ['rows' => [
            $this->runRow(['penyebab_stop' => 'gangguan']),
            $this->runRow(['start_time' => '18:00', 'stop_time' => '19:00', 'penyebab_stop' => 'gangguan']),
            $this->runRow(['tanggal' => '2026-09-06']),
        ]])->assertSessionHasNoErrors();

        $sheet = app(MesinHarianSheet::class);
        $readings = $sheet->kaliGangguan($this->unit, 9, 2026, $sheet->machines($this->unit));
        $this->assertSame([5 => 2.0], $readings[$this->mak1->id], 'Start Stop replaces the typed 9 of that day; a normal stop adds nothing.');
        $this->assertSame([20 => 2.0], $readings[$this->mak2->id]);

        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $this->actingAs($tl)->get(route('operasi.pengusahaan.kali-gangguan.index', ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('from_start_stop', true)
                ->where("readings.{$this->mak1->id}.5", 2));
    }
}
