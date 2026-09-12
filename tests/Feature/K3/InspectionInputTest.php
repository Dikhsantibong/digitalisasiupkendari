<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\Inspection;
use App\Models\InspectionChecklist;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class InspectionInputTest extends TestCase
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
            ->get(route('k3.input.inspection.index'))
            ->assertForbidden();
    }

    public function test_tl_sees_a_row_per_checklist_item_of_the_form(): void
    {
        $unit = Unit::factory()->create();
        InspectionChecklist::factory()->forUnit($unit)->count(3)->create(['form_code' => 'tempat-kerja']);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.input.inspection.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'form_code' => 'tempat-kerja']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/input/inspections')
                ->where('filters.form_code', 'tempat-kerja')
                ->has('rows', 3),
            );
    }

    public function test_saving_upserts_the_session_and_replaces_its_results(): void
    {
        $unit = Unit::factory()->create();
        InspectionChecklist::factory()->forUnit($unit)->create(['form_code' => 'tempat-kerja', 'item_text' => 'APAR tersedia']);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.input.inspection.store'), [
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'form_code' => 'tempat-kerja',
            'header' => ['inspection_date' => '2026-08-10', 'ketua_tim' => 'Andi'],
            'rows' => [
                ['item_ref' => 'APAR tersedia', 'kondisi' => 'Baik', 'tindak_lanjut' => '-'],
            ],
        ])->assertRedirect();

        $inspection = Inspection::query()->with('results')
            ->where('unit_id', $unit->id)->where('form_code', 'tempat-kerja')->firstOrFail();

        $this->assertSame('Andi', $inspection->ketua_tim);
        $this->assertCount(1, $inspection->results);
        $this->assertSame('Baik', $inspection->results->first()->kondisi);

        // Saving again replaces the results (no duplicates).
        $this->actingAs($user)->post(route('k3.input.inspection.store'), [
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'form_code' => 'tempat-kerja',
            'rows' => [['item_ref' => 'APAR tersedia', 'kondisi' => 'Rusak']],
        ])->assertRedirect();

        $this->assertSame(1, Inspection::query()->where('unit_id', $unit->id)->count());
        $this->assertSame(1, $inspection->fresh()->results()->count());
        $this->assertSame('Rusak', $inspection->fresh()->results->first()->kondisi);
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $ownUnit))
            ->post(route('k3.input.inspection.store'), [
                'unit_id' => $foreignUnit->id, 'year' => 2026, 'month' => 8, 'form_code' => 'tempat-kerja', 'rows' => [],
            ])
            ->assertForbidden();
    }
}
