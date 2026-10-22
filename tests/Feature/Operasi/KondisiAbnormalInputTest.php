<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Http\Controllers\Operasi\KondisiAbnormalController;
use App\Models\KondisiAbnormal;
use App\Models\Unit;
use App\Services\Operasi\OperasiReportSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

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

    public function test_the_page_lists_blank_rows_with_every_follow_up_field(): void
    {
        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $this->unit))
            ->get(route('operasi.input.kondisi-abnormal.index', $this->period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/input/kondisi-abnormal/index')
                ->has('rows', 30)
                ->where('rows.0.parameter_terkait', '')
                ->where('rows.0.tl_pendek_sr', 0)
                ->where('rows.0.tl_pendek_flm', 0)
                ->where('rows.0.tl_pendek_lainnya', 0)
                ->where('rows.0.tl_panjang', '')
                ->where('rows.0.dampak_sistem', '')
                ->where('rows.0.dampak_pembangkit', '')
                ->where('options.tindak_pendek', ['tl_pendek_sr' => 'SR', 'tl_pendek_flm' => 'FLM', 'tl_pendek_lainnya' => 'Lainnya'])
                ->where('summary.tindak_pendek', ['tl_pendek_sr' => 0, 'tl_pendek_flm' => 0, 'tl_pendek_lainnya' => 0]));
    }

    public function test_the_operator_saves_the_follow_up_and_impact_of_a_finding(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);

        $this->actingAs($operator)->post(route('operasi.input.kondisi-abnormal.store'), $this->period + ['rows' => [
            [
                'no_urut' => 1, 'uraian_kondisi' => 'Temperatur exhaust tinggi', 'tanggal' => '2026-08-12',
                'is_abnormal' => 1, 'durasi_abnormal' => 2.5, 'is_gangguan' => 0, 'durasi_gangguan' => 0,
                'parameter_terkait' => 'Exhaust silinder 3: 520 °C', 'tl_pendek_sr' => 1, 'tl_pendek_flm' => 1, 'tl_pendek_lainnya' => 0,
                'tl_pendek_keterangan' => 'SR-1029 dibuat, FLM pembersihan filter udara', 'tl_panjang' => 'Penggantian injector saat PM 6000',
                'dampak_sistem' => 'Tidak ada pemadaman', 'dampak_pembangkit' => 'Derating 200 kW',
            ],
            // A row holding only a follow-up is a finding too.
            ['no_urut' => 2, 'tl_pendek_lainnya' => 1, 'tl_pendek_keterangan' => 'Koordinasi dengan UP3'],
            ['no_urut' => 3, 'uraian_kondisi' => '', 'tanggal' => null, 'is_abnormal' => 0, 'durasi_abnormal' => 0, 'is_gangguan' => 0, 'durasi_gangguan' => 0, 'tl_pendek_sr' => 0],
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $rows = KondisiAbnormal::query()->orderBy('no_urut')->get();
        $this->assertSame([1, 2], $rows->pluck('no_urut')->all());
        $this->assertTrue($rows[0]->tl_pendek_sr);
        $this->assertTrue($rows[0]->tl_pendek_flm);
        $this->assertFalse($rows[0]->tl_pendek_lainnya);
        $this->assertSame('Exhaust silinder 3: 520 °C', $rows[0]->parameter_terkait);
        $this->assertSame('Penggantian injector saat PM 6000', $rows[0]->tl_panjang);
        $this->assertSame('Derating 200 kW', $rows[0]->dampak_pembangkit);
        $this->assertSame($operator->id, $rows[0]->input_by);

        $this->actingAs($operator)->get(route('operasi.input.kondisi-abnormal.index', $this->period))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.tl_pendek_sr', 1)
                ->where('rows.0.dampak_sistem', 'Tidak ada pemadaman')
                ->where('rows.1.tl_pendek_keterangan', 'Koordinasi dengan UP3')
                ->where('summary.total_abnormal_count', 1)
                ->where('summary.tindak_pendek', ['tl_pendek_sr' => 1, 'tl_pendek_flm' => 1, 'tl_pendek_lainnya' => 1]));

        // Clearing the row deletes it.
        $this->actingAs($operator)->post(route('operasi.input.kondisi-abnormal.store'), $this->period + ['rows' => [
            ['no_urut' => 2, 'tl_pendek_lainnya' => 0, 'tl_pendek_keterangan' => ''],
        ]])->assertSessionHasNoErrors();
        $this->assertSame([1], KondisiAbnormal::query()->pluck('no_urut')->all());
    }

    public function test_the_rekap_pdf_and_the_laporan_operasi_carry_the_new_columns(): void
    {
        KondisiAbnormal::query()->create($this->period + [
            'no_urut' => 1, 'uraian_kondisi' => 'Getaran tinggi', 'tanggal' => '2026-08-03', 'is_gangguan' => 1, 'durasi_gangguan' => 4,
            'parameter_terkait' => 'Vibrasi 12 mm/s', 'tl_pendek_flm' => true, 'tl_pendek_lainnya' => true, 'tl_pendek_keterangan' => 'Baut pondasi dikencangkan',
            'tl_panjang' => 'Alignment ulang', 'dampak_sistem' => 'Beban dialihkan', 'dampak_pembangkit' => 'Mesin #2 stop 4 jam',
        ]);

        [$view, $data] = app(KondisiAbnormalController::class)->pdfView($this->unit, 8, 2026);
        $html = view($view, $data)->render();

        $this->assertSame('03/08/2026', $data['rows'][0]['tanggal']);
        $this->assertSame(['tl_pendek_sr' => 0, 'tl_pendek_flm' => 1, 'tl_pendek_lainnya' => 1], $data['totalTindakPendek']);
        $this->assertStringContainsString('size: A4 landscape', $html);
        foreach (['PARAMETER TERKAIT', 'TINDAK LANJUT JANGKA PENDEK', 'TINDAK LANJUT JANGKA PANJANG', 'DAMPAK TERHADAP SISTEM', 'DAMPAK TERHADAP PEMBANGKIT',
            'Vibrasi 12 mm/s', 'Baut pondasi dikencangkan', 'Alignment ulang', 'Beban dialihkan', 'Mesin #2 stop 4 jam'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        $sections = app(OperasiReportSections::class)->build($this->unit, 8, 2026);
        $row = $sections['kondisi_abnormal']['rows'][0];
        $this->assertSame(['FLM', 'Lainnya'], $row['tindak_lanjut_pendek']);
        $this->assertSame('Alignment ulang', $row['tindak_lanjut_panjang']);
        $this->assertSame('Mesin #2 stop 4 jam', $row['dampak_pembangkit']);
    }

    public function test_the_pdf_downloads_in_landscape_and_long_text_is_rejected(): void
    {
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);

        $this->actingAs($user)->get(route('operasi.input.kondisi-abnormal.pdf', $this->period))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($user)->post(route('operasi.input.kondisi-abnormal.store'), $this->period + ['rows' => [
            ['no_urut' => 1, 'dampak_sistem' => str_repeat('x', 2001)],
        ]])->assertSessionHasErrors('rows.0.dampak_sistem');
    }
}
