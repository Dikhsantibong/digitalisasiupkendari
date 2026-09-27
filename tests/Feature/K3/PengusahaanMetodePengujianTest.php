<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3MetodePengujianPeralatan;
use App\Models\K3PengusahaanMetodePengujian;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanMetodePengujianTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_and_staf_k3_can_view_and_save_metode_pengujian_pengusahaan(): void
    {
        foreach ([RoleName::TeamLeaderK3, RoleName::StafK3] as $role) {
            $unit = Unit::factory()->create(['is_active' => true]);
            $user = $this->userWithRole($role, $unit);

            $this->actingAs($user)
                ->get(route('k3.pengusahaan.metode-pengujian.index', [
                    'unit_id' => $unit->id,
                    'month' => 9,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/k3/metode-pengujian/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('rows', 9)
                    ->has('meta')
                );

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.metode-pengujian.store'), [
                    'unit_id' => $unit->id,
                    'month' => 9,
                    'year' => 2026,
                    'rows' => [
                        [
                            'no_urut' => '1',
                            'nama_peralatan' => 'Crane Sentral',
                            'no_pengesahan' => '500/CR/2026',
                            'nama_kategori_alat' => 'Overhead Travelling Crane',
                            'uji_visual' => 'Memenuhi',
                            'uji_fungsi' => 'Memenuhi',
                            'uji_beban' => 'Memenuhi',
                            'uji_hydro' => '-',
                            'ndt' => 'Memenuhi',
                            'uji_ultrasonic_thickness' => '-',
                            'uji_ketahanan' => '-',
                            'sertifikasi_terakhir' => '2024-05-01',
                            'sertifikasi_ulang' => '2027-05-01',
                            'keterangan' => 'Kondisi Prima',
                            'sort_order' => 0,
                        ],
                    ],
                    'meta' => [
                        'nomor_dokumen' => 'SMT-FM-AK3-05',
                        'tanggal_terbit' => '01/09/2026',
                        'revisi' => '00',
                        'halaman' => '1 dari 1',
                        'catatan' => 'Pemeriksaan semester 2 selesai dilaksanakan.',
                    ],
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_metode_pengujians', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 9,
                'nama_peralatan' => 'Crane Sentral',
                'no_pengesahan' => '500/CR/2026',
                'nama_kategori_alat' => 'Overhead Travelling Crane',
                'uji_visual' => 'Memenuhi',
                'keterangan' => 'Kondisi Prima',
                'input_by' => $user->id,
            ]);

            $this->assertDatabaseHas('k3_pengusahaan_metode_pengujian_meta', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 9,
                'nomor_dokumen' => 'SMT-FM-AK3-05',
                'catatan' => 'Pemeriksaan semester 2 selesai dilaksanakan.',
            ]);
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
                ->get(route('k3.pengusahaan.metode-pengujian.index', [
                    'unit_id' => $unit->id,
                    'month' => 9,
                    'year' => 2026,
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.metode-pengujian.store'), [
                    'unit_id' => $unit->id,
                    'month' => 9,
                    'year' => 2026,
                    'rows' => [],
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
            ->get(route('k3.pengusahaan.metode-pengujian.index', [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pengusahaan/k3/metode-pengujian/index')
                ->where('can_write', false)
            );

        $this->actingAs($manager)
            ->post(route('k3.pengusahaan.metode-pengujian.store'), [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
                'rows' => [],
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_access_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create(['is_active' => true]);
        $foreignUnit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $ownUnit);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.metode-pengujian.index', [
                'unit_id' => $foreignUnit->id,
                'month' => 9,
                'year' => 2026,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.metode-pengujian.store'), [
                'unit_id' => $foreignUnit->id,
                'month' => 9,
                'year' => 2026,
                'rows' => [],
            ])
            ->assertForbidden();
    }

    public function test_saving_pengusahaan_does_not_modify_old_akses1_metode_pengujian(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        // Old Akses 1 Formulir record
        $oldRow = K3MetodePengujianPeralatan::query()->create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 9,
            'no_urut' => '1',
            'nama_peralatan' => 'Data Lama Akses 1',
            'no_pengesahan' => 'OLD-123',
            'nama_kategori_alat' => 'Tangki Lama',
            'uji_visual' => 'Memenuhi',
            'keterangan' => 'Catatan Akses 1 Jangan Berubah',
        ]);

        $initialOldCount = K3MetodePengujianPeralatan::count();

        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Save new Akses 2 Pengusahaan data
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.metode-pengujian.store'), [
                'unit_id' => $unit->id,
                'month' => 9,
                'year' => 2026,
                'rows' => [
                    [
                        'no_urut' => '1',
                        'nama_peralatan' => 'Data Baru Pengusahaan',
                        'no_pengesahan' => 'NEW-999',
                        'nama_kategori_alat' => 'Tangki Baru',
                        'uji_visual' => 'Memenuhi',
                        'keterangan' => 'Catatan Pengusahaan Baru',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        // Old table is completely untouched
        $this->assertSame($initialOldCount, K3MetodePengujianPeralatan::count());
        $this->assertDatabaseHas('k3_metode_pengujian_peralatans', [
            'id' => $oldRow->id,
            'unit_id' => $unit->id,
            'nama_peralatan' => 'Data Lama Akses 1',
            'no_pengesahan' => 'OLD-123',
            'keterangan' => 'Catatan Akses 1 Jangan Berubah',
        ]);

        // New record exists in pengusahaan table
        $this->assertDatabaseHas('k3_pengusahaan_metode_pengujians', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 9,
            'nama_peralatan' => 'Data Baru Pengusahaan',
            'no_pengesahan' => 'NEW-999',
            'keterangan' => 'Catatan Pengusahaan Baru',
        ]);

        $newRecord = K3PengusahaanMetodePengujian::query()
            ->where('unit_id', $unit->id)
            ->where('no_pengesahan', 'NEW-999')
            ->firstOrFail();

        $this->assertSame('Data Baru Pengusahaan', $newRecord->nama_peralatan);
    }
}
