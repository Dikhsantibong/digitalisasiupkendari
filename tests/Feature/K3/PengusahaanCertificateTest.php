<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\EquipmentCategory;
use App\Models\EquipmentCertificate;
use App\Models\K3PengusahaanCertificate;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanCertificateTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_and_staf_k3_can_view_and_save_certificate_pengusahaan(): void
    {
        $category = EquipmentCategory::factory()->create(['code' => 'PUBT', 'name' => 'Pesawat Uap dan Bejana Tekan']);

        foreach ([RoleName::TeamLeaderK3, RoleName::StafK3] as $role) {
            $unit = Unit::factory()->create(['is_active' => true]);
            $user = $this->userWithRole($role, $unit);

            $this->actingAs($user)
                ->get(route('k3.pengusahaan.certificate.index', ['unit_id' => $unit->id]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/certificate/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('options.categories')
                );

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.certificate.store'), [
                    'unit_id' => $unit->id,
                    'rows' => [
                        [
                            'category_code' => $category->code,
                            'jenis' => 'Bejana Tekan Angin Unit 1',
                            'kapasitas' => '500 Liter',
                            'lokasi' => 'Sentral PLTD',
                            'merk_manufacture' => 'Atlas Copco',
                            'no_seri' => 'AC-99281',
                            'regulasi' => 'Permenaker 37/2016',
                            'ijin_awal_nomor' => 'IA-1234/2020',
                            'ijin_awal_tanggal' => '2020-01-15',
                            'uji_terakhir_nomor' => 'UT-5678/2025',
                            'uji_terakhir_tanggal' => '2025-01-15',
                            'uji_ulang_tanggal' => '2027-01-15',
                            'batasan_uji' => '10 Bar',
                            'masa_berlaku_tahun' => 2,
                            'keterangan' => 'Kondisi Baik',
                        ],
                    ],
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_certificates', [
                'unit_id' => $unit->id,
                'equipment_category_id' => $category->id,
                'jenis' => 'Bejana Tekan Angin Unit 1',
                'kapasitas' => '500 Liter',
                'lokasi' => 'Sentral PLTD',
                'merk_manufacture' => 'Atlas Copco',
                'no_seri' => 'AC-99281',
                'regulasi' => 'Permenaker 37/2016',
                'ijin_awal_nomor' => 'IA-1234/2020',
                'uji_terakhir_nomor' => 'UT-5678/2025',
                'batasan_uji' => '10 Bar',
                'masa_berlaku_tahun' => 2,
                'keterangan' => 'Kondisi Baik',
                'input_by' => $user->id,
            ]);

            $savedCert = K3PengusahaanCertificate::query()
                ->where('unit_id', $unit->id)
                ->where('no_seri', 'AC-99281')
                ->firstOrFail();

            $this->assertSame('2020-01-15', $savedCert->ijin_awal_tanggal?->format('Y-m-d'));
            $this->assertSame('2025-01-15', $savedCert->uji_terakhir_tanggal?->format('Y-m-d'));
            $this->assertSame('2027-01-15', $savedCert->uji_ulang_tanggal?->format('Y-m-d'));
        }
    }

    public function test_koordinator_and_other_module_staf_receive_403(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $unauthorizedRoles = [
            RoleName::KoordinatorK3,
            RoleName::StafOperasi,
            RoleName::StafPemeliharaan,
            RoleName::Operator,
        ];

        foreach ($unauthorizedRoles as $role) {
            $user = $this->userWithRole($role, $unit);

            $this->actingAs($user)
                ->get(route('k3.pengusahaan.certificate.index', ['unit_id' => $unit->id]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.certificate.store'), [
                    'unit_id' => $unit->id,
                    'rows' => [],
                ])
                ->assertForbidden();
        }
    }

    public function test_manager_ul_can_view_but_cannot_save(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('k3.pengusahaan.certificate.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/certificate/index')
                ->where('can_write', false)
            );

        $this->actingAs($manager)
            ->post(route('k3.pengusahaan.certificate.store'), [
                'unit_id' => $unit->id,
                'rows' => [],
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_access_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create(['is_active' => true]);
        $foreignUnit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $ownUnit);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.certificate.index', ['unit_id' => $foreignUnit->id]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.certificate.store'), [
                'unit_id' => $foreignUnit->id,
                'rows' => [],
            ])
            ->assertForbidden();
    }

    public function test_saving_certificate_pengusahaan_does_not_modify_old_equipment_certificates(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $category = EquipmentCategory::factory()->create();

        // Old Akses 1 Equipment Certificate record
        $oldCert = EquipmentCertificate::factory()->create([
            'unit_id' => $unit->id,
            'equipment_category_id' => $category->id,
            'jenis' => 'Peralatan Akses 1 Lama',
            'no_seri' => 'OLD-SERIES-001',
            'keterangan' => 'Data Lama Jangan Berubah',
        ]);

        $initialOldCount = EquipmentCertificate::count();

        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Save new Akses 2 Pengusahaan Certificate
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.certificate.store'), [
                'unit_id' => $unit->id,
                'rows' => [
                    [
                        'category_code' => $category->code,
                        'jenis' => 'Peralatan Akses 2 Pengusahaan',
                        'no_seri' => 'NEW-PENGUSAHAAN-999',
                        'keterangan' => 'Data Baru Pengusahaan',
                    ],
                ],
            ])
            ->assertRedirect();

        // Assert old record and count in equipment_certificates is completely unchanged
        $this->assertSame($initialOldCount, EquipmentCertificate::count());
        $this->assertDatabaseHas('equipment_certificates', [
            'id' => $oldCert->id,
            'unit_id' => $unit->id,
            'jenis' => 'Peralatan Akses 1 Lama',
            'no_seri' => 'OLD-SERIES-001',
            'keterangan' => 'Data Lama Jangan Berubah',
        ]);

        // Assert new record exists in k3_pengusahaan_certificates
        $this->assertDatabaseHas('k3_pengusahaan_certificates', [
            'unit_id' => $unit->id,
            'equipment_category_id' => $category->id,
            'jenis' => 'Peralatan Akses 2 Pengusahaan',
            'no_seri' => 'NEW-PENGUSAHAAN-999',
            'keterangan' => 'Data Baru Pengusahaan',
        ]);

        $newCert = K3PengusahaanCertificate::query()
            ->where('unit_id', $unit->id)
            ->where('no_seri', 'NEW-PENGUSAHAAN-999')
            ->firstOrFail();

        $this->assertSame('Peralatan Akses 2 Pengusahaan', $newCert->jenis);
    }
}
