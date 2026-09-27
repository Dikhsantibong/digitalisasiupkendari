<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanJamKerja;
use App\Models\K3PengusahaanJamKerjaBulanan;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanJamKerjaBulananTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_and_staf_k3_can_view_and_save_jam_kerja_bulanan_pengusahaan(): void
    {
        foreach ([RoleName::TeamLeaderK3, RoleName::StafK3] as $role) {
            $unit = Unit::factory()->create(['is_active' => true]);
            $user = $this->userWithRole($role, $unit);

            $this->actingAs($user)
                ->get(route('k3.pengusahaan.jam-kerja-bulanan.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/jam-kerja-bulanan/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record')
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'no_dokumen' => 'FMZ-08.4.4.11',
                'tgl_berlaku' => '15 Okt 2014',
                'revisi' => '0.0',
                'halaman' => '1 dari 1',
                'jam_kerja_komulatif_bulan_lalu' => 10020,
                'karyawan_tetap' => 7,
                'karyawan_tetap_shift' => 0,
                'karyawan_tidak_tetap' => 29,
                'karyawan_tidak_tetap_shift' => 12,
                'hari_kerja' => 22,
                'jam_kerja_standart_karyawan' => 9312,
                'jam_kerja_lembur_karyawan' => 732,
                'jam_absensi_karyawan' => 24,
                'catatan' => 'Laporan bulanan Agustus 2026 selesai.',
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.jam-kerja-bulanan.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_jam_kerja_bulanans', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'jam_kerja_komulatif_bulan_lalu' => 10020.00,
                'karyawan_tetap' => 7,
                'karyawan_tetap_shift' => 0,
                'karyawan_tidak_tetap' => 29,
                'karyawan_tidak_tetap_shift' => 12,
                'jumlah_karyawan' => 48,
                'hari_kerja' => 22,
                'jam_kerja_standart' => 176.00,
                'jam_kerja_standart_karyawan' => 9312.00,
                'jam_kerja_lembur_karyawan' => 732.00,
                'jam_kerja_seluruh_karyawan' => 10044.00,
                'jam_absensi_karyawan' => 24.00,
                'jam_kerja_realisasi_karyawan' => 10020.00,
                'jam_kerja_komulatif_bulan_ini' => 20040.00,
                'input_by' => $user->id,
            ]);

            $saved = K3PengusahaanJamKerjaBulanan::query()
                ->where('unit_id', $unit->id)
                ->where('year', 2026)
                ->where('month', 8)
                ->firstOrFail();

            $this->assertEquals(48, $saved->jumlah_karyawan);
            $this->assertEquals(176.00, $saved->jam_kerja_standart);
            $this->assertEquals(10044.00, $saved->jam_kerja_seluruh_karyawan);
            $this->assertEquals(10020.00, $saved->jam_kerja_realisasi_karyawan);
            $this->assertEquals(20040.00, $saved->jam_kerja_komulatif_bulan_ini);
        }
    }

    public function test_defaults_pulled_from_k3_pengusahaan_jam_kerja_if_exists(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        K3PengusahaanJamKerja::factory()->create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'karyawan_tetap' => 8,
            'karyawan_tetap_shift' => 2,
            'karyawan_tidak_tetap' => 30,
            'karyawan_tidak_tetap_shift' => 10,
            'hari_tetap' => 21,
            'total_jam_kerja_orang' => 8500.0,
            'total_lembur' => 500.0,
            'total_absensi_jam' => 30.0,
        ]);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.jam-kerja-bulanan.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/jam-kerja-bulanan/index')
                ->where('has_detail_record', true)
                ->where('record.karyawan_tetap', 8)
                ->where('record.karyawan_tetap_shift', 2)
                ->where('record.karyawan_tidak_tetap', 30)
                ->where('record.karyawan_tidak_tetap_shift', 10)
                ->where('record.hari_kerja', 21)
                ->where('record.jam_kerja_standart_karyawan', 8500)
                ->where('record.jam_kerja_lembur_karyawan', 500)
                ->where('record.jam_absensi_karyawan', 30)
            );
    }

    public function test_komulatif_bulan_lalu_pulled_from_previous_month(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Previous month (July 2026) has 15000 cumulative hours
        K3PengusahaanJamKerjaBulanan::factory()->create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 7,
            'jam_kerja_komulatif_bulan_ini' => 15000.0,
        ]);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.jam-kerja-bulanan.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/jam-kerja-bulanan/index')
                ->where('komulatif_lalu_suggested', 15000)
                ->where('record.jam_kerja_komulatif_bulan_lalu', 15000)
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
                ->get(route('k3.pengusahaan.jam-kerja-bulanan.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.jam-kerja-bulanan.store'), [
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
            ->get(route('k3.pengusahaan.jam-kerja-bulanan.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/jam-kerja-bulanan/index')
                ->where('can_write', false)
            );

        $this->actingAs($manager)
            ->post(route('k3.pengusahaan.jam-kerja-bulanan.store'), [
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
            ->get(route('k3.pengusahaan.jam-kerja-bulanan.index', [
                'unit_id' => $foreignUnit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.jam-kerja-bulanan.store'), [
                'unit_id' => $foreignUnit->id,
                'month' => 8,
                'year' => 2026,
            ])
            ->assertForbidden();
    }
}
