<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Http\Controllers\Har\LogbookMutasiController;
use App\Models\Employee;
use App\Models\HarLogbookMutasi;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class LogbookMutasiTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Unit $unit, string $tanggal = '2026-08-31'): array
    {
        return [
            'unit_id' => $unit->id,
            'tanggal' => $tanggal,
            'absensi' => [
                ['nama' => 'Amirullah', 'jabatan' => 'Koord HAR', 'keterangan' => 'Hadir', 'paraf' => null],
                ['nama' => '', 'jabatan' => 'Mekanik', 'keterangan' => null, 'paraf' => null],
            ],
            'apd' => [['item' => 'HELM', 'keterangan' => 'Lengkap']],
            'rutin' => [['uraian' => 'PATROL CEK', 'keterangan' => 'Normal']],
            'non_rutin' => [['uraian' => 'Ganti filter oli mesin #2', 'keterangan' => 'Selesai']],
            'kondisi_k3' => [['uraian' => 'Ceceran oli di area mesin', 'keterangan' => 'Dibersihkan']],
        ];
    }

    public function test_the_page_prefills_staff_apd_and_routine_job_for_a_new_logbook(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        Employee::factory()->forUnit($unit)->create(['name' => 'Mekanik Satu', 'division' => 'pemeliharaan', 'is_active' => true]);
        Employee::factory()->forUnit($unit)->create(['name' => 'Operator Satu', 'division' => 'operasi', 'is_active' => true]);
        HarLogbookMutasi::query()->create([...$this->payload($unit, '2026-07-10'), 'year' => 2026, 'month' => 7]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.formulir.logbook-mutasi.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/formulir/logbook-mutasi/index')
                ->has('logbooks', 0)
                ->where('logbook', null)
                ->has('template.absensi', 1)
                ->where('template.absensi.0.nama', 'Mekanik Satu')
                ->where('template.apd.0.item', 'HELM')
                ->where('template.rutin.0.uraian', 'PATROL CEK'),
            );
    }

    public function test_a_logbook_is_saved_once_per_date_and_blank_rows_are_dropped(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.formulir.logbook-mutasi.store'), $this->payload($unit))
            ->assertRedirect(route('har.formulir.logbook-mutasi.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'tanggal' => '2026-08-31']))
            ->assertSessionHasNoErrors();

        $logbook = HarLogbookMutasi::query()->sole();
        $this->assertSame(8, $logbook->month);
        $this->assertCount(1, $logbook->absensi);
        $this->assertSame('Ganti filter oli mesin #2', $logbook->non_rutin[0]['uraian']);

        $payload = $this->payload($unit);
        $payload['non_rutin'] = [];
        $this->actingAs($user)->post(route('har.formulir.logbook-mutasi.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame(1, HarLogbookMutasi::query()->count());
        $this->assertSame([], $logbook->fresh()->non_rutin);

        $this->actingAs($user)
            ->get(route('har.formulir.logbook-mutasi.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'tanggal' => '2026-08-31']))
            ->assertInertia(fn ($page) => $page->has('logbooks', 1)->where('logbook.tanggal', '2026-08-31'));
    }

    public function test_the_pdf_prints_every_section(): void
    {
        $unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Containerized Poasia 6 Site']);
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);
        $this->actingAs($user)->post(route('har.formulir.logbook-mutasi.store'), $this->payload($unit))->assertSessionHasNoErrors();
        $logbook = HarLogbookMutasi::query()->sole();

        [$view, $data] = app(LogbookMutasiController::class)->pdfView($unit, collect([$logbook]));
        $html = view($view, $data)->render();
        foreach (['LOGBOOK MUTASI HARIAN TIM PEMELIHARAAN', 'SENIN, 31 AGUSTUS 2026', 'A. ABSENSI', 'Amirullah', 'B. KESIAPAN APD', 'C. JOB HARIAN RUTIN', 'PATROL CEK', 'D. JOB HARIAN NON RUTIN', 'Ganti filter oli mesin #2', 'E. KONDISI K3', 'Ceceran oli di area mesin'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        $this->actingAs($user)->get(route('har.formulir.logbook-mutasi.pdf', $logbook))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_validation_and_unit_protection(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $other = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.formulir.logbook-mutasi.store'), ['unit_id' => $unit->id, 'tanggal' => '31-08-2026'])
            ->assertSessionHasErrors(['tanggal', 'absensi', 'apd', 'rutin']);
        $this->actingAs($user)->post(route('har.formulir.logbook-mutasi.store'), $this->payload($other))->assertForbidden();

        $foreign = HarLogbookMutasi::query()->create([...$this->payload($other), 'year' => 2026, 'month' => 8]);
        $this->actingAs($user)->delete(route('har.formulir.logbook-mutasi.destroy', $foreign))->assertForbidden();
        $this->actingAs($user)->get(route('har.formulir.logbook-mutasi.pdf', $foreign))->assertForbidden();

        $own = HarLogbookMutasi::query()->create([...$this->payload($unit), 'year' => 2026, 'month' => 8]);
        $this->actingAs($user)->delete(route('har.formulir.logbook-mutasi.destroy', $own))->assertRedirect();
        $this->assertModelMissing($own);
    }

    public function test_manager_ul_can_view_but_not_save(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('har.formulir.logbook-mutasi.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($manager)->post(route('har.formulir.logbook-mutasi.store'), $this->payload($unit))->assertForbidden();
    }
}
