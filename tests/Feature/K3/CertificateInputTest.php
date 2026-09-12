<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\EquipmentCategory;
use App\Models\EquipmentCertificate;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class CertificateInputTest extends TestCase
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
            ->get(route('k3.input.certificate.index'))
            ->assertForbidden();
    }

    public function test_tl_sees_the_certificate_grid(): void
    {
        $unit = Unit::factory()->create();
        EquipmentCertificate::factory()->forUnit($unit)->count(2)->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.input.certificate.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/input/certificates')
                ->has('rows', 2),
            );
    }

    public function test_saving_replaces_rows_and_resolves_the_category_code(): void
    {
        $unit = Unit::factory()->create();
        $category = EquipmentCategory::factory()->create(['code' => 'CRANE']);
        EquipmentCertificate::factory()->forUnit($unit)->create(['jenis' => 'Lama']);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.input.certificate.store'), [
            'unit_id' => $unit->id,
            'rows' => [
                ['category_code' => 'CRANE', 'jenis' => 'Overhead Crane', 'uji_ulang_tanggal' => '2027-01-31'],
                ['category_code' => 'TIDAK-ADA', 'jenis' => 'Tangki'],
            ],
        ])->assertRedirect();

        $this->assertSame(2, EquipmentCertificate::query()->where('unit_id', $unit->id)->count());
        $this->assertDatabaseMissing('equipment_certificates', ['jenis' => 'Lama']);

        $crane = EquipmentCertificate::query()->where('jenis', 'Overhead Crane')->firstOrFail();
        $this->assertSame($category->id, $crane->equipment_category_id);

        // An unknown category code resolves to null, not an error.
        $tangki = EquipmentCertificate::query()->where('jenis', 'Tangki')->firstOrFail();
        $this->assertNull($tangki->equipment_category_id);
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $ownUnit))
            ->post(route('k3.input.certificate.store'), ['unit_id' => $foreignUnit->id, 'rows' => []])
            ->assertForbidden();
    }
}
