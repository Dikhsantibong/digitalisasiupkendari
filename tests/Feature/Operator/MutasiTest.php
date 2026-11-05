<?php

namespace Tests\Feature\Operator;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\Machine;
use App\Models\OperatorMutasi;
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
                ['machine_id' => null, 'nama' => 'CUMMINS #6', 'level_bbm' => '✓', 'tambah_bbm' => '1200', 'pelumas' => 'normal', 'status' => 'operasi'],
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
