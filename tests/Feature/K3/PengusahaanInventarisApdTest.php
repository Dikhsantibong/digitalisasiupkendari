<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanInventarisApd;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanInventarisApdTest extends TestCase
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
                ->get(route('k3.pengusahaan.inventaris-apd.index', [
                    'unit_id' => $unit->id,
                    'month' => 9,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/inventaris-apd/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record.items', 37)
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
                'no_dokumen' => 'SMT-FM-AK3-01.01',
                'revisi' => '01',
                'tanggal_dokumen' => '23 September 2019',
                'catatan' => 'Pemeriksaan rutin inventaris APD bulan September.',
                'items' => [
                    [
                        'kategori' => 'I',
                        'no_grup' => 1,
                        'nama_grup' => 'Sarung Tangan :',
                        'nama_alat' => 'Sarung tangan kain bintik',
                        'jumlah' => 4,
                        'lokasi' => 'Lemari K3',
                        'keterangan' => null,
                        'sort_order' => 0,
                    ],
                    [
                        'kategori' => 'I',
                        'no_grup' => 3,
                        'nama_grup' => null,
                        'nama_alat' => 'Helm',
                        'jumlah' => 71,
                        'lokasi' => 'ULPLTD Poasia',
                        'keterangan' => 'Pegawai dan mitra kerja',
                        'sort_order' => 1,
                    ],
                    [
                        'kategori' => 'II',
                        'no_grup' => 2,
                        'nama_grup' => 'Lampu Penerangan :',
                        'nama_alat' => 'Senter',
                        'jumlah' => 10,
                        'lokasi' => 'ULPLTD Poasia',
                        'keterangan' => 'K2LH, Pos Security, Lemari K3 & Operator',
                        'sort_order' => 2,
                    ],
                ],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.inventaris-apd.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_inventaris_apds', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 9,
                'no_dokumen' => 'SMT-FM-AK3-01.01',
                'input_by' => $user->id,
            ]);

            $record = K3PengusahaanInventarisApd::where('unit_id', $unit->id)->first();
            $this->assertNotNull($record);
            $this->assertCount(3, $record->items);
            $this->assertDatabaseHas('k3_pengusahaan_inventaris_apd_items', [
                'laporan_id' => $record->id,
                'nama_alat' => 'Helm',
                'jumlah' => 71,
                'lokasi' => 'ULPLTD Poasia',
            ]);
        }
    }

    public function test_subsequent_month_prefills_from_previous_month(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Save for month 8
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.inventaris-apd.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'items' => [
                    [
                        'kategori' => 'I',
                        'no_grup' => 9,
                        'nama_grup' => null,
                        'nama_alat' => 'Full Body Harness Petzl',
                        'jumlah' => 5,
                        'lokasi' => 'Harlist',
                        'keterangan' => 'Kondisi Baik',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        // Access month 9 (should prefill with custom item from month 8)
        $this->actingAs($user)
            ->get(route('k3.pengusahaan.inventaris-apd.index', [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/inventaris-apd/index')
                ->where('has_saved', false)
                ->where('record.items.0.nama_alat', 'Full Body Harness Petzl')
                ->where('record.items.0.jumlah', 5)
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
                ->get(route('k3.pengusahaan.inventaris-apd.index', [
                    'unit_id' => $unit->id,
                    'month' => 9,
                    'year' => 2026,
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.inventaris-apd.store'), [
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
            ->get(route('k3.pengusahaan.inventaris-apd.index', [
                'unit_id' => $unitB->id,
                'month' => 9,
                'year' => 2026,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.inventaris-apd.store'), [
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
            ->post(route('k3.pengusahaan.inventaris-apd.store'), [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
                'items' => [
                    [
                        'kategori' => 'I',
                        'no_grup' => 12,
                        'nama_grup' => null,
                        'nama_alat' => 'Baju Pelampung',
                        'jumlah' => 1,
                        'lokasi' => 'Lemari K3',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('k3_pengusahaan_inventaris_apd_items', 1);

        // Update to 2 items
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.inventaris-apd.store'), [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
                'items' => [
                    [
                        'kategori' => 'I',
                        'no_grup' => 12,
                        'nama_grup' => null,
                        'nama_alat' => 'Baju Pelampung',
                        'jumlah' => 2,
                        'lokasi' => 'Lemari K3',
                    ],
                    [
                        'kategori' => 'II',
                        'no_grup' => 4,
                        'nama_grup' => null,
                        'nama_alat' => 'TOA',
                        'jumlah' => 2,
                        'lokasi' => 'K2LH',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('k3_pengusahaan_inventaris_apd_items', 2);
        $this->assertDatabaseHas('k3_pengusahaan_inventaris_apd_items', [
            'nama_alat' => 'Baju Pelampung',
            'jumlah' => 2,
        ]);
        $this->assertDatabaseHas('k3_pengusahaan_inventaris_apd_items', [
            'nama_alat' => 'TOA',
            'jumlah' => 2,
        ]);
    }
}
