<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Http\Controllers\Operasi\KondisiAbnormalController;
use App\Models\KondisiAbnormal;
use App\Models\Unit;
use App\Models\User;
use App\Services\Operasi\OperasiReportSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Laporan Kondisi Abnormal & Gangguan: one report per kejadian with its
 * tanggal (several a day), the month's rekap, edit / delete rights, the PDF
 * and the Laporan Operasi.
 */
class KondisiAbnormalInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    /** @var array{unit_id: int, month: int, year: int} */
    private array $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutDefer();
        $this->seedAccessControl();
        $this->unit = Unit::factory()->create(['is_active' => true]);
        $this->period = ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026];
    }

    public function test_the_page_starts_empty_without_blank_rows(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, $this->unit))
            ->get(route('operasi.input.kondisi-abnormal.index', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/input/kondisi-abnormal/index')
                ->has('entries', 0)
                ->has('rekap_harian', 0)
                ->where('can_write', true)
                ->where('options.jenis', ['abnormal' => 'Abnormal', 'gangguan' => 'Gangguan'])
                ->where('options.tindak_pendek', ['tl_pendek_sr' => 'SR', 'tl_pendek_flm' => 'FLM', 'tl_pendek_lainnya' => 'Lainnya']));
    }

    public function test_the_operator_reports_several_kejadian_on_one_day_and_the_page_shows_the_rekap(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);

        $this->report($operator, [
            'tanggal' => '2026-08-12', 'jenis' => 'gangguan', 'durasi' => 1.5, 'uraian_kondisi' => 'Mesin #2 trip',
            'parameter_terkait' => 'Tekanan LO 1,8 bar', 'tl_pendek_sr' => true, 'tl_pendek_flm' => true,
            'tl_pendek_keterangan' => 'SR-1029', 'tl_panjang' => 'Ganti pompa LO', 'dampak_sistem' => 'Pemadaman 1 feeder', 'dampak_pembangkit' => 'Derating 500 kW',
        ]);
        $this->report($operator, ['tanggal' => '2026-08-12', 'jenis' => 'gangguan', 'durasi' => 0.5, 'uraian_kondisi' => 'Mesin #2 trip lagi']);
        $this->report($operator, ['tanggal' => '2026-08-05', 'jenis' => 'abnormal', 'durasi' => 2, 'uraian_kondisi' => 'Exhaust tinggi', 'tl_pendek_lainnya' => true]);

        $records = KondisiAbnormal::query()->orderBy('id')->get();
        $this->assertCount(3, $records);
        $this->assertSame([1, 2, 3], $records->pluck('no_urut')->all());
        $this->assertSame([1, 1, 0], $records->pluck('is_gangguan')->map(fn ($v): int => (int) $v)->all());
        $this->assertEquals(1.5, (float) $records[0]->durasi_gangguan);
        $this->assertEquals(0, (float) $records[0]->durasi_abnormal);
        $this->assertTrue($records[0]->tl_pendek_sr);
        $this->assertSame('Derating 500 kW', $records[0]->dampak_pembangkit);
        $this->assertSame($operator->id, $records[0]->input_by);

        $this->actingAs($operator)->get(route('operasi.input.kondisi-abnormal.index', $this->period))
            ->assertInertia(fn ($page) => $page
                // In date order, numbered for the rekap.
                ->has('entries', 3)
                ->where('entries.0.tanggal', '2026-08-05')
                ->where('entries.0.jenis', 'abnormal')
                ->where('entries.1.uraian_kondisi', 'Mesin #2 trip')
                ->where('entries.1.no', 2)
                ->where('entries.1.can_edit', true)
                ->where('entries.1.input_by', $operator->name)
                ->where('summary.total_abnormal_count', 1)
                ->where('summary.total_gangguan_count', 2)
                ->where('summary.total_gangguan_durasi', 2)
                ->where('summary.tindak_pendek', ['tl_pendek_sr' => 1, 'tl_pendek_flm' => 1, 'tl_pendek_lainnya' => 1])
                ->where('rekap_harian', [
                    ['tanggal' => '2026-08-05', 'abnormal' => 1, 'durasi_abnormal' => 2, 'gangguan' => 0, 'durasi_gangguan' => 0],
                    ['tanggal' => '2026-08-12', 'abnormal' => 0, 'durasi_abnormal' => 0, 'gangguan' => 2, 'durasi_gangguan' => 2],
                ]));
    }

    public function test_a_report_is_edited_moved_to_another_month_and_deleted(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $this->report($operator, ['tanggal' => '2026-08-12', 'jenis' => 'gangguan', 'durasi' => 1, 'uraian_kondisi' => 'Trip']);
        $record = KondisiAbnormal::query()->sole();

        $this->actingAs($operator)->put(route('operasi.input.kondisi-abnormal.update', $record), [
            'unit_id' => $this->unit->id, 'tanggal' => '2026-09-01', 'jenis' => 'abnormal', 'durasi' => 3, 'uraian_kondisi' => 'Ternyata abnormal',
        ])->assertRedirect(route('operasi.input.kondisi-abnormal.index', ['unit_id' => $this->unit->id, 'month' => 9, 'year' => 2026]));

        $record->refresh();
        $this->assertSame([9, 2026, 1, 1, 0], [$record->month, $record->year, (int) $record->is_abnormal, $record->no_urut, (int) $record->is_gangguan]);
        $this->assertEquals(3, (float) $record->durasi_abnormal);

        $this->actingAs($operator)->delete(route('operasi.input.kondisi-abnormal.destroy', $record))->assertRedirect();
        $this->assertSame(0, KondisiAbnormal::query()->count());
    }

    public function test_an_operator_edits_only_their_own_reports_the_koordinator_every_report(): void
    {
        $author = $this->userWithRole(RoleName::Operator, $this->unit);
        $colleague = $this->userWithRole(RoleName::Operator, $this->unit);
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $this->report($author, ['tanggal' => '2026-08-12', 'jenis' => 'gangguan', 'uraian_kondisi' => 'Trip']);
        $record = KondisiAbnormal::query()->sole();
        $payload = ['unit_id' => $this->unit->id, 'tanggal' => '2026-08-12', 'jenis' => 'gangguan', 'uraian_kondisi' => 'Diubah'];

        $this->actingAs($colleague)->put(route('operasi.input.kondisi-abnormal.update', $record), $payload)->assertForbidden();
        $this->actingAs($colleague)->delete(route('operasi.input.kondisi-abnormal.destroy', $record))->assertForbidden();
        $this->actingAs($colleague)->get(route('operasi.input.kondisi-abnormal.index', $this->period))->assertInertia(fn ($page) => $page->where('entries.0.can_edit', false));

        $this->actingAs($koordinator)->put(route('operasi.input.kondisi-abnormal.update', $record), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Diubah', $record->fresh()->uraian_kondisi);

        // Another unit's operator, and a view-only role, cannot add to this unit.
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->post(route('operasi.input.kondisi-abnormal.store'), $payload)->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit))
            ->post(route('operasi.input.kondisi-abnormal.store'), $payload)->assertForbidden();
    }

    public function test_the_report_fields_are_validated(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);
        $post = fn (array $data) => $this->actingAs($operator)->post(route('operasi.input.kondisi-abnormal.store'), ['unit_id' => $this->unit->id, ...$data]);

        $post(['jenis' => 'gangguan', 'uraian_kondisi' => 'Trip'])->assertSessionHasErrors('tanggal');
        $post(['tanggal' => '2026-08-12', 'uraian_kondisi' => 'Trip', 'jenis' => 'rusak'])->assertSessionHasErrors('jenis');
        $post(['tanggal' => '2026-08-12', 'jenis' => 'gangguan'])->assertSessionHasErrors('uraian_kondisi');
        $post(['tanggal' => '2026-08-12', 'jenis' => 'gangguan', 'uraian_kondisi' => 'Trip', 'durasi' => -1])->assertSessionHasErrors('durasi');
        $post(['tanggal' => '2026-08-12', 'jenis' => 'gangguan', 'uraian_kondisi' => 'Trip', 'dampak_sistem' => str_repeat('x', 2001)])->assertSessionHasErrors('dampak_sistem');

        $this->assertSame(0, KondisiAbnormal::query()->count());
    }

    public function test_the_rekap_pdf_and_the_laporan_operasi_list_the_reports(): void
    {
        KondisiAbnormal::query()->create($this->period + [
            'no_urut' => 1, 'uraian_kondisi' => 'Getaran tinggi', 'tanggal' => '2026-08-03', 'is_gangguan' => 1, 'durasi_gangguan' => 4,
            'parameter_terkait' => 'Vibrasi 12 mm/s', 'tl_pendek_flm' => true, 'tl_pendek_lainnya' => true, 'tl_pendek_keterangan' => 'Baut pondasi dikencangkan',
            'tl_panjang' => 'Alignment ulang', 'dampak_sistem' => 'Beban dialihkan', 'dampak_pembangkit' => 'Mesin #2 stop 4 jam',
        ]);

        [$view, $data] = app(KondisiAbnormalController::class)->pdfView($this->unit, 8, 2026);
        $html = view($view, $data)->render();

        $this->assertCount(1, $data['rows'], 'Only the reports, no blank rows.');
        $this->assertSame('03/08/2026', $data['rows'][0]['tanggal']);
        $this->assertSame(['tl_pendek_sr' => 0, 'tl_pendek_flm' => 1, 'tl_pendek_lainnya' => 1], $data['totalTindakPendek']);
        $this->assertStringContainsString('size: A4 landscape', $html);
        foreach (['Vibrasi 12 mm/s', 'Baut pondasi dikencangkan', 'Alignment ulang', 'Beban dialihkan', 'Mesin #2 stop 4 jam'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        [, $empty] = app(KondisiAbnormalController::class)->pdfView($this->unit, 9, 2026);
        $this->assertStringContainsString('Belum ada laporan kondisi abnormal', view($view, $empty)->render());

        $sections = app(OperasiReportSections::class)->build($this->unit, 8, 2026);
        $this->assertSame(['FLM', 'Lainnya'], $sections['kondisi_abnormal']['rows'][0]['tindak_lanjut_pendek']);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $this->unit))
            ->get(route('operasi.input.kondisi-abnormal.pdf', $this->period))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function report(User $user, array $data): void
    {
        $this->actingAs($user)->post(route('operasi.input.kondisi-abnormal.store'), ['unit_id' => $this->unit->id, ...$data])
            ->assertRedirect()->assertSessionHasNoErrors();
    }
}
