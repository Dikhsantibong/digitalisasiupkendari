<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanInspeksiTempatKerja;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanInspeksiTempatKerjaTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_and_staf_k3_can_view_default_and_save_checklist(): void
    {
        foreach ([RoleName::TeamLeaderK3, RoleName::StafK3] as $role) {
            $unit = Unit::factory()->create(['is_active' => true]);
            $user = $this->userWithRole($role, $unit);

            // Default loads all 68 checklist items
            $this->actingAs($user)
                ->get(route('k3.pengusahaan.inspeksi-tempat-kerja.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('k3/pengusahaan/inspeksi-tempat-kerja/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record.items', 68)
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'no_dokumen' => 'SMT-FM-AK3-12-01',
                'revisi' => '01',
                'tanggal_dokumen' => '23 September 2019',
                'tanggal_inspeksi' => '03 AGUSTUS 2026',
                'departemen' => 'PLN NUSANTARA POWER',
                'lokasi' => 'ULPLTD Poasia',
                'tim_inspektur' => 'ULPLTD Poasia',
                'ketua_tim' => 'Muh. Amin',
                'inspektur' => 'Azis',
                'catatan' => 'Inspeksi berkala tempat kerja dan fasilitas.',
                'items' => [
                    [
                        'category' => 'A. FURNITURE DAN PERALATAN KANTOR',
                        'no_urut' => 1,
                        'item' => 'Furnitur, peralatan dan alat elektronik telah diatur untuk memperoleh keamanan',
                        'status' => 'Y',
                        'comment' => 'Agar ditata sesuai dengan aturan 5R',
                        'sort_order' => 0,
                    ],
                    [
                        'category' => 'B. LORONG DAN LANTAI',
                        'no_urut' => 1,
                        'item' => 'Jarak lorong mencukupi untuk lalu lintas dua arah',
                        'status' => 'N',
                        'comment' => 'Masih perlu ditata sesuai aturan 5R dan SMK3',
                        'sort_order' => 1,
                    ],
                    [
                        'category' => 'N. SUMBER DAYA ALAM',
                        'no_urut' => 1,
                        'item' => 'Apakah ada pemakaian air yang tidak terkontrol',
                        'status' => 'NA',
                        'comment' => 'Tidak terdapat kebocoran',
                        'sort_order' => 2,
                    ],
                ],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.inspeksi-tempat-kerja.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_inspeksi_tempat_kerjas', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'no_dokumen' => 'SMT-FM-AK3-12-01',
                'tanggal_inspeksi' => '03 AGUSTUS 2026',
                'lokasi' => 'ULPLTD Poasia',
                'ketua_tim' => 'Muh. Amin',
                'inspektur' => 'Azis',
                'input_by' => $user->id,
            ]);

            $record = K3PengusahaanInspeksiTempatKerja::where('unit_id', $unit->id)->first();
            $this->assertNotNull($record);
            $this->assertCount(3, $record->items);
            $this->assertDatabaseHas('k3_pengusahaan_inspeksi_tempat_kerja_items', [
                'laporan_id' => $record->id,
                'category' => 'A. FURNITURE DAN PERALATAN KANTOR',
                'status' => 'Y',
                'comment' => 'Agar ditata sesuai dengan aturan 5R',
            ]);
            $this->assertDatabaseHas('k3_pengusahaan_inspeksi_tempat_kerja_items', [
                'laporan_id' => $record->id,
                'category' => 'B. LORONG DAN LANTAI',
                'status' => 'N',
            ]);
            $this->assertDatabaseHas('k3_pengusahaan_inspeksi_tempat_kerja_items', [
                'laporan_id' => $record->id,
                'category' => 'N. SUMBER DAYA ALAM',
                'status' => 'NA',
            ]);
        }
    }

    public function test_saved_report_is_loaded_on_subsequent_request(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.inspeksi-tempat-kerja.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'tanggal_inspeksi' => '03 AGUSTUS 2026',
                'lokasi' => 'ULPLTD Poasia',
                'tim_inspektur' => 'ULPLTD Poasia',
                'ketua_tim' => 'Muh. Amin',
                'inspektur' => 'Azis',
                'items' => [
                    [
                        'category' => 'A. FURNITURE DAN PERALATAN KANTOR',
                        'no_urut' => 1,
                        'item' => 'Furnitur diatur dengan aman',
                        'status' => 'Y',
                        'comment' => 'Sesuai aturan 5R',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.inspeksi-tempat-kerja.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/pengusahaan/inspeksi-tempat-kerja/index')
                ->where('has_saved', true)
                ->where('record.items.0.status', 'Y')
                ->where('record.items.0.comment', 'Sesuai aturan 5R')
                ->where('record.lokasi', 'ULPLTD Poasia')
                ->where('record.ketua_tim', 'Muh. Amin')
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
                ->get(route('k3.pengusahaan.inspeksi-tempat-kerja.index', ['unit_id' => $unit->id]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.inspeksi-tempat-kerja.store'), [
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
            ->get(route('k3.pengusahaan.inspeksi-tempat-kerja.index', ['unit_id' => $unitB->id]))
            ->assertForbidden();

        $this->actingAs($userA)
            ->post(route('k3.pengusahaan.inspeksi-tempat-kerja.store'), [
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
            ->post(route('k3.pengusahaan.inspeksi-tempat-kerja.store'), [
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
            ->get(route('k3.pengusahaan.inspeksi-tempat-kerja.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/pengusahaan/inspeksi-tempat-kerja/index')
                ->where('can_write', true)
                ->has('record.items', 68)
            );
    }
}
