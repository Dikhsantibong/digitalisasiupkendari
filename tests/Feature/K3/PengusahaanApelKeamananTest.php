<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanApelKeamanan;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanApelKeamananTest extends TestCase
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

            // Month 8 has 31 days => 31 * 3 = 93 default items
            $this->actingAs($user)
                ->get(route('k3.pengusahaan.apel-keamanan.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/apel-keamanan/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record.items', 93)
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'no_dokumen' => 'SMT-FM-AK3-13.13',
                'revisi' => '00',
                'tanggal_dokumen' => '23 SEPTEMBER 2019',
                'catatan' => 'Laporan apel security bulan Agustus lengkap dan aman.',
                'items' => [
                    [
                        'tanggal' => '2026-08-01',
                        'hari_ke' => 1,
                        'tim_regu' => 'A',
                        'shift' => 'Pagi',
                        'waktu_apel' => '08:00',
                        'jumlah_personil' => 2,
                        'kelengkapan_atribut' => 'Lengkap',
                        'paraf_komandan_regu' => '✓',
                        'keterangan' => 'Aman',
                        'sort_order' => 0,
                    ],
                    [
                        'tanggal' => '2026-08-01',
                        'hari_ke' => 1,
                        'tim_regu' => 'B',
                        'shift' => 'Sore',
                        'waktu_apel' => '16:00',
                        'jumlah_personil' => 2,
                        'kelengkapan_atribut' => 'Lengkap',
                        'paraf_komandan_regu' => '✓',
                        'keterangan' => 'Aman',
                        'sort_order' => 1,
                    ],
                    [
                        'tanggal' => '2026-08-01',
                        'hari_ke' => 1,
                        'tim_regu' => 'C',
                        'shift' => 'Malam',
                        'waktu_apel' => '22:00',
                        'jumlah_personil' => 2,
                        'kelengkapan_atribut' => 'Lengkap',
                        'paraf_komandan_regu' => '✓',
                        'keterangan' => 'Aman',
                        'sort_order' => 2,
                    ],
                ],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.apel-keamanan.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_apel_keamanans', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'no_dokumen' => 'SMT-FM-AK3-13.13',
                'input_by' => $user->id,
            ]);

            $record = K3PengusahaanApelKeamanan::where('unit_id', $unit->id)->first();
            $this->assertNotNull($record);
            $this->assertCount(3, $record->items);
            $this->assertDatabaseHas('k3_pengusahaan_apel_keamanan_items', [
                'laporan_id' => $record->id,
                'hari_ke' => 1,
                'tim_regu' => 'A',
                'shift' => 'Pagi',
                'jumlah_personil' => 2,
                'kelengkapan_atribut' => 'Lengkap',
            ]);
        }
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
                ->get(route('k3.pengusahaan.apel-keamanan.index', ['unit_id' => $unit->id]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.apel-keamanan.store'), [
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
            ->get(route('k3.pengusahaan.apel-keamanan.index', ['unit_id' => $unitB->id]))
            ->assertForbidden();

        $this->actingAs($userA)
            ->post(route('k3.pengusahaan.apel-keamanan.store'), [
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
            ->post(route('k3.pengusahaan.apel-keamanan.store'), [
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
            ->get(route('k3.pengusahaan.apel-keamanan.index', [
                'unit_id' => $unit->id,
                'month' => 2,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/apel-keamanan/index')
                ->where('can_write', true)
                ->has('record.items', 84) // Feb 2026 has 28 days => 28 * 3 = 84
            );
    }
}
