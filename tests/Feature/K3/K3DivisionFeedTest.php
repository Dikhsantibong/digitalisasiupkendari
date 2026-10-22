<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\HarUnsafeCondition;
use App\Models\K3KondisiK3;
use App\Models\LogistikFormRow;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\K3\K3ReportBuilder;
use App\Support\HarProgram5s5r;
use App\Support\OperasiProgram5s5r;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The Unsafe Action & Condition and 5S5R inputs of every division land in the
 * K3 module (Kondisi K3, Rekap 5S5R, Laporan K3) while each division keeps
 * its own page working as before.
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

    public function test_operasi_and_pemeliharaan_findings_land_in_kondisi_k3_with_their_division(): void
    {
        Storage::fake('public');
        $this->reportUnsafe('operasi', RoleName::KoordinatorOperasi, ['kategori' => 'UNSAFE ACTION', 'temuan' => 'Operator tidak memakai ear plug', 'lokasi' => 'Ruang mesin', 'keterangan' => 'open'],
            ['foto_sebelum' => UploadedFile::fake()->image('sebelum.jpg')]);
        $this->reportUnsafe('har', RoleName::KoordinatorPemeliharaan, ['kategori' => 'UNSAFE CONDITION', 'temuan' => 'Kabel terkelupas di panel', 'lokasi' => 'Ruang panel', 'keterangan' => 'close']);

        $this->assertSame(['operasi', 'har'], HarUnsafeCondition::query()->orderBy('id')->pluck('source')->all());

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $this->unit))
            ->get(route('k3.input.kondisi-k3.index', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/input/kondisi-k3/index')
                ->has('division_findings', 2)
                ->where('division_findings.0.sumber', 'Operasi')
                ->where('division_findings.0.kategori', 'Unsafe Action')
                ->where('division_findings.0.temuan', 'Operator tidak memakai ear plug')
                ->where('division_findings.0.lokasi', 'Ruang mesin')
                ->where('division_findings.0.status', 'Open')
                ->where('division_findings.0.foto_sebelum', fn (?string $url): bool => $url !== null && str_contains($url, 'har-unsafe/'))
                ->where('division_findings.0.foto_sesudah', null)
                ->where('division_findings.1.sumber', 'Pemeliharaan')
                ->where('division_findings.1.kategori', 'Unsafe Condition')
                ->where('division_findings.1.status', 'Close')
                ->where('division_summary', ['total' => 2, 'unsafe_action' => 1, 'unsafe_condition' => 1, 'open' => 1, 'closed' => 1])
                // The K3 own (editable) table is untouched: still the empty default form.
                ->where('has_saved', false),
            );

        // The shared Operasi & Pemeliharaan list works as before: both pages show both findings.
        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $this->unit))
            ->get(route('operasi.input.unsafe-condition.index', $this->period))
            ->assertInertia(fn ($page) => $page->has('rows', 2));
        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit))
            ->get(route('har.input.unsafe-condition.index', $this->period))
            ->assertInertia(fn ($page) => $page->has('rows', 2));
    }

    public function test_logistik_temuan_land_in_kondisi_k3_but_blank_rows_do_not(): void
    {
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $this->unit))
            ->post(route('logistik.input.form.store', ['form' => 'unsafe']), $this->period + ['rows' => [
                ['section' => 'temuan', 'data' => ['periode' => 'MINGGU KE - 1', 'kategori' => 'UNSAFE ACTION', 'temuan' => 'Forklift tanpa sabuk pengaman', 'lokasi' => 'Gudang 2', 'tindak_lanjut' => 'Teguran', 'keterangan' => 'Close']],
                ['section' => 'temuan', 'data' => ['periode' => 'MINGGU KE - 2', 'kategori' => 'UNSAFE CONDITION', 'temuan' => 'Rak miring', 'keterangan' => 'menunggu material']],
                // A default row only holding the preset periode / kategori is not a temuan.
                ['section' => 'temuan', 'data' => ['periode' => 'MINGGU KE - 3', 'kategori' => 'UNSAFE CONDITION']],
            ]])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, LogistikFormRow::query()->where('form', 'unsafe')->count());

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $this->unit))
            ->get(route('k3.input.kondisi-k3.index', $this->period))
            ->assertInertia(fn ($page) => $page
                ->has('division_findings', 2)
                ->where('division_findings.0.sumber', 'Logistik & Gudang')
                ->where('division_findings.0.sumber_key', 'logistik')
                ->where('division_findings.0.periode', 'MINGGU KE - 1')
                ->where('division_findings.0.kategori', 'Unsafe Action')
                ->where('division_findings.0.tindak_lanjut', 'Teguran')
                ->where('division_findings.0.status', 'Close')
                // A free-text keterangan is kept as is, without a guessed status.
                ->where('division_findings.1.status', null)
                ->where('division_findings.1.keterangan', 'menunggu material')
                ->where('division_summary.unsafe_action', 1)
                ->where('division_summary.unsafe_condition', 1),
            );
    }

    public function test_saving_kondisi_k3_keeps_the_division_findings(): void
    {
        $this->reportUnsafe('operasi', RoleName::KoordinatorOperasi, ['temuan' => 'Tumpahan oli di lantai']);
        LogistikFormRow::factory()->create([...$this->period, 'form' => 'unsafe', 'section' => 'temuan', 'data' => ['kategori' => 'UNSAFE CONDITION', 'temuan' => 'Lampu gudang mati']]);
        $k3 = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);

        // K3 replaces its own rows on every save; the division rows live elsewhere.
        foreach (['Helm tidak dipakai', 'Kotak P3K kosong'] as $temuan) {
            $this->actingAs($k3)->post(route('k3.input.kondisi-k3.store'), $this->period + ['rows' => [
                ['kategori' => 'Unsafe Action', 'temuan' => $temuan, 'keterangan' => 'Open'],
            ]])->assertSessionHasNoErrors();
        }

        $this->assertSame(['Kotak P3K kosong'], K3KondisiK3::query()->pluck('temuan')->all());
        $this->assertSame(1, HarUnsafeCondition::query()->count());
        $this->assertSame(1, LogistikFormRow::query()->where('form', 'unsafe')->count());

        $this->actingAs($k3)->get(route('k3.input.kondisi-k3.index', $this->period))
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->has('rows', 1)
                ->where('rows.0.temuan', 'Kotak P3K kosong')
                ->has('division_findings', 2)
                ->where('division_findings.0.temuan', 'Tumpahan oli di lantai')
                ->where('division_findings.1.temuan', 'Lampu gudang mati'));
    }

    public function test_findings_of_another_unit_or_month_and_old_rows_are_handled(): void
    {
        $otherUnit = Unit::factory()->create(['is_active' => true]);
        $base = ['periode' => 'Minggu 1', 'kategori' => 'UNSAFE CONDITION', 'keterangan' => 'open'];
        HarUnsafeCondition::query()->create([...$base, ...$this->period, 'temuan' => 'Tersimpan sebelum kolom sumber']);
        HarUnsafeCondition::query()->create([...$base, ...$this->period, 'month' => 7, 'source' => 'operasi', 'temuan' => 'Bulan lalu']);
        HarUnsafeCondition::query()->create([...$base, ...$this->period, 'unit_id' => $otherUnit->id, 'source' => 'har', 'temuan' => 'Unit lain']);
        LogistikFormRow::factory()->create([...$this->period, 'unit_id' => $otherUnit->id, 'form' => 'unsafe', 'section' => 'temuan', 'data' => ['kategori' => 'UNSAFE ACTION', 'temuan' => 'Logistik unit lain']]);
        // Another Logistik form of the same period is not a finding.
        LogistikFormRow::factory()->create([...$this->period, 'form' => 'pendukung', 'section' => 'pendukung', 'data' => ['temuan' => 'Bukan unsafe']]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $this->unit))
            ->get(route('k3.input.kondisi-k3.index', $this->period))
            ->assertInertia(fn ($page) => $page
                ->has('division_findings', 1)
                ->where('division_findings.0.temuan', 'Tersimpan sebelum kolom sumber')
                ->where('division_findings.0.sumber', 'Operasi / Pemeliharaan')
                ->where('division_findings.0.sumber_key', 'operasi-har'));
    }

    public function test_a_link_to_the_source_page_is_given_only_to_who_may_open_it(): void
    {
        $this->reportUnsafe('operasi', RoleName::KoordinatorOperasi, ['temuan' => 'Temuan operasi']);
        $this->reportUnsafe('har', RoleName::KoordinatorPemeliharaan, ['temuan' => 'Temuan pemeliharaan']);
        LogistikFormRow::factory()->create([...$this->period, 'form' => 'unsafe', 'section' => 'temuan', 'data' => ['kategori' => 'UNSAFE ACTION', 'temuan' => 'Temuan logistik']]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $this->unit))
            ->get(route('k3.input.kondisi-k3.index', $this->period))
            ->assertInertia(fn ($page) => $page
                ->where('division_findings.0.url', null)
                ->where('division_findings.1.url', null)
                ->where('division_findings.2.url', null));

        $k3AndOperasi = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);
        $k3AndOperasi->assignRole(RoleName::KoordinatorOperasi, $this->unit);

        $this->actingAs($k3AndOperasi->fresh())
            ->get(route('k3.input.kondisi-k3.index', $this->period))
            ->assertInertia(fn ($page) => $page
                ->where('division_findings.0.url', route('operasi.input.unsafe-condition.index', $this->period, false))
                ->where('division_findings.1.url', null)
                ->where('division_findings.2.url', null));
    }

    public function test_the_rekap_5s5r_collects_the_5s5r_input_of_every_division(): void
    {
        $operasi = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $this->actingAs($operasi)->post(route('operasi.input.program-5s5r.store'), $this->period + ['rows' => [
            [...OperasiProgram5s5r::blankRow(1, 'ringkas'), 'kondisi_awal' => 'Kurang Baik', 'progres' => '51-75', 'kondisi_akhir' => 'Baik'],
            [...OperasiProgram5s5r::blankRow(2, 'ringkas'), 'kondisi_awal' => 'Baik', 'progres' => '76-100', 'kondisi_akhir' => 'Baik'],
            [...OperasiProgram5s5r::blankRow(1, 'resik'), 'keterangan' => 'Lantai genset dibersihkan'],
        ]])->assertSessionHasNoErrors();

        $har = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $this->actingAs($har)->post(route('har.input.program-5s5r.store'), $this->period + ['rows' => [
            [...HarProgram5s5r::blankRow(3, 'rapi'), 'progres' => '26-50', 'keterangan' => 'Rak tools ditata ulang'],
        ]])->assertSessionHasNoErrors();

        $pdm = $this->userWithRole(RoleName::TeamLeaderPdm, $this->unit);
        $this->actingAs($pdm)->post(route('pdm.input.forms.store', ['form' => 'checklist-5s5r']), $this->period + [
            'header' => ['nama_pembangkit' => $this->unit->name],
            'rows' => ['ringkas' => [
                ['item' => 'Alat rusak dipisahkan', 'rencana' => '1', 'realisasi' => '1'],
                ['item' => 'Material bekas dibuang', 'rencana' => '1', 'realisasi' => null, 'temuan' => 'Drum bekas menumpuk', 'tindak_lanjut' => 'Diangkut ke TPS'],
            ]],
        ])->assertSessionHasNoErrors();

        $logistik = $this->userWithRole(RoleName::TeamLeaderLogistik, $this->unit);
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
                // Operasi: August 2026 has 5 weeks; Ringkas filled in 2 of them.
                ->where('programs.0.sumber', 'Operasi')
                ->where('programs.0.saved', true)
                ->where('programs.0.rows.0.program', 'Ringkas (Seiri)')
                ->where('programs.0.rows.0.rencana', 5)
                ->where('programs.0.rows.0.realisasi', 2)
                ->where('programs.0.rows.0.kinerja', '40%')
                ->where('programs.0.rows.0.catatan', 'Minggu 2 · kondisi akhir Baik · progres 76-100%')
                ->where('programs.0.rows.1.realisasi', 0)
                ->where('programs.0.rows.2.realisasi', 1)
                ->where('programs.0.total', ['rencana' => 25, 'realisasi' => 3, 'kinerja' => '12%'])
                ->where('programs.0.temuan', ['Minggu 1 — Resik (Seiso): Lantai genset dibersihkan'])
                // Pemeliharaan.
                ->where('programs.1.sumber', 'Pemeliharaan')
                ->where('programs.1.rows.1.realisasi', 1)
                ->where('programs.1.temuan', ['Minggu 3 — Rapi (Seiton): Rak tools ditata ulang'])
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

    public function test_the_laporan_k3_prints_every_division_finding_and_5s5r(): void
    {
        $k3 = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);
        $this->actingAs($k3)->post(route('k3.input.kondisi-k3.store'), $this->period + ['rows' => [
            ['kategori' => 'Unsafe Action', 'temuan' => 'Temuan K3 sendiri', 'keterangan' => 'Open'],
        ]])->assertSessionHasNoErrors();
        $this->reportUnsafe('operasi', RoleName::KoordinatorOperasi, ['temuan' => 'Temuan dari operasi', 'keterangan' => 'close']);
        LogistikFormRow::factory()->create([...$this->period, 'form' => 'unsafe', 'section' => 'temuan', 'data' => ['kategori' => 'UNSAFE CONDITION', 'temuan' => 'Temuan dari gudang']]);
        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit))
            ->post(route('har.input.program-5s5r.store'), $this->period + ['rows' => [[...HarProgram5s5r::blankRow(1, 'rawat'), 'progres' => '76-100']]])
            ->assertSessionHasNoErrors();

        $report = app(K3ReportBuilder::class)->monthly($this->unit, 8, 2026);
        $this->assertSame(['K3', 'Operasi', 'Logistik & Gudang'], array_column($report['unsafe_conditions'], 'sumber'));
        $this->assertSame(['Open', 'Close', null], array_column($report['unsafe_conditions'], 'keterangan'));
        $this->assertSame(['operasi', 'har', 'pdm', 'logistik'], array_column($report['program_5s5r'], 'key'));

        $this->actingAs($k3)
            ->get(route('k3.laporan.document.edit', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->loadDeferredProps('document', fn ($page) => $page
                ->where('content', function (string $html): bool {
                    $section15 = $this->between($html, 'id="sec-5-15"', 'id="sec-5-16"');
                    $section17 = $this->between($html, 'id="sec-5-17"', 'id="sec-5-18"');

                    return str_contains($section15, 'Temuan K3 sendiri')
                        && str_contains($section15, 'Temuan dari operasi')
                        && str_contains($section15, 'Temuan dari gudang')
                        && str_contains($section15, 'Logistik &amp; Gudang')
                        && str_contains($section17, 'Pelaksanaan 5S5R Seluruh Divisi')
                        && str_contains($section17, 'Rawat (Seiketsu)')
                        && str_contains($section17, 'Belum ada input 5S5R (Laporan Inspeksi Checklist 5S5R PdM)');
                })));
    }

    public function test_k3_is_told_about_new_findings_with_a_link_to_kondisi_k3(): void
    {
        $koordinatorK3 = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);
        $tlHar = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $this->unit);
        $kondisiUrl = route('k3.input.kondisi-k3.index', $this->period, false);

        $this->reportUnsafe('har', RoleName::KoordinatorPemeliharaan, ['temuan' => 'Grating lepas']);

        $this->assertSame($kondisiUrl, $koordinatorK3->notifications()->first()->data['url'], 'K3 cannot open the HAR page: it is sent to Kondisi K3.');
        $this->assertSame(route('har.input.unsafe-condition.index', $this->period, false), $tlHar->notifications()->first()->data['url']);
    }

    public function test_new_logistik_temuan_reach_k3_once(): void
    {
        $tlLogistik = $this->userWithRole(RoleName::TeamLeaderLogistik, $this->unit);
        $koordinatorK3 = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);
        $tlK3 = $this->userWithRole(RoleName::TeamLeaderK3, $this->unit);
        $koordinatorOperasi = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $first = ['section' => 'temuan', 'data' => ['periode' => 'MINGGU KE - 1', 'kategori' => 'UNSAFE ACTION', 'temuan' => 'Tidak memakai sarung tangan', 'lokasi' => 'Gudang']];
        $save = fn (array $rows) => $this->actingAs($tlLogistik)
            ->post(route('logistik.input.form.store', ['form' => 'unsafe']), $this->period + ['rows' => $rows])
            ->assertSessionHasNoErrors();

        $save([$first]);

        $this->assertSame(['Temuan unsafe baru · PLTD Uji (Logistik & Gudang)'], $this->titles($koordinatorK3));
        $this->assertSame(['Temuan unsafe baru · PLTD Uji (Logistik & Gudang)'], $this->titles($tlK3));
        $this->assertStringContainsString('Tidak memakai sarung tangan', $koordinatorK3->notifications()->first()->data['body']);
        $this->assertSame(route('k3.input.kondisi-k3.index', $this->period, false), $koordinatorK3->notifications()->first()->data['url']);
        $this->assertSame([], $this->titles($tlLogistik), 'The one who saved it is not notified.');
        $this->assertSame([], $this->titles($koordinatorOperasi));

        // Saving the same temuan again sends nothing; one more temuan sends one more notice.
        $save([$first]);
        $this->assertCount(1, $this->titles($koordinatorK3));

        $save([$first, ['section' => 'temuan', 'data' => ['periode' => 'MINGGU KE - 2', 'kategori' => 'UNSAFE CONDITION', 'temuan' => 'Atap bocor']]]);
        $this->assertCount(2, $this->titles($koordinatorK3));
        $this->assertContains('Atap bocor', $koordinatorK3->notifications()->get()->pluck('data.body')->all(), 'Only the new temuan is announced.');

        // Other Logistik forms never notify about unsafe findings.
        $this->actingAs($tlLogistik)->post(route('logistik.input.form.store', ['form' => 'kondisi-stok']), $this->period + ['rows' => []])->assertSessionHasNoErrors();
        $this->assertCount(2, $this->titles($koordinatorK3));
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, UploadedFile>  $files
     */
    private function reportUnsafe(string $module, RoleName $role, array $values, array $files = []): void
    {
        $this->actingAs($this->userWithRole($role, $this->unit))
            ->post(route("{$module}.input.unsafe-condition.store"), [...$this->period, 'periode' => 'Minggu 1', 'kategori' => 'UNSAFE CONDITION', 'keterangan' => 'open', ...$values, ...$files])
            ->assertSessionHasNoErrors();
    }

    /**
     * @return list<string>
     */
    private function titles(User $user): array
    {
        return $user->notifications()->get()->pluck('data.title')->all();
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $this->assertNotFalse($start, $from);
        $end = strpos($html, $to, $start);

        return substr($html, $start, $end === false ? null : $end - $start);
    }
}
