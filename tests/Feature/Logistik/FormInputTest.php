<?php

namespace Tests\Feature\Logistik;

use App\Enums\RoleName;
use App\Models\LogistikFormRow;
use App\Models\Unit;
use App\Support\LogistikForms\KondisiStokForm;
use App\Support\LogistikForms\LogistikForms;
use App\Support\LogistikForms\PendukungForm;
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
                    ->component('logistik/input/form')
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
