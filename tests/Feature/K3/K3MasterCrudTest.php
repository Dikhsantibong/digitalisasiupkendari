<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\ApdCategory;
use App\Models\ApdItem;
use App\Models\EmergencyEquipment;
use App\Models\EquipmentCategory;
use App\Models\FireExtinguisher;
use App\Models\InspectionChecklist;
use App\Models\K3ActivityType;
use App\Models\P3kBox;
use App\Models\PatrolLocation;
use App\Models\SecurityPost;
use App\Models\Unit;
use App\Services\K3\Master\K3MasterRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class K3MasterCrudTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_master_permission_is_forbidden(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::Operator, $unit);

        $this->actingAs($user)
            ->get(route('k3.master.index', ['resource' => 'activity-types']))
            ->assertForbidden();
    }

    public function test_all_k3_master_resources_are_unit_scoped(): void
    {
        $registry = app(K3MasterRegistry::class);

        foreach ($registry->resources() as $slug => $resource) {
            $this->assertTrue(
                $resource['unit_scoped'],
                "Expected K3 master resource [{$slug}] to be unit_scoped => true.",
            );
        }
    }

    public function test_it_creates_updates_and_deletes_activity_types_per_unit(): void
    {
        $unitA = Unit::factory()->create();
        $unitB = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($user)
            ->post(route('k3.master.store', ['resource' => 'activity-types']), [
                'unit_id' => $unitA->id,
                'code' => 'INSP-P3K',
                'name' => 'Inspeksi P3K Unit A',
                'category' => 'Inspeksi',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertRedirect();

        // Same code can exist independently on unit B
        $this->actingAs($user)
            ->post(route('k3.master.store', ['resource' => 'activity-types']), [
                'unit_id' => $unitB->id,
                'code' => 'INSP-P3K',
                'name' => 'Inspeksi P3K Unit B',
                'category' => 'Inspeksi',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->assertRedirect();

        $recordA = K3ActivityType::query()->where('unit_id', $unitA->id)->where('code', 'INSP-P3K')->firstOrFail();
        $recordB = K3ActivityType::query()->where('unit_id', $unitB->id)->where('code', 'INSP-P3K')->firstOrFail();
        $this->assertNotSame($recordA->id, $recordB->id);

        $this->actingAs($user)
            ->put(route('k3.master.update', ['resource' => 'activity-types', 'id' => $recordA->id]), [
                'unit_id' => $unitA->id,
                'code' => 'INSP-P3K',
                'name' => 'Inspeksi Kotak P3K Unit A',
                'category' => 'Inspeksi',
                'sort_order' => 2,
                'is_active' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('k3_activity_types', [
            'id' => $recordA->id,
            'unit_id' => $unitA->id,
            'name' => 'Inspeksi Kotak P3K Unit A',
            'is_active' => false,
        ]);

        // Unit B's record is untouched
        $this->assertDatabaseHas('k3_activity_types', [
            'id' => $recordB->id,
            'unit_id' => $unitB->id,
            'name' => 'Inspeksi P3K Unit B',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->delete(route('k3.master.destroy', ['resource' => 'activity-types', 'id' => $recordA->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('k3_activity_types', ['id' => $recordA->id]);
        $this->assertDatabaseHas('k3_activity_types', ['id' => $recordB->id]);
    }

    public function test_it_scopes_a_per_unit_resource_to_the_selected_unit(): void
    {
        $unitA = Unit::factory()->create();
        $unitB = Unit::factory()->create();
        PatrolLocation::factory()->forUnit($unitA)->create(['code' => 'POA1', 'name' => 'Pos 1']);
        PatrolLocation::factory()->forUnit($unitB)->create(['code' => 'BAU1', 'name' => 'Pos Bau']);

        $tlA = $this->userWithRole(RoleName::KoordinatorK3, $unitA);

        $this->actingAs($tlA)
            ->get(route('k3.master.index', ['resource' => 'patrol-locations', 'unit_id' => $unitA->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/master/index')
                ->has('rows', 1)
                ->where('rows.0.code', 'POA1'),
            );

        // Cannot write into another unit.
        $this->actingAs($tlA)
            ->post(route('k3.master.store', ['resource' => 'patrol-locations']), [
                'unit_id' => $unitB->id,
                'code' => 'BAU2',
                'name' => 'Pos 2',
            ])
            ->assertForbidden();
    }

    public function test_unit_masters_flow_into_jadwal_and_all_k3_inputs_per_unit(): void
    {
        $unitA = Unit::factory()->create(['name' => 'PLTD Unit A', 'is_active' => true]);
        $unitB = Unit::factory()->create(['name' => 'PLTM Unit B', 'is_active' => true]);

        // Seed distinct master data for Unit A vs Unit B
        K3ActivityType::factory()->forUnit($unitA)->create(['code' => 'ACT-A1', 'name' => 'Inspeksi Khusus Unit A', 'default_pic' => 'PIC A']);
        K3ActivityType::factory()->forUnit($unitB)->create(['code' => 'ACT-B1', 'name' => 'Inspeksi Turbin Unit B', 'default_pic' => 'PIC B']);

        $apdCatA = ApdCategory::factory()->forUnit($unitA)->create(['code' => 'HELM-A', 'name' => 'Helm Unit A']);
        $apdCatB = ApdCategory::factory()->forUnit($unitB)->create(['code' => 'HELM-B', 'name' => 'Helm Unit B']);

        ApdItem::factory()->forUnit($unitA)->create(['apd_category_id' => $apdCatA->id, 'name' => 'Helm Safety Merah Unit A', 'location' => 'CCR Unit A']);
        ApdItem::factory()->forUnit($unitB)->create(['apd_category_id' => $apdCatB->id, 'name' => 'Helm Safety Biru Unit B', 'location' => 'CCR Unit B']);

        $eqCatA = EquipmentCategory::factory()->forUnit($unitA)->create(['code' => 'TANGKI-A', 'name' => 'Tangki Timbun Unit A']);
        $eqCatB = EquipmentCategory::factory()->forUnit($unitB)->create(['code' => 'TURBIN-B', 'name' => 'Bejana Governor Unit B']);

        EmergencyEquipment::factory()->forUnit($unitA)->create(['group_name' => 'Pompa A', 'name' => 'Fire Pump Diesel Unit A', 'location' => 'Rumah Pompa A']);
        EmergencyEquipment::factory()->forUnit($unitB)->create(['group_name' => 'Pompa B', 'name' => 'Hydrant Indoor Unit B', 'location' => 'Power House B']);

        FireExtinguisher::factory()->forUnit($unitA)->create(['rfid' => 'A-01', 'location' => 'Sentral Unit A', 'merk' => 'Venom A']);
        FireExtinguisher::factory()->forUnit($unitB)->create(['rfid' => 'B-01', 'location' => 'Turbin Unit B', 'merk' => 'Yamato B']);

        P3kBox::factory()->forUnit($unitA)->create(['code' => 'P3K-A1', 'location' => 'Lobby Unit A']);
        P3kBox::factory()->forUnit($unitB)->create(['code' => 'P3K-B1', 'location' => 'Bendung Unit B']);

        PatrolLocation::factory()->forUnit($unitA)->create(['code' => 'UNITA1', 'name' => 'Pos Gerbang Unit A']);
        PatrolLocation::factory()->forUnit($unitB)->create(['code' => 'UNITB1', 'name' => 'Pos Intake Unit B']);

        SecurityPost::factory()->forUnit($unitA)->create(['name' => 'Regu Alpha']);
        SecurityPost::factory()->forUnit($unitB)->create(['name' => 'Regu Bravo']);

        InspectionChecklist::factory()->forUnit($unitA)->create(['form_code' => 'isi-p3k', 'item_text' => 'Kasa Steril Unit A']);
        InspectionChecklist::factory()->forUnit($unitB)->create(['form_code' => 'isi-p3k', 'item_text' => 'Perban Khusus Unit B']);

        InspectionChecklist::factory()->forUnit($unitA)->create(['form_code' => 'tempat-kerja', 'item_text' => 'A. KANTOR — Meja Kerja Unit A']);
        InspectionChecklist::factory()->forUnit($unitB)->create(['form_code' => 'tempat-kerja', 'item_text' => 'B. TURBIN — Lantai Turbin Unit B']);

        $koordA = $this->userWithRole(RoleName::KoordinatorK3, $unitA);
        $tlA = $this->userWithRole(RoleName::TeamLeaderK3, $unitA);

        // 1. Jadwal Time Frame Akses 1 pulls only Unit A's K3ActivityType
        $this->actingAs($koordA)
            ->get(route('k3.input.time-frame.index', ['unit_id' => $unitA->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.uraian', 'Inspeksi Khusus Unit A')
            );

        // 2. Input Akses 1 Certificate pulls only Unit A's EquipmentCategory
        $this->actingAs($koordA)
            ->get(route('k3.input.certificate.index', ['unit_id' => $unitA->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('options.categories', 1)
                ->where('options.categories.0.code', $eqCatA->code)
            );

        // 3. Input Akses 1 Daftar Inventaris APD pulls Unit A's ApdItem
        $this->actingAs($koordA)
            ->get(route('k3.input.apd-inventory.index', ['unit_id' => $unitA->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.nama', 'Helm Safety Merah Unit A')
                ->where('rows.0.subkategori', 'Helm Unit A')
            );

        // 4. Input Akses 1 Pemeriksaan Emergency Facility pulls Unit A's EmergencyEquipment
        $this->actingAs($koordA)
            ->get(route('k3.input.emergency-facility.index', ['unit_id' => $unitA->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.nama_peralatan', 'Fire Pump Diesel Unit A')
            );

        // 5. Input Akses 1 Kesiapan APD pulls Unit A's ApdItem, EmergencyEquipment, P3kBox, SecurityPost
        $this->actingAs($koordA)
            ->get(route('k3.input.kesiapan-apd.index', ['unit_id' => $unitA->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.inspeksi', 'Helm Safety Merah Unit A')
                ->where('rows.1.inspeksi', 'Fire Pump Diesel Unit A')
            );

        // 6. Pengusahaan Time Frame pulls Unit A's K3ActivityType
        $this->actingAs($tlA)
            ->get(route('k3.pengusahaan.time-frame.index', ['unit_id' => $unitA->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rows', 1)
                ->where('rows.0.uraian_pelaporan', 'Inspeksi Khusus Unit A')
            );

        // 7. Pengusahaan Alat Tanggap Darurat pulls Unit A's EmergencyEquipment
        $this->actingAs($tlA)
            ->get(route('k3.pengusahaan.alat-tanggap-darurat.index', ['unit_id' => $unitA->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('record.items', 1)
                ->where('record.items.0.jenis', 'Fire Pump Diesel Unit A')
            );

        // 8. Pengusahaan Kondisi APAR/APAB pulls Unit A's FireExtinguisher
        $this->actingAs($tlA)
            ->get(route('k3.pengusahaan.apar-apab.index', ['unit_id' => $unitA->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('record.items', 1)
                ->where('record.items.0.no_rfid', 'A-01')
                ->where('record.items.0.lokasi', 'Sentral Unit A')
            );

        // 9. Pengusahaan Inventaris APD pulls Unit A's ApdItem
        $this->actingAs($tlA)
            ->get(route('k3.pengusahaan.inventaris-apd.index', ['unit_id' => $unitA->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('record.items', 1)
                ->where('record.items.0.nama_alat', 'Helm Safety Merah Unit A')
            );

        // 10. Pengusahaan Pemeriksaan P3K pulls Unit A's P3kBox and InspectionChecklist (isi-p3k)
        $this->actingAs($tlA)
            ->get(route('k3.pengusahaan.pemeriksaan-p3k.index', ['unit_id' => $unitA->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('record.locations', ['Lobby Unit A'])
                ->has('record.items', 1)
                ->where('record.items.0.nama_isi', 'Kasa Steril Unit A')
            );

        // 11. Pengusahaan Inspeksi Tempat Kerja pulls Unit A's InspectionChecklist (tempat-kerja)
        $this->actingAs($tlA)
            ->get(route('k3.pengusahaan.inspeksi-tempat-kerja.index', ['unit_id' => $unitA->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('record.items', 1)
                ->where('record.items.0.category', 'A. KANTOR')
                ->where('record.items.0.item', 'Meja Kerja Unit A')
            );

        // 12. Pengusahaan Patrol Security pulls Unit A's PatrolLocation
        $this->actingAs($tlA)
            ->get(route('k3.pengusahaan.patrol-security.index', ['unit_id' => $unitA->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('record.items', 1)
                ->where('record.items.0.lokasi_kode', 'UNITA1')
                ->where('record.items.0.lokasi_nama', 'Pos Gerbang Unit A')
            );
    }
}
