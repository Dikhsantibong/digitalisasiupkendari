<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Http\Controllers\Operasi\FlmController;
use App\Models\OperasiFlmJadwal;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class FlmJadwalTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_index_renders_default_flm_rows_including_shift_schedule_row(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Bau-Bau', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.jadwal.flm.index', [
            'unit_id' => $unit->id,
            'month' => 11,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('operasi/jadwal/flm/index')
            ->where('filters.unit_id', $unit->id)
            ->where('filters.month', 11)
            ->where('filters.year', 2026)
            ->where('rows.0.section', 'rutin')
            ->where('rows.0.row_type', 'mark')
            ->where('rows.1.section', 'rutin')
            ->where('rows.1.row_type', 'shift')
            ->where('rows.1.label', '08:00 sd 15:00')
        );
    }

    public function test_store_normalizes_shift_letters_to_uppercase_and_renders_pdf(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Bau-Bau', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->post(route('operasi.jadwal.flm.store'), [
            'unit_id' => $unit->id,
            'month' => 11,
            'year' => 2026,
            'rows' => [
                [
                    'section' => 'rutin',
                    'shift' => 'JADWAL',
                    'label' => 'REALISASI',
                    'row_type' => 'mark',
                    'days' => ['1' => '1', '2' => '1'],
                    'target' => 30,
                ],
                [
                    'section' => 'rutin',
                    'shift' => '',
                    'label' => '08:00 sd 15:00',
                    'row_type' => 'shift',
                    'days' => ['1' => 'a', '2' => 'b', '3' => 'c', '4' => 'd'],
                    'target' => 0,
                ],
                [
                    'section' => 'non_rutin',
                    'shift' => 'A',
                    'label' => 'Waktu tentatif',
                    'row_type' => 'minutes',
                    'days' => ['1' => '45'],
                    'target' => 900,
                ],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $shiftRow = OperasiFlmJadwal::query()
            ->where('unit_id', $unit->id)
            ->where('year', 2026)
            ->where('month', 11)
            ->where('row_type', 'shift')
            ->firstOrFail();

        $this->assertSame(['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'], $shiftRow->days);

        [$view, $data] = app(FlmController::class)->pdfView($unit, 11, 2026);
        $html = view($view, $data)->render();
        $this->assertStringContainsString('08:00 sd 15:00', $html);

        $pdfResponse = $this->actingAs($superAdmin)->get(route('operasi.jadwal.flm.pdf', [
            'unit_id' => $unit->id,
            'month' => 11,
            'year' => 2026,
        ]));

        $pdfResponse->assertOk();
        $this->assertStringContainsString('application/pdf', $pdfResponse->headers->get('content-type') ?? '');
    }

    public function test_unit_user_cannot_access_or_store_another_unit(): void
    {
        $unit1 = Unit::factory()->create(['is_active' => true]);
        $unit2 = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit1);

        $this->actingAs($user)
            ->get(route('operasi.jadwal.flm.index', ['unit_id' => $unit1->id, 'month' => 11, 'year' => 2026]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('operasi.jadwal.flm.index', ['unit_id' => $unit2->id, 'month' => 11, 'year' => 2026]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('operasi.jadwal.flm.store'), [
                'unit_id' => $unit2->id,
                'month' => 11,
                'year' => 2026,
                'rows' => [],
            ])
            ->assertForbidden();
    }
}
