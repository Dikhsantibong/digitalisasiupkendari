<?php

namespace Tests\Feature\Reports;

use App\Enums\EmployeePosition;
use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Models\Employee;
use App\Models\PdmJadwalMeta;
use App\Models\ReportWorkflow;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Har\HarPrelubeTestPdfBuilder;
use App\Services\K3\K3FormulirDocumentBuilder;
use App\Services\Operasi\OperasiPengusahaanDocument;
use App\Services\Pdm\PdmJadwalSigners;
use App\Support\K3FormulirRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every signer of the Laporan Project & Pengusahaan is printed by the
 * person's name from Data Pegawai — never the jabatan text ("Manager UL",
 * "Project Leader", …) nor someone of another unit.
 */
class SignerNamesTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $ul = ServiceUnit::factory()->create(['name' => 'UL PLTD Poasia']);
        $this->unit = Unit::factory()->forServiceUnit($ul)->create(['name' => 'PLTD Poasia', 'is_active' => true]);
    }

    private function holder(EmployeePosition $position, string $name, ?Unit $unit = null): Employee
    {
        $unit ??= $this->unit;

        return Employee::factory()->create([
            'name' => $name,
            'position' => $position->value,
            'unit_id' => $position->isServiceUnitLevel() ? null : $unit->id,
            'service_unit_id' => $unit->service_unit_id,
            'is_active' => true,
            'singleton_key' => $position->singletonKey($unit->id, $unit->service_unit_id),
        ]);
    }

    public function test_har_formulir_follow_the_current_employee_name_and_never_print_the_jabatan(): void
    {
        $builder = app(HarPrelubeTestPdfBuilder::class);

        // No Manager UL / TL in Data Pegawai: empty (a line to sign on), not "Manager UL" nor a fixed name.
        $empty = $builder->buildData($this->unit);
        $this->assertSame('', $empty['manager_ul_name']);
        $this->assertSame('', $empty['tl_har_name']);

        $manager = $this->holder(EmployeePosition::ManagerUl, 'Zulkifli');
        $this->holder(EmployeePosition::TeamLeaderPemeliharaan, 'Muh Isyak');
        $data = $builder->buildData($this->unit->fresh());
        $this->assertSame('Zulkifli', $data['manager_ul_name']);
        $this->assertSame('Muh Isyak', $data['tl_har_name']);

        // A saved form keeps the employee id: a renamed employee shows the new name, not the stored snapshot.
        $manager->update(['name' => 'Zulkifli, S.T.']);
        $saved = $builder->buildData($this->unit->fresh(), input: ['manager_ul_id' => $manager->id, 'manager_ul_name' => 'Manager UL PLTD Poasia']);
        $this->assertSame('Zulkifli, S.T.', $saved['manager_ul_name']);

        // A name typed without an employee (e.g. a Plh.) is kept; a jabatan typed as a name is not.
        $this->assertSame('Suryadi Pratama', $builder->buildData($this->unit->fresh(), input: ['manager_ul_name' => 'Suryadi Pratama'])['manager_ul_name']);
        $this->assertSame('Zulkifli, S.T.', $builder->buildData($this->unit->fresh(), input: ['manager_ul_name' => 'Manager UL'])['manager_ul_name']);
    }

    public function test_pdm_jadwal_signers_are_names_of_the_jabatan_holders(): void
    {
        $this->holder(EmployeePosition::ProjectLeader, 'Firman');
        $this->holder(EmployeePosition::TeamLeaderPemeliharaan, 'Muh Isyak');

        // A sheet saved by an earlier version with the jabatan as the name.
        $saved = PdmJadwalMeta::query()->create([
            'unit_id' => $this->unit->id, 'type' => 'meeting', 'year' => 2026, 'month' => 10,
            'disetujui_nama' => 'Project Leader', 'disetujui_jabatan' => 'Project Leader',
            'mengetahui_nama' => 'Bpk. Andi (Plh.)', 'mengetahui_jabatan' => 'Team Leader Pemeliharaan',
        ]);
        $meta = app(PdmJadwalSigners::class)->apply($saved, $this->unit, 'meeting');

        $this->assertSame('Firman', $meta->disetujui_nama);
        $this->assertSame('Bpk. Andi (Plh.)', $meta->mengetahui_nama, 'A typed name stays.');
        $this->assertSame('', $meta->dibuat_nama, 'No Koordinator Pemeliharaan: empty, not the jabatan.');
        $this->assertSame('Koordinator Pemeliharaan', $meta->dibuat_jabatan);
        $this->assertSame('Project Leader', PdmJadwalMeta::query()->find($saved->id)->disetujui_nama, 'Nothing is saved.');

        // An unsaved sheet (PDF / laporan) gets the names too.
        $this->assertSame('Firman', app(PdmJadwalSigners::class)->apply(null, $this->unit, '5s5r')->disetujui_nama);
    }

    public function test_k3_formulir_leave_a_missing_signer_empty_instead_of_printing_the_jabatan(): void
    {
        $this->holder(EmployeePosition::TeamLeaderK3, 'Muh Amin');
        $form = ['title' => 'Uji', 'body_view' => 'k3.formulir.pdf', 'orientation' => 'portrait', 'signers' => K3FormulirRegistry::defaultSigners()];

        $data = app(K3FormulirDocumentBuilder::class)->buildData($this->unit, 10, 2026, $form, [], ['staff_k3_name' => 'Staff K3']);

        $this->assertSame('Muh Amin', $data['tl_k3_name']);
        $this->assertSame('', $data['staff_k3_name']);
        $this->assertSame('Staff K3', $data['staff_k3_title']);
    }

    public function test_the_operasi_pengusahaan_dibuat_oleh_is_the_staf_who_submitted_it(): void
    {
        $this->holder(EmployeePosition::ManagerUl, 'Zulkifli');
        $staf = User::factory()->create();
        Employee::factory()->create(['name' => 'Rina Staf', 'position' => 'Staf Operasi', 'unit_id' => $this->unit->id, 'user_id' => $staf->id, 'is_active' => true]);
        ReportWorkflow::query()->create([
            'module' => ReportModule::OperasiPengusahaan, 'unit_id' => $this->unit->id, 'month' => 10, 'year' => 2026,
            'status' => ReportStatus::Verifikasi, 'submitted_by' => $staf->id, 'submitted_at' => now(),
        ]);

        $signatories = app(OperasiPengusahaanDocument::class)->build($this->unit, 10, 2026, User::factory()->create())['report']['signatories'];

        $this->assertSame('Rina Staf', $signatories['staf']['name']);
        $this->assertSame('Zulkifli', $signatories['manager']['name']);
    }
}
