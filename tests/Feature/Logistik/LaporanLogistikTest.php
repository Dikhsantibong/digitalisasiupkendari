<?php

namespace Tests\Feature\Logistik;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\LogistikDocumentRecord;
use App\Models\LogistikRekomendasi;
use App\Models\Unit;
use App\Support\LogistikForms\InventarisLainnyaForm;
use App\Support\LogistikForms\LogistikForms;
use App\Support\LogistikJadwal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class LaporanLogistikTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_laporan_page_shows_the_filters(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $unit))
            ->get(route('logistik.laporan.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('logistik/laporan/index')
                ->where('filters', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]),
            );
    }

    public function test_the_document_holds_the_pengesahan_and_every_jadwal_and_input_table(): void
    {
        $unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Containerized Poasia']);
        Employee::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'name' => 'Ridwan Bahudi', 'position' => 'Office Logistik']);
        Employee::factory()->create(['unit_id' => $unit->id, 'is_active' => true, 'name' => 'Amirullah', 'position' => 'Koordinator Logistik']);
        LogistikRekomendasi::factory()->create(['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'uraian' => 'Ketersediaan stok material khusus']);

        $keys = [
            ...LogistikJadwal::keysFor('jadwal'),
            'rekomendasi',
            ...LogistikJadwal::keysFor('input'),
            ...array_map(fn ($form): string => 'form-'.$form->key(), LogistikForms::all()),
        ];

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $unit))
            ->get(route('logistik.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('logistik/laporan/document')
                ->where('has_saved', false)
                ->where('content', function (string $content) use ($keys): bool {
                    foreach ($keys as $key) {
                        if (! str_contains($content, 'id="part-'.$key.'"')) {
                            return false;
                        }
                    }

                    return str_contains($content, 'LEMBAR PENGESAHAN')
                        && str_contains($content, 'AMIRULLAH')
                        && str_contains($content, 'RIDWAN BAHUDI')
                        && strpos($content, 'id="ttd-pengesahan"') < strpos($content, 'id="sec-3"')
                        && strpos($content, 'id="ttd-laporan"') > strpos($content, 'id="sec-ttd"')
                        && str_contains($content, 'Kendari, 31 Agustus 2026')
                        && str_contains($content, 'Ketersediaan stok material khusus')
                        && strpos($content, 'id="sec-2"') < strpos($content, 'id="sec-3"');
                })
                ->has('grid.rows'),
            );
    }

    public function test_data_saved_in_the_new_jadwal_and_input_pages_reaches_the_document(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        // Jadwal Pemeliharaan & Jadwal Piket Patrol Check (sheets).
        $this->actingAs($user)->post(route('logistik.jadwal.sheet.store', ['jadwal' => 'pemeliharaan']), $period + [
            'rows' => [['nama' => 'PELAKSANA PEMELIHARAAN UJI', 'days' => ['3' => 'D', '10' => 'D'], 'target' => 5]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('logistik.jadwal.sheet.store', ['jadwal' => 'piket-patrol-check']), $period + [
            'rows' => [['nama' => 'PELAKSANA PIKET UJI', 'days' => ['7' => 'D'], 'target' => 5]],
        ])->assertSessionHasNoErrors();

        // Laporan Inventaris Lainnya & Laporan Permit To Work (forms).
        $this->actingAs($user)->post(route('logistik.input.form.store', ['form' => 'inventaris-lainnya']), $period + ['rows' => [
            ['section' => 'inventaris', 'data' => ['uraian' => 'PRINTER GUDANG UJI', 'merek' => 'Epson', 'jumlah' => 2, 'satuan' => 'Unit', 'target' => 4, 'd1_n' => 1, 'd2_t' => 1]],
        ]])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('logistik.input.form.store', ['form' => 'permit-to-work']), $period + ['rows' => [
            ['section' => 'ptw', 'data' => ['uraian' => 'PTW PENGELASAN TANGKI UJI', 'tanggal' => '2026-08-12', 'open' => 1]],
        ]])->assertSessionHasNoErrors();

        $props = [];
        $this->actingAs($user)
            ->get(route('logistik.laporan.document.edit', $period))
            ->assertOk()
            ->assertInertia(function ($page) use (&$props): void {
                $props = $page->toArray()['props'];
            });
        $content = $props['content'];
        $section = function (string $id) use ($content): string {
            $start = strpos($content, 'id="'.$id.'"');
            $this->assertNotFalse($start, $id);
            $end = strpos($content, 'class="lg-section', $start + 1);

            return substr($content, $start, $end === false ? null : $end - $start);
        };

        $pemeliharaan = $section('part-pemeliharaan');
        $this->assertStringContainsString('JADWAL PEMELIHARAAN LOGISTIK DAN GUDANG', $pemeliharaan);
        $this->assertStringContainsString('PELAKSANA PEMELIHARAAN UJI', $pemeliharaan);

        $piket = $section('part-piket-patrol-check');
        $this->assertStringContainsString('JADWAL PIKET PATROL CHECK LOGISTIK &amp; GUDANG', $piket);
        $this->assertStringContainsString('PELAKSANA PIKET UJI', $piket);

        $inventaris = $section('part-form-inventaris-lainnya');
        $this->assertStringContainsString('LAPORAN INVENTARIS LAINNYA LOGISTIK &amp; GUDANG', $inventaris);
        $this->assertStringContainsString('PRINTER GUDANG UJI', $inventaris);
        $this->assertStringContainsString('Epson', $inventaris);
        $this->assertStringContainsString('✓', $inventaris);
        $this->assertStringContainsString('50%', $inventaris);

        $ptw = $section('part-form-permit-to-work');
        $this->assertStringContainsString('LAPORAN PERMIT TO WORK PEMBANGKIT', $ptw);
        $this->assertStringContainsString('PTW PENGELASAN TANGKI UJI', $ptw);
        $this->assertStringContainsString('12/08/2026', $ptw);

        // Saved data replaces the page's default rows — nothing from the template is mixed in.
        $this->assertStringNotContainsString('OFFICER LOGISTIK &amp; GUDANG', $pemeliharaan);
        $this->assertStringNotContainsString('OFFICER LOGISTIK &amp; GUDANG', $piket);
        foreach (InventarisLainnyaForm::URAIAN as $default) {
            $this->assertDoesNotMatchRegularExpression('/>\s*'.preg_quote($default, '/').'\s*</', $inventaris, $default);
        }

        // The Excel (grid) mode carries the same saved data.
        $grid = (string) json_encode($props['grid']['rows'], JSON_UNESCAPED_UNICODE);
        foreach (['PELAKSANA PEMELIHARAAN UJI', 'PELAKSANA PIKET UJI', 'PRINTER GUDANG UJI', 'PTW PENGELASAN TANGKI UJI'] as $text) {
            $this->assertStringContainsString($text, $grid);
        }
    }

    public function test_the_edited_document_is_saved_and_can_be_regenerated(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderLogistik, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post(route('logistik.laporan.document.store'), $period + ['format' => 'html', 'content_html' => '<div class="lg-section"><p>Edit manual</p></div>'])
            ->assertRedirect();

        $record = LogistikDocumentRecord::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertStringContainsString('Edit manual', (string) $record->content_html);

        $this->actingAs($user)->post(route('logistik.laporan.document.regenerate'), $period)->assertRedirect();
        $this->assertStringContainsString('id="part-kegiatan"', (string) $record->fresh()->content_html);
    }

    public function test_the_pdf_merges_portrait_and_landscape_pages(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, $unit))
            ->get(route('logistik.laporan.document.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'download' => 1]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));

        $reader = new Fpdi;
        $pages = $reader->setSourceFile(StreamReader::createByString((string) $response->getContent()));
        $this->assertGreaterThan(15, $pages);
        $orientations = collect(range(1, $pages))->map(fn (int $page): string => $reader->getTemplateSize($reader->importPage($page))['orientation']);
        $this->assertSame('P', $orientations->first());
        $this->assertContains('L', $orientations->all());
    }

    public function test_a_role_without_logistik_write_cannot_save_the_document(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('logistik.laporan.document.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'format' => 'html', 'content_html' => '<p>x</p>'])
            ->assertForbidden();
    }
}
