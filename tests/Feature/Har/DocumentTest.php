<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\HarDocumentRecord;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Database\Seeders\HarMasterSeeder;
use Database\Seeders\HarSeeder;
use Database\Seeders\MachineSeeder;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_laporan_permission_cannot_open_the_document(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('har.laporan.document.edit'))
            ->assertForbidden();
    }

    public function test_regenerate_rebuilds_a_stale_saved_document_from_the_template(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        // A document saved with an old, hand-edited body (no cover, no sections).
        HarDocumentRecord::query()->create([
            'unit_id' => $unit->id, 'type' => 'bulanan', 'month' => 9, 'year' => 2026,
            'format' => 'html', 'content_html' => '<p>Isi lama</p>', 'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('har.laporan.document.regenerate'), ['unit_id' => $unit->id, 'month' => 9, 'year' => 2026])
            ->assertRedirect();

        $record = HarDocumentRecord::query()->where('unit_id', $unit->id)->where('month', 9)->firstOrFail();
        $this->assertStringNotContainsString('Isi lama', (string) $record->content_html);
        $this->assertStringContainsString('har-cover', (string) $record->content_html);
        $this->assertStringContainsString('LAPORAN PEMELIHARAAN', (string) $record->content_html);
        $this->assertStringContainsString('LEMBAR PENGESAHAN', (string) $record->content_html);
        $this->assertStringContainsString('JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6 SITE -KIT', (string) $record->content_html);
        // No hardcoded signer names: an unfilled jabatan prints a blank line.
        $this->assertStringNotContainsString('MUH. ISYAK', (string) $record->content_html);
        $this->assertStringNotContainsString('AMIRULLAH', (string) $record->content_html);
        $this->assertStringNotContainsString('ZULKIFLIN', (string) $record->content_html);
        $this->assertStringContainsString('id="ttd-pengesahan"', (string) $record->content_html);
        $this->assertStringContainsString('RESUME STATISTIK PEMELIHARAAN PEMBANGKIT', (string) $record->content_html);
        $this->assertStringNotContainsString('HERWIN SYAHPUTRA', (string) $record->content_html);
        $this->assertStringContainsString('Project Leader', (string) $record->content_html);
        $this->assertStringContainsString('Koordinator Pemeliharaan', (string) $record->content_html);
        $this->assertStringContainsString('Daftar Isi', (string) $record->content_html);
        $this->assertStringContainsString('JADWAL KEGIATAN PEMELIHARAAN', (string) $record->content_html);
        $this->assertStringContainsString('JADWAL PEMELIHARAAN RUTIN P0 - P5', (string) $record->content_html);
        $this->assertStringContainsString('JADWAL PIKET ONCALL PEMELIHARAAN PEMBANGKIT', (string) $record->content_html);
        $this->assertStringContainsString('JADWAL PIKET PATROL CHECK HARIAN', (string) $record->content_html);
        $this->assertStringContainsString('JADWAL MEETING PEMELIHARAAN PEMBANGKIT', (string) $record->content_html);
        $this->assertStringContainsString('JADWAL PEMBUATAN IK PEMELIHARAAN PEMBANGKIT', (string) $record->content_html);

        // Regenerate Pengusahaan Report — Akses 2, held by TL Pemeliharaan.
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->post(route('har.laporan.pengusahaan.regenerate'), ['unit_id' => $unit->id, 'month' => 9, 'year' => 2026])
            ->assertRedirect();

        $pengRecord = HarDocumentRecord::query()->where('unit_id', $unit->id)->where('type', 'pengusahaan')->where('month', 9)->firstOrFail();
        $this->assertStringContainsString('LAPORAN PENGUSAHAAN', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Executive Summary', (string) $pengRecord->content_html);
        $this->assertStringContainsString('MAINTENANCE SUMMARY', (string) $pengRecord->content_html);
        $this->assertStringContainsString('FMKD-314-10.3.3-A9', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Rekapitulasi WO Terbit dan Complete', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Rekapitulasi Status WO', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Penyelesaian Work Order Task', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Maintenance Mix Bulan ini', (string) $pengRecord->content_html);
        $this->assertStringContainsString('FMKD-314-10.3.3-A11', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Rekap Task WO', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Jumlah Task Preventive', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Har Listrik', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Har Mekanik 1', (string) $pengRecord->content_html);
        $this->assertStringContainsString('FMKD-314-10.3.3-A12', (string) $pengRecord->content_html);
        $this->assertStringContainsString('WO PREVENTIVE MAINTANANCE', (string) $pengRecord->content_html);
        $this->assertStringContainsString('WORK GROUP', (string) $pengRecord->content_html);
        $this->assertStringContainsString('FMKD-314-10.3.3-A13', (string) $pengRecord->content_html);
        $this->assertStringContainsString('WO PREDICTIVE MAINTANANCE', (string) $pengRecord->content_html);
        $this->assertStringContainsString('WO PdM YANG TERBIT BULAN INI', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Waiting Plant Condition', (string) $pengRecord->content_html);
        $this->assertStringContainsString('FMKD-314-10.3.3-A14', (string) $pengRecord->content_html);
        $this->assertStringContainsString('WO CORRECTIVE MAINTANANCE', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Formulir Checklist Prelube Test', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Formulir Checklist Hydrotest', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Formulir Checklist Timing Injection Pump', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Formulir Pengukuran Defleksi Crankshaft', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Formulir Pemeriksaan Kondisi Kekencangan Baut Counter Weight', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Formulir Pengukuran Clearance Valve', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Formulir Pengukuran Tekanan Pembakaran', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Formulir Pengukuran Tekanan Pengabutan Injektor', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Data Pengukuran Arus Kerja Elektro Motor', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Formulir Pengukuran Tekanan Vibrasi', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Formulir Pengukuran Kualitas Pelumas', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Formulir Pengukuran Tegangan Baterai', (string) $pengRecord->content_html);
        $this->assertStringContainsString('Lampiran Foto Kegiatan', (string) $pengRecord->content_html);
    }

    public function test_document_renders_real_maintenance_work_orders_when_present(): void
    {
        $this->seed(OrganizationSeeder::class);
        $this->seed(MachineSeeder::class);
        $this->seed(HarMasterSeeder::class);
        $this->seed(HarSeeder::class);

        $unit = Unit::query()->where('code', 'PLTD-WUAWUA')->firstOrFail();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)
            ->post(route('har.laporan.pengusahaan.regenerate'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026])
            ->assertRedirect();

        $record = HarDocumentRecord::query()->where('unit_id', $unit->id)->where('type', 'pengusahaan')->where('month', 8)->where('year', 2026)->firstOrFail();
        $html = (string) $record->content_html;

        // PM Work Orders
        $this->assertStringContainsString('WO13258', $html);
        $this->assertStringContainsString('PM WUAW INSPECTION COMPRESI', $html);

        // CM Work Orders
        $this->assertStringContainsString('WO13349', $html);
        $this->assertStringContainsString('PERBAIKAN KWH PELANGGAN', $html);

        // Waiting Work Orders and CM notes
        $this->assertStringContainsString('WO11369', $html);
        $this->assertStringContainsString('Waiting Shutdown', $html);
        $this->assertStringContainsString('WO10932', $html);
        $this->assertStringContainsString('Menunggu kajian dari tim Engineering UPKD', $html);
    }

    public function test_regenerate_requires_write_permission(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->create(['service_unit_id' => $serviceUnit->id]);

        // Manager UL may view the report but not write it.
        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $serviceUnit))
            ->post(route('har.laporan.document.regenerate'), ['unit_id' => $unit->id, 'month' => 9, 'year' => 2026])
            ->assertForbidden();
    }

    public function test_tl_pemeliharaan_sees_the_generated_document(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->get(route('har.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/laporan/document')
                ->where('document_number', 'FMKD-314-10.3.3')
                ->where('format', 'html')
                ->where('has_saved', false)
                ->where('can_write', true)
                ->has('content')
                ->has('grid'),
            );
    }

    public function test_tl_pemeliharaan_can_save_the_document(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.laporan.document.store'), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'format' => 'html',
            'content_html' => '<p>Laporan diedit</p>',
        ])->assertRedirect();

        $record = HarDocumentRecord::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame('html', $record->format);
        $this->assertStringContainsString('Laporan diedit', (string) $record->content_html);
        $this->assertSame('FMKD-314-10.3.3', $record->document_number);
    }

    public function test_a_saved_document_is_reloaded_on_next_open(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        // Save through the endpoint so the document is stamped with the current
        // template version (a document saved against the current layout reloads).
        $this->actingAs($user)->post(route('har.laporan.document.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'format' => 'html', 'content_html' => '<p>Versi tersimpan</p>',
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('har.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->where('content', '<p>Versi tersimpan</p>'),
            );
    }

    public function test_manager_ul_can_view_but_not_save(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->create(['service_unit_id' => $serviceUnit->id]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('har.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));

        $this->actingAs($manager)->post(route('har.laporan.document.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'format' => 'html', 'content_html' => '<p>x</p>',
        ])->assertForbidden();

        $this->assertDatabaseCount('har_document_records', 0);
    }

    public function test_the_document_exports_to_pdf(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->get(route('har.laporan.document.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_a_document_cannot_be_opened_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $ownUnit))
            ->get(route('har.laporan.document.edit', ['unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026]))
            ->assertForbidden();
    }

    public function test_document_renders_the_unit_signers_by_jabatan_without_signatures_before_final(): void
    {
        Storage::fake('public');
        $serviceUnit = ServiceUnit::factory()->create(['name' => 'UL PLTD Poasia']);
        $unit = Unit::factory()->create(['service_unit_id' => $serviceUnit->id, 'name' => 'PLTD Poasia']);
        $signature = UploadedFile::fake()->image('ttd.png')->store('signatures', 'public');

        $signers = [
            'Team Leader Pemeliharaan' => 'Ahmad TL Har',
            'Koordinator Pemeliharaan' => 'Budi Koordinator Har',
            'Project Leader' => 'Dedi Project Leader',
            'Office Pemeliharaan' => 'Eka Office Har',
        ];
        foreach ($signers as $position => $name) {
            Employee::factory()->create(['unit_id' => $unit->id, 'service_unit_id' => null, 'name' => $name, 'position' => $position, 'signature_path' => $signature, 'is_active' => true]);
        }
        Employee::factory()->create(['unit_id' => null, 'service_unit_id' => $serviceUnit->id, 'name' => 'Cahyo Manager UL', 'position' => 'Manager UL', 'signature_path' => $signature, 'is_active' => true]);
        // A look-alike jabatan in the unit is not a signer.
        Employee::factory()->create(['unit_id' => $unit->id, 'name' => 'Staf Bukan Penanda Tangan', 'position' => 'Staf Pemeliharaan', 'is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->post(route('har.laporan.document.regenerate'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026])
            ->assertRedirect();

        $html = (string) HarDocumentRecord::query()->where('unit_id', $unit->id)->where('month', 8)->firstOrFail()->content_html;

        // Lembar Pengesahan in workflow order: Memeriksa Koordinator Pemeliharaan · Menyetujui TL Pemeliharaan · Mengesahkan Manager UL
        $pengesahan = $this->block($html, 'ttd-pengesahan');
        $this->assertMatchesRegularExpression('/MEMERIKSA.*Koordinator Pemeliharaan.*BUDI KOORDINATOR HAR.*MENYETUJUI.*Team Leader Pemeliharaan.*AHMAD TL HAR.*MENGESAHKAN.*Manager UL.*CAHYO MANAGER UL/is', $pengesahan);

        // Tanda tangan laporan (Resume Statistik): Project Leader + Office Pemeliharaan
        $laporan = $this->block($html, 'ttd-laporan');
        $this->assertStringContainsString('DEDI PROJECT LEADER', $laporan);
        $this->assertStringContainsString('EKA OFFICE HAR', $laporan);
        $this->assertStringContainsString('RESUME STATISTIK PEMELIHARAAN PEMBANGKIT', $html);

        $this->assertStringNotContainsString('STAF BUKAN PENANDA TANGAN', $pengesahan.$laporan);
        // Signatures are printed only once the report is FINAL.
        $this->assertStringNotContainsString('data:image/png;base64,', $pengesahan.$laporan);
    }

    private function block(string $html, string $id): string
    {
        $this->assertSame(1, preg_match('/<table\b[^>]*id="'.$id.'".*?<\/table>/s', $html, $match), $id);

        return $match[0];
    }

    public function test_tl_pemeliharaan_sees_the_laporan_pengusahaan_document(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.laporan.pengusahaan.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/laporan/pengusahaan')
                ->where('document_number', 'FMKD-314-10.3.3')
                ->where('format', 'html')
                ->where('has_saved', false)
                ->where('can_write', true)
                ->where('content', fn (string $html) => str_contains($html, 'har-logos-table')
                    && str_contains($html, '/logo/sidebar-logo.png')
                    && str_contains($html, '/logo/k3.png')
                )
                ->has('grid'),
            );
    }

    public function test_tl_pemeliharaan_can_save_laporan_pengusahaan(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.laporan.pengusahaan.store'), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'format' => 'html',
            'content_html' => '<p>Pengusahaan diedit</p>',
        ])->assertRedirect();

        $record = HarDocumentRecord::query()->where('unit_id', $unit->id)->where('type', 'pengusahaan')->firstOrFail();
        $this->assertSame('html', $record->format);
        $this->assertStringContainsString('Pengusahaan diedit', (string) $record->content_html);
    }

    public function test_laporan_pengusahaan_exports_to_pdf(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.laporan.pengusahaan.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
