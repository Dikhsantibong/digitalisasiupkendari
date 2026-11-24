<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\HarUnsafeCondition;
use App\Models\K3KondisiK3;
use App\Models\LogistikFormRow;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\K3\K3ReportBuilder;
use App\Support\HarProgram5s5r;
use App\Support\OperasiProgram5s5r;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Every 5S5R input (Operasi, Pemeliharaan, PdM, Logistik & Gudang) still lands
 * in K3 Rekap 5S5R and Laporan K3, while Unsafe Action & Condition input is
 * consolidated exclusively in K3 Kondisi K3 (with read-only legacy fallback).
 */
class K3DivisionFeedTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private ServiceUnit $serviceUnit;

    private Unit $unit;

    /** @var array{unit_id: int, month: int, year: int} */
    private array $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutDefer();
        $this->seedAccessControl();
        $this->serviceUnit = ServiceUnit::factory()->create();
        $this->unit = Unit::factory()->forServiceUnit($this->serviceUnit)->create(['is_active' => true, 'name' => 'PLTD Uji']);
        $this->period = ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026];
    }

    public function test_non_k3_unsafe_input_endpoints_are_removed_and_legacy_findings_stay_readable(): void
    {
        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $this->unit))
            ->get('/operasi/input/unsafe-condition')
            ->assertNotFound();
        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit))
            ->get('/har/input/unsafe-condition')
            ->assertNotFound();
        $this->actingAs($this->userWithRole(RoleName::KoordinatorLogistik, $this->unit))
            ->get(route('logistik.input.form.index', ['form' => 'unsafe', ...$this->period]))
            ->assertNotFound();

        $otherUnit = Unit::factory()->create(['is_active' => true]);
        $base = ['periode' => 'Minggu 1', 'kategori' => 'UNSAFE CONDITION', 'keterangan' => 'open'];
        HarUnsafeCondition::query()->create([...$base, ...$this->period, 'source' => 'operasi', 'temuan' => 'Legacy operasi']);
        HarUnsafeCondition::query()->create([...$base, ...$this->period, 'month' => 7, 'source' => 'operasi', 'temuan' => 'Bulan lalu']);
        HarUnsafeCondition::query()->create([...$base, ...$this->period, 'unit_id' => $otherUnit->id, 'source' => 'har', 'temuan' => 'Unit lain']);
        LogistikFormRow::factory()->create([...$this->period, 'form' => 'unsafe', 'section' => 'temuan', 'data' => ['kategori' => 'UNSAFE ACTION', 'temuan' => 'Legacy logistik', 'keterangan' => 'Close']]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $this->unit))
            ->get(route('k3.input.kondisi-k3.index', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/input/kondisi-k3/index')
                ->has('division_findings', 2)
                ->where('division_findings.0.sumber', 'Operasi')
                ->where('division_findings.0.url', null)
                ->where('division_findings.1.sumber', 'Logistik & Gudang')
                ->where('division_findings.1.url', null)
                ->where('division_summary', ['total' => 2, 'unsafe_action' => 1, 'unsafe_condition' => 1, 'open' => 1, 'closed' => 1])
                ->where('has_saved', false),
            );
    }

    public function test_saving_kondisi_k3_keeps_any_legacy_division_findings(): void
    {
        HarUnsafeCondition::query()->create([...$this->period, 'periode' => 'Minggu 1', 'source' => 'har', 'kategori' => 'UNSAFE CONDITION', 'temuan' => 'Tumpahan oli di lantai', 'keterangan' => 'open']);
        $k3 = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);

        foreach (['Helm tidak dipakai', 'Kotak P3K kosong'] as $temuan) {
            $this->actingAs($k3)->post(route('k3.input.kondisi-k3.store'), $this->period + ['rows' => [
                ['kategori' => 'Unsafe Action', 'temuan' => $temuan, 'keterangan' => 'Open'],
            ]])->assertSessionHasNoErrors();
        }

        $this->assertSame(['Kotak P3K kosong'], K3KondisiK3::query()->pluck('temuan')->all());

        $this->actingAs($k3)->get(route('k3.input.kondisi-k3.index', $this->period))
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->has('rows', 1)
                ->where('rows.0.temuan', 'Kotak P3K kosong')
                ->has('division_findings', 1)
                ->where('division_findings.0.temuan', 'Tumpahan oli di lantai'));
    }

    public function test_the_rekap_5s5r_collects_the_5s5r_input_of_every_division(): void
    {
        $operasi = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $this->actingAs($operasi)->post(route('operasi.input.program-5s5r.store'), $this->period + ['rows' => [
            [...OperasiProgram5s5r::blankRow(1, 'Pagi', 'ringkas'), 'kondisi_awal' => 'Kurang Baik', 'progres' => '51-75', 'kondisi_akhir' => 'Baik'],
            [...OperasiProgram5s5r::blankRow(2, 'Sore', 'ringkas'), 'kondisi_awal' => 'Baik', 'progres' => '76-100', 'kondisi_akhir' => 'Baik'],
            [...OperasiProgram5s5r::blankRow(1, 'Pagi', 'resik'), 'keterangan' => 'Lantai genset dibersihkan'],
        ]])->assertSessionHasNoErrors();

        $har = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $this->actingAs($har)->post(route('har.input.program-5s5r.store'), $this->period + ['rows' => [
            [...HarProgram5s5r::blankRow(3, 'Malam', 'rapi'), 'progres' => '26-50', 'keterangan' => 'Rak tools ditata ulang'],
        ]])->assertSessionHasNoErrors();

        $pdm = $this->userWithRole(RoleName::KoordinatorPdm, $this->unit);
        $this->actingAs($pdm)->post(route('pdm.input.forms.store', ['form' => 'checklist-5s5r']), $this->period + [
            'header' => ['nama_pembangkit' => $this->unit->name],
            'rows' => ['ringkas' => [
                ['item' => 'Alat rusak dipisahkan', 'rencana' => '1', 'realisasi' => '1'],
                ['item' => 'Material bekas dibuang', 'rencana' => '1', 'realisasi' => null, 'temuan' => 'Drum bekas menumpuk', 'tindak_lanjut' => 'Diangkut ke TPS'],
            ]],
        ])->assertSessionHasNoErrors();

        $logistik = $this->userWithRole(RoleName::KoordinatorLogistik, $this->unit);
        $this->actingAs($logistik)->post(route('logistik.input.sheet.store', ['jadwal' => 'inspeksi-5s5r']), $this->period + ['rows' => [
            ['section' => 'ringkas', 'nama' => 'Alat rusak dipisahkan', 'days' => ['1' => 'D', '2' => 'D'], 'keterangan' => 'Dua alat rusak dipisah'],
            ['section' => 'rajin', 'nama' => 'Briefing dilaksanakan', 'days' => ['1' => 'D'], 'target' => 4],
        ]])->assertSessionHasNoErrors();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $this->unit))
            ->get(route('k3.input.rekap-5s5r.index', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/input/rekap-5s5r/index')
                ->has('programs', 4)
                // Operasi: August 2026 has 31 days × 3 shifts = 93 slots; Ringkas filled in 2 of them.
                ->where('programs.0.sumber', 'Operasi')
                ->where('programs.0.saved', true)
                ->where('programs.0.rows.0.program', 'Ringkas (Seiri)')
                ->where('programs.0.rows.0.rencana', 93)
                ->where('programs.0.rows.0.realisasi', 2)
                ->where('programs.0.rows.0.kinerja', '2%')
                ->where('programs.0.rows.0.catatan', 'Tgl 2 (Sore) · kondisi akhir Baik · progres 76-100%')
                ->where('programs.0.rows.1.realisasi', 0)
                ->where('programs.0.rows.2.realisasi', 1)
                ->where('programs.0.total', ['rencana' => 465, 'realisasi' => 3, 'kinerja' => '1%'])
                ->where('programs.0.temuan', ['Tgl 1 (Pagi) — Resik (Seiso): Lantai genset dibersihkan'])
                // Pemeliharaan.
                ->where('programs.1.sumber', 'Pemeliharaan')
                ->where('programs.1.rows.1.realisasi', 1)
                ->where('programs.1.temuan', ['Tgl 3 (Malam) — Rapi (Seiton): Rak tools ditata ulang'])
                // PdM: the rencana & realisasi of the checklist.
                ->where('programs.2.sumber', 'PdM')
                ->where('programs.2.saved', true)
                ->where('programs.2.rows.0.rencana', 2)
                ->where('programs.2.rows.0.realisasi', 1)
                ->where('programs.2.rows.0.kinerja', '50%')
                ->where('programs.2.temuan', ['Material bekas dibuang: Drum bekas menumpuk (tindak lanjut: Diangkut ke TPS)'])
                // Logistik: 15 pelaksanaan per item unless a target is set.
                ->where('programs.3.sumber', 'Logistik & Gudang')
                ->where('programs.3.rows.0.rencana', 15)
                ->where('programs.3.rows.0.realisasi', 2)
                ->where('programs.3.rows.0.kinerja', '13%')
                ->where('programs.3.rows.4.rencana', 4)
                ->where('programs.3.rows.4.realisasi', 1)
                ->where('programs.3.temuan', ['Alat rusak dipisahkan: Dua alat rusak dipisah'])
                ->where('summary.filled', 4)
                ->where('summary.divisions', 4),
            );
    }

    public function test_the_rekap_5s5r_marks_divisions_that_did_not_fill_it_in(): void
    {
        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $this->unit))
            ->get(route('k3.input.rekap-5s5r.index', $this->period))
            ->assertInertia(fn ($page) => $page
                ->where('programs.0.saved', false)
                ->where('programs.1.saved', false)
                ->where('programs.2.saved', false)
                ->where('programs.3.saved', false)
                // An unsaved PdM checklist does not count its default rencana.
                ->where('programs.2.total.rencana', 0)
                ->where('summary', ['divisions' => 4, 'filled' => 0, 'rencana' => 0, 'realisasi' => 0, 'kinerja' => '-']));
    }

    public function test_the_rekap_5s5r_is_for_k3_and_its_unit_only(): void
    {
        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $this->unit))
            ->get(route('k3.input.rekap-5s5r.index', $this->period))
            ->assertForbidden();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, Unit::factory()->create(['is_active' => true])))
            ->get(route('k3.input.rekap-5s5r.index', $this->period))
            ->assertForbidden();

        // Manager UL views the K3 laporan, so the rekap too.
        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $this->serviceUnit))
            ->get(route('k3.input.rekap-5s5r.index', $this->period))
            ->assertOk();
    }

    public function test_the_laporan_k3_prints_kondisi_k3_and_every_division_5s5r(): void
    {
        $k3 = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);
        $this->actingAs($k3)->post(route('k3.input.kondisi-k3.store'), $this->period + ['rows' => [
            ['kategori' => 'Unsafe Action', 'temuan' => 'Temuan K3 sendiri', 'keterangan' => 'Open'],
        ]])->assertSessionHasNoErrors();
        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit))
            ->post(route('har.input.program-5s5r.store'), $this->period + ['rows' => [[...HarProgram5s5r::blankRow(1, 'Pagi', 'rawat'), 'progres' => '76-100']]])
            ->assertSessionHasNoErrors();

        $report = app(K3ReportBuilder::class)->monthly($this->unit, 8, 2026);
        $this->assertSame(['K3'], array_column($report['unsafe_conditions'], 'sumber'));
        $this->assertSame(['Open'], array_column($report['unsafe_conditions'], 'keterangan'));
        $this->assertSame(['operasi', 'har', 'pdm', 'logistik'], array_column($report['program_5s5r'], 'key'));

        $this->actingAs($k3)
            ->get(route('k3.laporan.document.edit', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->loadDeferredProps('document', fn ($page) => $page
                ->where('content', function (string $html): bool {
                    $section15 = $this->between($html, 'id="sec-5-15"', 'id="sec-5-16"');
                    $section17 = $this->between($html, 'id="sec-5-17"', 'id="sec-5-18"');

                    return str_contains($section15, 'Temuan K3 sendiri')
                        && str_contains($section17, 'Pelaksanaan 5S5R Seluruh Divisi')
                        && str_contains($section17, 'Rawat (Seiketsu)')
                        && str_contains($section17, 'Belum ada input 5S5R (Laporan Inspeksi Checklist 5S5R PdM)');
                })));
    }

    public function test_k3_is_told_about_new_kondisi_k3_findings(): void
    {
        Carbon::setTestNow('2026-08-20 09:00:00');
        $koordinatorK3 = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);
        $tlK3 = $this->userWithRole(RoleName::TeamLeaderK3, $this->unit);
        $kondisiUrl = route('k3.input.kondisi-k3.index', $this->period, false);

        $this->actingAs($koordinatorK3)->post(route('k3.input.kondisi-k3.store'), $this->period + ['rows' => [
            ['kategori' => 'Unsafe Condition', 'temuan' => 'Grating lepas', 'keterangan' => 'Open'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame($kondisiUrl, $tlK3->notifications()->first()->data['url']);
        Carbon::setTestNow();
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, $from);
        $end = strpos($html, $to, $start);

        return substr($html, $start, $end === false ? null : $end - $start);
    }
}
