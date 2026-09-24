<?php

namespace Tests\Feature\Pdm;

use App\Enums\RoleName;
use App\Http\Controllers\Pdm\FormInputController;
use App\Models\Machine;
use App\Models\PdmFormDocument;
use App\Models\PdmFormItem;
use App\Models\Unit;
use App\Support\PdmForms\ChecklistPatrolCheckPdmForm;
use App\Support\PdmForms\PatrolCheckPdmForm;
use App\Support\PdmForms\PdmForms;
use App\Support\PdmForms\VibrasiForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PdmFormInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_every_form_page_opens_without_signatures(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        Machine::factory()->forUnit($unit)->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);

        foreach (PdmForms::all() as $form) {
            $this->actingAs($user)
                ->get(route('pdm.input.forms.index', ['form' => $form->key(), 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    // Each form has its own page folder (ensure_pages_exist checks the file).
                    ->component("pdm/input/{$form->key()}/index")
                    ->where('form.key', $form->key())
                    ->missing('form.signatures')
                    ->where('kop_lines', $form->kopLines($unit->name))
                    ->where('can_write', true),
                );
        }
    }

    public function test_an_unknown_form_is_not_found(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.input.forms.index', ['form' => 'tidak-ada', 'unit_id' => $unit->id]))
            ->assertNotFound();
    }

    public function test_the_5s5r_checklist_saves_rows_and_computes_the_akumulatif(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);

        $this->actingAs($user)
            ->post(route('pdm.input.forms.store', ['form' => 'checklist-5s5r']), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
                'header' => ['nama_pembangkit' => $unit->name, 'mengetahui_nama' => 'Diabaikan'],
                'rows' => [
                    'ringkas' => [
                        ['item' => 'Alat rusak dipisahkan', 'rencana' => '1', 'realisasi' => '1'],
                        ['item' => 'Material bekas dibuang', 'rencana' => '1', 'realisasi' => null],
                        ['item' => '', 'rencana' => null, 'realisasi' => null],
                    ],
                ],
            ])
            ->assertRedirect();

        $document = PdmFormDocument::query()->where('unit_id', $unit->id)->where('form', 'checklist-5s5r')->firstOrFail();
        $this->assertArrayNotHasKey('mengetahui_nama', $document->header);
        $this->assertSame(2, PdmFormItem::query()->where('pdm_form_document_id', $document->id)->count());

        $this->actingAs($user)
            ->get(route('pdm.input.forms.index', ['form' => 'checklist-5s5r', 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('document.saved', true)
                ->where('document.summary.rows.0.1', '2')
                ->where('document.summary.rows.0.2', '1')
                ->where('document.summary.rows.0.3', '50%'),
            );
    }

    public function test_vibrasi_keeps_one_document_per_machine_with_fixed_points(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        [$first, $second] = Machine::factory()->forUnit($unit)->count(2)->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);

        foreach ([$first, $second] as $machine) {
            $this->actingAs($user)
                ->post(route('pdm.input.forms.store', ['form' => 'vibrasi']), [
                    'unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'machine_id' => $machine->id,
                    'header' => ['rpm' => '1000'],
                    'rows' => ['titik' => [['titik' => 'X9', 'v_max' => '2.1']]],
                ])
                ->assertRedirect();
        }

        $documents = PdmFormDocument::query()->where('form', 'vibrasi')->orderBy('subject')->get();
        $this->assertSame([(string) $first->id, (string) $second->id], $documents->pluck('subject')->sort()->values()->all());

        $items = PdmFormItem::query()->where('pdm_form_document_id', $documents->first()->id)->orderBy('sort_order')->get();
        $this->assertCount(1, $items);
        $this->assertSame(VibrasiForm::POINTS[0], $items[0]->data['titik']);
        $this->assertSame('2.1', $items[0]->data['v_max']);
    }

    public function test_the_pdf_follows_the_form_orientation(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        Machine::factory()->forUnit($unit)->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);

        foreach (['kontrol-material' => 'L', 'vibrasi' => 'P'] as $form => $orientation) {
            $response = $this->actingAs($user)
                ->get(route('pdm.input.forms.pdf', ['form' => $form, 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $reader = new Fpdi;
            $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
            $this->assertSame($orientation, $reader->getTemplateSize($reader->importPage(1))['orientation'], $form);
        }
    }

    public function test_patrol_check_pdm_starts_from_the_default_checklist_and_identitas(): void
    {
        $unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Poasia']);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.input.forms.index', ['form' => 'patrol-check-pdm', 'unit_id' => $unit->id, 'month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pdm/input/patrol-check-pdm/index')
                ->where('kop_lines.3', 'PATROL CHECK PREDICTIVE MAINTENANCE (PdM)')
                ->where('document.saved', false)
                ->where('document.header.unit', 'PLTD Poasia')
                ->where('document.header.tanggal', '2026-07-01')
                ->where('document.header.tim_patrol', 'Officer K3L')
                ->has('document.rows.checklist', count(PatrolCheckPdmForm::ITEMS))
                ->where('document.rows.checklist.0.area', 'Mesin Diesel')
                ->where('document.rows.checklist.27.item', 'Temuan dibuatkan WO')
                ->where('document.summary.rows.0.4', (string) count(PatrolCheckPdmForm::ITEMS)),
            );
    }

    public function test_patrol_check_pdm_keeps_one_tick_per_row_and_summarises_the_result(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);

        $this->actingAs($user)
            ->post(route('pdm.input.forms.store', ['form' => 'patrol-check-pdm']), [
                'unit_id' => $unit->id, 'month' => 7, 'year' => 2026,
                'header' => ['unit' => $unit->name, 'tanggal' => '2026-07-10', 'waktu' => '10.00 WITA'],
                'rows' => [
                    'checklist' => [
                        ['area' => 'Mesin Diesel', 'item' => 'Tidak ada abnormal noise', 'ya' => '1'],
                        ['area' => 'Generator', 'item' => 'Getaran bearing normal', 'tidak' => '1', 'temuan' => 'Getaran tinggi DE'],
                        ['area' => 'K3', 'item' => 'APD digunakan', 'ya' => '1', 'tidak' => '1', 'na' => '1'],
                        ['area' => 'Dokumentasi', 'item' => 'Lengkap', 'na' => '1'],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $document = PdmFormDocument::query()->where('unit_id', $unit->id)->where('form', 'patrol-check-pdm')->firstOrFail();
        $items = PdmFormItem::query()->where('pdm_form_document_id', $document->id)->orderBy('sort_order')->get();
        $this->assertCount(4, $items);
        // Several ticks in one row keep only the first one (Ya).
        $this->assertSame('1', $items[2]->data['ya']);
        $this->assertNull($items[2]->data['tidak']);
        $this->assertNull($items[2]->data['na']);

        $this->actingAs($user)
            ->get(route('pdm.input.forms.index', ['form' => 'patrol-check-pdm', 'unit_id' => $unit->id, 'month' => 7, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('document.saved', true)
                ->where('document.rows.checklist.1.temuan', 'Getaran tinggi DE')
                // Total, Ya, Tidak, N/A, Belum diisi, % sesuai (Ya / (Ya + Tidak)).
                ->where('document.summary.rows.0', ['4', '2', '1', '1', '0', '67%']),
            );

        $pdf = $this->actingAs($user)
            ->get(route('pdm.input.forms.pdf', ['form' => 'patrol-check-pdm', 'unit_id' => $unit->id, 'month' => 7, 'year' => 2026]));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $reader->setSourceFile(StreamReader::createByString((string) $pdf->getContent()));
        $this->assertSame('P', $reader->getTemplateSize($reader->importPage(1))['orientation']);
    }

    public function test_patrol_check_pdm_rejects_an_invalid_tick_value(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->post(route('pdm.input.forms.store', ['form' => 'patrol-check-pdm']), [
                'unit_id' => $unit->id, 'month' => 7, 'year' => 2026,
                'rows' => ['checklist' => [['area' => 'Mesin', 'ya' => 'ya']]],
            ])
            ->assertSessionHasErrors('rows.checklist.0.ya');
    }

    public function test_patrol_check_pdm_pdf_prints_the_identitas_and_ticks(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);
        $this->actingAs($user)->post(route('pdm.input.forms.store', ['form' => 'patrol-check-pdm']), [
            'unit_id' => $unit->id, 'month' => 7, 'year' => 2026,
            'header' => ['tanggal' => '2026-07-10', 'pelaksana' => 'Andi PdM'],
            'rows' => ['checklist' => [['area' => 'Battery', 'item' => 'Tegangan normal', 'ya' => '1']]],
        ]);

        [$view, $data] = app(FormInputController::class)->pdfView(new PatrolCheckPdmForm, $unit, 7, 2026, null);
        $html = view($view, $data)->render();

        $this->assertStringContainsString('Jumat, 10 Juli 2026', $html);
        $this->assertStringContainsString('Andi PdM', $html);
        $this->assertStringContainsString('✓', $html);
        $this->assertStringContainsString('☐', $html);
        $this->assertStringContainsString('Rekap Hasil Patrol', $html);
    }

    public function test_checklist_patrol_check_starts_from_the_areas_numbered_across_sections(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.input.forms.index', ['form' => 'checklist-patrol-check', 'unit_id' => $unit->id, 'month' => 7, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('form.continuous_numbering', true)
                ->where('kop_lines.2', 'DOKUMEN/LAPORAN CHECKLIST PATROL CHECK PdM')
                ->where('document.header.nama_pembangkit', $unit->name)
                ->has('document.rows.turbin', 4)
                ->has('document.rows.monitoring', 4)
                ->where('document.rows.pelumasan.0.item', 'Tangki: level oli')
                ->where('document.summary.rows.0', ['17', '0', '0', '0', '17', '0%']),
            );
    }

    public function test_checklist_patrol_check_saves_statuses_and_summarises_the_patrol(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPdm, $unit);

        $this->actingAs($user)
            ->post(route('pdm.input.forms.store', ['form' => 'checklist-patrol-check']), [
                'unit_id' => $unit->id, 'month' => 7, 'year' => 2026,
                'header' => ['nama_pembangkit' => $unit->name, 'tanggal_patroli' => '2026-07-10', 'supervisor' => 'Project Leader'],
                'rows' => [
                    'turbin' => [
                        ['item' => 'Bearing Turbin', 'status' => 'OK'],
                        ['item' => 'Suhu bearing normal', 'status' => 'NOK', 'keterangan' => 'Suhu 92°C', 'tindakan' => 'Cek pelumasan bearing'],
                    ],
                    'generator' => [
                        ['item' => 'Bearing Generator: temperatur', 'status' => 'N/A'],
                        ['item' => 'Kebersihan Ventilasi Generator'],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('pdm.input.forms.index', ['form' => 'checklist-patrol-check', 'unit_id' => $unit->id, 'month' => 7, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('document.saved', true)
                ->where('document.rows.turbin.1.tindakan', 'Cek pelumasan bearing')
                // Total, OK, NOK, N/A, belum diisi, realisasi.
                ->where('document.summary.rows.0', ['4', '1', '1', '1', '1', '75%']),
            );

        [$view, $data] = app(FormInputController::class)->pdfView(new ChecklistPatrolCheckPdmForm, $unit, 7, 2026, null);
        $html = view($view, $data)->render();

        $this->assertStringContainsString('RINGKASAN HASIL PATROLI', $html);
        $this->assertStringContainsString('Suhu 92°C', $html);
        // Generator rows continue the numbering after the 2 turbin rows.
        $this->assertMatchesRegularExpression('/<td class="c">3<\/td>\s*<td class="">Bearing Generator: temperatur<\/td>/', $html);

        $pdf = $this->actingAs($user)
            ->get(route('pdm.input.forms.pdf', ['form' => 'checklist-patrol-check', 'unit_id' => $unit->id, 'month' => 7, 'year' => 2026]));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_role_without_pdm_input_cannot_save(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('pdm.input.forms.store', ['form' => 'kontrol-material']), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026])
            ->assertForbidden();
    }
}
