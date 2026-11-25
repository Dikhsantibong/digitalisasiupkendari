<?php

namespace Tests\Feature\Operator;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\Machine;
use App\Models\OperasiPatrolCheckEntry;
use App\Models\OperasiPatrolCheckMesin;
use App\Models\OperatorMutasi;
use App\Models\OperatorStartStop;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Lembar Mutasi Operator: the shift handover sheet (one per unit + date +
 * shift), handed over and signed by one regu, accepted by the next.
 */
class MutasiTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        Storage::fake('public');
    }

    /** The Leader Shift of a regu on the unit: the one who fills the sheet. */
    private function leader(Unit $unit, string $regu = 'D'): User
    {
        $user = $this->userWithRole(RoleName::Operator, $unit);
        Employee::factory()->create(['user_id' => $user->id, 'unit_id' => $unit->id, 'regu' => $regu, 'is_shift_leader' => true, 'is_active' => true]);

        return $user;
    }

    public function test_only_the_leader_shift_fills_the_sheet_other_operators_read_it(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $member = $this->userWithRole(RoleName::Operator, $unit);
        Employee::factory()->create(['user_id' => $member->id, 'unit_id' => $unit->id, 'regu' => 'D', 'is_shift_leader' => false, 'is_active' => true]);

        $this->actingAs($member)->get(route('operator.mutasi.index', ['unit_id' => $unit->id, 'tanggal' => '2026-09-09', 'shift' => 'sore']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false)->where('can_receive', false)->where('leader_only', true));
        $this->actingAs($member)->post(route('operator.mutasi.store'), $this->payload($unit))->assertForbidden();
        $this->assertSame(0, OperatorMutasi::query()->count());

        // The Leader Shift saves it; the Project Leader (manages the schedule) may too.
        $this->actingAs($this->leader($unit))->post(route('operator.mutasi.store'), $this->payload($unit))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $unit))
            ->get(route('operator.mutasi.index', ['unit_id' => $unit->id, 'tanggal' => '2026-09-09', 'shift' => 'sore']))
            ->assertInertia(fn ($page) => $page->where('can_write', true)->where('leader_only', false));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Unit $unit, array $overrides = []): array
    {
        return array_merge([
            'unit_id' => $unit->id,
            'tanggal' => '2026-09-09',
            'shift' => 'sore',
            'mesin' => [
                // Level tangki BBM in cm, tambah pelumas in liters (user, 2026-11-24); tambah_bbm is the legacy column, kept as sent.
                ['machine_id' => null, 'nama' => 'CUMMINS #6', 'level_bbm' => '85.5', 'tambah_pelumas' => '12', 'tambah_bbm' => '1200', 'pelumas' => 'normal', 'status' => 'operasi'],
                ['machine_id' => null, 'nama' => 'CUMMINS #7', 'level_bbm' => '', 'tambah_bbm' => null, 'pelumas' => 'rendah', 'status' => 'standby'],
            ],
            'tangki' => [['nama' => '1', 'level_cm' => '120'], ['nama' => '', 'level_cm' => '']],
            'peralatan' => [['nama' => 'Radio HT', 'ada' => true, 'jumlah' => 1], ['nama' => '', 'ada' => false, 'jumlah' => null]],
            'kejadian' => [['jam' => '16:00', 'uraian' => 'Terima tugas dari shift B'], ['jam' => '17:37', 'uraian' => '  ']],
            'gangguan_mesin' => 'CM 7 kebocoran lube hose',
            'catatan' => '',
            'regu_penyerah' => 'D',
            'penyerah_nama' => 'Operator D',
        ], $overrides);
    }

    public function test_a_new_sheet_starts_from_the_active_machines_and_the_previous_sheet(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $machine = Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'name' => 'Cummins #6']);
        Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => false]);
        OperatorMutasi::factory()->create([
            'unit_id' => $unit->id, 'tanggal' => '2026-09-09', 'shift' => 'pagi',
            'mesin' => [['machine_id' => $machine->id, 'nama' => 'CUMMINS #6', 'level_bbm' => null, 'tambah_bbm' => null, 'pelumas' => 'normal', 'status' => 'standby']],
            'peralatan' => [['nama' => 'Senter', 'ada' => true, 'jumlah' => 3]],
        ]);

        $this->actingAs($this->leader($unit))
            ->get(route('operator.mutasi.index', ['unit_id' => $unit->id, 'tanggal' => '2026-09-09', 'shift' => 'sore']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operator/mutasi/index')
                ->where('mutasi.id', null)
                ->where('mutasi.status', 'baru')
                ->has('mutasi.mesin', 1)
                ->where('mutasi.mesin.0.nama', 'CUMMINS #6')
                ->where('mutasi.mesin.0.status', 'standby')
                ->where('mutasi.peralatan.0', ['nama' => 'Senter', 'ada' => false, 'jumlah' => null])
                ->where('mutasi.carried_from', '09/09/2026 shift pagi')
                ->has('riwayat', 1)
                ->where('can_write', true)
                ->where('can_receive', false));
    }

    public function test_the_start_stop_of_the_shift_comes_from_start_stop_engine(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $machine = Machine::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'name' => 'Cummins #6']);
        $record = fn (string $date, ?string $start, ?string $stop, string $cause = 'normal', ?string $note = null) => OperatorStartStop::query()->create([
            'unit_id' => $unit->id, 'engine_id' => $machine->id, 'tanggal' => $date, 'start_time' => $start, 'stop_time' => $stop, 'penyebab_stop' => $cause, 'keterangan' => $note,
        ]);
        $record('2026-09-09', '17:10', '21:30', 'gangguan', 'Overheat');   // sore
        $record('2026-09-09', '09:00', '12:00');                            // pagi
        $record('2026-09-09', '23:15', '02:40');                            // malam, stops after midnight
        $record('2026-09-08', '23:00', '01:00');                            // yesterday's malam

        $events = fn (string $shift) => $this->actingAs($this->leader($unit))
            ->get(route('operator.mutasi.index', ['unit_id' => $unit->id, 'tanggal' => '2026-09-09', 'shift' => $shift]))
            ->viewData('page')['props']['start_stop'];

        $this->assertSame(['17:10 Start Cummins #6', '21:30 Stop Cummins #6 — Gangguan: Overheat'], array_map(fn (array $e): string => $e['jam'].' '.$e['uraian'], $events('sore')));
        $this->assertSame(['09:00', '12:00'], array_column($events('pagi'), 'jam'));
        $this->assertSame(['23:15', '02:40'], array_column($events('malam'), 'jam'));

        // The saved sheet's PDF prints them.
        $this->actingAs($this->leader($unit))->post(route('operator.mutasi.store'), $this->payload($unit))->assertSessionHasNoErrors();
        $this->actingAs($this->leader($unit))->get(route('operator.mutasi.pdf', OperatorMutasi::query()->sole()))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_abnormal_patrol_check_findings_of_the_shift_are_handed_over(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $patrol = OperasiPatrolCheckMesin::query()->create(['unit_id' => $unit->id, 'nama_mesin' => 'MIRRLEES #1', 'year' => 2026, 'month' => 9]);
        $entry = fn (string $date, string $jam, array $items) => OperasiPatrolCheckEntry::query()->create([
            'patrol_check_mesin_id' => $patrol->id, 'tanggal' => $date, 'shift' => 'Shift B', 'jam' => $jam, 'operator_name' => 'Budi', 'items' => $items,
        ]);
        $tn = ['system' => 'FUEL SYSTEM', 'peralatan' => 'Fuel Line Pipe', 'status' => 'TN', 'catatan' => 'Rembes di sambungan'];
        $entry('2026-09-09', '17:30', [$tn, ['system' => 'FUEL SYSTEM', 'peralatan' => 'Fuel Daily Tank', 'status' => 'N', 'catatan' => '']]);
        $entry('2026-09-09', '09:00', [$tn]);               // pagi
        $entry('2026-09-10', '03:00', [$tn]);               // malam of the 9th

        $findings = fn (string $shift) => $this->actingAs($this->leader($unit))
            ->get(route('operator.mutasi.index', ['unit_id' => $unit->id, 'tanggal' => '2026-09-09', 'shift' => $shift]))
            ->viewData('page')['props']['patrol_findings'];

        $sore = $findings('sore');
        $this->assertCount(1, $sore);
        $this->assertSame(['17:30', 'MIRRLEES #1', 'Fuel Line Pipe', 'Rembes di sambungan', 'Budi'], [$sore[0]['jam'], $sore[0]['mesin'], $sore[0]['peralatan'], $sore[0]['catatan'], $sore[0]['operator']]);
        $this->assertCount(1, $findings('pagi'));
        $this->assertSame(['03:00'], array_column($findings('malam'), 'jam'));
    }

    public function test_the_fuel_level_and_the_oil_top_up_are_numbers(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $mesin = fn (array $row): array => ['mesin' => [['machine_id' => null, 'nama' => 'CUMMINS #6', 'pelumas' => 'normal', 'status' => 'operasi', ...$row]]];

        // The ✓ mark is gone: only numbers (cm / liter).
        $this->actingAs($this->leader($unit))->post(route('operator.mutasi.store'), $this->payload($unit, $mesin(['level_bbm' => '✓'])))
            ->assertSessionHasErrors('mesin.0.level_bbm');
        $this->actingAs($this->leader($unit))->post(route('operator.mutasi.store'), $this->payload($unit, $mesin(['tambah_pelumas' => 'dua liter'])))
            ->assertSessionHasErrors('mesin.0.tambah_pelumas');
        $this->assertSame(0, OperatorMutasi::query()->count());
    }

    public function test_the_operator_saves_a_draft_and_empty_rows_are_dropped(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->leader($unit))
            ->post(route('operator.mutasi.store'), $this->payload($unit))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('operator.mutasi.index', ['unit_id' => $unit->id, 'tanggal' => '2026-09-09', 'shift' => 'sore']));

        $mutasi = OperatorMutasi::query()->sole();
        $this->assertSame('2026-09-09', $mutasi->tanggal->toDateString());
        $this->assertCount(1, $mutasi->tangki);
        $this->assertCount(1, $mutasi->peralatan);
        $this->assertCount(1, $mutasi->kejadian);
        $this->assertNull($mutasi->mesin[1]['level_bbm']);
        $this->assertSame(['85.5', '12', '1200'], [$mutasi->mesin[0]['level_bbm'], $mutasi->mesin[0]['tambah_pelumas'], $mutasi->mesin[0]['tambah_bbm']]);
        $this->assertNull($mutasi->diserahkan_at);

        // Saving again updates the same sheet.
        $this->post(route('operator.mutasi.store'), $this->payload($unit, ['catatan' => 'Update']))->assertSessionHasNoErrors();
        $this->assertSame(1, OperatorMutasi::query()->count());
        $this->assertSame('Update', $mutasi->fresh()->catatan);
    }

    public function test_handing_over_needs_a_regu_and_a_paraf(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $operator = $this->leader($unit);

        $this->actingAs($operator)->post(route('operator.mutasi.store'), $this->payload($unit, ['serahkan' => true]))->assertSessionHasErrors('paraf');
        $this->actingAs($operator)->post(route('operator.mutasi.store'), $this->payload($unit, ['serahkan' => true, 'regu_penyerah' => null, 'paraf' => self::PNG]))->assertSessionHasErrors('regu_penyerah');
        $this->actingAs($operator)->post(route('operator.mutasi.store'), $this->payload($unit, ['serahkan' => true, 'paraf' => 'data:image/png;base64,'.base64_encode('x')]))->assertSessionHasErrors('paraf');
        $this->assertSame(0, OperatorMutasi::query()->count());

        $this->actingAs($operator)->post(route('operator.mutasi.store'), $this->payload($unit, ['serahkan' => true, 'paraf' => self::PNG]))->assertSessionHasNoErrors();

        $mutasi = OperatorMutasi::query()->sole();
        $this->assertNotNull($mutasi->diserahkan_at);
        Storage::disk('public')->assertExists($mutasi->paraf_penyerah);
    }

    public function test_the_next_regu_accepts_and_the_sheet_locks(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $mutasi = OperatorMutasi::factory()->create(['unit_id' => $unit->id, 'diserahkan_at' => now(), 'regu_penyerah' => 'D']);
        $operator = $this->leader($unit);

        $this->actingAs($operator)->post(route('operator.mutasi.terima', $mutasi), ['regu_penerima' => 'D', 'paraf' => self::PNG])->assertSessionHasErrors('regu_penerima');
        $this->actingAs($operator)->post(route('operator.mutasi.terima', $mutasi), ['regu_penerima' => 'A', 'penerima_nama' => 'Operator A', 'paraf' => self::PNG])->assertSessionHasNoErrors();

        $mutasi->refresh();
        $this->assertTrue($mutasi->isReceived());
        $this->assertSame('A', $mutasi->regu_penerima);
        Storage::disk('public')->assertExists($mutasi->paraf_penerima);

        $this->actingAs($operator)
            ->get(route('operator.mutasi.index', ['unit_id' => $unit->id, 'tanggal' => '2026-09-09', 'shift' => 'sore']))
            ->assertInertia(fn ($page) => $page->where('mutasi.status', 'diterima')->where('can_write', false)->where('can_receive', false));

        $this->actingAs($operator)->post(route('operator.mutasi.store'), $this->payload($unit))->assertSessionHasErrors('shift');
        $this->actingAs($operator)->post(route('operator.mutasi.terima', $mutasi), ['regu_penerima' => 'B', 'paraf' => self::PNG])->assertSessionHasErrors('regu_penerima');
    }

    public function test_a_sheet_not_handed_over_cannot_be_accepted(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $mutasi = OperatorMutasi::factory()->create(['unit_id' => $unit->id]);

        $this->actingAs($this->leader($unit))
            ->post(route('operator.mutasi.terima', $mutasi), ['regu_penerima' => 'A', 'paraf' => self::PNG])
            ->assertSessionHasErrors('regu_penerima');
    }

    public function test_viewers_cannot_write_and_other_roles_and_units_are_forbidden(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true]);
        $foreign = OperatorMutasi::factory()->create(['diserahkan_at' => now()]);

        $tl = $this->userWithRole(RoleName::TeamLeaderOperasi, $unit);
        $this->actingAs($tl)->get(route('operator.mutasi.index', ['unit_id' => $unit->id]))->assertOk()->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($tl)->post(route('operator.mutasi.store'), $this->payload($unit))->assertForbidden();

        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $serviceUnit))->get(route('operator.mutasi.index', ['unit_id' => $unit->id]))->assertOk();
        $this->actingAs($this->userWithRole(RoleName::SiteLeader, $unit))->get(route('operator.mutasi.index'))->assertForbidden();

        $operator = $this->leader($unit);
        $this->actingAs($operator)->post(route('operator.mutasi.terima', $foreign), ['regu_penerima' => 'A', 'paraf' => self::PNG])->assertForbidden();
        $this->actingAs($operator)->get(route('operator.mutasi.pdf', $foreign))->assertForbidden();
    }

    public function test_the_pdf_follows_the_paper_form(): void
    {
        $unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Poasia']);
        $mutasi = OperatorMutasi::factory()->received()->create(['unit_id' => $unit->id, 'diserahkan_at' => now()]);

        $html = view('operator.mutasi-pdf', [
            'unit' => $unit, 'mutasi' => $mutasi, 'shifts' => OperatorMutasi::SHIFTS, 'hariTanggal' => 'Rabu, 9 September 2026',
            'parafPenyerah' => self::PNG, 'parafPenerima' => null, 'logoLeft' => null, 'logoRight' => null,
        ])->render();

        $this->assertStringContainsString('LEMBAR MUTASI OPERATOR PLTD POASIA', $html);
        $this->assertStringContainsString('CUMMINS #6', $html);
        $this->assertStringContainsString('Radio HT', $html);
        $this->assertStringContainsString('Terima tugas dari shift B', $html);
        $this->assertStringContainsString('CM 7 kebocoran pada sisi lube hose', $html);
        $this->assertStringContainsString('alt="Paraf penyerah"', $html);

        $this->actingAs($this->leader($unit))
            ->get(route('operator.mutasi.pdf', $mutasi))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
