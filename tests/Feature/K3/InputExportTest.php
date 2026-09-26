<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Http\Controllers\K3\ApdInventoryController;
use App\Models\K3ApdInventory;
use App\Models\K3RambuInspection;
use App\Models\Unit;
use App\Services\K3\K3InputTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class InputExportTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_every_input_exports_a_landscape_pdf(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        foreach (array_keys(K3InputTables::INPUTS) as $input) {
            $url = route('k3.input.export.pdf', ['input' => $input, 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]);
            $response = $this->actingAs($user)->followingRedirects()->get($url);

            $response->assertOk()->assertHeader('content-type', 'application/pdf');

            $reader = new Fpdi;
            $count = $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
            for ($page = 1; $page <= $count; $page++) {
                $this->assertSame('L', $reader->getTemplateSize($reader->importPage($page))['orientation'], "{$input} page {$page}");
            }
        }
    }

    public function test_the_excel_data_carries_the_saved_rows(): void
    {
        $unit = Unit::factory()->create();
        K3RambuInspection::query()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8,
            'rambu' => 'Rambu Wajib APD', 'lokasi' => 'Gerbang', 'kondisi' => 'Baik', 'keterangan' => 'Terpasang',
        ]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->getJson(route('k3.input.export.data', ['input' => 'rambu', 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertJsonPath('tables.0.has_data', true)
            ->assertJsonPath('tables.0.columns.1.label', 'Rambu-Rambu K3')
            ->assertJsonPath('tables.0.rows.0.cells', ['1', 'Rambu Wajib APD', 'Gerbang', 'Baik', 'Terpasang']);
    }

    public function test_a_period_without_saved_rows_has_no_data(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->getJson(route('k3.input.export.data', ['input' => 'rambu', 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertJsonPath('tables.0.has_data', false)
            ->assertJsonPath('tables.0.rows', []);
    }

    public function test_an_unsaved_input_exports_the_default_rows_its_page_shows(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->getJson(route('k3.input.export.data', ['input' => 'apd-inventory', 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertJsonPath('tables.0.has_data', true)
            ->assertJsonPath('tables.0.saved', false);

        $names = collect($response->json('tables.0.rows'))->where('kind', 'data')->pluck('cells.2')->all();
        $this->assertSame(array_column(ApdInventoryController::DEFAULTS, 'nama'), $names);
    }

    public function test_every_input_has_its_own_pdf_blade(): void
    {
        foreach (array_keys(K3InputTables::INPUTS) as $input) {
            $this->assertTrue(view()->exists("k3.input.{$input}-pdf"), "resources/views/k3/input/{$input}-pdf.blade.php");
        }
    }

    public function test_the_air_limbah_pdf_uses_its_own_official_layout(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->get(route('k3.input.export.pdf', ['input' => 'air-limbah', 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertRedirect(route('k3.input.air-limbah.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));
    }

    public function test_grouped_inputs_add_group_rows(): void
    {
        $unit = Unit::factory()->create();
        K3ApdInventory::query()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'grup' => 'Pelindung Kepala',
            'nama' => 'Safety Helmet', 'jumlah' => 12, 'satuan' => 'Pcs',
        ]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->getJson(route('k3.input.export.data', ['input' => 'apd-inventory', 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertJsonPath('tables.0.rows.0.kind', 'group')
            ->assertJsonPath('tables.0.rows.0.cells.0', 'Pelindung Kepala')
            ->assertJsonPath('tables.0.rows.1.cells.2', 'Safety Helmet')
            ->assertJsonPath('tables.0.rows.1.cells.3', '12');
    }

    public function test_an_unknown_input_is_not_found(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->get(route('k3.input.export.pdf', ['input' => 'tidak-ada', 'unit_id' => $unit->id]))
            ->assertNotFound();
    }

    public function test_a_foreign_unit_cannot_be_exported(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $ownUnit))
            ->getJson(route('k3.input.export.data', ['input' => 'rambu', 'unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026]))
            ->assertForbidden();
    }

    public function test_a_role_without_k3_access_cannot_export(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('k3.input.export.pdf', ['input' => 'rambu', 'unit_id' => $unit->id]))
            ->assertForbidden();
    }
}
