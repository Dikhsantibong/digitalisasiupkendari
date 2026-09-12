<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\AccidentReport;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class AccidentInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_k3_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('k3.input.accident.index'))
            ->assertForbidden();
    }

    public function test_manager_can_view_but_not_write(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.input.accident.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('k3/input/accidents')->where('can_write', true));
    }

    public function test_saving_replaces_the_periods_rows(): void
    {
        $unit = Unit::factory()->create();
        AccidentReport::factory()->forUnit($unit)->create(['year' => 2026, 'month' => 8]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.input.accident.store'), [
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8,
            'rows' => [
                ['category' => 'pak', 'luka_ringan' => 2, 'luka_berat' => 0, 'meninggal' => 0, 'is_nihil' => false, 'lokasi' => 'Ruang Genset'],
            ],
        ])->assertRedirect();

        $this->assertSame(1, AccidentReport::query()->where('unit_id', $unit->id)->count());
        $report = AccidentReport::query()->firstOrFail();
        $this->assertSame(2, $report->luka_ringan);
        $this->assertFalse($report->is_nihil);
    }

    public function test_a_nihil_month_is_stored(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.input.accident.store'), [
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8,
            'rows' => [['category' => 'pak', 'is_nihil' => true]],
        ])->assertRedirect();

        $report = AccidentReport::query()->firstOrFail();
        $this->assertTrue($report->is_nihil);
        $this->assertSame(0, $report->meninggal);
    }

    public function test_an_invalid_category_is_rejected(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->post(route('k3.input.accident.store'), [
                'unit_id' => $unit->id, 'year' => 2026, 'month' => 8,
                'rows' => [['category' => 'tidak-ada']],
            ])
            ->assertSessionHasErrors('rows.0.category');
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $ownUnit))
            ->post(route('k3.input.accident.store'), [
                'unit_id' => $foreignUnit->id, 'year' => 2026, 'month' => 8, 'rows' => [],
            ])
            ->assertForbidden();
    }
}
