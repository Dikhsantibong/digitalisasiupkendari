<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanLaporanCctv;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanLaporanCctvTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_and_staf_k3_can_view_default_and_save_report(): void
    {
        foreach ([RoleName::TeamLeaderK3, RoleName::StafK3] as $role) {
            $unit = Unit::factory()->create(['is_active' => true]);
            $user = $this->userWithRole($role, $unit);

            // Default has 1 row containing the 8 CCTV points
            $this->actingAs($user)
                ->get(route('k3.pengusahaan.laporan-cctv.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('k3/pengusahaan/laporan-cctv/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record.items', 1)
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'no_dokumen' => 'SMT-FM-AK3-14.01',
                'revisi' => '00',
                'tanggal_dokumen' => '23 SEPTEMBER 2019',
                'catatan' => 'Semua 8 titik kamera pengawas CCTV beroperasi normal dan visual terpantau jelas.',
                'items' => [
                    [
                        'no_urut' => 1,
                        'tanggal' => '31/8/2026',
                        'lokasi_cctv' => "1. Ruang Pembangkit (Lokal)\n2. Depan Kantor Unit\n3. Depan Pos Satpam",
                        'waktu_pantau' => 'Setiap Saat',
                        'kondisi_pantau' => 'Aman',
                        'keterangan' => 'Nihil gangguan',
                        'sort_order' => 0,
                    ],
                ],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.laporan-cctv.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_laporan_cctvs', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'no_dokumen' => 'SMT-FM-AK3-14.01',
                'input_by' => $user->id,
            ]);

            $record = K3PengusahaanLaporanCctv::where('unit_id', $unit->id)->first();
            $this->assertNotNull($record);
            $this->assertCount(1, $record->items);
            $this->assertDatabaseHas('k3_pengusahaan_laporan_cctv_items', [
                'laporan_id' => $record->id,
                'waktu_pantau' => 'Setiap Saat',
                'kondisi_pantau' => 'Aman',
            ]);
        }
    }

    public function test_subsequent_month_prefills_from_previous_month(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Save for month 7 with a custom camera point
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.laporan-cctv.store'), [
                'unit_id' => $unit->id,
                'month' => 7,
                'year' => 2026,
                'items' => [
                    [
                        'no_urut' => 1,
                        'tanggal' => '31/7/2026',
                        'lokasi_cctv' => '9. Area Dermaga Jetty Baru',
                        'waktu_pantau' => '24 Jam',
                        'kondisi_pantau' => 'Aman',
                        'keterangan' => 'Kamera PTZ baru',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        // Access month 8 (should prefill from month 7)
        $this->actingAs($user)
            ->get(route('k3.pengusahaan.laporan-cctv.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/pengusahaan/laporan-cctv/index')
                ->where('has_saved', false)
                ->where('record.items.0.lokasi_cctv', '9. Area Dermaga Jetty Baru')
            );
    }

    public function test_unauthorized_roles_receive_403(): void
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
                ->get(route('k3.pengusahaan.laporan-cctv.index', ['unit_id' => $unit->id]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.laporan-cctv.store'), [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                    'items' => [],
                ])
                ->assertForbidden();
        }
    }

    public function test_user_cannot_access_foreign_unit(): void
    {
        $unitA = Unit::factory()->create(['is_active' => true]);
        $unitB = Unit::factory()->create(['is_active' => true]);

        $userA = $this->userWithRole(RoleName::TeamLeaderK3, $unitA);

        $this->actingAs($userA)
            ->get(route('k3.pengusahaan.laporan-cctv.index', ['unit_id' => $unitB->id]))
            ->assertForbidden();

        $this->actingAs($userA)
            ->post(route('k3.pengusahaan.laporan-cctv.store'), [
                'unit_id' => $unitB->id,
                'month' => 8,
                'year' => 2026,
                'items' => [],
            ])
            ->assertForbidden();
    }

    public function test_store_validation_errors(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.laporan-cctv.store'), [
                'unit_id' => $unit->id,
                'month' => 13,
                'year' => 2026,
                'items' => [],
            ])
            ->assertSessionHasErrors(['month', 'items']);
    }

    public function test_superadmin_can_access(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::SuperAdmin, $unit);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.laporan-cctv.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/pengusahaan/laporan-cctv/index')
                ->where('can_write', true)
                ->has('record.items', 1)
            );
    }
}
