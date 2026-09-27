<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanJamKerja;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanJamKerjaTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_and_staf_k3_can_view_and_save_jam_kerja_pengusahaan(): void
    {
        foreach ([RoleName::TeamLeaderK3, RoleName::StafK3] as $role) {
            $unit = Unit::factory()->create(['is_active' => true]);
            $user = $this->userWithRole($role, $unit);

            $this->actingAs($user)
                ->get(route('k3.pengusahaan.jam-kerja.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/jam-kerja/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record')
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'no_dokumen' => 'FMZ-08.4.4.10',
                'tgl_berlaku' => '15 Okt 2014',
                'revisi' => '0.0',
                'halaman' => '1 dari 1',
                'karyawan_tetap' => 7,
                'karyawan_tetap_shift' => 0,
                'karyawan_tidak_tetap' => 29,
                'karyawan_tidak_tetap_shift' => 12,
                'hari_tetap' => 22,
                'jam_tetap' => 8,
                'lembur_tetap' => 0,
                'hari_tetap_shift' => 0,
                'jam_tetap_shift' => 8,
                'lembur_tetap_shift' => 0,
                'hari_tidak_tetap' => 22,
                'jam_tidak_tetap' => 8,
                'lembur_tidak_tetap' => 0,
                'hari_tidak_tetap_shift' => 31,
                'jam_tidak_tetap_shift' => 8,
                'lembur_tidak_tetap_shift' => 732,
                'cuti_orang' => 0,
                'cuti_hari' => 0,
                'cuti_jam' => 0,
                'ijin_orang' => 1,
                'ijin_hari' => 1,
                'ijin_jam' => 8,
                'sakit_orang' => 2,
                'sakit_hari' => 2,
                'sakit_jam' => 16,
                'catatan' => 'Laporan bulan Agustus 2026',
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.jam-kerja.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_jam_kerjas', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'karyawan_tetap' => 7,
                'karyawan_tidak_tetap' => 29,
                'karyawan_tidak_tetap_shift' => 12,
                'total_jam_kerja_orang' => 9312.00,
                'total_lembur' => 732.00,
                'total_absensi_jam' => 24.00,
                'total_jam_kerja_seluruh' => 10020.00,
                'input_by' => $user->id,
            ]);

            $saved = K3PengusahaanJamKerja::query()
                ->where('unit_id', $unit->id)
                ->where('year', 2026)
                ->where('month', 8)
                ->firstOrFail();

            $this->assertEquals(9312.00, $saved->total_jam_kerja_orang);
            $this->assertEquals(732.00, $saved->total_lembur);
            $this->assertEquals(24.00, $saved->total_absensi_jam);
            $this->assertEquals(10020.00, $saved->total_jam_kerja_seluruh);
        }
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
                ->get(route('k3.pengusahaan.jam-kerja.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.jam-kerja.store'), [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ])
                ->assertForbidden();
        }
    }

    public function test_manager_ul_can_view_but_cannot_save(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('k3.pengusahaan.jam-kerja.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/jam-kerja/index')
                ->where('can_write', false)
            );

        $this->actingAs($manager)
            ->post(route('k3.pengusahaan.jam-kerja.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_access_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create(['is_active' => true]);
        $foreignUnit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $ownUnit);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.jam-kerja.index', [
                'unit_id' => $foreignUnit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.jam-kerja.store'), [
                'unit_id' => $foreignUnit->id,
                'month' => 8,
                'year' => 2026,
            ])
            ->assertForbidden();
    }
}
