<?php

namespace Tests\Feature\Logistik;

use App\Enums\RoleName;
use App\Http\Controllers\Logistik\RekomendasiController;
use App\Models\LogistikRekomendasi;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class RekomendasiInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_page_starts_with_the_standard_uraian_and_eleven_rows(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $unit))
            ->get(route('logistik.input.rekomendasi.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('logistik/input/rekomendasi/index')
                ->has('rows', RekomendasiController::MIN_ROWS)
                ->where('rows.0.uraian', 'Ketersediaan stok material')
                ->where('rows.10.no_urut', 11)
                ->where('has_saved', false)
                ->where('can_write', true),
            );
    }

    public function test_blank_rows_are_skipped_and_rows_renumbered(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $unit))
            ->post(route('logistik.input.rekomendasi.store'), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
                'rows' => [
                    ['uraian' => 'Ketersediaan stok material', 'kondisi_existing' => 'Beberapa item mendekati minimum stock', 'tindak_lanjut' => 'Monitoring stok', 'keterangan' => 'Prioritas material kritis'],
                    ['uraian' => '', 'kondisi_existing' => '', 'tindak_lanjut' => '', 'keterangan' => ''],
                    ['uraian' => 'Ketersediaan Laptop', 'kondisi_existing' => '', 'tindak_lanjut' => 'Melakukan permintaan', 'keterangan' => 'Progres'],
                ],
            ])
            ->assertRedirect();

        $rows = LogistikRekomendasi::query()->where('unit_id', $unit->id)->orderBy('no_urut')->get();
        $this->assertSame([1, 2], $rows->pluck('no_urut')->all());
        $this->assertSame('Ketersediaan Laptop', $rows[1]->uraian);
        $this->assertNull($rows[1]->kondisi_existing);
    }

    public function test_saved_rows_replace_the_standard_uraian(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        LogistikRekomendasi::factory()->create(['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'uraian' => 'Inventaris Gudang']);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $unit))
            ->get(route('logistik.input.rekomendasi.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.uraian', 'Inventaris Gudang')
                ->where('rows.1.uraian', '')
                ->has('rows', RekomendasiController::MIN_ROWS)
                ->where('has_saved', true),
            );
    }

    public function test_the_pdf_is_portrait(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $unit))
            ->get(route('logistik.input.rekomendasi.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $this->assertSame('P', $reader->getTemplateSize($reader->importPage(1))['orientation']);
    }

    public function test_a_role_without_logistik_input_cannot_save(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('logistik.input.rekomendasi.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => []])
            ->assertForbidden();
    }

    public function test_a_foreign_unit_cannot_be_opened(): void
    {
        $own = Unit::factory()->create(['is_active' => true]);
        $foreign = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $own))
            ->get(route('logistik.input.rekomendasi.index', ['unit_id' => $foreign->id]))
            ->assertForbidden();
    }
}
