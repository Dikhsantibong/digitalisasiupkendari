<?php

namespace Tests\Feature\Reports;

use App\Enums\EmployeePosition;
use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\K3DocumentRecord;
use App\Models\LogistikDocumentRecord;
use App\Models\Machine;
use App\Models\OperasiReportDocument;
use App\Models\PdmDocumentRecord;
use App\Models\ReportWorkflow;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\ReportWorkflowService;
use Database\Seeders\EmployeeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The Laporan Pembangkit workflow: DRAFT → DIAJUKAN → (Koordinator divisi
 * memeriksa) VERIFIKASI → (Team Leader sesuai modul menyetujui) DISETUJUI →
 * (Manager UL mengesahkan, last) DISAHKAN → FINAL; rejection at any step
 * sends the report back for perbaikan; per-unit signers and the audit trail.
 */
class ReportWorkflowTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const PERIOD = ['month' => 8, 'year' => 2026];

    private const REASON = 'Data belum lengkap, mohon diperbaiki';

    private Unit $unit;

    /** @var array<string, User> jabatan => the account linked to its employee */
    private array $signers = [];

    /** @var array<string, User> module => a maker (pembuat) with the module's write permission */
    private array $makers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        Storage::fake('public');

        $serviceUnit = ServiceUnit::factory()->create();
        $this->unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['name' => 'PLTD Poasia']);

        $this->signers[EmployeePosition::ManagerUl->value] = $this->signer(EmployeePosition::ManagerUl, RoleName::ManagerUl, $serviceUnit, 'Citra Manager');
        foreach ([
            [EmployeePosition::TeamLeaderPemeliharaan, RoleName::TeamLeaderPemeliharaan, 'Budi TL Har'],
            [EmployeePosition::TeamLeaderOperasi, RoleName::TeamLeaderOperasi, 'Rudi TL Operasi'],
            [EmployeePosition::TeamLeaderK3, RoleName::TeamLeaderK3, 'Sari TL K3'],
            [EmployeePosition::KoordinatorPemeliharaan, RoleName::KoordinatorPemeliharaan, 'Amir Koordinator Har'],
            [EmployeePosition::KoordinatorOperasi, RoleName::KoordinatorOperasi, 'Ahmad Koordinator Operasi'],
            [EmployeePosition::KoordinatorK3, RoleName::KoordinatorK3, 'Kiki Koordinator K3'],
            [EmployeePosition::KoordinatorLogistik, RoleName::TeamLeaderLogistik, 'Lina Koordinator Logistik'],
            [EmployeePosition::KoordinatorPdm, RoleName::TeamLeaderPdm, 'Dian Koordinator PDM'],
            [EmployeePosition::ProjectLeader, RoleName::ProjectLeaderOperasi, 'Herwin Project Leader'],
            [EmployeePosition::OfficePemeliharaan, RoleName::KoordinatorPemeliharaan, 'Olla Office Har'],
            [EmployeePosition::OfficeOperasi, RoleName::KoordinatorOperasi, 'Oki Office Operasi'],
            [EmployeePosition::OfficeK3, RoleName::KoordinatorK3, 'Opi Office K3'],
            [EmployeePosition::OfficeLogistik, RoleName::TeamLeaderLogistik, 'Ola Office Logistik'],
            [EmployeePosition::PicPdm, RoleName::TeamLeaderPdm, 'Pia PIC PDM'],
        ] as [$position, $role, $name]) {
            $this->signers[$position->value] = $this->signer($position, $role, $this->unit, $name);
        }

        $this->makers = [
            'har' => $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit),
            'operasi' => $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit),
            'k3' => $this->userWithRole(RoleName::KoordinatorK3, $this->unit),
            'logistik' => $this->userWithRole(RoleName::TeamLeaderLogistik, $this->unit),
            'pdm' => $this->userWithRole(RoleName::TeamLeaderPdm, $this->unit),
        ];
    }

    // Pengajuan

    public function test_a_saved_draft_is_submitted_with_the_approval_chain_frozen_and_the_document_locked(): void
    {
        $maker = $this->makers['har'];
        $this->actingAs($maker)
            ->get(route('har.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page
                ->where('workflow.status', 'draft')
                ->where('workflow.can.submit', true)
                ->where('workflow.can.verify', false)
                ->where('can_write', true)
                ->where('workflow.steps.0.caption', 'Memeriksa')
                ->where('workflow.steps.0.name', 'Amir Koordinator Har'));

        // Pembuatan laporan comes first: nothing saved yet → cannot be diajukan.
        $this->actingAs($maker)->post(route('report-workflow.submit', 'har'), $this->target())->assertSessionHasErrors('workflow');

        $this->submit('har', 'Mohon diperiksa');

        $workflow = $this->workflow('har');
        $this->assertSame(ReportStatus::Diajukan, $workflow->status);
        $this->assertSame(
            [
                ['pengesahan', 1, 'Memeriksa', 'Koordinator Pemeliharaan'],
                ['pengesahan', 2, 'Menyetujui', 'Team Leader Pemeliharaan'],
                ['pengesahan', 3, 'Mengesahkan', 'Manager UL'],
                ['tanda_tangan', 1, 'Mengetahui', 'Project Leader'],
                ['tanda_tangan', 2, 'Dibuat', 'Office Pemeliharaan'],
            ],
            $workflow->steps->map(fn ($step): array => [$step->stage, $step->sequence, $step->caption, $step->position])->all(),
        );
        $this->assertDatabaseHas('report_workflow_logs', ['report_workflow_id' => $workflow->id, 'action' => 'ajukan', 'from_status' => 'draft', 'to_status' => 'diajukan', 'note' => 'Mohon diperiksa', 'user_id' => $maker->id]);

        // Locked while in process.
        $this->actingAs($maker)->post(route('har.laporan.document.store'), $this->target() + ['format' => 'html', 'content_html' => '<p>ubah</p>'])->assertForbidden();
        $this->actingAs($maker)->post(route('har.laporan.document.regenerate'), $this->target())->assertForbidden();
        $this->actingAs($maker)->post(route('report-workflow.submit', 'har'), $this->target())->assertForbidden();
    }

    public function test_a_role_without_the_module_write_permission_cannot_submit(): void
    {
        $this->saveDocument(ReportModule::Har);

        $this->actingAs($this->userWithRole(RoleName::Operator, $this->unit))
            ->post(route('report-workflow.submit', 'har'), $this->target())
            ->assertForbidden();
        $this->assertNull($this->workflow('har'));
    }

    public function test_submission_is_refused_while_a_chain_jabatan_has_no_active_employee(): void
    {
        Employee::query()->where('position', EmployeePosition::KoordinatorPemeliharaan->value)->update(['is_active' => false, 'singleton_key' => null]);
        $this->saveDocument(ReportModule::Har);

        $this->actingAs($this->makers['har'])->post(route('report-workflow.submit', 'har'), $this->target())
            ->assertSessionHasErrors(['workflow' => 'Pegawai aktif dengan jabatan Koordinator Pemeliharaan di PLTD Poasia belum terdaftar di Master Pegawai.']);
    }

    // Test 1 — Koordinator memeriksa laporan divisinya saja

    public function test_each_report_is_verified_by_the_koordinator_of_its_division(): void
    {
        $expected = [
            'operasi' => EmployeePosition::KoordinatorOperasi,
            'har' => EmployeePosition::KoordinatorPemeliharaan,
            'k3' => EmployeePosition::KoordinatorK3,
            'logistik' => EmployeePosition::KoordinatorLogistik,
            'pdm' => EmployeePosition::KoordinatorPdm,
        ];

        foreach ($expected as $module => $koordinator) {
            $this->submit($module);
            $this->assertSame($koordinator->value, $this->workflow($module)->steps->first()->position, $module);
        }

        $koordinatorOperasi = $this->signers[EmployeePosition::KoordinatorOperasi->value];

        // Koordinator Operasi may verify Operasi …
        $this->actingAs($koordinatorOperasi)
            ->get(route('operasi.laporan.document.edit', ['report' => 'laporan-operasi-bulanan', 'engine_id' => Machine::factory()->forUnit($this->unit)->create()->id] + $this->target()))
            ->assertInertia(fn ($page) => $page->where('workflow.can.verify', true)->where('workflow.can.reject', true));

        // … but not K3, Logistik or any other divisi.
        foreach (['k3', 'logistik', 'har', 'pdm'] as $module) {
            $this->actingAs($koordinatorOperasi)->post(route('report-workflow.verify', $module), $this->target())->assertForbidden();
            $this->actingAs($koordinatorOperasi)->post(route('report-workflow.reject', $module), $this->target() + ['reason' => 'Bukan kewenangan'])->assertForbidden();
            $this->assertSame(ReportStatus::Diajukan, $this->workflow($module)->status, $module);
        }

        $this->act('verify', 'operasi', EmployeePosition::KoordinatorOperasi, 'Data laporan telah diperiksa')->assertRedirect();

        $workflow = $this->workflow('operasi');
        $this->assertSame(ReportStatus::Verifikasi, $workflow->status);
        $this->assertSame($koordinatorOperasi->id, $workflow->verified_by);
        $this->assertSame('Data laporan telah diperiksa', $workflow->verification_note);
        $this->assertDatabaseHas('report_workflow_logs', ['report_workflow_id' => $workflow->id, 'user_id' => $koordinatorOperasi->id, 'jabatan' => 'Koordinator Operasi', 'action' => 'verifikasi', 'from_status' => 'diajukan', 'to_status' => 'verifikasi', 'note' => 'Data laporan telah diperiksa']);
    }

    public function test_a_koordinator_of_another_unit_or_an_unlinked_account_cannot_verify(): void
    {
        $otherUnit = Unit::factory()->forServiceUnit($this->unit->serviceUnit)->create();
        $otherKoordinator = $this->signer(EmployeePosition::KoordinatorPemeliharaan, RoleName::KoordinatorPemeliharaan, $otherUnit, 'Koordinator Unit Lain');
        $this->submit('har');

        $this->actingAs($otherKoordinator)->post(route('report-workflow.verify', 'har'), $this->target())->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::SiteLeader, $this->unit))->post(route('report-workflow.verify', 'har'), $this->target())->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit))->post(route('report-workflow.verify', 'har'), $this->target())->assertForbidden();

        $this->assertSame(ReportStatus::Diajukan, $this->workflow('har')->status);
    }

    // Test 2 — Team Leader hanya setelah Koordinator

    public function test_the_team_leader_approves_only_after_the_koordinator_verified(): void
    {
        $this->submit('k3');

        $this->act('approve', 'k3', EmployeePosition::TeamLeaderK3)->assertForbidden();
        $this->assertSame(ReportStatus::Diajukan, $this->workflow('k3')->status);

        $this->act('verify', 'k3', EmployeePosition::KoordinatorK3)->assertRedirect();

        $this->actingAs($this->signers[EmployeePosition::TeamLeaderK3->value])
            ->get(route('k3.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page->where('workflow.can.approve', true)->where('workflow.can.verify', false)->where('workflow.can.ratify', false));

        $this->act('approve', 'k3', EmployeePosition::TeamLeaderK3, 'Sudah sesuai')->assertRedirect();

        $workflow = $this->workflow('k3');
        $this->assertSame(ReportStatus::Disetujui, $workflow->status);
        $this->assertDatabaseHas('report_workflow_logs', ['report_workflow_id' => $workflow->id, 'jabatan' => 'Team Leader K3 & Keamanan', 'action' => 'setujui', 'from_status' => 'verifikasi', 'to_status' => 'disetujui']);
        // The Koordinator cannot act twice.
        $this->act('verify', 'k3', EmployeePosition::KoordinatorK3)->assertForbidden();
    }

    public function test_each_module_uses_its_correct_team_leader_for_approval(): void
    {
        $expected = [
            'har' => EmployeePosition::TeamLeaderPemeliharaan,
            'operasi' => EmployeePosition::TeamLeaderOperasi,
            'k3' => EmployeePosition::TeamLeaderK3,
            'logistik' => EmployeePosition::TeamLeaderPemeliharaan,
            'pdm' => EmployeePosition::TeamLeaderPemeliharaan,
        ];

        foreach ($expected as $module => $teamLeader) {
            $this->submit($module);
            $workflow = $this->workflow($module);
            $tlStep = $workflow->steps->where('stage', 'pengesahan')->firstWhere('sequence', 2);
            $this->assertNotNull($tlStep, "{$module}: pengesahan step 2 should exist");
            $this->assertSame($teamLeader->value, $tlStep->position, "{$module}: TL should be {$teamLeader->value}");

            // The wrong TL cannot approve.
            if ($teamLeader !== EmployeePosition::TeamLeaderPemeliharaan) {
                $this->act('verify', $module, EmployeePosition::from($workflow->steps->first()->position));
                $this->act('approve', $module, EmployeePosition::TeamLeaderPemeliharaan)->assertForbidden();
            }
        }
    }

    // Test 3 & 4 — Manager UL terakhir, lalu FINAL

    public function test_the_manager_ul_ratifies_last_and_the_report_becomes_final_and_locked(): void
    {
        $managerSignature = UploadedFile::fake()->image('ttd.png')->store('signatures', 'public');
        Employee::query()->where('position', EmployeePosition::ManagerUl->value)->update(['signature_path' => $managerSignature]);

        $this->submit('har');

        // Before the Koordinator: no pengesahan.
        $this->act('ratify', 'har', EmployeePosition::ManagerUl)->assertForbidden();
        $this->act('verify', 'har', EmployeePosition::KoordinatorPemeliharaan)->assertRedirect();

        // Before the Team Leader: still no pengesahan.
        $this->act('ratify', 'har', EmployeePosition::ManagerUl)->assertForbidden();
        $this->actingAs($this->signers[EmployeePosition::ManagerUl->value])
            ->get(route('har.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page->where('workflow.can.ratify', false)->where('workflow.can.reject', false));
        $this->assertStringNotContainsString('data:image/png;base64,', $this->documentContent());

        $this->act('approve', 'har', EmployeePosition::TeamLeaderPemeliharaan)->assertRedirect();

        $this->actingAs($this->signers[EmployeePosition::ManagerUl->value])
            ->get(route('har.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page->where('workflow.can.ratify', true)->where('workflow.can.reject', true));
        $this->act('ratify', 'har', EmployeePosition::ManagerUl, 'Disahkan')->assertRedirect();

        $workflow = $this->workflow('har');
        $this->assertSame(ReportStatus::Final, $workflow->status);
        $this->assertNotNull($workflow->finalized_at);
        $this->assertSame(
            [['ajukan', 'draft', 'diajukan'], ['verifikasi', 'diajukan', 'verifikasi'], ['setujui', 'verifikasi', 'disetujui'], ['sahkan', 'disetujui', 'disahkan'], ['final', 'disahkan', 'final']],
            $workflow->logs->map(fn ($log): array => [$log->action, $log->from_status, $log->to_status])->all(),
        );
        $this->assertSame(['Koordinator Pemeliharaan', 'Team Leader Pemeliharaan', 'Manager UL'], $workflow->logs->slice(1, 3)->pluck('jabatan')->values()->all());

        // Signatures print only now, the in-report block dated with the pengesahan.
        $content = $this->documentContent();
        $this->assertStringContainsString('data:image/png;base64,', $content);
        $this->assertStringContainsString('Ditandatangani secara elektronik', $content);

        // FINAL cannot be edited, regenerated, re-submitted or rejected.
        $maker = $this->makers['har'];
        $this->actingAs($maker)->post(route('har.laporan.document.store'), $this->target() + ['format' => 'html', 'content_html' => '<p>x</p>'])->assertForbidden();
        $this->actingAs($maker)->post(route('har.laporan.document.regenerate'), $this->target())->assertForbidden();
        $this->actingAs($maker)->post(route('report-workflow.submit', 'har'), $this->target())->assertForbidden();
        $this->act('reject', 'har', EmployeePosition::ManagerUl)->assertForbidden();
        $this->actingAs($maker)->get(route('har.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page->where('can_write', false)->where('workflow.status', 'final'));
    }

    // Test 5 — Penolakan di tiap tahap

    public function test_the_koordinator_rejects_and_the_maker_fixes_and_resubmits(): void
    {
        $this->submit('logistik');

        $this->actingAs($this->signers[EmployeePosition::KoordinatorLogistik->value])
            ->post(route('report-workflow.reject', 'logistik'), $this->target() + ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->act('reject', 'logistik', EmployeePosition::KoordinatorLogistik)->assertSessionHasNoErrors();

        $this->assertRejectedAndResubmittable('logistik', 'Koordinator Logistik', 'diajukan');
    }

    public function test_the_team_leader_rejects_and_the_maker_fixes_and_resubmits(): void
    {
        $this->submit('har');
        $this->act('verify', 'har', EmployeePosition::KoordinatorPemeliharaan);
        $this->act('reject', 'har', EmployeePosition::TeamLeaderPemeliharaan)->assertSessionHasNoErrors();

        $this->assertRejectedAndResubmittable('har', 'Team Leader Pemeliharaan', 'verifikasi');
    }

    public function test_the_manager_ul_rejects_and_the_maker_fixes_and_resubmits(): void
    {
        $this->submit('pdm');
        $this->act('verify', 'pdm', EmployeePosition::KoordinatorPdm);
        $this->act('approve', 'pdm', EmployeePosition::TeamLeaderPemeliharaan);
        $this->act('reject', 'pdm', EmployeePosition::ManagerUl)->assertSessionHasNoErrors();

        $this->assertRejectedAndResubmittable('pdm', 'Manager UL', 'disetujui');
    }

    public function test_only_the_signer_whose_turn_it_is_may_reject(): void
    {
        $this->submit('har');

        $this->act('reject', 'har', EmployeePosition::TeamLeaderPemeliharaan)->assertForbidden();
        $this->act('reject', 'har', EmployeePosition::ManagerUl)->assertForbidden();
        $this->assertSame(ReportStatus::Diajukan, $this->workflow('har')->status);
    }

    // PdM: rantai persetujuan standar, tanda tangan dokumen khusus

    public function test_the_pdm_report_keeps_its_koordinator_pemeliharaan_and_pic_pdm_signature_block(): void
    {
        $this->submit('pdm');

        $workflow = $this->workflow('pdm');
        $this->assertSame(['Koordinator PDM', 'Team Leader Pemeliharaan', 'Manager UL'], $workflow->steps->where('stage', 'pengesahan')->pluck('position')->values()->all());
        $this->assertSame(
            [['Mengetahui', 'Koordinator Pemeliharaan', 'Amir Koordinator Har'], ['Dibuat', 'PIC PDM', 'Pia PIC PDM']],
            $workflow->steps->where('stage', 'tanda_tangan')->map(fn ($step): array => [$step->caption, $step->position, $step->employee->name])->values()->all(),
        );

        $this->actingAs($this->makers['pdm'])
            ->get(route('pdm.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page->where('content', fn (string $content): bool => str_contains($content, 'PIA PIC PDM') && str_contains($content, 'AMIR KOORDINATOR HAR') && ! str_contains($content, 'HERWIN PROJECT LEADER')));
    }

    // Halaman pengesahan: Memeriksa → Menyetujui → Mengesahkan

    public function test_the_lembar_pengesahan_prints_the_chain_in_workflow_order(): void
    {
        $this->saveDocument(ReportModule::Har);

        $content = $this->documentContent();
        $this->assertSame(1, preg_match('/<table\b[^>]*id="ttd-pengesahan".*?<\/table>/s', $content, $match));
        $this->assertMatchesRegularExpression('/Memeriksa.*Koordinator Pemeliharaan.*AMIR KOORDINATOR HAR.*Menyetujui.*Team Leader Pemeliharaan.*BUDI TL HAR.*Mengesahkan.*Manager UL.*CITRA MANAGER/s', $match[0]);
        $this->assertStringNotContainsString('Mengetahui', $match[0]);
    }

    public function test_a_signer_without_the_module_permission_may_open_the_report_they_sign(): void
    {
        // TL Pemeliharaan approves (menyetujui) the PdM report without holding pdm.laporan.view.
        $teamLeader = $this->signers[EmployeePosition::TeamLeaderPemeliharaan->value];

        $this->actingAs($teamLeader)->get(route('pdm.laporan.document.edit', $this->target()))->assertForbidden();

        $this->submit('pdm');

        $this->actingAs($teamLeader)->get(route('pdm.laporan.document.edit', $this->target()))->assertOk();
    }

    // Satu pemegang jabatan per unit

    public function test_a_unit_has_at_most_one_active_project_leader(): void
    {
        $this->expectException(QueryException::class);

        Employee::factory()->create(['unit_id' => $this->unit->id, 'position' => 'Project Leader', 'is_active' => true]);
    }

    public function test_a_unit_has_at_most_one_office_per_division(): void
    {
        $this->expectException(QueryException::class);

        Employee::factory()->create(['unit_id' => $this->unit->id, 'position' => 'Office Pemeliharaan', 'is_active' => true]);
    }

    public function test_an_inactive_or_other_unit_holder_is_allowed(): void
    {
        Employee::factory()->create(['unit_id' => $this->unit->id, 'position' => 'Office Pemeliharaan', 'is_active' => false]);
        Employee::factory()->create(['unit_id' => Unit::factory()->create()->id, 'position' => 'Office Pemeliharaan', 'is_active' => true]);

        $this->assertSame(3, Employee::query()->where('position', 'Office Pemeliharaan')->count());
    }

    public function test_master_pegawai_rejects_a_second_holder_with_a_clear_message(): void
    {
        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.employees.store'), ['name' => 'Koordinator Kedua', 'unit_id' => $this->unit->id, 'position' => 'Koordinator Operasi', 'is_active' => true])
            ->assertSessionHasErrors(['position' => 'Jabatan Koordinator Operasi di unit ini sudah dipegang pegawai aktif Ahmad Koordinator Operasi. Nonaktifkan pegawai tersebut terlebih dahulu.']);
    }

    public function test_the_employee_seeder_adds_every_signer_jabatan_once_per_unit_idempotently(): void
    {
        $units = Unit::factory()->count(2)->create();

        $this->seed(EmployeeSeeder::class);
        $this->seed(EmployeeSeeder::class);

        foreach ($units as $unit) {
            foreach (['Koordinator Pemeliharaan', 'Koordinator Operasi', 'Koordinator K3', 'Koordinator PDM', 'Koordinator Logistik', 'Project Leader', 'Office Pemeliharaan', 'Office Operasi', 'Office K3', 'Office PDM', 'Office Logistik', 'PIC PDM'] as $position) {
                $this->assertSame(1, Employee::query()->where('unit_id', $unit->id)->where('position', $position)->where('is_active', true)->count(), "{$unit->code} {$position}");
            }
        }
        $this->assertSame(1, Employee::query()->where('unit_id', $this->unit->id)->where('position', 'Koordinator Operasi')->count());
        $this->assertSame(Employee::query()->count(), Employee::query()->distinct()->count('nip'));
    }

    private function assertRejectedAndResubmittable(string $module, string $jabatan, string $rejectedFrom): void
    {
        $workflow = $this->workflow($module);
        $this->assertSame(ReportStatus::Ditolak, $workflow->status);
        $this->assertSame(self::REASON, $workflow->rejection_reason);
        $this->assertTrue($workflow->steps->every(fn ($step): bool => $step->signed_at === null));
        $this->assertDatabaseHas('report_workflow_logs', ['report_workflow_id' => $workflow->id, 'jabatan' => $jabatan, 'action' => 'tolak', 'from_status' => $rejectedFrom, 'to_status' => 'ditolak', 'note' => self::REASON]);

        // Perbaikan: editable again, then diajukan kembali from the start of the chain.
        $this->assertTrue(app(ReportWorkflowService::class)->isEditable(ReportModule::from($module), $this->unit->id, self::PERIOD['month'], self::PERIOD['year']));
        $this->actingAs($this->makers[$module])->post(route('report-workflow.submit', $module), $this->target())->assertSessionHasNoErrors();

        $workflow = $this->workflow($module);
        $this->assertSame(ReportStatus::Diajukan, $workflow->status);
        $this->assertSame('ajukan_kembali', $workflow->logs->last()->action);
    }

    private function signer(EmployeePosition $position, RoleName $role, ServiceUnit|Unit $scope, string $name): User
    {
        $user = $this->userWithRole($role, $scope);
        Employee::factory()->create([
            'unit_id' => $scope instanceof Unit ? $scope->id : null,
            'service_unit_id' => $scope instanceof ServiceUnit ? $scope->id : null,
            'name' => $name,
            'position' => $position->value,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return $user->fresh();
    }

    /**
     * @return array{unit_id: int, month: int, year: int}
     */
    private function target(): array
    {
        return ['unit_id' => $this->unit->id, ...self::PERIOD];
    }

    private function saveDocument(ReportModule $module): void
    {
        $attributes = ['unit_id' => $this->unit->id, ...self::PERIOD, 'format' => 'html', 'content_html' => '<p>Laporan</p>'];

        match ($module) {
            ReportModule::Har => $this->actingAs($this->makers['har'])->post(route('har.laporan.document.store'), $this->target() + [
                'format' => 'html',
                'content_html' => '<p>Laporan HAR</p><table id="ttd-pengesahan"><tr><td></td></tr></table><table id="ttd-laporan"><tr><td></td></tr></table>',
            ])->assertRedirect(),
            ReportModule::Operasi => OperasiReportDocument::query()->create($attributes + ['report_code' => 'laporan-operasi-bulanan', 'content_version' => 7]),
            ReportModule::K3 => K3DocumentRecord::query()->create($attributes + ['type' => 'bulanan', 'content_version' => 14]),
            ReportModule::Logistik => LogistikDocumentRecord::query()->create($attributes + ['type' => 'bulanan', 'content_version' => 2]),
            ReportModule::Pdm => PdmDocumentRecord::query()->create(['content_html' => '<p>PdM</p><table id="ttd-laporan"><tr><td></td></tr></table>'] + $attributes + ['type' => 'bulanan', 'content_version' => 2]),
        };
    }

    private function submit(string $module, ?string $note = null): void
    {
        $this->saveDocument(ReportModule::from($module));
        $this->actingAs($this->makers[$module])->post(route('report-workflow.submit', $module), $this->target() + ['note' => $note])->assertSessionHasNoErrors();
    }

    /**
     * Verifikasi / Setujui / Sahkan / Tolak by the account linked to the given jabatan.
     */
    private function act(string $action, string $module, EmployeePosition $position, ?string $note = null): TestResponse
    {
        $payload = $action === 'reject' ? ['reason' => $note ?? self::REASON] : ['note' => $note];

        return $this->actingAs($this->signers[$position->value])->post(route('report-workflow.'.$action, $module), $this->target() + $payload);
    }

    private function workflow(string $module): ?ReportWorkflow
    {
        return ReportWorkflow::query()->where('module', $module)->where('unit_id', $this->unit->id)->with(['steps.employee', 'logs'])->first();
    }

    /**
     * The HAR document body as the editor shows it (with refreshed signature blocks).
     */
    private function documentContent(): string
    {
        $content = '';
        $this->actingAs($this->makers['har'])
            ->get(route('har.laporan.document.edit', $this->target()))
            ->assertInertia(function ($page) use (&$content) {
                $content = $page->toArray()['props']['content'];
            });

        return $content;
    }
}
