<?php

namespace Tests\Feature\Keuangan;

use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Models\CashCardEntry;
use App\Models\CashCardReport;
use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Keuangan\CashCardDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Administrasi & Keuangan per UL (user, 2026-11-20): the JOF Administrasi
 * Umum keeps the Laporan Realisasi Cash Card and the Master Supplier; each row
 * prints a Bukti Penerimaan Kas Kecil + Lampiran with its photos and the
 * supplier's KTP, e-mail & NPWP.
 */
class CashCardTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const PERIOD = ['month' => 9, 'year' => 2026];

    private ServiceUnit $ul;

    private User $jof;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        Storage::fake('public');

        $this->ul = ServiceUnit::factory()->create(['name' => 'UL PLTD Poasia']);
        $this->jof = $this->userWithRole(RoleName::JofAdministrasiUmum, $this->ul);
        Employee::factory()->create(['service_unit_id' => $this->ul->id, 'unit_id' => null, 'name' => 'Ramlin Jamaludin', 'position' => EmployeePosition::JofAdministrasiUmum->value, 'user_id' => $this->jof->id, 'is_active' => true]);
        Employee::factory()->create(['service_unit_id' => $this->ul->id, 'unit_id' => null, 'name' => 'Zulkiflin', 'position' => EmployeePosition::ManagerUl->value, 'is_active' => true]);
    }

    public function test_the_jof_keeps_the_master_supplier_with_the_ktp_photo(): void
    {
        $this->actingAs($this->jof)->post(route('keuangan.supplier.store'), [
            'ul_id' => $this->ul->id, 'name' => 'TOKO DILLA JAYA', 'email' => 'jurmal1778@gmail.com', 'npwp' => '01.234.567.8-901.000',
            'ktp' => UploadedFile::fake()->image('ktp.jpg', 600, 380),
        ])->assertSessionHasNoErrors();

        $supplier = Supplier::query()->sole();
        $this->assertSame([$this->ul->id, 'TOKO DILLA JAYA', 'jurmal1778@gmail.com', '01.234.567.8-901.000'], [$supplier->service_unit_id, $supplier->name, $supplier->email, $supplier->npwp]);
        Storage::disk('public')->assertExists($supplier->ktp_path);

        $this->actingAs($this->jof)->get(route('keuangan.supplier.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('keuangan/supplier/index')->has('suppliers', 1)->where('can_manage', true));

        // Used by a cash card row → only deactivated; otherwise deleted.
        $report = CashCardReport::query()->create(['service_unit_id' => $this->ul->id, ...self::PERIOD, 'saldo_awal' => 0]);
        $report->entries()->create(['supplier_id' => $supplier->id, 'pengeluaran' => 1000]);
        $this->actingAs($this->jof)->delete(route('keuangan.supplier.destroy', $supplier))->assertSessionHasNoErrors();
        $this->assertFalse($supplier->fresh()->is_active);

        $unused = Supplier::query()->create(['service_unit_id' => $this->ul->id, 'name' => 'TOKO LAIN']);
        $this->actingAs($this->jof)->delete(route('keuangan.supplier.destroy', $unused));
        $this->assertNull($unused->fresh());
    }

    public function test_the_cash_card_is_saved_with_its_rows_saldo_and_photos(): void
    {
        $dilla = Supplier::query()->create(['service_unit_id' => $this->ul->id, 'name' => 'DILLA JAYA', 'email' => 'jurmal1778@gmail.com']);
        $foreign = Supplier::query()->create(['service_unit_id' => ServiceUnit::factory()->create()->id, 'name' => 'TOKO UL LAIN']);

        $this->actingAs($this->jof)->post(route('keuangan.cash-card.store'), [
            'ul_id' => $this->ul->id, ...self::PERIOD, 'saldo_awal' => 0, 'rows' => [
                ['tanggal' => '2026-09-01', 'period' => 1, 'no_entry' => 1, 'uraian' => 'Penarikan Dana Cash Card Per. I September 2026', 'penerimaan' => 56500000],
                ['tanggal' => '2026-09-04', 'period' => 5, 'no_entry' => 5, 'uraian' => 'By. Perlengkapan Kantor (ATK dll). Poasia', 'supplier_id' => $dilla->id, 'pengeluaran' => 925500,
                    'kode_perkiraan' => '5.1.01', 'files' => [UploadedFile::fake()->image('nota.jpg'), UploadedFile::fake()->image('barang.jpg')]],
                ['uraian' => '', 'penerimaan' => 0, 'pengeluaran' => 0],
                ['uraian' => 'By. lain', 'supplier_id' => $foreign->id, 'pengeluaran' => 1000],
            ],
        ])->assertSessionHasNoErrors();

        $report = CashCardReport::query()->sole();
        $entries = $report->entries()->get();
        $this->assertCount(3, $entries); // the blank row is not saved
        $this->assertSame($dilla->id, $entries[1]->supplier_id);
        $this->assertNull($entries[2]->supplier_id); // a supplier of another UL is ignored
        $this->assertCount(2, $entries[1]->photos);
        Storage::disk('public')->assertExists($entries[1]->photos);

        // Running saldo, totals and the printed uraian.
        $ledger = app(CashCardDocument::class)->ledger($report);
        $this->assertSame([56500000.0, 55574500.0, 55573500.0], array_column($ledger['rows'], 'saldo'));
        $this->assertSame(55573500.0, $ledger['saldo_akhir']);
        $this->assertSame('By. Perlengkapan Kantor (ATK dll). Poasia / DILLA JAYA', $ledger['rows'][1]['uraian']);
        $this->assertSame('Sembilan Ratus Dua Puluh Lima Ribu Lima Ratus Rupiah', CashCardDocument::terbilang(925500));

        // Saving again: one photo kept, the other removed from the disk.
        [$keep, $drop] = $entries[1]->photos;
        $this->actingAs($this->jof)->post(route('keuangan.cash-card.store'), [
            'ul_id' => $this->ul->id, ...self::PERIOD, 'saldo_awal' => 0, 'rows' => [
                ['uraian' => 'By. Perlengkapan Kantor', 'supplier_id' => $dilla->id, 'pengeluaran' => 925500, 'photos_keep' => [$keep, 'keuangan/lain/x.jpg']],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$keep], CashCardEntry::query()->sole()->photos);
        Storage::disk('public')->assertMissing($drop);

        $this->actingAs($this->jof)->get(route('keuangan.cash-card.index', ['ul_id' => $this->ul->id, ...self::PERIOD]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('keuangan/cash-card/index')
                ->where('has_saved', true)->has('entries', 1)->where('entries.0.supplier_id', $dilla->id)->where('can_write', true));
    }

    public function test_the_laporan_and_each_bukti_with_its_lampiran_print(): void
    {
        $dilla = Supplier::query()->create(['service_unit_id' => $this->ul->id, 'name' => 'DILLA JAYA', 'email' => 'jurmal1778@gmail.com', 'npwp' => '01.234',
            'ktp_path' => UploadedFile::fake()->image('ktp.jpg')->store('keuangan/supplier/'.$this->ul->id, 'public')]);
        $report = CashCardReport::query()->create(['service_unit_id' => $this->ul->id, ...self::PERIOD, 'saldo_awal' => 0]);
        $report->entries()->create(['sort_order' => 0, 'uraian' => 'Penarikan Dana', 'penerimaan' => 56500000]);
        $entry = $report->entries()->create(['sort_order' => 1, 'no_entry' => 5, 'uraian' => 'By. ATK', 'supplier_id' => $dilla->id, 'pengeluaran' => 925500,
            'photos' => [UploadedFile::fake()->image('nota.jpg')->store('keuangan/cash-card/'.$this->ul->id.'/2026-09', 'public')]]);
        $query = ['ul_id' => $this->ul->id, ...self::PERIOD];

        $laporan = $this->actingAs($this->jof)->get(route('keuangan.cash-card.pdf', $query));
        $laporan->assertOk()->assertHeader('content-type', 'application/pdf');

        // One row: the bukti page then its lampiran page.
        $bukti = $this->actingAs($this->jof)->get(route('keuangan.cash-card.bukti', [...$query, 'entry' => $entry->id]));
        $bukti->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(2, (new Fpdi)->setSourceFile(StreamReader::createByString((string) $bukti->getContent())));
        $data = app(CashCardDocument::class)->bukti($report->load('serviceUnit'), [app(CashCardDocument::class)->ledger($report)['rows'][1]]);
        $this->assertSame([5, 'DILLA JAYA', 'jurmal1778@gmail.com', 'Ramlin Jamaludin', 'Zulkiflin'], [$data['items'][0]['nomor'], $data['items'][0]['terima_dari'], $data['items'][0]['supplier']['email'], $data['signers']['jof'], $data['signers']['manager']]);
        $this->assertNotNull($data['items'][0]['supplier']['ktp']);
        $this->assertCount(1, $data['items'][0]['photos']);

        // Every row at once: 2 pages each.
        $all = $this->actingAs($this->jof)->get(route('keuangan.cash-card.bukti', $query));
        $this->assertSame(4, (new Fpdi)->setSourceFile(StreamReader::createByString((string) $all->getContent())));
    }

    public function test_access_follows_the_role_and_the_ul(): void
    {
        $other = ServiceUnit::factory()->create();
        $query = ['ul_id' => $this->ul->id, ...self::PERIOD];

        // Another UL stays closed to the JOF.
        $this->actingAs($this->jof)->get(route('keuangan.cash-card.index', ['ul_id' => $other->id]))->assertForbidden();
        $this->actingAs($this->jof)->post(route('keuangan.cash-card.store'), ['ul_id' => $other->id, ...self::PERIOD, 'rows' => []])->assertForbidden();

        // The Manager UL & Admin UL read, never write.
        foreach ([RoleName::ManagerUl, RoleName::AdminUl] as $role) {
            $reader = $this->userWithRole($role, $this->ul);
            $this->actingAs($reader)->get(route('keuangan.cash-card.index', $query))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('can_write', false));
            $this->actingAs($reader)->post(route('keuangan.cash-card.store'), [...$query, 'rows' => []])->assertForbidden();
            $this->actingAs($reader)->post(route('keuangan.supplier.store'), ['ul_id' => $this->ul->id, 'name' => 'X'])->assertForbidden();
        }

        // Field roles have no Administrasi & Keuangan.
        $operator = $this->userWithRole(RoleName::Operator, Unit::factory()->create(['service_unit_id' => $this->ul->id]));
        $this->actingAs($operator)->get(route('keuangan.cash-card.index', $query))->assertForbidden();
        $this->actingAs($operator)->get(route('keuangan.supplier.index', $query))->assertForbidden();
    }
}
