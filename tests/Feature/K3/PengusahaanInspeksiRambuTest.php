<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanInspeksiRambu;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanInspeksiRambuTest extends TestCase
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

            // Default has 34 items from SMT-FM-AK3-07.01
            $this->actingAs($user)
                ->get(route('k3.pengusahaan.inspeksi-rambu.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/inspeksi-rambu/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record.items', 34)
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'no_dokumen' => 'SMT-FM-AK3-07.01',
                'revisi' => '01',
                'tanggal_dokumen' => '23 September 2019',
                'tanggal_inspeksi' => '01/09/2026',
                'catatan' => 'Inspeksi rambu K3 bulan Agustus seluruhnya dalam kondisi baik dan nihil pelanggaran.',
                'items' => [
                    [
                        'no_id' => 1,
                        'rambu_k3' => 'Gunakan Helm',
                        'lokasi' => 'Ruang Pembangkit',
                        'tingkat_pelanggaran' => 'Nihil',
                        'keterangan' => 'Perhatian',
                        'sort_order' => 0,
                    ],
                    [
                        'no_id' => 2,
                        'rambu_k3' => 'Gunakan Pelindung Telinga',
                        'lokasi' => 'Ruang Pembangkit',
                        'tingkat_pelanggaran' => 'Nihil',
                        'keterangan' => 'Perhatian',
                        'sort_order' => 1,
                    ],
                ],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.inspeksi-rambu.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_inspeksi_rambus', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'no_dokumen' => 'SMT-FM-AK3-07.01',
                'input_by' => $user->id,
            ]);

            $record = K3PengusahaanInspeksiRambu::where('unit_id', $unit->id)->first();
            $this->assertNotNull($record);
            $this->assertCount(2, $record->items);
            $this->assertDatabaseHas('k3_pengusahaan_inspeksi_rambu_items', [
                'laporan_id' => $record->id,
                'rambu_k3' => 'Gunakan Helm',
                'lokasi' => 'Ruang Pembangkit',
                'tingkat_pelanggaran' => 'Nihil',
                'keterangan' => 'Perhatian',
            ]);
        }
    }

    public function test_subsequent_month_prefills_from_previous_month(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Save for month 7 with a custom signage
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.inspeksi-rambu.store'), [
                'unit_id' => $unit->id,
                'month' => 7,
                'year' => 2026,
                'items' => [
                    [
                        'no_id' => 99,
                        'rambu_k3' => 'Awas Radiasi Laser',
                        'lokasi' => 'Laboratorium Pengujian',
                        'tingkat_pelanggaran' => 'Nihil',
                        'keterangan' => 'Bahaya',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        // Access month 8 (should prefill from month 7)
        $this->actingAs($user)
            ->get(route('k3.pengusahaan.inspeksi-rambu.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/inspeksi-rambu/index')
                ->where('has_saved', false)
                ->where('record.items.0.rambu_k3', 'Awas Radiasi Laser')
                ->where('record.items.0.lokasi', 'Laboratorium Pengujian')
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
                ->get(route('k3.pengusahaan.inspeksi-rambu.index', ['unit_id' => $unit->id]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.inspeksi-rambu.store'), [
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
            ->get(route('k3.pengusahaan.inspeksi-rambu.index', ['unit_id' => $unitB->id]))
            ->assertForbidden();

        $this->actingAs($userA)
            ->post(route('k3.pengusahaan.inspeksi-rambu.store'), [
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
            ->post(route('k3.pengusahaan.inspeksi-rambu.store'), [
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
            ->get(route('k3.pengusahaan.inspeksi-rambu.index', [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/inspeksi-rambu/index')
                ->where('can_write', true)
                ->has('record.items', 34)
            );
    }
}
