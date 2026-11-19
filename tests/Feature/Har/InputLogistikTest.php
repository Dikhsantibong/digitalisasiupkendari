<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\LogistikFormRow;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\Reports\UlReport;
use App\Support\LogistikForms\InputLogistikForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Akses 2 — Input Logistik (user, 2026-11-19): the kondisi stok tools &
 * material with Dasar Stok (Cashcard, SPPL, Internal, Kontrak) and Nomor
 * Surat / Kontrak, for the TL Pemeliharaan / TL Ophar and the Staf
 * Pemeliharaan — not the Project Leader or the Koordinator.
 */
class InputLogistikTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const PERIOD = ['month' => 8, 'year' => 2026];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    public function test_the_tl_pemeliharaan_tl_ophar_and_staf_fill_the_input_logistik(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $target = ['unit_id' => $unit->id, ...self::PERIOD];

        foreach ([RoleName::TeamLeaderPemeliharaan, RoleName::TeamLeaderOphar, RoleName::StafPemeliharaan] as $role) {
            $user = $this->userWithRole($role, $unit);

            $this->actingAs($user)->get(route('har.pengusahaan.input-logistik.index', $target))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/har/input-logistik/index')
                    ->where('form.key', 'input-logistik')
                    ->where('can_write', true)
                    ->where('urls.store', route('har.pengusahaan.input-logistik.store'))
                    ->where('form.columns', fn ($columns): bool => collect($columns)->pluck('key')->slice(2, 3)->values()->all() === ['nama', 'dasar_stok', 'nomor_surat']
                        && collect($columns)->firstWhere('key', 'dasar_stok')['options'] === ['Cashcard', 'SPPL', 'Internal', 'Kontrak']));
        }

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOphar, $unit))
            ->post(route('har.pengusahaan.input-logistik.store'), $target + ['rows' => [
                ['section' => 'material', 'data' => ['nama' => 'Majun', 'dasar_stok' => 'SPPL', 'nomor_surat' => 'SPPL/001/2026', 'stok_awal' => 10, 'material_masuk' => 5, 'material_keluar' => 3, 'pemakaian_rata' => 2, 'ilt' => 3, 'safety_stock' => 2]],
                ['section' => 'peralatan', 'data' => []],
            ]])
            ->assertSessionHasNoErrors()->assertRedirect();

        $row = LogistikFormRow::query()->where('form', 'input-logistik')->sole();
        $this->assertSame(['SPPL', 'SPPL/001/2026'], [$row->data['dasar_stok'], $row->data['nomor_surat']]);
        $this->assertArrayNotHasKey('stok_akhir', $row->data);

        // Saved rows come back with the computed stok akhir (10 + 5 - 3) and ROP (2 × 3 + 2).
        $this->actingAs($this->userWithRole(RoleName::StafPemeliharaan, $unit))->get(route('har.pengusahaan.input-logistik.index', $target))
            ->assertInertia(fn ($page) => $page->where('has_saved', true)
                ->where('rows', fn ($rows): bool => collect($rows)->contains(fn (array $r): bool => ($r['data']['nama'] ?? null) === 'Majun' && $r['data']['stok_akhir'] === 12 && $r['data']['rop'] === 8)));

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.pengusahaan.input-logistik.pdf', $target))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_only_the_four_dasar_stok_are_accepted(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->post(route('har.pengusahaan.input-logistik.store'), ['unit_id' => $unit->id, ...self::PERIOD, 'rows' => [
                ['section' => 'material', 'data' => ['nama' => 'Majun', 'dasar_stok' => 'Hibah']],
            ]])
            ->assertSessionHasErrors('rows.0.data.dasar_stok');

        $this->assertSame(InputLogistikForm::DASAR_STOK, ['Cashcard', 'SPPL', 'Internal', 'Kontrak']);
    }

    public function test_the_project_leader_and_the_koordinator_have_no_input_logistik(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $target = ['unit_id' => $unit->id, ...self::PERIOD];

        foreach ([RoleName::ProjectLeaderOperasi, RoleName::KoordinatorPemeliharaan, RoleName::KoordinatorLogistik, RoleName::Operator] as $role) {
            $user = $this->userWithRole($role, $unit);
            $this->actingAs($user)->get(route('har.pengusahaan.input-logistik.index', $target))->assertForbidden();
            $this->actingAs($user)->post(route('har.pengusahaan.input-logistik.store'), $target + ['rows' => []])->assertForbidden();
            $this->actingAs($user)->get(route('har.pengusahaan.input-logistik.pdf', $target))->assertForbidden();
        }

        // Another unit stays closed, and the Logistik & Gudang form routes never serve this form.
        $other = Unit::factory()->create(['is_active' => true]);
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.pengusahaan.input-logistik.index', ['unit_id' => $other->id]))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::KoordinatorLogistik, $unit))
            ->get('/logistik/input/form/input-logistik?unit_id='.$unit->id)->assertNotFound();
    }

    public function test_the_tl_input_logistik_flows_into_the_laporan_logistik_of_the_laporan_ul(): void
    {
        $ul = ServiceUnit::factory()->create(['name' => 'UL PLTD Raha']);
        $raha = Unit::factory()->forServiceUnit($ul)->create(['name' => 'PLTD Raha', 'is_active' => true]);
        $ereke = Unit::factory()->forServiceUnit($ul)->create(['name' => 'PLTD Ereke', 'is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOphar, $raha))
            ->post(route('har.pengusahaan.input-logistik.store'), ['unit_id' => $raha->id, ...self::PERIOD, 'rows' => [
                ['section' => 'material', 'data' => ['nama' => 'Majun', 'dasar_stok' => 'Kontrak', 'nomor_surat' => 'KTR-07', 'stok_awal' => 10, 'material_masuk' => 0, 'material_keluar' => 9, 'harga_satuan' => 15000, 'pemakaian_rata' => 1, 'ilt' => 3, 'safety_stock' => 2]],
                ['section' => 'peralatan', 'data' => ['nama' => 'Kunci Torsi', 'dasar_stok' => 'Cashcard', 'stok_awal' => 2, 'harga_satuan' => 500000]],
            ]])
            ->assertSessionHasNoErrors();

        $part = app(UlReport::class)->build('logistik', $ul, self::PERIOD['month'], self::PERIOD['year'])['parts'][0];
        [$recap, $detail] = $part['sections'];

        // Rekap per unit: items, stock value (1 × 15.000 + 2 × 500.000), items at/below ROP (1 ≤ 5), per Dasar Stok.
        $this->assertSame(['Unit', 'Jumlah Item', 'Nilai Stok', 'Item ≤ ROP', 'Cashcard', 'SPPL', 'Internal', 'Kontrak'], $recap['columns']);
        $this->assertSame(['PLTD Ereke', '0', 'Rp 0', '0', '0', '0', '0', '0'], $recap['rows'][0]);
        $this->assertSame(['PLTD Raha', '2', 'Rp 1.015.000', '1', '1', '0', '0', '1'], $recap['rows'][1]);
        $this->assertSame('TOTAL UL', $recap['footer'][0]);

        // Rincian: Dasar Stok & Nomor Surat / Kontrak of each saved row (default rows are not counted).
        $this->assertCount(2, $detail['rows']);
        $this->assertSame(['PLTD Raha', 'Material', 'Majun', 'Kontrak', 'KTR-07'], array_slice($detail['rows'][0], 0, 5));
        $this->assertTrue(collect($part['insights'])->contains(fn (array $i): bool => str_contains($i['text'], 'belum diisi: PLTD Ereke')));

        // The other Laporan UL keep their own parts.
        $this->assertSame([], array_values(array_filter(app(UlReport::class)->build('k3l', $ul, self::PERIOD['month'], self::PERIOD['year'])['parts'][0]['sections'], fn (array $s): bool => str_contains($s['title'], 'Input Logistik'))));
    }
}
