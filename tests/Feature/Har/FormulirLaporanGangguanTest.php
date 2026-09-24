<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\HarLaporanGangguan;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Formulir Laporan Gangguan (Form LH-05) — moved from the Input menu to the
 * Formulir menu; the monthly rekap lives at har.input.laporan-gangguan.
 */
class FormulirLaporanGangguanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_form_opens_from_the_formulir_menu(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.formulir.laporan-gangguan.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('har/formulir/laporan-gangguan/index'));
    }

    public function test_a_report_is_saved_printed_and_deleted(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)
            ->post(route('har.formulir.laporan-gangguan.store'), [
                'unit_id' => $unit->id,
                'year' => 2026,
                'nomor' => '003/ULPLTD POASIA/LH05/XII/2023',
                'hal' => 'SHAFT & BEARING GENERATOR',
                'form_code' => 'LH - 05',
                'merek' => 'Cummins Unit #6',
                'peralatan_rusak' => 'Bearing generator',
                'analisa_penyebab' => 'Pelumasan kurang',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $report = HarLaporanGangguan::query()->where('unit_id', $unit->id)->sole();
        $this->assertSame('SHAFT & BEARING GENERATOR', $report->hal);

        $this->actingAs($user)
            ->get(route('har.formulir.laporan-gangguan.pdf', $report))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)
            ->delete(route('har.formulir.laporan-gangguan.destroy', $report))
            ->assertRedirect();
        $this->assertModelMissing($report);
    }

    public function test_the_old_input_address_now_serves_the_monthly_rekap(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.input.laporan-gangguan.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('har/input/laporan-gangguan/index')->where('tabel.key', 'laporan-gangguan'));
    }
}
