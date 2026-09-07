<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\MachineSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the core promise: a user assigned to one generating unit only ever sees
 * and edits that unit's data. Uses the real organisation + machine seeders so
 * the assertions run against the actual PLTD Bau-Bau vs PLTD Poasia data.
 */
class UnitScopingTest extends TestCase
{
    use RefreshDatabase;

    private Unit $bauBau;

    private Unit $poasia;

    private User $tlBauBau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, OrganizationSeeder::class, MachineSeeder::class]);

        $this->bauBau = Unit::query()->where('code', 'PLTD-BAUBAU')->firstOrFail();
        $this->poasia = Unit::query()->where('code', 'PLTD-POASIA')->firstOrFail();

        $this->tlBauBau = User::factory()->create();
        $this->tlBauBau->assignRole(RoleName::TeamLeaderOperasi, $this->bauBau);
        $this->tlBauBau = $this->tlBauBau->fresh();
    }

    public function test_access_control_resolves_only_the_assigned_unit(): void
    {
        $this->assertSame([$this->bauBau->id], $this->tlBauBau->accessibleUnitIds());
        $this->assertTrue($this->tlBauBau->canAccessUnit($this->bauBau));
        $this->assertFalse($this->tlBauBau->canAccessUnit($this->poasia));
    }

    public function test_the_input_grid_only_lists_bau_bau_machines(): void
    {
        $bauBauMachineIds = Machine::query()->where('unit_id', $this->bauBau->id)->pluck('id')->sort()->values()->all();
        $poasiaMachineIds = Machine::query()->where('unit_id', $this->poasia->id)->pluck('id')->all();

        // Sanity: the two units actually have distinct machines seeded.
        $this->assertNotEmpty($bauBauMachineIds);
        $this->assertNotEmpty($poasiaMachineIds);

        $this->actingAs($this->tlBauBau)
            ->get(route('operasi.input.daily-report.index', ['unit_id' => $this->bauBau->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.unit_id', $this->bauBau->id)
                ->where('options.machines', function ($machines) use ($bauBauMachineIds, $poasiaMachineIds): bool {
                    $ids = collect($machines)->pluck('id')->sort()->values()->all();

                    return $ids === $bauBauMachineIds
                        && collect($machines)->pluck('id')->intersect($poasiaMachineIds)->isEmpty();
                }),
            );
    }

    public function test_requesting_a_foreign_unit_falls_back_to_the_users_own_unit(): void
    {
        // Even if the URL asks for Poasia, the user only ever gets their own unit.
        $this->actingAs($this->tlBauBau)
            ->get(route('operasi.input.daily-report.index', ['unit_id' => $this->poasia->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('filters.unit_id', $this->bauBau->id));
    }

    public function test_input_cannot_be_saved_against_a_foreign_units_machine(): void
    {
        $poasiaEngine = Machine::query()->where('unit_id', $this->poasia->id)->firstOrFail();

        $this->actingAs($this->tlBauBau)
            ->post(route('operasi.input.daily-report.store'), [
                'unit_id' => $this->poasia->id,
                'engine_id' => $poasiaEngine->id,
                'month' => 8,
                'year' => 2026,
                'rows' => [['day' => 1, 'kwh_produksi_stand_akhir' => '100']],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('daily_engine_reports', 0);
    }

    public function test_master_operasi_only_shows_the_users_unit(): void
    {
        // The master screen defaults to and is limited to the user's own unit.
        $this->actingAs($this->tlBauBau)
            ->get(route('operasi.master.index', ['resource' => 'feeders', 'unit_id' => $this->poasia->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('filters.unit_id', $this->bauBau->id));
    }
}
