<?php

namespace Tests\Feature\Operasi;

use App\Enums\MaterialKelengkapan;
use App\Enums\RoleName;
use App\Http\Controllers\Operasi\MaterialPeralatanController;
use App\Models\OperasiMaterialPeralatan;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Laporan Material dan Peralatan: the Koordinator / Project Leader prepares
 * the items and their stock, the operators check each item (Lengkap = normal,
 * Tidak Lengkap / Tidak Ada with the uraian of the abnormal condition).
 */
class MaterialPeralatanCheckTest extends TestCase
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

    public function test_the_koordinator_and_project_leader_prepare_the_list_and_the_operator_only_checks(): void
    {
        foreach ([RoleName::KoordinatorOperasi, RoleName::ProjectLeaderOperasi] as $role) {
            $this->actingAs($this->userWithRole($role, $this->unit))
                ->get(route('operasi.input.material-peralatan.index', $this->period))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('can_manage', true)->where('can_check', true));
        }

        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $this->actingAs($operator)->get(route('operasi.input.material-peralatan.index', $this->period))
            ->assertInertia(fn ($page) => $page
                ->component('operasi/input/material-peralatan/index')
                ->where('can_manage', false)
                ->where('can_check', true)
                ->where('options.kelengkapan', ['lengkap' => 'Lengkap', 'tidak_lengkap' => 'Tidak Lengkap', 'tidak_ada' => 'Tidak Ada'])
                ->where('peralatan.0.kelengkapan', null)
                ->where('summary.checked_count', 0));

        // The operator neither saves the list & stock nor deletes an item.
        $item = OperasiMaterialPeralatan::query()->where('unit_id', $this->unit->id)->firstOrFail();
        $this->actingAs($operator)->post(route('operasi.input.material-peralatan.store'), $this->period + ['items' => [
            ['id' => $item->id, 'kategori' => 'PERALATAN', 'nama_item' => 'Diganti operator', 'stok_awal' => 9],
        ]])->assertForbidden();
        $this->actingAs($operator)->delete(route('operasi.input.material-peralatan.destroy', $item))->assertForbidden();
        $this->assertNotSame('Diganti operator', $item->fresh()->nama_item);
        $this->assertModelExists($item);

        // A view-only role checks nothing.
        $this->check($this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit), [['id' => $item->id, 'kelengkapan' => 'lengkap']])->assertForbidden();
    }

    public function test_the_operator_checks_lengkap_as_normal_and_describes_what_is_not(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        [$toolkit, $tang, $bor] = $this->items(['Toolkit Mekanik', 'Tang Kombinasi', 'Bor Listrik']);

        $this->check($operator, [
            // An uraian sent with Lengkap is dropped: lengkap means normal.
            ['id' => $toolkit->id, 'kelengkapan' => 'lengkap', 'uraian_kondisi' => 'abaikan'],
            ['id' => $tang->id, 'kelengkapan' => 'tidak_lengkap', 'uraian_kondisi' => 'Tinggal 1 dari 2'],
            ['id' => $bor->id, 'kelengkapan' => 'tidak_ada', 'uraian_kondisi' => 'Dipinjam unit lain'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $toolkit->refresh();
        $this->assertSame(MaterialKelengkapan::Lengkap, $toolkit->kelengkapan);
        $this->assertNull($toolkit->uraian_kondisi);
        $this->assertSame($operator->id, $toolkit->checked_by);
        $this->assertNotNull($toolkit->checked_at);
        $this->assertSame([MaterialKelengkapan::TidakLengkap, 'Tinggal 1 dari 2'], [$tang->fresh()->kelengkapan, $tang->fresh()->uraian_kondisi]);
        $this->assertSame(MaterialKelengkapan::TidakAda, $bor->fresh()->kelengkapan);

        $this->actingAs($operator)->get(route('operasi.input.material-peralatan.index', $this->period))
            ->assertInertia(fn ($page) => $page
                ->where('summary.checked_count', 3)
                ->where('summary.tidak_normal_count', 2)
                ->where('peralatan.1.kelengkapan', 'tidak_lengkap')
                ->where('peralatan.1.uraian_kondisi', 'Tinggal 1 dari 2')
                ->where('peralatan.1.checked_by_name', $operator->name));

        // A check saved again unchanged keeps who checked it; unchecking clears it all.
        $colleague = $this->userWithRole(RoleName::Operator, $this->unit);
        $this->check($colleague, [
            ['id' => $toolkit->id, 'kelengkapan' => 'lengkap'],
            ['id' => $tang->id, 'kelengkapan' => null, 'uraian_kondisi' => 'Tinggal 1 dari 2'],
        ])->assertSessionHasNoErrors();
        $this->assertSame($operator->id, $toolkit->fresh()->checked_by);
        $tang->refresh();
        $this->assertSame([null, null, null, null], [$tang->kelengkapan, $tang->uraian_kondisi, $tang->checked_by, $tang->checked_at]);
    }

    public function test_the_check_is_validated(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        [$item] = $this->items(['Toolkit Mekanik']);
        $otherMonth = OperasiMaterialPeralatan::query()->create(['unit_id' => $this->unit->id, 'year' => 2026, 'month' => 9, 'kategori' => 'MATERIAL', 'nama_item' => 'Bulan lain']);
        $otherUnit = OperasiMaterialPeralatan::query()->create(['unit_id' => Unit::factory()->create()->id, 'year' => 2026, 'month' => 8, 'kategori' => 'MATERIAL', 'nama_item' => 'Unit lain']);

        $this->check($operator, [['id' => $item->id, 'kelengkapan' => 'tidak_lengkap', 'uraian_kondisi' => ' ']])->assertSessionHasErrors('items.0.uraian_kondisi');
        $this->check($operator, [['id' => $item->id, 'kelengkapan' => 'tidak_ada']])->assertSessionHasErrors('items.0.uraian_kondisi');
        $this->check($operator, [['id' => $item->id, 'kelengkapan' => 'rusak']])->assertSessionHasErrors('items.0.kelengkapan');
        $this->check($operator, [['id' => $otherMonth->id, 'kelengkapan' => 'lengkap']])->assertSessionHasErrors('items.0.id');
        $this->check($operator, [['id' => $otherUnit->id, 'kelengkapan' => 'lengkap']])->assertSessionHasErrors('items.0.id');

        $this->assertNull($item->fresh()->kelengkapan);
        $this->assertNull($otherUnit->fresh()->kelengkapan);

        // Another unit's operator cannot check this unit.
        $this->check($this->userWithRole(RoleName::Operator, Unit::factory()->create()), [['id' => $item->id, 'kelengkapan' => 'lengkap']])->assertForbidden();
    }

    public function test_the_koordinator_sheet_save_keeps_or_sets_the_check(): void
    {
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        [$tang] = $this->items(['Tang Kombinasi']);
        $this->check($operator, [['id' => $tang->id, 'kelengkapan' => 'tidak_lengkap', 'uraian_kondisi' => 'Tinggal 1']]);

        $row = ['kategori' => 'PERALATAN', 'stok_awal' => 2, 'masuk' => 1, 'keluar' => 1, 'satuan' => 'Unit'];

        // A payload without the check (an older page) changes the stock and leaves the operator's check.
        $this->actingAs($koordinator)->post(route('operasi.input.material-peralatan.store'), $this->period + ['items' => [
            ['id' => $tang->id, 'nama_item' => 'Tang Kombinasi 8"'] + $row,
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $tang->refresh();
        $this->assertSame(['Tang Kombinasi 8"', '2.00', MaterialKelengkapan::TidakLengkap, $operator->id], [$tang->nama_item, $tang->stok_akhir, $tang->kelengkapan, $tang->checked_by]);

        // The sheet with the check: the new item is checked by the Koordinator; a missing uraian is rejected.
        $this->actingAs($koordinator)->post(route('operasi.input.material-peralatan.store'), $this->period + ['items' => [
            ['id' => $tang->id, 'nama_item' => 'Tang Kombinasi 8"', 'kelengkapan' => 'tidak_ada'] + $row,
        ]])->assertSessionHasErrors('items.0.uraian_kondisi');

        $this->actingAs($koordinator)->post(route('operasi.input.material-peralatan.store'), $this->period + ['items' => [
            ['id' => $tang->id, 'nama_item' => 'Tang Kombinasi 8"', 'kelengkapan' => 'tidak_lengkap', 'uraian_kondisi' => 'Tinggal 1'] + $row,
            ['nama_item' => 'Kunci Inggris', 'kelengkapan' => 'lengkap', 'uraian_kondisi' => null] + $row,
        ]])->assertSessionHasNoErrors();

        $this->assertSame($operator->id, $tang->fresh()->checked_by, 'An unchanged check keeps its checker.');
        $new = OperasiMaterialPeralatan::query()->where('nama_item', 'Kunci Inggris')->sole();
        $this->assertSame([MaterialKelengkapan::Lengkap, $koordinator->id, $koordinator->id], [$new->kelengkapan, $new->checked_by, $new->input_by]);
    }

    public function test_the_pdf_and_the_laporan_show_the_check(): void
    {
        [$toolkit, $tang, $bor] = $this->items(['Toolkit Mekanik', 'Tang Kombinasi', 'Bor Listrik']);
        $toolkit->update(['kelengkapan' => MaterialKelengkapan::Lengkap]);
        $tang->update(['kelengkapan' => MaterialKelengkapan::TidakLengkap, 'uraian_kondisi' => 'Tinggal 1 dari 2']);

        [$view, $data] = app(MaterialPeralatanController::class)->pdfView($this->unit, 8, 2026);
        $html = view($view, $data)->render();

        $this->assertStringContainsString('Kelengkapan', $html);
        $this->assertStringContainsString('Normal', $html);
        $this->assertStringContainsString('Tidak normal: Tinggal 1 dari 2', $html);
        $this->assertStringContainsString('Tidak Lengkap', $html);
        $this->assertNull($bor->fresh()->kelengkapan);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $this->unit))
            ->get(route('operasi.input.material-peralatan.pdf', $this->period))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function check(User $user, array $items): TestResponse
    {
        return $this->actingAs($user)->post(route('operasi.input.material-peralatan.check'), $this->period + ['items' => $items]);
    }

    /**
     * @param  list<string>  $names
     * @return list<OperasiMaterialPeralatan>
     */
    private function items(array $names): array
    {
        return array_map(fn (string $name, int $i): OperasiMaterialPeralatan => OperasiMaterialPeralatan::query()->create([
            'unit_id' => $this->unit->id, 'year' => 2026, 'month' => 8, 'kategori' => 'PERALATAN', 'nama_item' => $name, 'satuan' => 'Unit', 'sort_order' => $i + 1,
        ]), $names, array_keys($names));
    }
}
