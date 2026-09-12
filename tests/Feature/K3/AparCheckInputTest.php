<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\FireExtinguisher;
use App\Models\FireExtinguisherCheck;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class AparCheckInputTest extends TestCase
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
            ->get(route('k3.input.apar-check.index'))
            ->assertForbidden();
    }

    public function test_tl_sees_a_row_per_extinguisher(): void
    {
        $unit = Unit::factory()->create();
        FireExtinguisher::factory()->forUnit($unit)->count(3)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.input.apar-check.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/input/apar-checks')
                ->has('rows', 3),
            );
    }

    public function test_saving_upserts_a_check_per_extinguisher(): void
    {
        $unit = Unit::factory()->create();
        $ext = FireExtinguisher::factory()->forUnit($unit)->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.input.apar-check.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'rows' => [[
                'extinguisher_id' => $ext->id, 'kondisi_tabung' => 'Baik',
                'indikator_tekanan' => 'Hijau', 'exp_date' => '2027-01-31',
            ]],
        ])->assertRedirect();

        $check = FireExtinguisherCheck::query()->where('fire_extinguisher_id', $ext->id)->firstOrFail();
        $this->assertSame('Baik', $check->kondisi_tabung);
        $this->assertSame('2027-01-31', $check->exp_date->toDateString());

        // A second save updates the same row, not a duplicate.
        $this->actingAs($user)->post(route('k3.input.apar-check.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'rows' => [['extinguisher_id' => $ext->id, 'kondisi_tabung' => 'Perlu Isi Ulang']],
        ])->assertRedirect();

        $this->assertSame(1, FireExtinguisherCheck::query()->where('fire_extinguisher_id', $ext->id)->count());
        $this->assertSame('Perlu Isi Ulang', $check->fresh()->kondisi_tabung);
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $ownUnit))
            ->post(route('k3.input.apar-check.store'), [
                'unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026, 'rows' => [],
            ])
            ->assertForbidden();
    }
}
