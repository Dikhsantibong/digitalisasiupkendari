<?php

namespace Tests\Feature\Logistik;

use App\Enums\RoleName;
use App\Http\Controllers\Logistik\FormController;
use App\Models\LogistikFormRow;
use App\Models\Unit;
use App\Support\LogistikForms\InventarisLainnyaForm;
use App\Support\LogistikForms\KondisiStokForm;
use App\Support\LogistikForms\LogistikForms;
use App\Support\LogistikForms\PendukungForm;
use App\Support\LogistikForms\PermitToWorkForm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class FormInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_every_form_opens_with_its_default_rows_and_prints_a_pdf(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        foreach (LogistikForms::all() as $form) {
            $this->actingAs($user)
                ->get(route('logistik.input.form.index', ['form' => $form->key(), ...$period]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    // Each form has its own page folder (ensure_pages_exist checks the file).
                    ->component("logistik/input/{$form->key()}/index")
                    ->where('form.key', $form->key())
                    ->where('has_saved', false)
                    ->where('rows', fn ($rows): bool => count($rows) === collect($form->sections())->sum(fn (array $s): int => count($s['rows']) + $s['blank_rows'])),
                );

            $response = $this->actingAs($user)->get(route('logistik.input.form.pdf', ['form' => $form->key(), ...$period]));
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $reader = new Fpdi;
            $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
            $this->assertSame($form->orientation() === 'portrait' ? 'P' : 'L', $reader->getTemplateSize($reader->importPage(1))['orientation'], $form->key());
        }
    }

    public function test_the_pendukung_form_starts_from_the_document_list(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $unit))
            ->get(route('logistik.input.form.index', ['form' => 'pendukung', 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->has('rows', count(PendukungForm::URAIAN))
                ->where('rows.0.data.uraian', '1.LAPORAN LOGISTIK & GUDANG')
                ->where('rows.11.data.keterangan', 'ROP'),
            );
    }

    public function test_kondisi_stok_computes_stok_akhir_and_rop(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post(route('logistik.input.form.store', ['form' => 'kondisi-stok']), $period + ['rows' => [
                ['section' => 'material', 'data' => ['nama' => 'Baterai AA', 'satuan' => 'Bh', 'stok_awal' => 10, 'material_masuk' => 5, 'material_keluar' => 3, 'pemakaian_rata' => 1.5, 'safety_stock' => 2, 'ilt' => 3, 'stok_akhir' => 999]],
                ['section' => 'peralatan', 'data' => ['safety_stock' => KondisiStokForm::SAFETY_STOCK, 'ilt' => KondisiStokForm::ILT]],
            ]])
            ->assertRedirect();

        $saved = LogistikFormRow::query()->where('unit_id', $unit->id)->get();
        $this->assertCount(1, $saved);
        $this->assertArrayNotHasKey('stok_akhir', $saved[0]->data);

        $this->actingAs($user)
            ->get(route('logistik.input.form.index', ['form' => 'kondisi-stok', ...$period]))
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->where('rows', fn ($rows): bool => collect($rows)->contains(fn ($row): bool => ($row['data']['nama'] ?? null) === 'Baterai AA'
                    && $row['data']['stok_akhir'] === 12 && $row['data']['rop'] === 6.5)),
            );
    }

    public function test_peralatan_counts_realisasi_and_the_recap(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post(route('logistik.input.form.store', ['form' => 'peralatan']), $period + ['rows' => [
                ['section' => 'peralatan', 'data' => ['uraian' => 'Kunci Pas', 'p1_baik' => 1, 'p2_rusak' => 1, 'p3_hilang' => 1]],
            ]])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('logistik.input.form.index', ['form' => 'peralatan', ...$period]))
            ->assertInertia(fn ($page) => $page
                ->where('rows.0.data.realisasi', 3)
                ->where('rows.0.data.kinerja', '75%')
                ->has('rows', 10 + 11 + 8)
                ->where('summary.rows.0', [1, 'REKAPITULASI PERALATAN', 'BAIK', 1]),
            );
    }

    public function test_unsafe_photos_are_stored_and_temuan_counted(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post(route('logistik.input.form.store', ['form' => 'unsafe']), $period + ['rows' => [
                ['section' => 'temuan', 'data' => ['periode' => 'MINGGU KE - 1', 'kategori' => 'UNSAFE ACTION', 'temuan' => 'Tidak memakai APD', 'eviden_sebelum' => 'logistik/lain/x.jpg'], 'files' => ['eviden_sebelum' => UploadedFile::fake()->image('sebelum.jpg')]],
                ['section' => 'temuan', 'data' => ['periode' => 'MINGGU KE - 2', 'kategori' => 'UNSAFE CONDITION']],
            ]])
            ->assertRedirect();

        $rows = LogistikFormRow::query()->where('form', 'unsafe')->get();
        $this->assertCount(1, $rows);
        $this->assertStringStartsWith("logistik/form/unsafe/{$unit->id}/", $rows[0]->data['eviden_sebelum']);
        Storage::disk('public')->assertExists($rows[0]->data['eviden_sebelum']);

        $this->actingAs($user)
            ->get(route('logistik.input.form.index', ['form' => 'unsafe', ...$period]))
            ->assertInertia(fn ($page) => $page->where('summary.rows.0', [1, 1, 0]));
    }

    public function test_every_form_table_fits_the_printed_page(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        foreach (LogistikForms::all() as $form) {
            [$view, $data] = app(FormController::class)->pdfView($form, $unit, 8, 2026);
            $dompdf = Pdf::loadHTML(view($view, $data)->render())->setPaper('a4', $form->orientation())->getDomPDF();
            $rightEdge = 0.0;
            $dompdf->setCallbacks([[
                'event' => 'end_frame',
                'f' => function ($frame) use (&$rightEdge): void {
                    if (in_array($frame->get_node()->nodeName, ['td', 'th'], true)) {
                        $box = $frame->get_border_box();
                        $rightEdge = max($rightEdge, $box['x'] + $box['w']);
                    }
                },
            ]]);
            $dompdf->render();

            // Page width minus the 10 mm right margin, in pt.
            $limit = ($form->orientation() === 'landscape' ? 841.89 : 595.28) - 10 / 25.4 * 72;
            $this->assertLessThanOrEqual($limit + 0.5, $rightEdge, "{$form->key()} table overflows the page");
        }
    }

    public function test_inventaris_lainnya_ticks_one_result_per_day_and_counts_the_realisasi(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 7, 'year' => 2026];

        $this->actingAs($user)
            ->get(route('logistik.input.form.index', ['form' => 'inventaris-lainnya', ...$period]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('logistik/input/inventaris-lainnya/index')
                ->has('rows', count(InventarisLainnyaForm::URAIAN) + InventarisLainnyaForm::BLANK_ROWS)
                ->where('rows.0.data.uraian', 'MEJA KERJA')
                ->where('rows.0.data.target', InventarisLainnyaForm::TARGET),
            );

        $allDaysNormal = collect(range(1, 31))->mapWithKeys(fn (int $day): array => ["d{$day}_n" => 1])->all();
        $this->actingAs($user)
            ->post(route('logistik.input.form.store', ['form' => 'inventaris-lainnya']), $period + ['rows' => [
                ['section' => 'inventaris', 'data' => ['uraian' => 'MEJA KERJA', 'target' => 30, ...$allDaysNormal]],
                ['section' => 'inventaris', 'data' => ['uraian' => 'KURSI KERJA', 'target' => 4, 'd1_n' => 1, 'd1_t' => 1, 'd2_t' => 1]],
                ['section' => 'inventaris', 'data' => ['uraian' => '', 'target' => 4]],
            ]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $saved = LogistikFormRow::query()->where('form', 'inventaris-lainnya')->orderBy('id')->get();
        $this->assertCount(2, $saved);
        // Both N and T ticked on day 1: only N (first) is kept.
        $this->assertSame(1, $saved[1]->data['d1_n']);
        $this->assertNull($saved[1]->data['d1_t']);

        $this->actingAs($user)
            ->get(route('logistik.input.form.index', ['form' => 'inventaris-lainnya', ...$period]))
            ->assertInertia(fn ($page) => $page
                // 31 of 30 like the sheet: 103%.
                ->where('rows.0.data.realisasi', 31)
                ->where('rows.0.data.kinerja', '103%')
                ->where('rows.1.data.realisasi', 2)
                ->where('rows.1.data.kinerja', '50%')
                // Total inventaris, pemeriksaan N, pemeriksaan T.
                ->where('summary.rows.0', [2, 32, 1]),
            );
    }

    public function test_permit_to_work_starts_with_thirty_blank_rows_in_portrait(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $unit))
            ->get(route('logistik.input.form.index', ['form' => 'permit-to-work', 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('logistik/input/permit-to-work/index')
                ->where('form.kop', 'LAPORAN PERMIT TO WORK PEMBANGKIT')
                ->where('form.orientation', 'portrait')
                ->has('rows', PermitToWorkForm::ROWS)
                ->where('summary.rows.0', [0, 0, 0, 0]),
            );
    }

    public function test_permit_to_work_keeps_one_status_per_permit_and_totals_open_and_close(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post(route('logistik.input.form.store', ['form' => 'permit-to-work']), $period + ['rows' => [
                ['section' => 'ptw', 'data' => ['uraian' => 'Penggantian filter oli', 'tanggal' => '2026-08-03', 'open' => 1]],
                ['section' => 'ptw', 'data' => ['uraian' => 'Perbaikan pompa', 'tanggal' => '2026-08-05', 'close' => 1]],
                ['section' => 'ptw', 'data' => ['uraian' => 'Pengelasan pipa', 'tanggal' => '2026-08-07', 'open' => 1, 'close' => 1]],
                ['section' => 'ptw', 'data' => ['uraian' => 'Pembersihan panel']],
                ['section' => 'ptw', 'data' => ['uraian' => '', 'tanggal' => null]],
            ]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $saved = LogistikFormRow::query()->where('unit_id', $unit->id)->orderBy('id')->get();
        $this->assertCount(4, $saved);
        // Both ticked: only the first status (Open) is kept.
        $this->assertSame(1, $saved[2]->data['open']);
        $this->assertNull($saved[2]->data['close']);

        $this->actingAs($user)
            ->get(route('logistik.input.form.index', ['form' => 'permit-to-work', ...$period]))
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->where('rows.0.data.tanggal', '2026-08-03')
                // Total PTW, Open, Close, belum ada status.
                ->where('summary.rows.0', [4, 2, 1, 1]),
            );

        [$view, $data] = app(FormController::class)->pdfView(new PermitToWorkForm, $unit, 8, 2026);
        $html = view($view, $data)->render();
        $this->assertStringContainsString('LAPORAN PTW PEMBANGKIT', $html);
        $this->assertStringContainsString('03/08/2026', $html);
        $this->assertStringContainsString('✓', $html);
        $this->assertSame(['open' => 2, 'close' => 1], (new PermitToWorkForm)->totals('ptw', $saved->pluck('data')->all()));

        $pdf = $this->actingAs($user)->get(route('logistik.input.form.pdf', ['form' => 'permit-to-work', ...$period]));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $reader->setSourceFile(StreamReader::createByString((string) $pdf->getContent()));
        $this->assertSame('P', $reader->getTemplateSize($reader->importPage(1))['orientation']);
    }

    public function test_permit_to_work_rejects_a_bad_date_or_tick(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $unit))
            ->post(route('logistik.input.form.store', ['form' => 'permit-to-work']), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
                'rows' => [['section' => 'ptw', 'data' => ['uraian' => 'Uji', 'tanggal' => '31-08-2026', 'open' => 'ya']]],
            ])
            ->assertSessionHasErrors(['rows.0.data.tanggal', 'rows.0.data.open']);
    }

    public function test_an_unknown_select_value_is_rejected(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $unit))
            ->post(route('logistik.input.form.store', ['form' => 'unsafe']), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [['section' => 'temuan', 'data' => ['kategori' => 'LAINNYA']]]])
            ->assertSessionHasErrors('rows.0.data.kategori');
    }

    public function test_a_role_without_logistik_input_cannot_save(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('logistik.input.form.store', ['form' => 'pendukung']), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => []])
            ->assertForbidden();
    }
}
