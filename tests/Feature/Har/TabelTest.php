<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Http\Controllers\Har\TabelController;
use App\Models\HarTabelRow;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Support\HarTabel\HarTabels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class TabelTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_every_tabel_opens_empty_and_prints_a_landscape_pdf(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        foreach (HarTabels::all() as $tabel) {
            $this->actingAs($user)
                ->get(route("har.input.{$tabel->key()}.index", $period))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component("har/input/{$tabel->key()}/index")
                    ->where('tabel.key', $tabel->key())
                    ->where('has_saved', false)
                    ->where('rows', [])
                    ->where('urls.store', route("har.input.{$tabel->key()}.store")),
                );

            $response = $this->actingAs($user)->get(route("har.input.{$tabel->key()}.pdf", $period));
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $reader = new Fpdi;
            $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
            $this->assertSame('L', $reader->getTemplateSize($reader->importPage(1))['orientation'], $tabel->key());
        }
    }

    public function test_rekap_gangguan_saves_rows_and_sums_durasi_kwh_and_status(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 1, 'year' => 2026];

        $this->actingAs($user)
            ->post(route('har.input.laporan-gangguan.store'), $period + ['rows' => [
                ['tanggal_kejadian' => '2026-01-08', 'no_unit' => 'CUM #7', 'merk_type' => 'CUMMINS KTA 50 G8', 'daya_terpasang' => 1120, 'daya_mampu' => 850, 'tindakan' => 'Alihkan fungsi ke tombol lain', 'material_rusak' => 'Tombol HMI', 'tanggal_operasi' => '2026-01-08', 'durasi' => 0.5, 'komponen' => 'Kontrol-Proteksi', 'sistem' => 'Sistem Kelistrikan', 'status' => 'Close'],
                ['tanggal_kejadian' => '2026-01-21', 'no_unit' => 'CUM #6', 'tindakan' => 'Ganti O ring', 'durasi' => 6, 'sistem' => 'Sistem Pelumas', 'kwh_loss' => 5100, 'status' => 'Open'],
                ['tanggal_kejadian' => null, 'tindakan' => ''],
            ]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $saved = HarTabelRow::query()->where('tabel', 'laporan-gangguan')->orderBy('sort_order')->get();
        $this->assertCount(2, $saved);
        $this->assertSame('CUMMINS KTA 50 G8', $saved[0]->data['merk_type']);

        $this->actingAs($user)
            ->get(route('har.input.laporan-gangguan.index', $period))
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->has('rows', 2)
                ->where('summary.rows.0', [2, 1, 1, 6.5, 5100]),
            );

        [$view, $data] = app(TabelController::class)->pdfView(HarTabels::find('laporan-gangguan'), $unit, 1, 2026);
        $html = view($view, $data)->render();
        $this->assertStringContainsString('REKAP LAPORAN GANGGUAN', $html);
        $this->assertStringContainsString('08/01/2026', $html);
        $this->assertStringContainsString('status-close', $html);
        $this->assertStringContainsString('Tombol HMI', $html);
    }

    public function test_rekap_gangguan_rejects_unknown_sistem_status_and_bad_dates(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->post(route('har.input.laporan-gangguan.store'), ['unit_id' => $unit->id, 'month' => 1, 'year' => 2026, 'rows' => [
                ['tanggal_kejadian' => '08-01-2026', 'sistem' => 'Sistem Roket', 'status' => 'Selesai', 'durasi' => 'lama'],
            ]])
            ->assertSessionHasErrors(['rows.0.tanggal_kejadian', 'rows.0.sistem', 'rows.0.status', 'rows.0.durasi']);
    }

    public function test_abnormal_gangguan_keeps_one_status_per_row_and_totals_like_the_sheet(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post(route('har.input.abnormal-gangguan.store'), $period + ['rows' => [
                ['uraian' => 'Bocor BBM pada trasfer Pump to T.25 KL', 'jenis' => 'MESIN', 'tanggal' => '2026-08-20', 'abnormal' => 1, 'durasi_abnormal' => 20],
                ['uraian' => 'Temperature Coolant, Hunting', 'jenis' => 'MESIN #6', 'tanggal' => '2026-08-28', 'abnormal' => 1, 'durasi_abnormal' => 6],
                ['uraian' => 'Silincer Sisi Kanan, miring', 'jenis' => 'MESIN #6', 'tanggal' => '2026-08-28', 'abnormal' => 1, 'gangguan' => 1, 'durasi_abnormal' => 8],
            ]])
            ->assertSessionHasNoErrors();

        $rows = HarTabelRow::query()->where('tabel', 'abnormal-gangguan')->orderBy('sort_order')->get();
        $this->assertCount(3, $rows);
        // Both ticked: only the first (Abnormal) is kept.
        $this->assertSame(1, $rows[2]->data['abnormal']);
        $this->assertNull($rows[2]->data['gangguan']);

        [$view, $data] = app(TabelController::class)->pdfView(HarTabels::find('abnormal-gangguan'), $unit, 8, 2026);
        // TOTAL like the sheet: 3 abnormal, 34 jam, 0 gangguan, 0 jam.
        $this->assertSame(['abnormal' => 3, 'durasi_abnormal' => 34, 'gangguan' => 0, 'durasi_gangguan' => 0], $data['totals']);
        $html = view($view, $data)->render();
        $this->assertStringContainsString('LAPORAN KONDISI ABNORMAL DAN GANGGUAN PEMBANGKIT', $html);
        $this->assertStringContainsString('AGUSTUS 2026', $html);
        $this->assertStringContainsString('KOLOM ABNORMAL DAN GANGGUAN', strtoupper($html));
    }

    public function test_months_are_kept_apart(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.abnormal-gangguan.store'), ['unit_id' => $unit->id, 'month' => 7, 'year' => 2026, 'rows' => [['uraian' => 'Juli', 'abnormal' => 1]]]);
        $this->actingAs($user)->post(route('har.input.abnormal-gangguan.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [['uraian' => 'Agustus', 'gangguan' => 1]]]);

        $this->actingAs($user)
            ->get(route('har.input.abnormal-gangguan.index', ['unit_id' => $unit->id, 'month' => 7, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.uraian', 'Juli'));
    }

    public function test_manager_ul_can_view_but_not_save(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('har.input.laporan-gangguan.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($manager)
            ->post(route('har.input.laporan-gangguan.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => []])
            ->assertForbidden();
    }
}
