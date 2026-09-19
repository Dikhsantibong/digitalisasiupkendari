<?php

namespace Tests\Feature\Pdm;

use App\Enums\RoleName;
use App\Http\Controllers\Pdm\RealisasiPrediktifController;
use App\Models\PdmRealisasiPrediktif;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class RealisasiPrediktifInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_page_starts_with_the_default_activities_and_red_weekends(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.input.realisasi-prediktif.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pdm/input/realisasi-prediktif/index')
                ->has('days', 31)
                ->where('days.0.is_red', true)
                ->where('days.2.is_red', false)
                ->has('rows', count(RealisasiPrediktifController::DEFAULT_URAIAN))
                ->where('rows.0.uraian', 'VIBRASI')
                ->missing('meta.mengetahui_nama')
                ->missing('options.employees')
                ->where('has_saved', false),
            );
    }

    public function test_saving_cleans_the_days_and_computes_kinerja(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);

        $this->actingAs($user)
            ->post(route('pdm.input.realisasi-prediktif.store'), [
                'unit_id' => $unit->id, 'month' => 2, 'year' => 2026,
                'rows' => [['uraian' => 'VIBRASI', 'mesin' => 'ZHEJIANG #1', 'rencana' => [10, 3, 3, 30], 'realisasi' => [3], 'durasi' => 1.5]],
                'meta' => ['doc_number' => 'SMT-FM-KIT-02.01', 'dibuat_nama' => 'Diabaikan'],
            ])
            ->assertRedirect();

        $row = PdmRealisasiPrediktif::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame([3, 10], $row->rencana);

        $this->actingAs($user)
            ->get(route('pdm.input.realisasi-prediktif.index', ['unit_id' => $unit->id, 'month' => 2, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.target', 2)
                ->where('rows.0.realisasi_count', 1)
                ->where('rows.0.kinerja', '50%')
                ->where('has_saved', true),
            );
    }

    public function test_an_activity_needs_an_uraian(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->post(route('pdm.input.realisasi-prediktif.store'), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
                'rows' => [['uraian' => '', 'rencana' => [1]]],
            ])
            ->assertSessionHasErrors('rows.0.uraian');
    }

    public function test_the_pdf_is_landscape(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        PdmRealisasiPrediktif::factory()->create(['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]);

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.input.realisasi-prediktif.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $this->assertSame('L', $reader->getTemplateSize($reader->importPage(1))['orientation']);
    }

    public function test_a_role_without_pdm_input_cannot_save(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('pdm.input.realisasi-prediktif.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => []])
            ->assertForbidden();
    }
}
