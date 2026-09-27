<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanEmergencyFacility;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanEmergencyFacilityTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_and_staf_k3_can_view_and_save_emergency_facility_m1(): void
    {
        foreach ([RoleName::TeamLeaderK3, RoleName::StafK3] as $role) {
            $unit = Unit::factory()->create(['is_active' => true]);
            $user = $this->userWithRole($role, $unit);

            $this->actingAs($user)
                ->get(route('k3.pengusahaan.emergency-facility.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                    'periode' => 'M1',
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('k3/pengusahaan/emergency-facility/index')
                    ->where('unit.id', $unit->id)
                    ->where('filters.periode', 'M1')
                    ->where('can_write', true)
                    ->has('rows')
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'periode' => 'M1',
                'no_dokumen' => 'SMT-FM-AK3-12.01',
                'revisi' => '00',
                'tanggal_dokumen' => '23 September 2019',
                'halaman' => '1 dari 1',
                'rows' => [
                    [
                        'grup' => 'Fire Pump',
                        'no_urut' => 1,
                        'nama_peralatan' => 'Fire Protection Jockey Pump',
                        'jml_total' => '1',
                        'jml_ready' => '1',
                        'jml_not_ready' => '0',
                        'persen_kesiapan' => '100%',
                        'lokasi' => 'Fire Pump House',
                        'kendala' => null,
                        'tindak_lanjut' => null,
                    ],
                    [
                        'grup' => 'Fire Pump',
                        'no_urut' => 5,
                        'nama_peralatan' => 'Fire Water Level',
                        'jml_total' => '196.000 Liter',
                        'jml_ready' => '',
                        'jml_not_ready' => '',
                        'persen_kesiapan' => '87.5%',
                        'lokasi' => 'Fire Water Tank',
                        'kendala' => null,
                        'tindak_lanjut' => 'Telah disediakan jalur pengisian bertahap',
                    ],
                ],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.emergency-facility.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_emergency_facilities', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'periode' => 'M1',
                'nama_peralatan' => 'Fire Protection Jockey Pump',
                'jml_total' => '1',
                'jml_ready' => '1',
                'persen_kesiapan' => '100%',
                'input_by' => $user->id,
            ]);

            $this->assertDatabaseHas('k3_pengusahaan_emergency_facilities', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'periode' => 'M1',
                'nama_peralatan' => 'Fire Water Level',
                'jml_total' => '196.000 Liter',
                'persen_kesiapan' => '87.5%',
            ]);
        }
    }

    public function test_can_copy_emergency_facility_from_m1_to_m2(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Seed M1 data
        K3PengusahaanEmergencyFacility::factory()->create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'periode' => 'M1',
            'no_urut' => 1,
            'nama_peralatan' => 'Fire Protection Jockey Pump',
            'jml_total' => '1',
            'jml_ready' => '1',
            'jml_not_ready' => '0',
            'persen_kesiapan' => '100%',
            'lokasi' => 'Fire Pump House',
        ]);

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.emergency-facility.copy'), [
                'unit_id' => $unit->id,
                'source_year' => 2026,
                'source_month' => 8,
                'source_periode' => 'M1',
                'target_year' => 2026,
                'target_month' => 8,
                'target_periode' => 'M2',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('k3_pengusahaan_emergency_facilities', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'periode' => 'M2',
            'nama_peralatan' => 'Fire Protection Jockey Pump',
            'persen_kesiapan' => '100%',
        ]);
    }

    public function test_m1_to_m4_have_different_weekly_inspection_data(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Fetch M1
        $resM1 = $this->actingAs($user)
            ->get(route('k3.pengusahaan.emergency-facility.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'periode' => 'M1',
            ]))
            ->assertOk();

        $rowsM1 = $resM1->inertiaProps('rows');
        $waterM1 = collect($rowsM1)->firstWhere('no_urut', 5);

        // Fetch M2
        $resM2 = $this->actingAs($user)
            ->get(route('k3.pengusahaan.emergency-facility.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'periode' => 'M2',
            ]))
            ->assertOk();

        $rowsM2 = $resM2->inertiaProps('rows');
        $waterM2 = collect($rowsM2)->firstWhere('no_urut', 5);

        // Fetch M4
        $resM4 = $this->actingAs($user)
            ->get(route('k3.pengusahaan.emergency-facility.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'periode' => 'M4',
            ]))
            ->assertOk();

        $rowsM4 = $resM4->inertiaProps('rows');
        $waterM4 = collect($rowsM4)->firstWhere('no_urut', 5);

        // Verify that M1, M2, and M4 have distinct inspection data as requested
        $this->assertEquals('196.000 Liter', $waterM1['jml_ready']);
        $this->assertEquals('87.5%', $waterM1['persen_kesiapan']);

        $this->assertEquals('198.000 Liter', $waterM2['jml_ready']);
        $this->assertEquals('88.4%', $waterM2['persen_kesiapan']);

        $this->assertEquals('224.000 Liter', $waterM4['jml_ready']);
        $this->assertEquals('100%', $waterM4['persen_kesiapan']);
    }

    public function test_koordinator_and_other_modules_receive_403(): void
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
                ->get(route('k3.pengusahaan.emergency-facility.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                    'periode' => 'M1',
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.emergency-facility.store'), [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                    'periode' => 'M1',
                ])
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.emergency-facility.copy'), [
                    'unit_id' => $unit->id,
                    'source_year' => 2026,
                    'source_month' => 8,
                    'source_periode' => 'M1',
                    'target_year' => 2026,
                    'target_month' => 8,
                    'target_periode' => 'M2',
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
            ->get(route('k3.pengusahaan.emergency-facility.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'periode' => 'M1',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/pengusahaan/emergency-facility/index')
                ->where('can_write', false)
            );

        $this->actingAs($manager)
            ->post(route('k3.pengusahaan.emergency-facility.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'periode' => 'M1',
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_access_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create(['is_active' => true]);
        $foreignUnit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $ownUnit);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.emergency-facility.index', [
                'unit_id' => $foreignUnit->id,
                'month' => 8,
                'year' => 2026,
                'periode' => 'M1',
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.emergency-facility.store'), [
                'unit_id' => $foreignUnit->id,
                'month' => 8,
                'year' => 2026,
                'periode' => 'M1',
            ])
            ->assertForbidden();
    }
}
