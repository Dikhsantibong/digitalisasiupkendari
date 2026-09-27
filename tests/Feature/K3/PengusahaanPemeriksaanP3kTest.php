<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanPemeriksaanP3k;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanPemeriksaanP3kTest extends TestCase
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
                ->get(route('k3.pengusahaan.pemeriksaan-p3k.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/pemeriksaan-p3k/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record.items', 20)
                    ->has('record.locations', 7)
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'no_dokumen' => 'SMT-FM-AK3-10.01',
                'revisi' => '01',
                'tanggal_dokumen' => '23 September 2019',
                'locations' => ['Ruang CCR', 'Ruang Lobby', 'Pos Security'],
                'catatan' => 'Pemeriksaan rutin kotak P3K bulan Agustus.',
                'items' => [
                    [
                        'no_urut' => 1,
                        'nama_isi' => 'Kasa steril',
                        'standar_jumlah' => 20,
                        'satuan' => 'bh',
                        'kondisi_lokasi' => [
                            'Ruang CCR' => 20,
                            'Ruang Lobby' => 0,
                            'Pos Security' => 20,
                        ],
                        'keterangan' => 'Lengkap kecuali Lobby',
                        'sort_order' => 0,
                    ],
                    [
                        'no_urut' => 2,
                        'nama_isi' => 'Perban Lebar 5 cm',
                        'standar_jumlah' => 2,
                        'satuan' => 'rol',
                        'kondisi_lokasi' => [
                            'Ruang CCR' => 2,
                            'Ruang Lobby' => 2,
                            'Pos Security' => 2,
                        ],
                        'keterangan' => 'Lengkap',
                        'sort_order' => 1,
                    ],
                ],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.pemeriksaan-p3k.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_pemeriksaan_p3ks', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'no_dokumen' => 'SMT-FM-AK3-10.01',
                'input_by' => $user->id,
            ]);

            $record = K3PengusahaanPemeriksaanP3k::where('unit_id', $unit->id)->first();
            $this->assertNotNull($record);
            $this->assertCount(2, $record->items);
            $this->assertEquals(['Ruang CCR', 'Ruang Lobby', 'Pos Security'], $record->locations);
            $this->assertDatabaseHas('k3_pengusahaan_pemeriksaan_p3k_items', [
                'laporan_id' => $record->id,
                'nama_isi' => 'Kasa steril',
                'standar_jumlah' => 20,
            ]);
        }
    }

    public function test_subsequent_month_prefills_from_previous_month(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Save for month 7 with custom locations
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.pemeriksaan-p3k.store'), [
                'unit_id' => $unit->id,
                'month' => 7,
                'year' => 2026,
                'locations' => ['Gedung A', 'Gedung B'],
                'items' => [
                    [
                        'no_urut' => 1,
                        'nama_isi' => 'Kasa Steril Khusus',
                        'standar_jumlah' => 15,
                        'satuan' => 'bh',
                        'kondisi_lokasi' => ['Gedung A' => 15, 'Gedung B' => 10],
                        'keterangan' => 'Perlu isi ulang Gedung B',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        // Access month 8 (should prefill with custom item and locations from month 7)
        $this->actingAs($user)
            ->get(route('k3.pengusahaan.pemeriksaan-p3k.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/pemeriksaan-p3k/index')
                ->where('has_saved', false)
                ->where('record.locations', ['Gedung A', 'Gedung B'])
                ->where('record.items.0.nama_isi', 'Kasa Steril Khusus')
                ->where('record.items.0.standar_jumlah', 15)
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
                ->get(route('k3.pengusahaan.pemeriksaan-p3k.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.pemeriksaan-p3k.store'), [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                    'locations' => ['CCR'],
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
            ->get(route('k3.pengusahaan.pemeriksaan-p3k.index', [
                'unit_id' => $unitB->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.pemeriksaan-p3k.store'), [
                'unit_id' => $unitB->id,
                'month' => 8,
                'year' => 2026,
                'locations' => ['CCR'],
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
            ->post(route('k3.pengusahaan.pemeriksaan-p3k.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'locations' => ['Pos Security'],
                'items' => [
                    [
                        'no_urut' => 1,
                        'nama_isi' => 'Kapas 25 gr',
                        'standar_jumlah' => 1,
                        'satuan' => 'buah',
                        'kondisi_lokasi' => ['Pos Security' => 1],
                        'keterangan' => 'Lengkap',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('k3_pengusahaan_pemeriksaan_p3k_items', 1);

        // Update to 2 items
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.pemeriksaan-p3k.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'locations' => ['Pos Security'],
                'items' => [
                    [
                        'no_urut' => 1,
                        'nama_isi' => 'Kapas 25 gr',
                        'standar_jumlah' => 1,
                        'satuan' => 'buah',
                        'kondisi_lokasi' => ['Pos Security' => 1],
                        'keterangan' => 'Lengkap',
                    ],
                    [
                        'no_urut' => 2,
                        'nama_isi' => 'Gunting',
                        'standar_jumlah' => 1,
                        'satuan' => 'buah',
                        'kondisi_lokasi' => ['Pos Security' => 1],
                        'keterangan' => 'Lengkap',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('k3_pengusahaan_pemeriksaan_p3k_items', 2);
        $this->assertDatabaseHas('k3_pengusahaan_pemeriksaan_p3k_items', [
            'nama_isi' => 'Gunting',
            'standar_jumlah' => 1,
        ]);
    }
}
