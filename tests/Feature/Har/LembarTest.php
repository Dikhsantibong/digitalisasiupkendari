<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Http\Controllers\Har\LembarController;
use App\Models\HarLembarMeta;
use App\Models\HarLembarRow;
use App\Models\Holiday;
use App\Models\Machine;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Support\HarLembar\HarLembars;
use App\Support\HarLembar\InventarisasiToolsLembar;
use App\Support\HarLembar\PatrolCheckPemeliharaanLembar;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class LembarTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    private function route(string $key, string $action, array $query = []): string
    {
        $menu = HarLembars::find($key)->menu();

        return route("har.{$menu}.lembar.{$action}", ['lembar' => $key, ...$query]);
    }

    public function test_every_lembar_opens_on_its_own_page_and_prints_a_pdf(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        Machine::factory()->forUnit($unit)->create();
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        foreach (HarLembars::all() as $lembar) {
            $this->actingAs($user)
                ->get($this->route($lembar->key(), 'index', $period))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    // ensure_pages_exist checks the page file.
                    ->component("har/{$lembar->menu()}/{$lembar->key()}/index")
                    ->where('lembar.key', $lembar->key())
                    ->where('has_saved', false)
                    ->has('rows', count($lembar->defaultRows())),
                );

            $response = $this->actingAs($user)->get($this->route($lembar->key(), 'pdf', $period));
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $reader = new Fpdi;
            $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
            $this->assertSame('L', $reader->getTemplateSize($reader->importPage(1))['orientation'], $lembar->key());
        }
    }

    public function test_a_lembar_is_only_served_under_its_own_menu(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        $this->actingAs($user)->get("/har/input/lembar/inventarisasi-tools?unit_id={$unit->id}")->assertNotFound();
        $this->actingAs($user)->get("/har/jadwal/lembar/patrol-check-pemeliharaan?unit_id={$unit->id}")->assertNotFound();
    }

    public function test_inventarisasi_starts_from_the_tool_list_without_results_and_saves_codes(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->get($this->route('inventarisasi-tools', 'index', $period))
            ->assertInertia(fn ($page) => $page
                ->has('rows', count(InventarisasiToolsLembar::TOOLS))
                ->where('rows.0.fields.uraian', 'Kunci Shock Set 8 - 32 mm')
                ->where('rows.0.fields.merek', 'TEKIRO')
                ->where('rows.0.cells.main', [])
                ->where('kop_lines.3', 'JADWAL INVENTARISASI TOOLS & MATERIAL BULAN AGUSTUS 2026'),
            );

        $this->actingAs($user)
            ->post($this->route('inventarisasi-tools', 'store'), $period + ['rows' => [
                ['section' => 'tools', 'fields' => ['uraian' => 'Kunci Inggris 12"', 'merek' => 'TEKIRO', 'satuan' => 'Bh', 'jumlah' => 1], 'cells' => ['main' => ['w1' => '1', 'w2' => '2', 'w3' => '3', 'w4' => '9', 'w5' => '1']]],
                ['section' => 'tools', 'fields' => ['uraian' => ''], 'cells' => ['main' => []]],
            ]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $saved = HarLembarRow::query()->where('lembar', 'inventarisasi-tools')->sole();
        // Unknown code (9) and unknown column (w5) are dropped; the blank row is skipped.
        $this->assertSame(['w1' => '1', 'w2' => '2', 'w3' => '3'], $saved->cells['main']);
        $this->assertSame(1, $saved->fields['jumlah']);

        $this->actingAs($user)
            ->get($this->route('inventarisasi-tools', 'index', $period))
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->has('rows', 1)
                ->where('summary.rows.0', [1, 1, 1, 1]),
            );
    }

    public function test_blackstart_is_yearly_with_rencana_realisasi_and_the_monthly_kinerja(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $weeks = fn (int $month): array => collect(range(1, 4))->mapWithKeys(fn (int $w): array => ["m{$month}w{$w}" => '1'])->all();

        $this->actingAs($user)
            ->post($this->route('pemeriksaan-blackstart', 'store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [
                ['section' => 'data-teknis', 'fields' => ['uraian' => 'ENGINE DIESEL GENSET (EDG)', 'pic' => 'Koordinator'], 'cells' => [
                    'rencana' => [...$weeks(7), ...$weeks(8)],
                    'realisasi' => [...$weeks(7), 'm8w1' => '1', 'm8w2' => '1', 'm8w3' => '1'],
                ]],
                ['section' => 'data-teknis', 'fields' => ['uraian' => 'SISTEM BATTERY'], 'cells' => ['rencana' => $weeks(8), 'realisasi' => $weeks(8)]],
            ]])
            ->assertSessionHasNoErrors();

        // Stored once per year (month = 0), so any month of the year reads it.
        $this->assertSame([0], HarLembarRow::query()->where('lembar', 'pemeriksaan-blackstart')->pluck('month')->unique()->values()->all());

        $this->actingAs($user)
            ->get($this->route('pemeriksaan-blackstart', 'index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('lembar.yearly', true)
                ->has('lembar.grid', 48)
                ->has('rows', 2)
                ->where('summary.title', 'KINERJA BULAN AUGUST')
                ->where('summary.rows.0', [1, 'ENGINE DIESEL GENSET (EDG)', 4, 3, '75%'])
                ->where('summary.rows.1', [2, 'SISTEM BATTERY', 4, 4, '100%'])
                ->where('summary.rows.2', [3, 'AVERAGE', 8, 7, '88%']),
            );

        $this->actingAs($user)
            ->get($this->route('pemeriksaan-blackstart', 'index', ['unit_id' => $unit->id, 'month' => 7, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page->where('summary.rows.0', [1, 'ENGINE DIESEL GENSET (EDG)', 4, 4, '100%']));

        [$view, $data] = app(LembarController::class)->pdfView(HarLembars::find('pemeriksaan-blackstart'), $unit, 8, 2026, null);
        $html = view($view, $data)->render();
        $this->assertStringContainsString('JANUARY', $html);
        $this->assertStringContainsString('RENCANA', $html);
        // TOTAL = 8 + 7 + 4 + 4 marks.
        $this->assertMatchesRegularExpression('/TOTAL<\/td>\s*<td class="c">23<\/td>/', $html);
    }

    public function test_patrol_check_is_kept_per_machine_with_one_result_per_day(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        [$first, $second] = Machine::factory()->forUnit($unit)->count(2)->create();
        Holiday::factory()->create(['year' => 2026, 'date' => '2026-08-17', 'day_name' => 'Monday', 'description' => 'Hari Kemerdekaan RI']);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->get($this->route('patrol-check-pemeliharaan', 'index', $period + ['machine_id' => $first->id]))
            ->assertInertia(fn ($page) => $page
                ->where('filters.machine_id', $first->id)
                ->has('rows', 29)
                ->where('rows.0.fields.peralatan', 'Lube Oil Line Pipe')
                ->where('lembar.grid.0.is_red', true) // Sabtu 1 Agustus
                ->where('lembar.grid.2.is_red', false) // Senin 3 Agustus
                ->where('lembar.grid.16.is_red', true), // Senin 17 Agustus (libur)
            );

        $this->actingAs($user)
            ->post($this->route('patrol-check-pemeliharaan', 'store'), $period + [
                'machine_id' => $first->id,
                'rows' => [
                    ['section' => 'fuel', 'fields' => ['peralatan' => 'PT Pump'], 'cells' => ['main' => ['d3' => 'N', 'd4' => 'T', 'd5' => 'X']]],
                    ['section' => 'cooling', 'fields' => ['peralatan' => 'Radiator Core'], 'cells' => ['main' => ['d3' => 'N']]],
                ],
                'catatan' => 'Radiator perlu pembersihan',
            ])
            ->assertSessionHasNoErrors();

        $rows = HarLembarRow::query()->where('lembar', 'patrol-check-pemeliharaan')->orderBy('sort_order')->get();
        $this->assertCount(2, $rows);
        $this->assertSame((string) $first->id, $rows[0]->subject);
        $this->assertSame(['d3' => 'N', 'd4' => 'T'], $rows[0]->cells['main']);
        $this->assertSame('Radiator perlu pembersihan', HarLembarMeta::query()->sole()->catatan);

        // The other machine keeps its own (empty) document.
        $this->actingAs($user)
            ->get($this->route('patrol-check-pemeliharaan', 'index', $period + ['machine_id' => $second->id]))
            ->assertInertia(fn ($page) => $page->where('has_saved', false)->has('rows', 29)->where('catatan', ''));

        $this->actingAs($user)
            ->get($this->route('patrol-check-pemeliharaan', 'index', $period + ['machine_id' => $first->id]))
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->where('catatan', 'Radiator perlu pembersihan')
                ->where('summary.rows.1', ['FUEL SYSTEM', 1, 1])
                ->where('summary.rows.2', ['COOLING SYSTEM', 1, 0])
                ->where('summary.rows.5', ['TOTAL', 2, 1]),
            );
    }

    public function test_a_per_machine_lembar_requires_a_machine_of_the_unit(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $otherMachine = Machine::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        $this->actingAs($user)
            ->post($this->route('patrol-check-pemeliharaan', 'store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => []])
            ->assertSessionHasErrors('machine_id');

        $this->actingAs($user)
            ->post($this->route('patrol-check-pemeliharaan', 'store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'machine_id' => $otherMachine->id, 'rows' => []])
            ->assertNotFound();
    }

    public function test_manager_ul_can_view_but_not_save_and_other_roles_are_forbidden(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get($this->route('inventarisasi-tools', 'index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($manager)
            ->post($this->route('inventarisasi-tools', 'store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => []])
            ->assertForbidden();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get($this->route('inventarisasi-tools', 'index', ['unit_id' => $unit->id]))
            ->assertForbidden();
    }

    public function test_every_lembar_table_fits_the_printed_page(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $machine = Machine::factory()->forUnit($unit)->create();

        foreach (HarLembars::all() as $lembar) {
            [$view, $data] = app(LembarController::class)->pdfView($lembar, $unit, 8, 2026, $lembar->perMachine() ? $machine : null);
            $dompdf = Pdf::loadView($view, $data)->setPaper('a4', $lembar->orientation())->getDomPDF();
            $rightEdge = 0.0;
            $dompdf->setCallbacks([[
                'event' => 'end_frame',
                'f' => function ($frame) use (&$rightEdge): void {
                    if (in_array($frame->get_node()->nodeName, ['td', 'th'], true)) {
                        $box = $frame->get_border_box();
                        $rightEdge = max($rightEdge, $box['x'] + $box['w']);
                    }
                },
            ]]);
            $dompdf->render();

            // A4 landscape width minus the 8 mm right margin, in pt.
            $this->assertLessThanOrEqual(841.89 - 8 / 25.4 * 72 + 0.5, $rightEdge, "{$lembar->key()} table overflows the page");
        }
    }

    public function test_the_patrol_lembar_counts_twenty_nine_items_across_five_systems(): void
    {
        $this->assertSame(29, collect(PatrolCheckPemeliharaanLembar::SYSTEMS)->sum(fn (array $s): int => count($s[1])));
    }
}
