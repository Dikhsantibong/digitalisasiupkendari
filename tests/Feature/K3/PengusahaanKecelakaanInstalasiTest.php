<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanKecelakaanInstalasiTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_and_staf_k3_can_view_and_save_nihil_report(): void
    {
        foreach ([RoleName::TeamLeaderK3, RoleName::StafK3] as $role) {
            $unit = Unit::factory()->create(['is_active' => true]);
            $user = $this->userWithRole($role, $unit);

            $this->actingAs($user)
                ->get(route('k3.pengusahaan.kecelakaan-instalasi.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('k3/pengusahaan/kecelakaan-instalasi/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->where('record.is_nihil', true)
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'is_nihil' => true,
                'lampiran_teks' => 'Lampiran 1 Keputusan Direksi PT PLN (Persero)',
                'nomor_keputusan' => 'Nomor :0251.P/DIR/2016',
                'catatan' => 'Bulan Agustus nihil kecelakaan instalasi.',
                'items' => [],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.kecelakaan-instalasi.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_kecelakaan_instalasis', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'is_nihil' => true,
                'nomor_keputusan' => 'Nomor :0251.P/DIR/2016',
                'input_by' => $user->id,
            ]);

            $this->assertDatabaseCount('k3_pengusahaan_kecelakaan_instalasi_items', 0);
        }
    }

    public function test_tl_can_save_report_with_incidents(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $payload = [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'is_nihil' => false,
            'lampiran_teks' => 'Lampiran 1 Keputusan Direksi PT PLN (Persero)',
            'nomor_keputusan' => 'Nomor :0251.P/DIR/2016',
            'catatan' => 'Terjadi gangguan trafo step up.',
            'items' => [
                [
                    'tanggal_kejadian' => '14/08/2026',
                    'fungsi' => 'Pemeliharaan',
                    'lokasi_kejadian' => 'Area Trafo Step Up Unit 2',
                    'luka_ringan' => 1,
                    'luka_berat' => 0,
                    'meninggal' => 0,
                    'kerugian_material' => 15000000,
                    'keterangan' => 'Penggantian kabel bushing',
                ],
            ],
        ];

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.kecelakaan-instalasi.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('k3_pengusahaan_kecelakaan_instalasis', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'is_nihil' => false,
        ]);

        $this->assertDatabaseHas('k3_pengusahaan_kecelakaan_instalasi_items', [
            'tanggal_kejadian' => '14/08/2026',
            'fungsi' => 'Pemeliharaan',
            'lokasi_kejadian' => 'Area Trafo Step Up Unit 2',
            'luka_ringan' => 1,
            'kerugian_material' => 15000000.00,
        ]);
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
                ->get(route('k3.pengusahaan.kecelakaan-instalasi.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.kecelakaan-instalasi.store'), [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                    'is_nihil' => true,
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
            ->get(route('k3.pengusahaan.kecelakaan-instalasi.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/pengusahaan/kecelakaan-instalasi/index')
                ->where('can_write', false)
            );

        $this->actingAs($manager)
            ->post(route('k3.pengusahaan.kecelakaan-instalasi.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'is_nihil' => true,
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_access_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create(['is_active' => true]);
        $foreignUnit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $ownUnit);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.kecelakaan-instalasi.index', [
                'unit_id' => $foreignUnit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.kecelakaan-instalasi.store'), [
                'unit_id' => $foreignUnit->id,
                'month' => 8,
                'year' => 2026,
                'is_nihil' => true,
            ])
            ->assertForbidden();
    }
}
