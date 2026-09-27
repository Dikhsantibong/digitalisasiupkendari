<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3ActivityPlan;
use App\Models\K3ActivityType;
use App\Models\K3PengusahaanTimeFrame;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanTimeFrameTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_and_staf_k3_can_view_and_save_time_frame_pengusahaan(): void
    {
        foreach ([RoleName::TeamLeaderK3, RoleName::StafK3] as $role) {
            $unit = Unit::factory()->create(['is_active' => true]);
            $user = $this->userWithRole($role, $unit);

            $this->actingAs($user)
                ->get(route('k3.pengusahaan.time-frame.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('k3/pengusahaan/time-frame/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('days', 31)
                    ->has('rows', 14)
                );

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.time-frame.store'), [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                    'rows' => [
                        [
                            'no_urut' => 1,
                            'uraian_pelaporan' => 'Inspeksi P3K',
                            'pic' => 'K3 & Keamanan',
                            'rencana' => [13],
                            'realisasi' => [13],
                            'keterangan' => 'Selesai tepat waktu',
                            'sort_order' => 1,
                        ],
                    ],
                ])
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_time_frames', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'uraian_pelaporan' => 'Inspeksi P3K',
                'pic' => 'K3 & Keamanan',
                'keterangan' => 'Selesai tepat waktu',
            ]);
        }
    }

    public function test_koordinator_and_other_module_staf_receive_403(): void
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
                ->get(route('k3.pengusahaan.time-frame.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.time-frame.store'), [
                    'unit_id' => $unit->id,
                    'month' => 8,
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
            ->get(route('k3.pengusahaan.time-frame.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/pengusahaan/time-frame/index')
                ->where('can_write', false)
            );

        $this->actingAs($manager)
            ->post(route('k3.pengusahaan.time-frame.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
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
            ->get(route('k3.pengusahaan.time-frame.index', ['unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.time-frame.store'), [
                'unit_id' => $foreignUnit->id,
                'month' => 8,
                'year' => 2026,
                'rows' => [],
            ])
            ->assertForbidden();
    }

    public function test_saving_time_frame_pengusahaan_does_not_modify_old_time_frame(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $type = K3ActivityType::factory()->create();

        // Old Akses 1 Time Frame record
        $oldPlan = K3ActivityPlan::query()->create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'k3_activity_type_id' => $type->id,
            'pic' => 'Petugas Lama',
            'plan_days' => ['5' => '1'],
            'real_days' => ['5' => '1'],
            'keterangan' => 'Rencana Lama Akses 1',
        ]);

        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Save new Akses 2 Time Frame Pengusahaan
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.time-frame.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'rows' => [
                    [
                        'no_urut' => 1,
                        'uraian_pelaporan' => 'Inspeksi P3K Pengusahaan',
                        'pic' => 'K3 & Keamanan',
                        'rencana' => [13],
                        'realisasi' => [13],
                        'keterangan' => 'Pengusahaan Akses 2',
                        'sort_order' => 1,
                    ],
                ],
            ])
            ->assertRedirect();

        // Assert old record in k3_activity_plans is completely unchanged
        $this->assertDatabaseHas('k3_activity_plans', [
            'id' => $oldPlan->id,
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'pic' => 'Petugas Lama',
            'keterangan' => 'Rencana Lama Akses 1',
        ]);

        $freshOldPlan = K3ActivityPlan::query()->find($oldPlan->id);
        $this->assertSame(['5' => '1'], $freshOldPlan->plan_days);
        $this->assertSame(['5' => '1'], $freshOldPlan->real_days);

        // Assert new record exists in k3_pengusahaan_time_frames
        $this->assertDatabaseHas('k3_pengusahaan_time_frames', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'uraian_pelaporan' => 'Inspeksi P3K Pengusahaan',
            'pic' => 'K3 & Keamanan',
            'keterangan' => 'Pengusahaan Akses 2',
        ]);

        $newRecord = K3PengusahaanTimeFrame::query()
            ->where('unit_id', $unit->id)
            ->where('year', 2026)
            ->where('month', 8)
            ->firstOrFail();

        $this->assertSame([13], $newRecord->rencana);
        $this->assertSame([13], $newRecord->realisasi);
    }
}
