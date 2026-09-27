<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanKecelakaanMasyarakatTest extends TestCase
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
                ->get(route('k3.pengusahaan.kecelakaan-masyarakat.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/kecelakaan-masyarakat/index')
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
                'nomor_keputusan' => 'Nomor :0252.P/DIR/2016',
                'catatan' => 'Bulan Agustus nihil kecelakaan masyarakat umum.',
                'items' => [],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.kecelakaan-masyarakat.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_kecelakaan_masyarakats', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'is_nihil' => true,
                'nomor_keputusan' => 'Nomor :0252.P/DIR/2016',
                'input_by' => $user->id,
            ]);

            $this->assertDatabaseCount('k3_pengusahaan_kecelakaan_masyarakat_items', 0);
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
            'nomor_keputusan' => 'Nomor :0252.P/DIR/2016',
            'catatan' => 'Terjadi sengatan listrik tegangan menengah pada masyarakat.',
            'items' => [
                [
                    'tanggal_kejadian' => '10/08/2026',
                    'fungsi' => 'Distribusi',
                    'lokasi_kejadian' => 'Jl. Poros Bandara No. 45',
                    'luka_ringan' => 0,
                    'luka_berat' => 1,
                    'meninggal' => 0,
                    'kerugian_material' => 5000000,
                    'keterangan' => 'Tertimpa ranting pohon roboh dekat tiang',
                ],
            ],
        ];

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.kecelakaan-masyarakat.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('k3_pengusahaan_kecelakaan_masyarakats', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'is_nihil' => false,
        ]);

        $this->assertDatabaseHas('k3_pengusahaan_kecelakaan_masyarakat_items', [
            'tanggal_kejadian' => '10/08/2026',
            'fungsi' => 'Distribusi',
            'lokasi_kejadian' => 'Jl. Poros Bandara No. 45',
            'luka_berat' => 1,
            'kerugian_material' => 5000000.00,
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
                ->get(route('k3.pengusahaan.kecelakaan-masyarakat.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.kecelakaan-masyarakat.store'), [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                    'is_nihil' => true,
                ])
                ->assertForbidden();
        }
    }

    public function test_user_cannot_access_foreign_unit(): void
    {
        $unitA = Unit::factory()->create(['is_active' => true]);
        $unitB = Unit::factory()->create(['is_active' => true]);

        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unitA);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.kecelakaan-masyarakat.index', [
                'unit_id' => $unitB->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.kecelakaan-masyarakat.store'), [
                'unit_id' => $unitB->id,
                'month' => 8,
                'year' => 2026,
                'is_nihil' => true,
            ])
            ->assertForbidden();
    }

    public function test_updating_existing_report_replaces_items(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // First save with 1 incident
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.kecelakaan-masyarakat.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'is_nihil' => false,
                'items' => [
                    [
                        'tanggal_kejadian' => '05/08/2026',
                        'fungsi' => 'K3',
                        'lokasi_kejadian' => 'Pintu Gerbang',
                        'luka_ringan' => 1,
                        'luka_berat' => 0,
                        'meninggal' => 0,
                        'kerugian_material' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('k3_pengusahaan_kecelakaan_masyarakat_items', 1);

        // Update to NIHIL
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.kecelakaan-masyarakat.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'is_nihil' => true,
                'items' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('k3_pengusahaan_kecelakaan_masyarakats', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'is_nihil' => true,
        ]);

        $this->assertDatabaseCount('k3_pengusahaan_kecelakaan_masyarakat_items', 0);
    }
}
