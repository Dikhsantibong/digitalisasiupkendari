<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Http\Controllers\Har\AbnormalGangguanController;
use App\Http\Controllers\Har\TabelController;
use App\Models\HarTabelRow;
use App\Models\KondisiAbnormal;
use App\Models\Machine;
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
                    ->where('linked_rows', [])
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

    public function test_abnormal_gangguan_shares_format_and_records_with_operasi_kondisi_abnormal(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $harUser = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $opUser = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];
        $mesin = Machine::factory()->create(['unit_id' => $unit->id, 'name' => 'MAK #2']);

        // 1. Operasi inputs an abnormal and a gangguan record via Operasi Kondisi Abnormal.
        $this->actingAs($opUser)
            ->post(route('operasi.input.kondisi-abnormal.store'), [
                'unit_id' => $unit->id,
                'tanggal' => '2026-08-03',
                'jenis' => 'gangguan',
                'jam_mulai' => '08:00',
                'jam_selesai' => '09:30',
                'machine_id' => $mesin->id,
                'peralatan' => 'Cylinder head',
                'uraian_kondisi' => 'Mesin trip karena suhu tinggi',
                'parameter_terkait' => 'Exhaust 530 C',
                'kronologi' => '08:00 alarm suhu tinggi, 08:05 trip',
                'tl_pendek_sr' => true,
                'tl_pendek_flm' => true,
                'tl_pendek_keterangan' => 'SR-001',
                'tl_panjang' => 'Overhaul top',
                'dampak_sistem' => 'Beban dialihkan',
                'dampak_pembangkit' => 'Derating 500 kW',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // 2. Pemeliharaan opens har.input.abnormal-gangguan and automatically sees the exact same record & fields.
        $this->actingAs($harUser)
            ->get(route('har.input.abnormal-gangguan.index', $period))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/input/abnormal-gangguan/index')
                ->has('entries', 1)
                ->where('entries.0.uraian_kondisi', 'Mesin trip karena suhu tinggi')
                ->where('entries.0.jenis', 'gangguan')
                ->where('entries.0.durasi', 1.5)
                ->where('entries.0.mesin', 'MAK #2')
                ->where('entries.0.peralatan', 'Cylinder head')
                ->where('entries.0.parameter_terkait', 'Exhaust 530 C')
                ->where('entries.0.kronologi', '08:00 alarm suhu tinggi, 08:05 trip')
                ->where('entries.0.tl_pendek_sr', 1)
                ->where('entries.0.tl_pendek_flm', 1)
                ->where('entries.0.tl_panjang', 'Overhaul top')
                ->where('summary.total_gangguan_count', 1)
                ->where('summary.total_gangguan_durasi', 1.5)
            );

        // 3. Pemeliharaan adds another record from har.input.abnormal-gangguan.
        $this->actingAs($harUser)
            ->post(route('har.input.abnormal-gangguan.store'), [
                'unit_id' => $unit->id,
                'tanggal' => '2026-08-20',
                'jenis' => 'abnormal',
                'durasi' => 2,
                'uraian_kondisi' => 'Bocor BBM pada transfer pump',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, KondisiAbnormal::query()->where('unit_id', $unit->id)->count());

        // 4. Both records appear in the Pemeliharaan PDF and summary.
        [$view, $data] = app(AbnormalGangguanController::class)->pdfView($unit, 8, 2026);
        $this->assertSame(1, $data['totalAbnormalCount']);
        $this->assertEquals(2.0, $data['totalAbnormalDurasi']);
        $this->assertSame(1, $data['totalGangguanCount']);
        $this->assertEquals(1.5, $data['totalGangguanDurasi']);
        $html = view($view, $data)->render();
        $this->assertStringContainsString('LAPORAN KONDISI ABNORMAL DAN GANGGUAN PEMBANGKIT', $html);
        $this->assertStringContainsString('Mesin trip karena suhu tinggi', $html);
        $this->assertStringContainsString('Bocor BBM pada transfer pump', $html);
    }

    public function test_months_are_kept_apart(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.abnormal-gangguan.store'), ['unit_id' => $unit->id, 'tanggal' => '2026-07-10', 'jenis' => 'abnormal', 'uraian_kondisi' => 'Juli']);
        $this->actingAs($user)->post(route('har.input.abnormal-gangguan.store'), ['unit_id' => $unit->id, 'tanggal' => '2026-08-10', 'jenis' => 'gangguan', 'uraian_kondisi' => 'Agustus']);

        $this->actingAs($user)
            ->get(route('har.input.abnormal-gangguan.index', ['unit_id' => $unit->id, 'month' => 7, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page->has('entries', 1)->where('entries.0.uraian_kondisi', 'Juli'));
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
