<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3DocumentRecord;
use App\Models\K3PengusahaanBukuTamu;
use App\Models\K3PengusahaanTimeFrame;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class LaporanPengusahaanDocumentTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_laporan_permission_cannot_open_pengusahaan(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('k3.laporan.pengusahaan.edit'))
            ->assertForbidden();
    }

    public function test_tl_sees_the_generated_pengusahaan_document(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.laporan.pengusahaan.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/laporan/pengusahaan')
                ->where('format', 'html')
                ->where('has_saved', false)
                ->where('can_write', true)
                ->has('content')
                ->has('grid'),
            );
    }

    public function test_tl_can_save_pengusahaan_document(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.laporan.pengusahaan.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'format' => 'html', 'content_html' => '<div class="seg-cover-info">Laporan Pengusahaan diedit</div>',
        ])->assertRedirect();

        $record = K3DocumentRecord::query()
            ->where('unit_id', $unit->id)
            ->where('type', 'pengusahaan')
            ->firstOrFail();

        $this->assertSame('html', $record->format);
        $this->assertSame('pengusahaan', $record->type);
        $this->assertStringContainsString('Laporan Pengusahaan diedit', (string) $record->content_html);
    }

    public function test_tl_can_regenerate_pengusahaan_document(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.laporan.pengusahaan.regenerate'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
        ])->assertRedirect();

        $record = K3DocumentRecord::query()
            ->where('unit_id', $unit->id)
            ->where('type', 'pengusahaan')
            ->firstOrFail();

        $this->assertStringContainsString('pc-cover', (string) $record->content_html);
        $this->assertStringContainsString('background/bg-login.jpeg', (string) $record->content_html);
        $this->assertStringContainsString('seg-tables-wide', (string) $record->content_html);
        // The Laporan Pengusahaan K3 has no Lembar Pengesahan.
        $this->assertStringNotContainsString('LEMBAR PENGESAHAN', (string) $record->content_html);
    }

    public function test_the_pengusahaan_document_exports_to_pdf_with_merged_orientations(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.laporan.pengusahaan.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertNotEmpty($response->getContent());
    }

    public function test_a_pengusahaan_document_cannot_be_opened_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $ownUnit))
            ->get(route('k3.laporan.pengusahaan.edit', ['unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026]))
            ->assertForbidden();
    }

    public function test_the_report_is_built_from_every_pengusahaan_k3_input_and_formulir(): void
    {
        $unit = Unit::factory()->create();
        $tl = $this->userWithRole(RoleName::TeamLeaderK3, $unit);
        $bukuTamu = K3PengusahaanBukuTamu::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 8]);
        $bukuTamu->items()->create(['no_urut' => 1, 'tanggal' => '2026-08-04', 'jumlah_kehadiran_tamu' => 7, 'tamu_pln' => 3, 'instansi' => 2, 'kontraktor' => 1, 'lainnya' => 1, 'keterangan' => 'Kunjungan audit SMK3']);
        K3PengusahaanTimeFrame::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'uraian_pelaporan' => 'Inspeksi Rambu Mingguan', 'rencana' => [3, 10], 'realisasi' => [3]]);

        $this->actingAs($tl)
            ->get(route('k3.laporan.pengusahaan.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(function ($page): void {
                $content = (string) $page->toArray()['props']['content'];
                $gridText = json_encode($page->toArray()['props']['grid']);

                // Every Akses 2 form has its section, starting with the recap.
                foreach (['Rekapitulasi Pengisian', 'Time Frame Kinerja K3', 'Laporan Mutasi Buku Tamu', 'Inspeksi APAR', 'Pemeriksaan Kotak P3K', 'Daftar Sertifikasi Peralatan', 'Metode Pengujian', 'Evaluasi Hasil Pengujian'] as $title) {
                    $this->assertStringContainsString($title, $content);
                }
                $this->assertStringContainsString('Kunjungan audit SMK3', $content);
                $this->assertStringContainsString('Inspeksi Rambu Mingguan', $content);
                $this->assertStringContainsString('Kunjungan audit SMK3', (string) $gridText);
                // The Akses 1 sections are gone.
                $this->assertStringNotContainsString('Resume Kinerja', $content);
            });
    }

    /**
     * @return list<array{0: string}>
     */
    public static function pengusahaanForms(): array
    {
        return array_map(fn (string $model): array => [$model], [
            'K3PengusahaanAlatTanggapDarurat', 'K3PengusahaanAparApab', 'K3PengusahaanApat', 'K3PengusahaanApelKeamanan',
            'K3PengusahaanBukuTamu', 'K3PengusahaanCertificate', 'K3PengusahaanEmergencyFacility', 'K3PengusahaanEvaluasiPengujian',
            'K3PengusahaanFireAlarm', 'K3PengusahaanHydrant', 'K3PengusahaanInspeksiRambu', 'K3PengusahaanInspeksiTempatKerja',
            'K3PengusahaanInventarisApd', 'K3PengusahaanJamKerjaBulanan', 'K3PengusahaanJamKerja', 'K3PengusahaanKecelakaanInstalasi',
            'K3PengusahaanKecelakaanMasyarakat', 'K3PengusahaanLaporanCctv', 'K3PengusahaanMetodePengujian', 'K3PengusahaanPatrolSecurity',
            'K3PengusahaanPemeriksaanP3k', 'K3PengusahaanTimeFrame',
        ]);
    }

    #[DataProvider('pengusahaanForms')]
    public function test_a_filled_pengusahaan_form_renders_in_the_document_and_the_pdf(string $model): void
    {
        $unit = Unit::factory()->create();
        $class = 'App\\Models\\'.$model;
        $class::factory()->create(['unit_id' => $unit->id]);
        $tl = $this->userWithRole(RoleName::TeamLeaderK3, $unit);
        $query = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($tl)->get(route('k3.laporan.pengusahaan.edit', $query))->assertOk();
        $this->actingAs($tl)->get(route('k3.laporan.pengusahaan.pdf', $query))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
