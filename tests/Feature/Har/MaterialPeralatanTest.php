<?php

namespace Tests\Feature\Har;

use App\Enums\MaterialKelengkapan;
use App\Enums\RoleName;
use App\Http\Controllers\Har\MaterialPeralatanController;
use App\Models\HarMaterialPeralatan;
use App\Models\OperasiMaterialPeralatan;
use App\Models\Unit;
use App\Services\Har\HarDocumentBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Input Pemeliharaan "Laporan Material dan Peralatan", like the Operasi one:
 * the Koordinator Pemeliharaan prepares the items and their stock, Harmes /
 * Harlist check each item; kept apart from the Operasi list and printed in the
 * Laporan Pemeliharaan.
 */
class MaterialPeralatanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    /** @var array{unit_id: int, month: int, year: int} */
    private array $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->unit = Unit::factory()->create(['is_active' => true]);
        $this->period = ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026];
    }

    public function test_the_koordinator_prepares_the_list_and_harmes_harlist_only_check(): void
    {
        $koordinator = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);

        $this->actingAs($koordinator)->get(route('har.input.material-peralatan.index', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/input/material-peralatan/index')
                ->where('can_manage', true)
                ->has('peralatan', 10));
        // The default list lands in the HAR table, not the Operasi one.
        $this->assertGreaterThan(0, HarMaterialPeralatan::query()->where('unit_id', $this->unit->id)->count());
        $this->assertSame(0, OperasiMaterialPeralatan::query()->count());

        // The Koordinator adds an item with its stock.
        $this->actingAs($koordinator)->post(route('har.input.material-peralatan.store'), $this->period + ['items' => [
            ['kategori' => 'PERALATAN', 'nama_item' => 'Feeler Gauge', 'satuan' => 'Set', 'stok_awal' => 2, 'masuk' => 1, 'keluar' => 0],
        ]])->assertSessionHasNoErrors();
        $feeler = HarMaterialPeralatan::query()->where('nama_item', 'Feeler Gauge')->sole();
        $this->assertSame('3.00', $feeler->stok_akhir);

        foreach ([RoleName::Harmes, RoleName::Harlist] as $role) {
            $teknisi = $this->userWithRole($role, $this->unit);
            $this->actingAs($teknisi)->get(route('har.input.material-peralatan.index', $this->period))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('can_manage', false)->where('can_check', true));

            // They neither change the list nor delete an item.
            $this->actingAs($teknisi)->post(route('har.input.material-peralatan.store'), $this->period + ['items' => [
                ['id' => $feeler->id, 'kategori' => 'PERALATAN', 'nama_item' => 'Diganti teknisi'],
            ]])->assertForbidden();
            $this->actingAs($teknisi)->delete(route('har.input.material-peralatan.destroy', $feeler->id))->assertForbidden();
        }

        $harmes = $this->userWithRole(RoleName::Harmes, $this->unit);
        $this->actingAs($harmes)->post(route('har.input.material-peralatan.check'), $this->period + ['items' => [
            ['id' => $feeler->id, 'kelengkapan' => 'tidak_lengkap'],
        ]])->assertSessionHasErrors('items.0.uraian_kondisi');
        $this->actingAs($harmes)->post(route('har.input.material-peralatan.check'), $this->period + ['items' => [
            ['id' => $feeler->id, 'kelengkapan' => 'tidak_lengkap', 'uraian_kondisi' => 'Blade 0,05 hilang'],
        ]])->assertSessionHasNoErrors();

        $feeler->refresh();
        $this->assertSame(MaterialKelengkapan::TidakLengkap, $feeler->kelengkapan);
        $this->assertSame('Blade 0,05 hilang', $feeler->uraian_kondisi);
        $this->assertSame($harmes->id, $feeler->checked_by);
        $this->assertSame('Feeler Gauge', $feeler->nama_item);

        $this->actingAs($koordinator)->delete(route('har.input.material-peralatan.destroy', $feeler->id))->assertRedirect();
        $this->assertModelMissing($feeler);
    }

    public function test_other_divisions_cannot_open_it_and_the_laporan_prints_it(): void
    {
        foreach ([RoleName::Operator, RoleName::KoordinatorOperasi] as $role) {
            $this->actingAs($this->userWithRole($role, $this->unit))
                ->get(route('har.input.material-peralatan.index', $this->period))
                ->assertForbidden();
        }

        HarMaterialPeralatan::query()->create(['unit_id' => $this->unit->id, 'year' => 2026, 'month' => 8, 'kategori' => 'PERALATAN', 'nama_item' => 'Kunci Momen', 'satuan' => 'Unit', 'kelengkapan' => 'lengkap']);

        $part = collect(app(HarDocumentBuilder::class)->build($this->unit, 8, 2026)['parts'])->firstWhere('key', 'material-peralatan');
        $this->assertNotNull($part);
        $this->assertTrue($part['saved']);
        $this->assertStringContainsString('Kunci Momen', $part['body']);

        [$view, $data] = app(MaterialPeralatanController::class)->pdfView($this->unit, 8, 2026);
        $this->assertStringContainsString('Kunci Momen', view($view, $data)->render());
    }
}
