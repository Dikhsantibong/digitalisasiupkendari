<?php

namespace Tests\Feature\Pdm;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\PdmFormDocument;
use App\Models\PdmFormItem;
use App\Models\Unit;
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
                    ->component('pdm/input/forms/show')
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

    public function test_a_role_without_pdm_input_cannot_save(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('pdm.input.forms.store', ['form' => 'kontrol-material']), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026])
            ->assertForbidden();
    }
}
