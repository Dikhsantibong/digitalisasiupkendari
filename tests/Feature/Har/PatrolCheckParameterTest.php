<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Http\Controllers\Har\PatrolCheckParameterController;
use App\Models\HarPatrolCheckReading;
use App\Models\Machine;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\Har\HarDocumentBuilder;
use App\Support\HarPatrolCheckParameter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PatrolCheckParameterTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_header_matches_the_sheet_layout(): void
    {
        $rows = HarPatrolCheckParameter::headerRows();

        $this->assertCount(HarPatrolCheckParameter::HEADER_DEPTH, $rows);
        $this->assertSame(['TGL', 'PIC', 'JAM', 'LOAD (KW)', 'ENGINE', 'GENERATOR', 'TRAFO', 'VOLT. BATTERY (VDC)'], array_column($rows[0], 'label'));
        $this->assertSame([6, 12, 2], array_column(array_values(array_filter($rows[0], fn (array $c): bool => $c['colspan'] > 1)), 'colspan'));
        // Every level spans all 25 columns once rowspans are accounted for.
        $this->assertSame(1 + count(HarPatrolCheckParameter::columns()), array_sum(array_column($rows[0], 'colspan')));
        $this->assertEqualsWithDelta(100, array_sum(HarPatrolCheckParameter::widthPercents()), 0.1);
    }

    public function test_readings_are_saved_per_machine_and_day(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        [$first, $second] = Machine::factory()->count(2)->sequence(['name' => 'A Mesin'], ['name' => 'B Mesin'])->create(['unit_id' => $unit->id]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 9, 'year' => 2026];

        $this->actingAs($user)->get(route('har.input.patrol-check-parameter.index', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/input/patrol-check-parameter/index')
                ->where('filters.machine_id', $first->id)
                ->has('days', 30)
                ->where('days.4.is_red', true) // Sabtu 5 September 2026
                ->where('has_saved', false));

        $this->actingAs($user)->post(route('har.input.patrol-check-parameter.store'), $period + [
            'machine_id' => $first->id,
            'readings' => [
                '1' => ['pic' => 'Aswadi', 'jam' => '10.00', 'load' => '850', 'cos_phi' => '0,85', 'oil_level' => 'Normal'],
                '2' => ['pic' => '', 'load' => ''],
            ],
        ])->assertSessionHasNoErrors();

        $reading = HarPatrolCheckReading::query()->sole();
        $this->assertSame(1, $reading->day);
        $this->assertSame('0.85', $reading->values['cos_phi']);
        $this->assertSame($first->id, $reading->machine_id);

        $this->actingAs($user)->get(route('har.input.patrol-check-parameter.index', $period + ['machine_id' => $second->id]))
            ->assertInertia(fn ($page) => $page->where('has_saved', false));
        $this->actingAs($user)->get(route('har.input.patrol-check-parameter.index', $period + ['machine_id' => $first->id]))
            ->assertInertia(fn ($page) => $page->where('has_saved', true)->where('readings.1.pic', 'Aswadi'));
    }

    public function test_invalid_numbers_days_and_foreign_machines_are_rejected(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $machine = Machine::factory()->create(['unit_id' => $unit->id]);
        $foreign = Machine::factory()->create(['unit_id' => Unit::factory()->create()->id]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 9, 'year' => 2026];

        $this->actingAs($user)->post(route('har.input.patrol-check-parameter.store'), $period + [
            'machine_id' => $machine->id,
            'readings' => ['3' => ['load' => 'banyak'], '31' => ['pic' => 'X']],
        ])->assertSessionHasErrors(['readings.3.load', 'readings.31']);

        $this->actingAs($user)->post(route('har.input.patrol-check-parameter.store'), $period + [
            'machine_id' => $foreign->id, 'readings' => [],
        ])->assertNotFound();

        $this->assertSame(0, HarPatrolCheckReading::query()->count());
    }

    public function test_the_pdf_prints_the_month_on_one_landscape_page_and_reaches_the_report(): void
    {
        $unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Containerized Poasia']);
        $machine = Machine::factory()->create(['unit_id' => $unit->id, 'name' => 'Cummins #7']);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $this->actingAs($user)->post(route('har.input.patrol-check-parameter.store'), [
            'unit_id' => $unit->id, 'month' => 9, 'year' => 2026, 'machine_id' => $machine->id,
            'readings' => ['7' => ['pic' => 'Pembaca Parameter Uji', 'kvar' => '120']],
        ])->assertSessionHasNoErrors();

        [$view, $data] = app(PatrolCheckParameterController::class)->pdfView($unit, 9, 2026, $machine);
        $html = view($view, $data)->render();
        $this->assertStringContainsString('PATROL CHECK PEMELIHARAAN', $html);
        $this->assertStringContainsString('MESIN: CUMMINS #7', $html);
        $this->assertStringContainsString('Pembaca Parameter Uji', $html);
        $this->assertSame(8, substr_count($html, '<tr class="red">')); // 4 Sabtu + 4 Minggu September 2026

        $response = $this->actingAs($user)->get(route('har.input.patrol-check-parameter.pdf', ['unit_id' => $unit->id, 'month' => 9, 'year' => 2026, 'machine_id' => $machine->id]));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $this->assertSame(1, $reader->setSourceFile(StreamReader::createByString((string) $response->getContent())));
        $this->assertSame('L', $reader->getTemplateSize($reader->importPage(1))['orientation']);

        // Printed in the Laporan Pemeliharaan right after the Patrol Check, listed in the Daftar Isi.
        $builder = app(HarDocumentBuilder::class);
        $data = $builder->build($unit, 9, 2026);
        $keys = array_column($data['parts'], 'key');
        $part = collect($data['parts'])->firstWhere('key', "patrol-check-parameter-{$machine->id}");
        $this->assertTrue($part['saved']);
        $this->assertSame('landscape', $part['orientation']);
        $this->assertSame(array_search("lembar-patrol-check-pemeliharaan-{$machine->id}", $keys, true) + 1, array_search($part['key'], $keys, true));
        $body = $builder->bodyHtml($data);
        $this->assertStringContainsString('Pembaca Parameter Uji', $body);
        $this->assertStringContainsString('href="#part-patrol-check-parameter-'.$machine->id.'"', $body);
    }

    public function test_manager_ul_can_view_but_not_save(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true]);
        $machine = Machine::factory()->create(['unit_id' => $unit->id]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)->get(route('har.input.patrol-check-parameter.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($manager)->post(route('har.input.patrol-check-parameter.store'), [
            'unit_id' => $unit->id, 'month' => 9, 'year' => 2026, 'machine_id' => $machine->id, 'readings' => [],
        ])->assertForbidden();
    }
}
