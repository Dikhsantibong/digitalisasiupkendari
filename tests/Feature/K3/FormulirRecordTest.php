<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3FormulirRecord;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class FormulirRecordTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function forms(): array
    {
        return [
            'sarana prasarana' => ['sarana-prasarana', 'atribut_peralatan', 19],
            'kontrol k3 mingguan' => ['kontrol-mingguan', 'kontrol_apd', 8],
            'pemeliharaan tps lb3' => ['pemeliharaan-tps-lb3', 'sarana', 10],
            'pemeliharaan oil trap' => ['pemeliharaan-oil-trap', 'sarana', 8],
        ];
    }

    #[DataProvider('forms')]
    public function test_page_shows_default_template_and_document_props(string $form, string $section, int $defaultRows): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $response = $this->actingAs($user)->get(route('k3.formulir.record.index', [
            'form' => $form,
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component("k3/formulir/{$form}/index")
            ->where('form.key', $form)
            ->has("sections.{$section}", $defaultRows)
            ->where('has_saved', false)
            ->where('can_write', true)
            ->has('document')
            ->has('signers')
            ->has('rendered_html')
            ->where('pdf_url', fn (string $url): bool => str_contains($url, "/k3/formulir/{$form}/pdf"))
        );
    }

    #[DataProvider('forms')]
    public function test_form_can_be_saved_and_reloaded(string $form, string $section): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $response = $this->actingAs($user)->post(route('k3.formulir.record.store', ['form' => $form]), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'week' => 2,
            'sections' => [
                $section => [
                    ['item' => 'Item Uji', 'keterangan' => 'Baik', 'kolom_asing' => 'dibuang'],
                ],
            ],
            'catatan' => 'Catatan uji',
            'document_number' => 'DOC-01',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $record = K3FormulirRecord::query()->where('unit_id', $unit->id)->where('form', $form)->sole();
        $this->assertSame('DOC-01', $record->document_number);
        $this->assertSame('Item Uji', $record->data['sections'][$section][0]['item']);
        $this->assertArrayNotHasKey('kolom_asing', $record->data['sections'][$section][0]);

        $week = $form === 'kontrol-mingguan' ? 2 : 0;
        $this->assertSame($week, $record->week);

        $this->actingAs($user)->get(route('k3.formulir.record.index', [
            'form' => $form,
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'week' => 2,
        ]))->assertInertia(fn ($page) => $page
            ->where('has_saved', true)
            ->has("sections.{$section}", 1)
            ->where("sections.{$section}.0.item", 'Item Uji')
            ->where('document.document_number', 'DOC-01')
            ->has('history', 1)
        );
    }

    #[DataProvider('forms')]
    public function test_pdf_is_rendered(string $form): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $response = $this->actingAs($user)->get(route('k3.formulir.record.pdf', [
            'form' => $form,
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'download' => 1,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('attachment;', $response->headers->get('Content-Disposition'));
    }

    public function test_maintenance_form_uses_project_signers_and_period_title(): void
    {
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia 6 Site']);
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $this->actingAs($user)->get(route('k3.formulir.record.index', [
            'form' => 'pemeliharaan-tps-lb3',
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]))->assertInertia(fn ($page) => $page
            ->has('signers', 2)
            ->where('signers.0.key', 'manager_ul')
            ->where('signers.1.key', 'staff_k3')
            ->where('document.manager_ul_title', 'Koordinator Project')
            ->where('document.staff_k3_title', 'Officer K3L')
            ->where('document.sign_place_date', 'Kendari, 1 September 2026')
            ->where('rendered_html', fn (string $html): bool => str_contains($html, 'FORMULIR PEMELIHARAAN TPS LB3 PERIODE AGUSTUS 2026')
                && str_contains($html, 'Kendari, 1 September 2026'))
        );
    }

    public function test_html_mode_content_is_used_for_pdf_and_editor(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $this->actingAs($user)->post(route('k3.formulir.record.store', ['form' => 'pemeliharaan-oil-trap']), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'sections' => ['sarana' => [['item' => 'Kolam 1']]],
            'format' => 'html',
            'content_html' => '<p>Dokumen oil trap disunting</p>',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->get(route('k3.formulir.record.index', [
            'form' => 'pemeliharaan-oil-trap',
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
        ]))->assertInertia(fn ($page) => $page
            ->where('document.format', 'html')
            ->where('rendered_html', '<p>Dokumen oil trap disunting</p>')
            ->where('generated_html', fn (string $html): bool => str_contains($html, 'Kolam 1'))
        );
    }

    public function test_weekly_form_defaults_to_a_week_and_keeps_header_fields(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $this->actingAs($user)->post(route('k3.formulir.record.store', ['form' => 'kontrol-mingguan']), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'week' => 3,
            'sections' => [],
            'header' => ['nama_petugas' => 'Andi', 'tidak_terdaftar' => 'x'],
        ])->assertSessionHasNoErrors();

        $record = K3FormulirRecord::query()->where('form', 'kontrol-mingguan')->sole();
        $this->assertSame(3, $record->week);
        $this->assertSame(['nama_petugas' => 'Andi'], $record->data['header']);

        $this->actingAs($user)->get(route('k3.formulir.record.index', [
            'form' => 'kontrol-mingguan',
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'week' => 3,
        ]))->assertInertia(fn ($page) => $page
            ->where('filters.week', 3)
            ->where('header.nama_petugas', 'Andi')
            ->where('document.format', 'form')
        );
    }

    public function test_unknown_form_returns_not_found(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $this->actingAs($user)->get('/k3/formulir/tidak-ada')->assertNotFound();
    }

    public function test_user_without_k3_permission_cannot_view_or_download(): void
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('k3.formulir.record.index', ['form' => 'sarana-prasarana', 'unit_id' => $unit->id]))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('k3.formulir.record.pdf', ['form' => 'sarana-prasarana', 'unit_id' => $unit->id]))
            ->assertForbidden();
    }

    public function test_view_only_user_cannot_save(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create();
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('k3.formulir.record.index', ['form' => 'sarana-prasarana', 'unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));

        $this->actingAs($manager)->post(route('k3.formulir.record.store', ['form' => 'sarana-prasarana']), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'sections' => [],
        ])->assertForbidden();

        $this->assertDatabaseCount('k3_formulir_records', 0);
    }
}
