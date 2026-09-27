<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanAparApab;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanAparApabTest extends TestCase
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
                ->get(route('k3.pengusahaan.apar-apab.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/apar-apab/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record.items', 33)
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'no_dokumen' => 'SMT-FM-AK3-12.03',
                'revisi' => '01',
                'tanggal_dokumen' => '23 SEPTEMBER 2019',
                'catatan' => 'Inspeksi bulanan tabung APAR di seluruh area unit.',
                'items' => [
                    [
                        'no_urut' => 1,
                        'no_rfid' => '051',
                        'lokasi' => 'CCR',
                        'tgl_periksa' => '12/8/2026',
                        'merk_apar' => 'Fire Venom',
                        'jenis_apar' => 'Gas Cair',
                        'berat_kg' => 6.00,
                        'kondisi_tabung' => 'baik',
                        'kondisi_nozzle_selang' => 'baik',
                        'indikator_tekanan' => 'ok',
                        'kondisi_pin_segel' => 'baik',
                        'keterangan' => 'Ex. 10/11/2027',
                        'sort_order' => 0,
                    ],
                    [
                        'no_urut' => 2,
                        'no_rfid' => '056',
                        'lokasi' => 'CCR',
                        'tgl_periksa' => '12/8/2026',
                        'merk_apar' => 'Alpinas',
                        'jenis_apar' => 'Powder',
                        'berat_kg' => 6.00,
                        'kondisi_tabung' => 'baik',
                        'kondisi_nozzle_selang' => 'baik',
                        'indikator_tekanan' => 'ok',
                        'kondisi_pin_segel' => 'baik',
                        'keterangan' => 'Ex. 04/08/2021',
                        'sort_order' => 1,
                    ],
                ],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.apar-apab.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_apar_apabs', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'no_dokumen' => 'SMT-FM-AK3-12.03',
                'input_by' => $user->id,
            ]);

            $record = K3PengusahaanAparApab::where('unit_id', $unit->id)->first();
            $this->assertNotNull($record);
            $this->assertCount(2, $record->items);
            $this->assertDatabaseHas('k3_pengusahaan_apar_apab_items', [
                'laporan_id' => $record->id,
                'no_rfid' => '051',
                'lokasi' => 'CCR',
                'merk_apar' => 'Fire Venom',
                'berat_kg' => 6.00,
            ]);
        }
    }

    public function test_subsequent_month_prefills_from_previous_month(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Save for month 8
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.apar-apab.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'items' => [
                    [
                        'no_urut' => 1,
                        'no_rfid' => '999',
                        'lokasi' => 'Turbine Area',
                        'tgl_periksa' => '10/08/2026',
                        'merk_apar' => 'Yamato',
                        'jenis_apar' => 'CO2',
                        'berat_kg' => 10,
                        'kondisi_tabung' => 'baik',
                        'kondisi_nozzle_selang' => 'baik',
                        'indikator_tekanan' => 'ok',
                        'kondisi_pin_segel' => 'baik',
                        'keterangan' => 'Ex. 01/01/2028',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        // Access month 9 (should prefill with custom item from month 8)
        $this->actingAs($user)
            ->get(route('k3.pengusahaan.apar-apab.index', [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/apar-apab/index')
                ->where('has_saved', false)
                ->where('record.items.0.lokasi', 'Turbine Area')
                ->where('record.items.0.no_rfid', '999')
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
                ->get(route('k3.pengusahaan.apar-apab.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.apar-apab.store'), [
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

        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unitA);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.apar-apab.index', [
                'unit_id' => $unitB->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.apar-apab.store'), [
                'unit_id' => $unitB->id,
                'month' => 8,
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
            ->post(route('k3.pengusahaan.apar-apab.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'items' => [
                    [
                        'no_urut' => 1,
                        'lokasi' => 'CCR',
                        'merk_apar' => 'Fire Venom',
                        'berat_kg' => 6,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('k3_pengusahaan_apar_apab_items', 1);

        // Update to 2 items
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.apar-apab.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'items' => [
                    [
                        'no_urut' => 1,
                        'lokasi' => 'CCR',
                        'merk_apar' => 'Fire Venom',
                        'berat_kg' => 6,
                    ],
                    [
                        'no_urut' => 2,
                        'lokasi' => 'Workshop',
                        'merk_apar' => 'Garra Fire',
                        'berat_kg' => 7,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('k3_pengusahaan_apar_apab_items', 2);
    }
}
