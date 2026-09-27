<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanPatrolSecurity;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanPatrolSecurityTest extends TestCase
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
            $unit = Unit::factory()->create(['name' => 'ULPLTD Poasia', 'is_active' => true]);
            $user = $this->userWithRole($role, $unit);

            // Default has 14 checkpoints (e.g. POA1..POA14)
            $this->actingAs($user)
                ->get(route('k3.pengusahaan.patrol-security.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('k3/pengusahaan/patrol-security/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record.items', 14)
                    ->where('days_in_month', 31)
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'judul' => 'PATROL CHECK SECURITY',
                'catatan' => 'Patroli keamanan area Poasia berjalan kondusif.',
                'items' => [
                    [
                        'lokasi_kode' => 'POA1',
                        'lokasi_nama' => 'POA1',
                        'scans' => ['1' => 1, '2' => 3, '3' => 2, '23' => 0, '30' => 0],
                        'total' => 6,
                        'persentase' => 60.0,
                        'sort_order' => 0,
                    ],
                    [
                        'lokasi_kode' => 'POA2',
                        'lokasi_nama' => 'POA2',
                        'scans' => ['1' => 0, '2' => 0, '18' => 1],
                        'total' => 1,
                        'persentase' => 10.0,
                        'sort_order' => 1,
                    ],
                ],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.patrol-security.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_patrol_securities', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'judul' => 'PATROL CHECK SECURITY',
                'input_by' => $user->id,
            ]);

            $record = K3PengusahaanPatrolSecurity::where('unit_id', $unit->id)->first();
            $this->assertNotNull($record);
            $this->assertCount(2, $record->items);
            $this->assertDatabaseHas('k3_pengusahaan_patrol_security_items', [
                'laporan_id' => $record->id,
                'lokasi_kode' => 'POA1',
                'total' => 6,
            ]);
        }
    }

    public function test_subsequent_month_prefills_from_previous_month(): void
    {
        $unit = Unit::factory()->create(['name' => 'ULPLTD Poasia', 'is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Save for month 7 with custom checkpoint
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.patrol-security.store'), [
                'unit_id' => $unit->id,
                'month' => 7,
                'year' => 2026,
                'items' => [
                    [
                        'lokasi_kode' => 'POA99',
                        'lokasi_nama' => 'Gerbang Barat Khusus',
                        'scans' => ['1' => 2],
                        'total' => 2,
                        'persentase' => 100.0,
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        // Access month 8 (should prefill checkpoints from month 7 with 0 scans)
        $this->actingAs($user)
            ->get(route('k3.pengusahaan.patrol-security.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/pengusahaan/patrol-security/index')
                ->where('has_saved', false)
                ->where('record.items.0.lokasi_kode', 'POA99')
                ->where('record.items.0.total', 0)
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
                ->get(route('k3.pengusahaan.patrol-security.index', ['unit_id' => $unit->id]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.patrol-security.store'), [
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
            ->get(route('k3.pengusahaan.patrol-security.index', ['unit_id' => $unitB->id]))
            ->assertForbidden();

        $this->actingAs($userA)
            ->post(route('k3.pengusahaan.patrol-security.store'), [
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
            ->post(route('k3.pengusahaan.patrol-security.store'), [
                'unit_id' => $unit->id,
                'month' => 13,
                'year' => 2026,
                'items' => [],
            ])
            ->assertSessionHasErrors(['month', 'items']);
    }

    public function test_superadmin_can_access(): void
    {
        $unit = Unit::factory()->create(['name' => 'ULPLTD Poasia', 'is_active' => true]);
        $user = $this->userWithRole(RoleName::SuperAdmin, $unit);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.patrol-security.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/pengusahaan/patrol-security/index')
                ->where('can_write', true)
                ->has('record.items', 14)
            );
    }
}
