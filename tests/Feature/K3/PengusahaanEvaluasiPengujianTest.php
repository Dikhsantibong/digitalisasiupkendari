<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanEvaluasiPengujian;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanEvaluasiPengujianTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_and_staf_k3_can_view_and_save_evaluasi_pengujian_pengusahaan(): void
    {
        foreach ([RoleName::TeamLeaderK3, RoleName::StafK3] as $role) {
            $unit = Unit::factory()->create(['is_active' => true]);
            $user = $this->userWithRole($role, $unit);

            $this->actingAs($user)
                ->get(route('k3.pengusahaan.evaluasi-pengujian.index', [
                    'unit_id' => $unit->id,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/evaluasi-pengujian/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('rows', 9)
                    ->has('meta')
                );

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.evaluasi-pengujian.store'), [
                    'unit_id' => $unit->id,
                    'year' => 2026,
                    'rows' => [
                        [
                            'no_urut' => '1',
                            'nama_kategori_alat' => 'Crane',
                            'jenis' => 'Overhead Traveling Crane',
                            'kapasitas' => '5 ton',
                            'temuan_sertifikat' => 'Kabel sling aus 10%',
                            'progres_bulan' => [1, 2, 3, 4, 5],
                            'keterangan' => 'Mei 2026 Close Temuan',
                            'sort_order' => 0,
                        ],
                    ],
                    'meta' => [
                        'nomor_dokumen' => 'FMG-08-2.3.60',
                        'tanggal_terbit' => '21 Mei 2018',
                        'revisi' => '01',
                        'halaman' => '1 dari 1',
                        'catatan' => 'Sling baru sudah dipasang dan diverifikasi.',
                    ],
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_evaluasi_pengujians', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'nama_kategori_alat' => 'Crane',
                'jenis' => 'Overhead Traveling Crane',
                'kapasitas' => '5 ton',
                'temuan_sertifikat' => 'Kabel sling aus 10%',
                'keterangan' => 'Mei 2026 Close Temuan',
                'input_by' => $user->id,
            ]);

            $saved = K3PengusahaanEvaluasiPengujian::query()
                ->where('unit_id', $unit->id)
                ->where('year', 2026)
                ->firstOrFail();

            $this->assertSame([1, 2, 3, 4, 5], $saved->progres_bulan);

            $this->assertDatabaseHas('k3_pengusahaan_evaluasi_pengujian_meta', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'nomor_dokumen' => 'FMG-08-2.3.60',
                'revisi' => '01',
                'catatan' => 'Sling baru sudah dipasang dan diverifikasi.',
            ]);
        }
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
                ->get(route('k3.pengusahaan.evaluasi-pengujian.index', [
                    'unit_id' => $unit->id,
                    'year' => 2026,
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.evaluasi-pengujian.store'), [
                    'unit_id' => $unit->id,
                    'year' => 2026,
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
            ->get(route('k3.pengusahaan.evaluasi-pengujian.index', [
                'unit_id' => $unit->id,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/evaluasi-pengujian/index')
                ->where('can_write', false)
            );

        $this->actingAs($manager)
            ->post(route('k3.pengusahaan.evaluasi-pengujian.store'), [
                'unit_id' => $unit->id,
                'year' => 2026,
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
            ->get(route('k3.pengusahaan.evaluasi-pengujian.index', [
                'unit_id' => $foreignUnit->id,
                'year' => 2026,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.evaluasi-pengujian.store'), [
                'unit_id' => $foreignUnit->id,
                'year' => 2026,
                'rows' => [],
            ])
            ->assertForbidden();
    }
}
