<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3ActivityPlan;
use App\Models\K3ActivityType;
use App\Models\K3DocumentRecord;
use App\Models\K3FormulirRecord;
use App\Models\K3HydrantInspection;
use App\Models\K3MetodePengujianPeralatan;
use App\Models\K3MetodePengujianPeralatanMeta;
use App\Models\K3PatrolCheckJadwal;
use App\Models\K3RambuInspection;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Services\K3\K3DocumentBuilder;
use App\Services\K3\K3DocumentGridBuilder;
use App\Services\K3\K3InputTables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class LaporanDocumentTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    /** Formulir points (see k3/formulir) print portrait; every other table is landscape. */
    private const FORMULIR_SECTIONS = ['sec-5-13', 'sec-5-14', 'sec-5-18', 'sec-5-20', 'sec-5-23'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_laporan_permission_cannot_open_the_document(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('k3.laporan.document.edit'))
            ->assertForbidden();
    }

    public function test_tl_sees_the_generated_document(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->get(route('k3.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/laporan/document')
                ->where('document_number', 'SMT-FM-AK3-00')
                ->where('format', 'html')
                ->where('has_saved', false)
                ->where('can_write', true)
                ->has('content')
                ->has('grid'),
            );
    }

    public function test_tl_can_save_the_document(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $this->actingAs($user)->post(route('k3.laporan.document.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'format' => 'html', 'content_html' => '<p>Laporan K3 diedit</p>',
        ])->assertRedirect();

        $record = K3DocumentRecord::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame('html', $record->format);
        $this->assertStringContainsString('Laporan K3 diedit', (string) $record->content_html);
        $this->assertSame('SMT-FM-AK3-00', $record->document_number);
    }

    public function test_manager_ul_can_view_but_not_save(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->create(['service_unit_id' => $serviceUnit->id]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('k3.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));

        $this->actingAs($manager)->post(route('k3.laporan.document.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'format' => 'html', 'content_html' => '<p>x</p>',
        ])->assertForbidden();

        $this->assertDatabaseCount('k3_document_records', 0);
    }

    public function test_the_document_exports_to_pdf(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->get(route('k3.laporan.document.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_a_document_cannot_be_opened_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $ownUnit))
            ->get(route('k3.laporan.document.edit', ['unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026]))
            ->assertForbidden();
    }

    public function test_the_document_follows_the_daftar_isi_order(): void
    {
        $html = $this->bodyHtml(Unit::factory()->create());

        $ids = ['sec-1', 'sec-2', 'sec-3', 'sec-4'];
        for ($point = 1; $point <= 31; $point++) {
            $ids[] = 'sec-5-'.$point;
        }
        array_push($ids, 'sec-6', 'sec-7');

        $positions = array_map(fn (string $id): int|false => strpos($html, 'id="'.$id.'"'), $ids);

        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    public function test_formulir_points_are_portrait_and_other_tables_landscape(): void
    {
        $html = $this->bodyHtml(Unit::factory()->create());

        for ($point = 1; $point <= 31; $point++) {
            $id = 'sec-5-'.$point;
            $expected = in_array($id, self::FORMULIR_SECTIONS, true)
                ? '<div class="k3-section" id="'.$id.'">'
                : '<div class="k3-section k3-landscape" id="'.$id.'">';

            $this->assertStringContainsString($expected, $html, $id);
        }
    }

    public function test_a_point_without_data_shows_a_red_line_instead_of_a_table(): void
    {
        $section = $this->section($this->bodyHtml(Unit::factory()->create()), 'sec-5-1');

        $this->assertStringContainsString('k3-red-line', $section);
        $this->assertStringNotContainsString('wide-table', $section);
    }

    public function test_a_point_with_data_shows_its_table(): void
    {
        $unit = Unit::factory()->create();
        K3ActivityPlan::query()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 8,
            'k3_activity_type_id' => K3ActivityType::factory()->create()->id,
            'pic' => 'Budi', 'plan_days' => ['3' => '1', '10' => '1'], 'real_days' => ['3' => '1'],
        ]);

        $section = $this->section($this->bodyHtml($unit), 'sec-5-1');

        $this->assertStringNotContainsString('k3-red-line', $section);
        $this->assertStringContainsString('<table class="k3x-table k3x-dense">', $section);
        $this->assertStringContainsString('Budi', $section);

        // Same rows as the Time Frame export: R on days 3 & 10, Rl on day 3.
        $table = app(K3InputTables::class)->tables('time-frame', $unit, 8, 2026)[0];
        [$planned, $realised] = array_map(fn (array $row): array => $row['cells'], $table['rows']);
        $this->assertSame('R', $planned[3 + 3]);
        $this->assertSame('R', $planned[3 + 10]);
        $this->assertSame('Rl', $realised[3 + 3]);
        $this->assertSame('', $realised[3 + 10]);
    }

    public function test_points_derived_from_inputs_are_filled(): void
    {
        $unit = Unit::factory()->create();
        $period = ['unit_id' => $unit->id, 'year' => 2026, 'month' => 8];
        K3ActivityPlan::query()->create($period + [
            'k3_activity_type_id' => K3ActivityType::factory()->create(['name' => 'Inspeksi APAR'])->id,
            'pic' => 'K3L', 'plan_days' => ['3' => '1', '10' => '1'], 'real_days' => ['3' => '1'],
        ]);
        K3HydrantInspection::query()->create($period + ['lokasi' => 'Hydrant Pilar A', 'hose' => 'Baik', 'nozzle' => 'Baik', 'box' => 'Baik', 'tekanan' => '7']);
        K3HydrantInspection::query()->create($period + ['lokasi' => 'Hydrant Box B', 'hose' => 'Bocor', 'nozzle' => 'Baik', 'box' => 'Baik', 'tekanan' => '5']);
        K3PatrolCheckJadwal::query()->create($period + ['no_urut' => 1, 'uraian' => 'Patrol area mesin', 'bobot_sla' => 5, 'rencana' => [1, 2, 3, 4], 'realisasi' => [1, 2, 3]]);
        K3RambuInspection::query()->create($period + ['rambu' => 'Wajib APD', 'lokasi' => 'Gerbang', 'kondisi' => 'Baik']);

        $html = $this->bodyHtml($unit);

        // 27. Program Kerja K3 from the Time Frame: 1 of 2 realised.
        $programKerja = $this->section($html, 'sec-5-27');
        $this->assertStringNotContainsString('k3-red-line', $programKerja);
        $this->assertStringContainsString('Inspeksi APAR', $programKerja);
        $this->assertStringContainsString('50%', $programKerja);

        // 25./26. Hydrant monitoring & monthly recap classify each point.
        $this->assertStringContainsString('Perlu Perbaikan', $this->section($html, 'sec-5-25'));
        $recap = $this->section($html, 'sec-5-26');
        $this->assertStringNotContainsString('k3-red-line', $recap);
        $this->assertStringContainsString('Persentase kondisi baik', $recap);

        // 6. & 30. Patrol check jadwal: daily matrix and 75% kinerja.
        $this->assertStringContainsString('Patrol area mesin', $this->section($html, 'sec-5-6'));
        $this->assertStringContainsString('75%', $this->section($html, 'sec-5-30'));

        // 11. Rambu uses the same table as the rambu export.
        $rambu = $this->section($html, 'sec-5-11');
        $this->assertStringContainsString('Wajib APD', $rambu);
        $this->assertStringNotContainsString('k3-red-line', $rambu);

        // Points without any input nor related jadwal stay red.
        $this->assertStringContainsString('k3-red-line', $this->section($html, 'sec-5-7'));
    }

    public function test_points_without_their_own_input_are_filled_from_the_jadwal(): void
    {
        $html = $this->bodyHtml(Unit::factory()->create());

        // Nothing saved: the jadwal defaults (as their pages show them) fill the formulir points.
        $oilTrap = $this->section($html, 'sec-5-13');
        $this->assertStringNotContainsString('k3-red-line', $oilTrap);
        $this->assertStringContainsString('CEK DAN BERSIHKAN OIL TRAP', $oilTrap);
        $this->assertStringContainsString('Jadwal Patrol Check', $oilTrap);

        $this->assertStringContainsString('Daily Meeting / Safety Briefing', $this->section($html, 'sec-5-5'));
        $this->assertStringContainsString('Pemeriksaan APD Personil', $this->section($html, 'sec-5-16'));
        $this->assertStringContainsString('Laporan checklist patrol chek K3L KIT', $this->section($html, 'sec-5-23'));

        // Hydrant without inspections falls back to the related jadwal rows, with a note.
        $hydrant = $this->section($html, 'sec-5-8');
        $this->assertStringContainsString('ditampilkan kegiatan terkait dari Jadwal K3', $hydrant);
        $this->assertStringContainsString('PILAR DAN PANEL PENYIMPANAN SELANG HIDRANT', $hydrant);

        // APD inventory shows the default list the page pre-fills.
        $this->assertStringContainsString('Helm safety putih', $this->section($html, 'sec-5-22'));
    }

    public function test_saved_formulir_fill_their_report_points(): void
    {
        $unit = Unit::factory()->create();
        $period = ['unit_id' => $unit->id, 'year' => 2026, 'month' => 8];
        $sheet = fn (string $form, array $sections, int $week = 0, ?string $catatan = null): K3FormulirRecord => K3FormulirRecord::factory()->create(
            $period + ['form' => $form, 'week' => $week, 'data' => ['sections' => $sections, 'header' => []], 'catatan' => $catatan],
        );
        $sheet('pemeliharaan-oil-trap', ['sarana' => [['item' => 'Kolam Uji Oil Trap', 'tanggal_inspeksi' => '2026-08-31', 'kondisi' => 'Baik']]]);
        $sheet('pemeliharaan-tps-lb3', ['sarana' => [['item' => 'Atap Gedung TPS Uji', 'kondisi' => 'Rusak', 'rencana_perbaikan' => 'Ganti seng']]]);
        $sheet('sarana-prasarana', ['administrasi' => [['item' => 'Kartu Tanda Anggota Uji', 'satuan' => 'Lembar', 'ada' => '5']]]);
        $sheet('kontrol-mingguan', ['kontrol_apd' => [['item' => 'Helm Minggu Satu']]], 1, 'Catatan minggu pertama');
        $sheet('kontrol-mingguan', ['kontrol_apd' => [['item' => 'Helm Minggu Dua']]], 2);
        K3MetodePengujianPeralatan::query()->create($period + ['no_urut' => '1', 'nama_peralatan' => 'Bejana Tekan Uji', 'uji_hydro' => 'Memenuhi']);
        K3MetodePengujianPeralatanMeta::query()->create($period + ['catatan' => 'Rekomendasi uji ulang']);

        $builder = app(K3DocumentBuilder::class);
        $data = $builder->build($unit, 8, 2026);
        $html = $builder->bodyHtml($data);

        $oilTrap = $this->section($html, 'sec-5-13');
        $this->assertStringContainsString('Kolam Uji Oil Trap', $oilTrap);
        $this->assertStringContainsString('31 Agustus 2026', $oilTrap);
        $this->assertStringNotContainsString('CEK DAN BERSIHKAN OIL TRAP', $oilTrap);

        $tps = $this->section($html, 'sec-5-14');
        $this->assertStringContainsString('Atap Gedung TPS Uji', $tps);
        $this->assertStringContainsString('Ganti seng', $tps);

        $metode = $this->section($html, 'sec-5-18');
        $this->assertStringContainsString('Bejana Tekan Uji', $metode);
        $this->assertStringContainsString('Rekomendasi uji ulang', $metode);
        $this->assertStringContainsString('<div class="k3-section k3-landscape" id="sec-5-18">', $html);

        $sarpras = $this->section($html, 'sec-5-20');
        $this->assertStringContainsString('Kartu Tanda Anggota Uji', $sarpras);
        $this->assertStringContainsString('ADMINISTRASI', $sarpras);

        $mingguan = $this->section($html, 'sec-5-23');
        $this->assertStringContainsString('Minggu ke-1 Agustus 2026', $mingguan);
        $this->assertStringContainsString('Helm Minggu Dua', $mingguan);
        $this->assertStringContainsString('Catatan minggu pertama', $mingguan);
        $this->assertStringContainsString('<div class="k3-section k3-landscape" id="sec-5-23">', $html);

        // The Excel (grid) mode carries the same formulir rows.
        $cells = collect(app(K3DocumentGridBuilder::class)->build($data)['rows'])->flatten(1)->pluck('t')->implode('|');
        foreach (['Kolam Uji Oil Trap', 'Atap Gedung TPS Uji', 'Bejana Tekan Uji', 'Kartu Tanda Anggota Uji', 'Helm Minggu Dua'] as $text) {
            $this->assertStringContainsString($text, $cells);
        }
    }

    public function test_formulir_of_another_period_do_not_leak_into_the_report(): void
    {
        $unit = Unit::factory()->create();
        K3FormulirRecord::factory()->create([
            'unit_id' => $unit->id, 'year' => 2026, 'month' => 7, 'form' => 'pemeliharaan-oil-trap',
            'data' => ['sections' => ['sarana' => [['item' => 'Kolam Bulan Juli']]], 'header' => []],
        ]);

        $oilTrap = $this->section($this->bodyHtml($unit), 'sec-5-13');

        $this->assertStringNotContainsString('Kolam Bulan Juli', $oilTrap);
        $this->assertStringContainsString('CEK DAN BERSIHKAN OIL TRAP', $oilTrap);
    }

    public function test_the_pdf_merges_portrait_and_landscape_pages_in_daftar_isi_order(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->get(route('k3.laporan.document.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk();

        $reader = new Fpdi;
        $count = $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $orientations = '';
        for ($page = 1; $page <= $count; $page++) {
            $orientations .= $reader->getTemplateSize($reader->importPage($page))['orientation'];
        }

        // I–IV portrait; V. points 1–12 L, 13–14 P, 15–17 L, 18 P, 19 L, 20 P, 21–22 L, 23 P, 24–31 L; VI L; VII P
        // (a long table may span several pages of its own orientation).
        $this->assertStringStartsWith('PPPP', $orientations);
        $this->assertSame('PLPLPLPLPLP', preg_replace('/(.)\1+/', '$1', $orientations));
    }

    private function bodyHtml(Unit $unit): string
    {
        $builder = app(K3DocumentBuilder::class);

        return $builder->bodyHtml($builder->build($unit, 8, 2026));
    }

    /**
     * The markup of one report section, up to the next one.
     */
    private function section(string $html, string $id): string
    {
        $start = strpos($html, 'id="'.$id.'"');
        $this->assertNotFalse($start, $id);
        $end = strpos($html, 'class="k3-section', $start);

        return substr($html, $start, $end === false ? null : $end - $start);
    }
}
