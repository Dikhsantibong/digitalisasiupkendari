<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanBukuTamu;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanBukuTamuTest extends TestCase
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

            // Default has 3 initial rows
            $this->actingAs($user)
                ->get(route('k3.pengusahaan.buku-tamu.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/buku-tamu/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record.items', 3)
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'no_dokumen' => 'SMT-FM-AK3-06.04',
                'revisi' => '01',
                'tanggal_dokumen' => '23 SEPTEMBER 2019',
                'catatan' => 'Laporan mutasi buku tamu periode Agustus 2026 berjalan tertib.',
                'items' => [
                    [
                        'no_urut' => 1,
                        'tanggal' => '1/8/2026',
                        'jumlah_kehadiran_tamu' => 1,
                        'tamu_pln' => 0,
                        'instansi' => 0,
                        'kontraktor' => 0,
                        'lainnya' => 1,
                        'keterangan' => 'SERVICE AC',
                        'sort_order' => 0,
                    ],
                    [
                        'no_urut' => 2,
                        'tanggal' => '3/8/2026',
                        'jumlah_kehadiran_tamu' => 2,
                        'tamu_pln' => 0,
                        'instansi' => 0,
                        'kontraktor' => 2,
                        'lainnya' => 0,
                        'keterangan' => "PT RGP\nPT. ANS",
                        'sort_order' => 1,
                    ],
                ],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.buku-tamu.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_buku_tamus', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'no_dokumen' => 'SMT-FM-AK3-06.04',
                'input_by' => $user->id,
            ]);

            $record = K3PengusahaanBukuTamu::where('unit_id', $unit->id)->first();
            $this->assertNotNull($record);
            $this->assertCount(2, $record->items);
            $this->assertDatabaseHas('k3_pengusahaan_buku_tamu_items', [
                'laporan_id' => $record->id,
                'tanggal' => '1/8/2026',
                'jumlah_kehadiran_tamu' => 1,
                'lainnya' => 1,
                'keterangan' => 'SERVICE AC',
            ]);
        }
    }

    public function test_saved_report_is_loaded_on_subsequent_request(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.buku-tamu.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'items' => [
                    [
                        'no_urut' => 1,
                        'tanggal' => '4/8/2026',
                        'jumlah_kehadiran_tamu' => 2,
                        'tamu_pln' => 0,
                        'instansi' => 0,
                        'kontraktor' => 2,
                        'lainnya' => 0,
                        'keterangan' => 'PT. SUCOFINDO / SURVEY JAKARTA',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.buku-tamu.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/buku-tamu/index')
                ->where('has_saved', true)
                ->where('record.items.0.keterangan', 'PT. SUCOFINDO / SURVEY JAKARTA')
                ->where('record.items.0.kontraktor', 2)
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
                ->get(route('k3.pengusahaan.buku-tamu.index', ['unit_id' => $unit->id]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.buku-tamu.store'), [
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
            ->get(route('k3.pengusahaan.buku-tamu.index', ['unit_id' => $unitB->id]))
            ->assertForbidden();

        $this->actingAs($userA)
            ->post(route('k3.pengusahaan.buku-tamu.store'), [
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
            ->post(route('k3.pengusahaan.buku-tamu.store'), [
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
            ->get(route('k3.pengusahaan.buku-tamu.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/buku-tamu/index')
                ->where('can_write', true)
                ->has('record.items', 3)
            );
    }
}
