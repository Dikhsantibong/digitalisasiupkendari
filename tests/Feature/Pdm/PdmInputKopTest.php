<?php

namespace Tests\Feature\Pdm;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\Unit;
use App\Support\PdmForms\PdmForms;
use App\Support\PdmInputKop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PdmInputKopTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function customInputs(): array
    {
        return [
            'kesiapan apd' => ['kesiapan-apd'],
            'permit to work' => ['permit-to-work'],
            'realisasi prediktif' => ['realisasi-prediktif'],
            'sample monitoring' => ['sample-monitoring'],
        ];
    }

    #[DataProvider('customInputs')]
    public function test_the_input_page_shows_the_same_kop_as_its_pdf(string $input): void
    {
        $unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Poasia']);
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];
        $lines = PdmInputKop::lines($input, $unit->name);

        $this->assertContains('PLN NP UP KENDARI PLTD POASIA', $lines);

        $this->actingAs($user)
            ->get(route("pdm.input.{$input}.index", $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component("pdm/input/{$input}/index")
                ->where('kop_lines', $lines),
            );

        $this->actingAs($user)
            ->get(route("pdm.input.{$input}.pdf", $period))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[DataProvider('customInputs')]
    public function test_the_pdf_view_prints_the_shared_kop_lines(string $input): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $controller = app('App\\Http\\Controllers\\Pdm\\'.str($input)->studly().'Controller');

        [$view, $data] = $controller->pdfView($unit, 8, 2026);
        $html = view($view, $data)->render();

        foreach (PdmInputKop::lines($input, $unit->name) as $line) {
            $this->assertStringContainsString(e($line), $html);
        }
    }

    public function test_every_generic_form_page_gets_its_kop_lines(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        Machine::factory()->forUnit($unit)->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);

        foreach (PdmForms::all() as $form) {
            $this->actingAs($user)
                ->get(route('pdm.input.forms.index', ['form' => $form->key(), 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('kop_lines', $form->kopLines($unit->name)));
        }
    }

    public function test_an_unknown_input_has_no_kop(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PdmInputKop::lines('tidak-ada', 'Unit');
    }
}
