<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanAlatTanggapDarurat;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanAlatTanggapDaruratTest extends TestCase
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

            $this->actingAs($user)
                ->get(route('k3.pengusahaan.alat-tanggap-darurat.index', [
                    'unit_id' => $unit->id,
                    'month' => 9,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/alat-tanggap-darurat/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record.items', 10)
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
                'no_dokumen' => 'SMT-FM-AK3-03.03',
                'revisi' => '01',
                'tanggal_dokumen' => '23 September 2019',
                'catatan' => 'Pemeriksaan rutin alat tanggap darurat bulan September.',
                'items' => [
                    [
                        'no_urut' => 1,
                        'jenis' => 'Alat Pemadam Api Ringan (APAR)',
                        'siap_pakai' => 26,
                        'kadaluarsa' => 10,
                        'kosong' => 0,
                        'tgl_diisi_kembali' => '20/09/2026',
                        'keterangan' => 'Baik',
                        'sort_order' => 0,
                    ],
                    [
                        'no_urut' => 2,
                        'jenis' => 'Alat Pemadam Api Berat (APAB)',
                        'siap_pakai' => 4,
                        'kadaluarsa' => 0,
                        'kosong' => 0,
                        'tgl_diisi_kembali' => null,
                        'keterangan' => 'Baik',
                        'sort_order' => 1,
                    ],
                ],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.alat-tanggap-darurat.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_alat_tanggap_darurats', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 9,
                'no_dokumen' => 'SMT-FM-AK3-03.03',
                'input_by' => $user->id,
            ]);

            $record = K3PengusahaanAlatTanggapDarurat::where('unit_id', $unit->id)->first();
            $this->assertNotNull($record);
            $this->assertCount(2, $record->items);
            $this->assertDatabaseHas('k3_pengusahaan_alat_tanggap_darurat_items', [
                'laporan_id' => $record->id,
                'jenis' => 'Alat Pemadam Api Ringan (APAR)',
                'siap_pakai' => 26,
                'kadaluarsa' => 10,
            ]);
        }
    }

    public function test_subsequent_month_prefills_from_previous_month(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Save for month 8
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.alat-tanggap-darurat.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'items' => [
                    [
                        'no_urut' => 1,
                        'jenis' => 'Custom APAR Halon',
                        'siap_pakai' => 15,
                        'kadaluarsa' => 2,
                        'kosong' => null,
                        'tgl_diisi_kembali' => null,
                        'keterangan' => 'Standby',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        // Access month 9 (should prefill with custom item from month 8)
        $this->actingAs($user)
            ->get(route('k3.pengusahaan.alat-tanggap-darurat.index', [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/alat-tanggap-darurat/index')
                ->where('has_saved', false)
                ->where('record.items.0.jenis', 'Custom APAR Halon')
                ->where('record.items.0.siap_pakai', 15)
            );
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
                ->get(route('k3.pengusahaan.alat-tanggap-darurat.index', [
                    'unit_id' => $unit->id,
                    'month' => 9,
                    'year' => 2026,
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.alat-tanggap-darurat.store'), [
                    'unit_id' => $unit->id,
                    'month' => 9,
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

        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unitA);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.alat-tanggap-darurat.index', [
                'unit_id' => $unitB->id,
                'month' => 9,
                'year' => 2026,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.alat-tanggap-darurat.store'), [
                'unit_id' => $unitB->id,
                'month' => 9,
                'year' => 2026,
                'items' => [],
            ])
            ->assertForbidden();
    }

    public function test_updating_existing_report_replaces_items(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // First save with 1 item
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.alat-tanggap-darurat.store'), [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
                'items' => [
                    [
                        'no_urut' => 1,
                        'jenis' => 'Kotak P3K',
                        'siap_pakai' => 4,
                        'kadaluarsa' => 0,
                        'kosong' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('k3_pengusahaan_alat_tanggap_darurat_items', 1);

        // Update to 2 items
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.alat-tanggap-darurat.store'), [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
                'items' => [
                    [
                        'no_urut' => 1,
                        'jenis' => 'Kotak P3K',
                        'siap_pakai' => 5,
                        'kadaluarsa' => 0,
                        'kosong' => 0,
                    ],
                    [
                        'no_urut' => 2,
                        'jenis' => 'APAR CO2 5kg',
                        'siap_pakai' => 8,
                        'kadaluarsa' => 0,
                        'kosong' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('k3_pengusahaan_alat_tanggap_darurat_items', 2);
        $this->assertDatabaseHas('k3_pengusahaan_alat_tanggap_darurat_items', [
            'jenis' => 'Kotak P3K',
            'siap_pakai' => 5,
        ]);
    }
}
