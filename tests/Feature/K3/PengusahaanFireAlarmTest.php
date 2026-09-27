<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3PengusahaanFireAlarm;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PengusahaanFireAlarmTest extends TestCase
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
                ->get(route('k3.pengusahaan.fire-alarm.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('k3/pengusahaan/fire-alarm/index')
                    ->where('unit.id', $unit->id)
                    ->where('can_write', true)
                    ->has('record.items', 6)
                );

            $payload = [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'no_dokumen' => 'SMT-FM-AK3-12.06',
                'revisi' => '00',
                'tanggal_dokumen' => '23 SEPTEMBER 2019',
                'tanggal_periksa' => '12/08/2026',
                'catatan' => 'Pemeriksaan rutin panel dan sensor fire alarm bulan Agustus.',
                'items' => [
                    [
                        'no_urut' => 1,
                        'lokasi' => 'LANTAI 2 (CCR)',
                        'tanggal_periksa' => '12/08/2026',
                        'kondisi' => 'Baik',
                        'panel_indikator' => 'Baik',
                        'keterangan' => 'Normal standby',
                        'sort_order' => 0,
                    ],
                    [
                        'no_urut' => 2,
                        'lokasi' => 'R. PEMBANGKIT (LOKAL)',
                        'tanggal_periksa' => '12/08/2026',
                        'kondisi' => 'Baik',
                        'panel_indikator' => 'Baik',
                        'keterangan' => 'Normal standby',
                        'sort_order' => 1,
                    ],
                ],
            ];

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.fire-alarm.store'), $payload)
                ->assertRedirect();

            $this->assertDatabaseHas('k3_pengusahaan_fire_alarms', [
                'unit_id' => $unit->id,
                'year' => 2026,
                'month' => 8,
                'no_dokumen' => 'SMT-FM-AK3-12.06',
                'input_by' => $user->id,
            ]);

            $record = K3PengusahaanFireAlarm::where('unit_id', $unit->id)->first();
            $this->assertNotNull($record);
            $this->assertCount(2, $record->items);
            $this->assertDatabaseHas('k3_pengusahaan_fire_alarm_items', [
                'laporan_id' => $record->id,
                'lokasi' => 'LANTAI 2 (CCR)',
                'kondisi' => 'Baik',
                'panel_indikator' => 'Baik',
            ]);
        }
    }

    public function test_subsequent_month_prefills_from_previous_month(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        // Save for month 7 with custom item
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.fire-alarm.store'), [
                'unit_id' => $unit->id,
                'month' => 7,
                'year' => 2026,
                'items' => [
                    [
                        'no_urut' => 1,
                        'lokasi' => 'Gedung Workshop Khusus',
                        'tanggal_periksa' => '15/07/2026',
                        'kondisi' => 'Baik',
                        'panel_indikator' => 'Normal',
                        'keterangan' => 'Detector baru dipasang',
                        'sort_order' => 0,
                    ],
                ],
            ])
            ->assertRedirect();

        // Access month 8 (should prefill with custom item from month 7)
        $this->actingAs($user)
            ->get(route('k3.pengusahaan.fire-alarm.index', [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/pengusahaan/fire-alarm/index')
                ->where('has_saved', false)
                ->where('record.items.0.lokasi', 'Gedung Workshop Khusus')
                ->where('record.items.0.panel_indikator', 'Normal')
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
                ->get(route('k3.pengusahaan.fire-alarm.index', [
                    'unit_id' => $unit->id,
                    'month' => 8,
                    'year' => 2026,
                ]))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('k3.pengusahaan.fire-alarm.store'), [
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

        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unitA);

        $this->actingAs($user)
            ->get(route('k3.pengusahaan.fire-alarm.index', [
                'unit_id' => $unitB->id,
                'month' => 8,
                'year' => 2026,
            ]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('k3.pengusahaan.fire-alarm.store'), [
                'unit_id' => $unitB->id,
                'month' => 8,
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
            ->post(route('k3.pengusahaan.fire-alarm.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'items' => [
                    [
                        'no_urut' => 1,
                        'lokasi' => 'LANTAI 2 (CCR)',
                        'tanggal_periksa' => '12/08/2026',
                        'kondisi' => 'Baik',
                        'panel_indikator' => 'Baik',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('k3_pengusahaan_fire_alarm_items', 1);

        // Update to 2 items
        $this->actingAs($user)
            ->post(route('k3.pengusahaan.fire-alarm.store'), [
                'unit_id' => $unit->id,
                'month' => 8,
                'year' => 2026,
                'items' => [
                    [
                        'no_urut' => 1,
                        'lokasi' => 'LANTAI 2 (CCR)',
                        'tanggal_periksa' => '12/08/2026',
                        'kondisi' => 'Baik',
                        'panel_indikator' => 'Baik',
                    ],
                    [
                        'no_urut' => 2,
                        'lokasi' => 'AREA RADIATOR',
                        'tanggal_periksa' => '12/08/2026',
                        'kondisi' => 'Perlu Perbaikan',
                        'panel_indikator' => 'Trouble',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('k3_pengusahaan_fire_alarm_items', 2);
        $this->assertDatabaseHas('k3_pengusahaan_fire_alarm_items', [
            'lokasi' => 'AREA RADIATOR',
            'kondisi' => 'Perlu Perbaikan',
            'panel_indikator' => 'Trouble',
        ]);
    }
}
