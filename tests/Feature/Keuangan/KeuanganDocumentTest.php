<?php

namespace Tests\Feature\Keuangan;

use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Models\CashCardReport;
use App\Models\Employee;
use App\Models\KeuanganDocument;
use App\Models\ServiceUnit;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Laporan Administrasi Keuangan as an editable document (user, 2026-11-20):
 * page one the cash card table, then each row's bukti & lampiran — edited,
 * saved and printed like the other reports.
 */
class KeuanganDocumentTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const PERIOD = ['month' => 9, 'year' => 2026];

    private ServiceUnit $ul;

    private User $jof;

    /** @var array{ul_id: int, month: int, year: int} */
    private array $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        Storage::fake('public');

        $this->ul = ServiceUnit::factory()->create(['name' => 'UL PLTD Poasia']);
        $this->jof = $this->userWithRole(RoleName::JofAdministrasiUmum, $this->ul);
        Employee::factory()->create(['service_unit_id' => $this->ul->id, 'unit_id' => null, 'name' => 'Ramlin Jamaludin', 'position' => EmployeePosition::JofAdministrasiUmum->value, 'user_id' => $this->jof->id, 'is_active' => true]);
        $this->query = ['ul_id' => $this->ul->id, ...self::PERIOD];

        $dilla = Supplier::query()->create(['service_unit_id' => $this->ul->id, 'name' => 'DILLA JAYA', 'email' => 'jurmal1778@gmail.com', 'npwp' => '01.234',
            'ktp_path' => UploadedFile::fake()->image('ktp.jpg')->store('keuangan/supplier/'.$this->ul->id, 'public')]);
        $report = CashCardReport::query()->create(['service_unit_id' => $this->ul->id, ...self::PERIOD, 'saldo_awal' => 0]);
        $report->entries()->create(['sort_order' => 0, 'uraian' => 'Penarikan Dana', 'penerimaan' => 56500000]);
        $report->entries()->create(['sort_order' => 1, 'no_entry' => 5, 'uraian' => 'By. ATK', 'supplier_id' => $dilla->id, 'pengeluaran' => 925500,
            'photos' => [UploadedFile::fake()->image('nota.jpg')->store('keuangan/cash-card/'.$this->ul->id.'/2026-09', 'public')]]);
    }

    public function test_the_document_holds_the_laporan_then_the_bukti_and_lampiran(): void
    {
        $this->actingAs($this->jof)->get(route('keuangan.document.show', $this->query))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('keuangan/dokumen')
                ->where('has_saved', false)->where('can_write', true)->where('format', 'html')
                ->loadDeferredProps('document', fn (AssertableInertia $page) => $page
                    ->where('document_number', fn (string $number): bool => str_starts_with($number, 'KEU/') && str_ends_with($number, '/09/2026'))
                    ->where('content', fn (string $html): bool => str_contains($html, 'id="keu-laporan"')
                        && str_contains($html, 'id="keu-bukti"')
                        && strpos($html, 'keu-laporan') < strpos($html, 'keu-bukti')
                        && str_contains($html, 'DILLA JAYA')
                        && str_contains($html, 'jurmal1778@gmail.com')
                        && str_contains($html, '/storage/keuangan/cash-card/'))
                    ->has('grid')));

        // Laporan page + per row bukti & lampiran (2 pages each).
        $pdf = $this->actingAs($this->jof)->get(route('keuangan.document.pdf', $this->query));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertGreaterThanOrEqual(5, (new Fpdi)->setSourceFile(StreamReader::createByString((string) $pdf->getContent())));
    }

    public function test_an_edited_document_is_saved_printed_and_reloaded_from_the_data(): void
    {
        $this->actingAs($this->jof)->post(route('keuangan.document.store'), [...$this->query, 'format' => 'html', 'content_html' => '<p>CATATAN EDIT JOF</p>'])
            ->assertSessionHasNoErrors();

        $document = KeuanganDocument::query()->sole();
        $this->assertSame('<p>CATATAN EDIT JOF</p>', $document->content_html);

        $this->actingAs($this->jof)->get(route('keuangan.document.show', $this->query))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('has_saved', true)
                ->loadDeferredProps('document', fn (AssertableInertia $page) => $page->where('content', '<p>CATATAN EDIT JOF</p>')));

        // The PDF prints the saved document: a single page.
        $pdf = $this->actingAs($this->jof)->get(route('keuangan.document.pdf', [...$this->query, 'download' => 1]));
        $pdf->assertOk();
        $this->assertStringStartsWith('attachment', (string) $pdf->headers->get('content-disposition'));
        $this->assertSame(1, (new Fpdi)->setSourceFile(StreamReader::createByString((string) $pdf->getContent())));

        // Muat Ulang dari Data rebuilds it from the cash card.
        $this->actingAs($this->jof)->post(route('keuangan.document.regenerate'), $this->query)->assertSessionHasNoErrors();
        $this->assertStringContainsString('id="keu-bukti"', (string) $document->fresh()->content_html);
    }

    public function test_the_manager_and_admin_ul_read_only_and_other_uls_stay_closed(): void
    {
        foreach ([RoleName::ManagerUl, RoleName::AdminUl] as $role) {
            $reader = $this->userWithRole($role, $this->ul);
            $this->actingAs($reader)->get(route('keuangan.document.show', $this->query))->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->where('can_write', false));
            $this->actingAs($reader)->get(route('keuangan.document.pdf', $this->query))->assertOk();
            $this->actingAs($reader)->post(route('keuangan.document.store'), [...$this->query, 'format' => 'html', 'content_html' => '<p>x</p>'])->assertForbidden();
            $this->actingAs($reader)->post(route('keuangan.document.regenerate'), $this->query)->assertForbidden();
        }
        $this->assertSame(0, KeuanganDocument::query()->count());

        $other = ServiceUnit::factory()->create();
        $this->actingAs($this->jof)->get(route('keuangan.document.show', ['ul_id' => $other->id]))->assertForbidden();
        $this->actingAs($this->jof)->post(route('keuangan.document.store'), ['ul_id' => $other->id, ...self::PERIOD, 'format' => 'html', 'content_html' => '<p>x</p>'])->assertForbidden();

        $operator = $this->userWithRole(RoleName::Operator, Unit::factory()->create(['service_unit_id' => $this->ul->id]));
        $this->actingAs($operator)->get(route('keuangan.document.show', $this->query))->assertForbidden();
    }
}
