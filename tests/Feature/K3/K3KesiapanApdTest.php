<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class K3KesiapanApdTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    public function test_authorized_user_can_view_kesiapan_apd_page(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $response = $this->actingAs($user)->get(route('k3.input.kesiapan-apd.index', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('k3/input/kesiapan-apd')
            ->has('rows')
            ->has('groups')
            ->has('options')
            ->where('filters.unit_id', $unit->id)
            ->where('filters.month', 8)
            ->where('filters.year', 2026)
        );
    }

    public function test_authorized_user_can_save_kesiapan_apd_data(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $payload = [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'rows' => [
                [
                    'kelompok' => 'I. Alat Pelindung Diri (APD)',
                    'no_urut' => '1',
                    'inspeksi' => 'Sarung tangan kain bintik',
                    'apd_jumlah' => '5 set',
                    'kelayakan_apd' => 'Layak',
                    'peralatan_jumlah' => '-',
                    'peralatan_kelayakan' => '-',
                    'sop_pnp' => '-',
                    'sop_vendor' => '-',
                    'p3k_ada' => '-',
                    'p3k_memenuhi' => '-',
                    'cara_kerja' => '-',
                    'keterangan' => 'Kondisi baik',
                    'sort_order' => 0,
                ],
                [
                    'kelompok' => 'II. Peralatan Kerja',
                    'no_urut' => '1',
                    'inspeksi' => 'APAR',
                    'apd_jumlah' => '-',
                    'kelayakan_apd' => '-',
                    'peralatan_jumlah' => '7 buah',
                    'peralatan_kelayakan' => 'Layak',
                    'sop_pnp' => '-',
                    'sop_vendor' => '-',
                    'p3k_ada' => '-',
                    'p3k_memenuhi' => '-',
                    'cara_kerja' => '-',
                    'keterangan' => '7 bh apar expired Agustus 2026',
                    'sort_order' => 1,
                ],
            ],
            'meta' => [
                'catatan' => 'Semua APD terdistribusi dengan baik.',
            ],
        ];

        $response = $this->actingAs($user)->post(route('k3.input.kesiapan-apd.store'), $payload);

        $response->assertRedirect(route('k3.input.kesiapan-apd.index', [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $this->assertDatabaseHas('k3_kesiapan_apds', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'inspeksi' => 'Sarung tangan kain bintik',
            'kelayakan_apd' => 'Layak',
        ]);

        $this->assertDatabaseHas('k3_kesiapan_apds', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'inspeksi' => 'APAR',
            'peralatan_kelayakan' => 'Layak',
        ]);

        $this->assertDatabaseHas('k3_kesiapan_apd_meta', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'catatan' => 'Semua APD terdistribusi dengan baik.',
        ]);
    }
}
