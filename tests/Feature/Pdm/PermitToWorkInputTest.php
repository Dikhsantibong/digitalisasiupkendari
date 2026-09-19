<?php

namespace Tests\Feature\Pdm;

use App\Enums\RoleName;
use App\Http\Controllers\Pdm\PermitToWorkController;
use App\Models\PdmPermitToWork;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PermitToWorkInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_page_shows_thirty_numbered_rows(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.input.permit-to-work.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pdm/input/permit-to-work/index')
                ->has('rows', PermitToWorkController::MIN_ROWS)
                ->where('rows.29.no_urut', 30)
                ->where('has_saved', false),
            );
    }

    public function test_blank_rows_are_skipped_and_permits_renumbered(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->post(route('pdm.input.permit-to-work.store'), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
                'rows' => [
                    ['uraian' => 'Pengukuran vibrasi mesin #1', 'tanggal' => '2026-08-04', 'status' => 'close'],
                    ['uraian' => '', 'tanggal' => null, 'status' => 'open'],
                    ['uraian' => 'Sampling oli trafo', 'tanggal' => '2026-08-10', 'status' => 'open'],
                ],
            ])
            ->assertRedirect();

        $permits = PdmPermitToWork::query()->where('unit_id', $unit->id)->orderBy('no_urut')->get();
        $this->assertSame([1, 2], $permits->pluck('no_urut')->all());
        $this->assertSame(['close', 'open'], $permits->pluck('status')->all());
    }

    public function test_the_pdf_is_portrait_like_the_form(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        PdmPermitToWork::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'no_urut' => 1]);

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.input.permit-to-work.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $this->assertSame('P', $reader->getTemplateSize($reader->importPage(1))['orientation']);
    }

    public function test_an_invalid_status_is_rejected(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->post(route('pdm.input.permit-to-work.store'), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
                'rows' => [['uraian' => 'x', 'status' => 'pending']],
            ])
            ->assertSessionHasErrors('rows.0.status');
    }
}
