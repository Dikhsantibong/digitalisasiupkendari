<?php

namespace Tests\Feature\Pdm;

use App\Enums\RoleName;
use App\Models\PdmJadwalMeta;
use App\Models\PdmKesiapanApd;
use App\Models\Unit;
use App\Support\PdmKesiapanApdForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class KesiapanApdInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_page_starts_with_the_default_apd_items(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.input.kesiapan-apd.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('pdm/input/kesiapan-apd/index')
                ->has('rows', count(PdmKesiapanApdForm::DEFAULT_ITEMS))
                ->where('rows.0.inspeksi', 'Sarung tangan kain bintik')
                ->where('rows.13.inspeksi', 'Respirator')
                ->where('has_saved', false)
                ->where('can_write', true)
                ->where('options.answers.cara_kerja', ['Ergonomi', 'Tidak Ergonomi'])
                ->missing('options.employees')
                ->where('meta', ['catatan' => '']),
            );
    }

    public function test_the_form_is_saved_with_its_catatan_and_without_signatures(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->post(route('pdm.input.kesiapan-apd.store'), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
                'rows' => [
                    ['kelompok' => 'Alat Pelindung Diri (APD)', 'inspeksi' => 'Helm', 'jumlah' => 20, 'satuan' => 'Buah', 'kelayakan_apd' => 'Layak', 'p3k_kotak' => 'Ada', 'cara_kerja' => 'Ergonomi'],
                    ['kelompok' => '', 'inspeksi' => 'Ear plug', 'jumlah' => 8, 'satuan' => 'Buah', 'kelayakan_apd' => 'Tidak Layak'],
                ],
                'meta' => ['catatan' => 'Stok ear plug perlu ditambah', 'dibuat_nama' => 'Diabaikan'],
            ])
            ->assertRedirect();

        $rows = PdmKesiapanApd::query()->where('unit_id', $unit->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(20, $rows[0]->jumlah);
        $this->assertSame('Ergonomi', $rows[0]->cara_kerja);
        $this->assertSame('Alat Pelindung Diri (APD)', $rows[1]->kelompok);
        $this->assertSame('Tidak Layak', $rows[1]->kelayakan_apd);

        $meta = PdmJadwalMeta::query()->where('unit_id', $unit->id)->where('type', 'kesiapan-apd')->firstOrFail();
        $this->assertSame('Stok ear plug perlu ditambah', $meta->catatan);
        $this->assertNull($meta->dibuat_nama);
    }

    public function test_an_unknown_answer_is_rejected(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->post(route('pdm.input.kesiapan-apd.store'), [
                'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
                'rows' => [['inspeksi' => 'Helm', 'kelayakan_apd' => 'Bagus']],
            ])
            ->assertSessionHasErrors('rows.0.kelayakan_apd');
    }

    public function test_the_pdf_is_landscape(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $unit))
            ->get(route('pdm.input.kesiapan-apd.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $this->assertSame('L', $reader->getTemplateSize($reader->importPage(1))['orientation']);
    }

    public function test_a_role_without_pdm_input_cannot_save(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('pdm.input.kesiapan-apd.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => []])
            ->assertForbidden();
    }

    public function test_a_foreign_unit_cannot_be_opened(): void
    {
        $own = Unit::factory()->create(['is_active' => true]);
        $foreign = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, $own))
            ->get(route('pdm.input.kesiapan-apd.index', ['unit_id' => $foreign->id]))
            ->assertForbidden();
    }
}
