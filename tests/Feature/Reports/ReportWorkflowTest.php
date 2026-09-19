<?php

namespace Tests\Feature\Reports;

use App\Enums\EmployeePosition;
use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\PdmDocumentRecord;
use App\Models\ReportWorkflow;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\EmployeeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The Laporan Pembangkit workflow: DRAFT → DIAJUKAN → VERIFIKASI → pengesahan
 * (Koordinator Pemeliharaan → TL Pemeliharaan → Manager UL) → tanda tangan
 * (Project Leader + Office divisi; PdM: Koordinator Pemeliharaan + PIC PDM)
 * → FINAL, with rejection & resubmission, per-unit signers and the audit trail.
 */
class ReportWorkflowTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const PERIOD = ['month' => 8, 'year' => 2026];

    private Unit $unit;

    /** @var array<string, User> jabatan => the account linked to its employee */
    private array $signers = [];

    private User $maker;

    private User $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        Storage::fake('public');

        $serviceUnit = ServiceUnit::factory()->create();
        $this->unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['name' => 'PLTD Poasia']);

        $this->signers = [
            EmployeePosition::ManagerUl->value => $this->signer(EmployeePosition::ManagerUl, RoleName::ManagerUl, $serviceUnit, 'Zul Manager'),
            EmployeePosition::TeamLeaderPemeliharaan->value => $this->signer(EmployeePosition::TeamLeaderPemeliharaan, RoleName::TeamLeaderPemeliharaan, $this->unit, 'Isyak TL Har'),
            EmployeePosition::KoordinatorPemeliharaan->value => $this->signer(EmployeePosition::KoordinatorPemeliharaan, RoleName::TeamLeaderPemeliharaan, $this->unit, 'Amir Koordinator'),
            EmployeePosition::ProjectLeader->value => $this->signer(EmployeePosition::ProjectLeader, RoleName::ProjectLeaderOperasi, $this->unit, 'Herwin Project Leader'),
            EmployeePosition::OfficePemeliharaan->value => $this->signer(EmployeePosition::OfficePemeliharaan, RoleName::TeamLeaderPemeliharaan, $this->unit, 'Olla Office Har'),
            EmployeePosition::PicPdm->value => $this->signer(EmployeePosition::PicPdm, RoleName::TeamLeaderPdm, $this->unit, 'Pia PIC PDM'),
        ];

        $this->maker = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $this->unit);
        $this->verifier = $this->userWithRole(RoleName::SiteLeader, $this->unit);
    }

    // 1. Membuat laporan draft & mengajukan laporan

    public function test_a_saved_draft_is_submitted_with_its_signers_frozen_and_the_document_locked(): void
    {
        $this->actingAs($this->maker)
            ->get(route('har.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page
                ->where('workflow.status', 'draft')
                ->where('workflow.can.submit', true)
                ->where('workflow.can.verify', false)
                ->where('can_write', true)
                ->where('workflow.steps.0.name', 'Amir Koordinator'));

        // Pembuatan laporan comes first: nothing saved yet → cannot be diajukan.
        $this->actingAs($this->maker)->post(route('report-workflow.submit', 'har'), $this->target())
            ->assertSessionHasErrors('workflow');

        $this->saveHarDocument();
        $this->actingAs($this->maker)->post(route('report-workflow.submit', 'har'), $this->target() + ['note' => 'Mohon diperiksa'])
            ->assertSessionHasNoErrors();

        $workflow = $this->workflow();
        $this->assertSame(ReportStatus::Diajukan, $workflow->status);
        $this->assertSame($this->maker->id, $workflow->submitted_by);
        $this->assertSame(
            [['pengesahan', 'Memeriksa', 'Koordinator Pemeliharaan'], ['pengesahan', 'Menyetujui', 'Team Leader Pemeliharaan'], ['pengesahan', 'Mengetahui', 'Manager UL'], ['tanda_tangan', 'Mengetahui', 'Project Leader'], ['tanda_tangan', 'Dibuat', 'Office Pemeliharaan']],
            $workflow->steps->map(fn ($step): array => [$step->stage, $step->caption, $step->position])->all(),
        );
        $this->assertDatabaseHas('report_workflow_logs', ['report_workflow_id' => $workflow->id, 'action' => 'ajukan', 'from_status' => 'draft', 'to_status' => 'diajukan', 'note' => 'Mohon diperiksa', 'user_id' => $this->maker->id]);

        // Locked while in process: no editing, no regenerating, no second submit.
        $this->actingAs($this->maker)->post(route('har.laporan.document.store'), $this->target() + ['format' => 'html', 'content_html' => '<p>ubah</p>'])->assertForbidden();
        $this->actingAs($this->maker)->post(route('har.laporan.document.regenerate'), $this->target())->assertForbidden();
        $this->actingAs($this->maker)->post(route('report-workflow.submit', 'har'), $this->target())->assertForbidden();
        $this->actingAs($this->maker)
            ->get(route('har.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page->where('can_write', false)->where('workflow.status', 'diajukan')->where('workflow.can.submit', false));
    }

    public function test_a_role_without_the_module_write_permission_cannot_submit(): void
    {
        $this->saveHarDocument();

        $this->actingAs($this->userWithRole(RoleName::Operator, $this->unit))
            ->post(route('report-workflow.submit', 'har'), $this->target())
            ->assertForbidden();
        $this->assertNull($this->workflow());
    }

    public function test_submission_is_refused_while_a_signer_jabatan_has_no_active_employee(): void
    {
        Employee::query()->where('position', EmployeePosition::OfficePemeliharaan->value)->update(['is_active' => false, 'singleton_key' => null]);
        $this->saveHarDocument();

        $this->actingAs($this->maker)->post(route('report-workflow.submit', 'har'), $this->target())
            ->assertSessionHasErrors(['workflow' => 'Pegawai aktif dengan jabatan Office Pemeliharaan di PLTD Poasia belum terdaftar di Master Pegawai.']);
    }

    // 2. Verifikasi laporan berhasil

    public function test_verification_records_the_verifier_time_and_note(): void
    {
        $this->submitHar();

        // Only a user with the report_unit.approve permission in the unit may verify.
        $this->actingAs($this->maker)->post(route('report-workflow.verify', 'har'), $this->target())->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::SiteLeader, Unit::factory()->create()))
            ->post(route('report-workflow.verify', 'har'), $this->target())->assertForbidden();

        $this->actingAs($this->verifier)->post(route('report-workflow.verify', 'har'), $this->target() + ['note' => 'Data lengkap'])->assertRedirect();

        $workflow = $this->workflow();
        $this->assertSame(ReportStatus::Verifikasi, $workflow->status);
        $this->assertSame($this->verifier->id, $workflow->verified_by);
        $this->assertNotNull($workflow->verified_at);
        $this->assertSame('Data lengkap', $workflow->verification_note);
        $this->assertDatabaseHas('report_workflow_logs', ['report_workflow_id' => $workflow->id, 'action' => 'verifikasi', 'from_status' => 'diajukan', 'to_status' => 'verifikasi', 'user_id' => $this->verifier->id]);
    }

    // 3. Verifikasi ditolak dengan catatan, diperbaiki & diajukan kembali

    public function test_a_rejected_report_shows_the_reason_and_is_fixed_and_resubmitted(): void
    {
        $this->submitHar();

        $this->actingAs($this->verifier)->post(route('report-workflow.reject', 'har'), $this->target() + ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->actingAs($this->verifier)->post(route('report-workflow.reject', 'har'), $this->target() + ['reason' => 'Tabel WO bulan Agustus belum lengkap'])
            ->assertSessionHasNoErrors();

        $workflow = $this->workflow();
        $this->assertSame(ReportStatus::Ditolak, $workflow->status);
        $this->assertSame('Tabel WO bulan Agustus belum lengkap', $workflow->rejection_reason);
        $this->assertSame($this->verifier->id, $workflow->rejected_by);

        // The maker sees why and may edit again.
        $this->actingAs($this->maker)
            ->get(route('har.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page
                ->where('can_write', true)
                ->where('workflow.status', 'ditolak')
                ->where('workflow.rejection.reason', 'Tabel WO bulan Agustus belum lengkap')
                ->where('workflow.rejection.active', true)
                ->where('workflow.can.submit', true));

        $this->actingAs($this->maker)->post(route('har.laporan.document.store'), $this->target() + ['format' => 'html', 'content_html' => '<p>diperbaiki</p>'])->assertRedirect();
        $this->actingAs($this->maker)->post(route('report-workflow.submit', 'har'), $this->target())->assertSessionHasNoErrors();

        $this->assertSame(ReportStatus::Diajukan, $this->workflow()->status);
        $this->assertSame(
            ['ajukan', 'tolak', 'ajukan_kembali'],
            $this->workflow()->logs->pluck('action')->all(),
        );
    }

    // 4. Pengesahan oleh masing-masing jabatan, berurutan

    public function test_pengesahan_runs_in_order_and_only_by_the_assigned_employee(): void
    {
        $this->submitHar();
        $this->actingAs($this->verifier)->post(route('report-workflow.verify', 'har'), $this->target());

        // Manager UL & TL cannot sign before the Koordinator; the Project Leader not before pengesahan is done.
        $this->sign(EmployeePosition::ManagerUl)->assertForbidden();
        $this->sign(EmployeePosition::TeamLeaderPemeliharaan)->assertForbidden();
        $this->sign(EmployeePosition::ProjectLeader)->assertForbidden();
        // A user holding the same role but not linked to the employee cannot sign.
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $this->unit))
            ->post(route('report-workflow.sign', 'har'), $this->target())->assertForbidden();

        $this->actingAs($this->signers[EmployeePosition::KoordinatorPemeliharaan->value])
            ->get(route('har.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page->where('workflow.can.sign', 'Setujui')->where('workflow.can.verify', false));

        $this->sign(EmployeePosition::KoordinatorPemeliharaan)->assertRedirect();
        $this->assertSame(ReportStatus::Disetujui, $this->workflow()->status);
        $this->sign(EmployeePosition::TeamLeaderPemeliharaan)->assertRedirect();
        $this->assertSame(ReportStatus::Disetujui, $this->workflow()->status);

        $this->actingAs($this->signers[EmployeePosition::ManagerUl->value])
            ->get(route('har.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page->where('workflow.can.sign', 'Sahkan'));
        $this->sign(EmployeePosition::ManagerUl)->assertRedirect();

        $workflow = $this->workflow();
        $this->assertSame(ReportStatus::Disahkan, $workflow->status);
        $this->assertSame([true, true, true, false, false], $workflow->steps->map(fn ($step): bool => $step->signed_at !== null)->all());
        $this->assertDatabaseHas('report_workflow_logs', ['report_workflow_id' => $workflow->id, 'action' => 'setujui', 'jabatan' => 'Koordinator Pemeliharaan', 'from_status' => 'verifikasi', 'to_status' => 'disetujui']);
        $this->assertDatabaseHas('report_workflow_logs', ['report_workflow_id' => $workflow->id, 'action' => 'sahkan', 'jabatan' => 'Manager UL', 'from_status' => 'disetujui', 'to_status' => 'disahkan']);
    }

    public function test_a_signer_may_reject_during_pengesahan_which_clears_the_signatures(): void
    {
        $this->submitHar();
        $this->actingAs($this->verifier)->post(route('report-workflow.verify', 'har'), $this->target());
        $this->sign(EmployeePosition::KoordinatorPemeliharaan);

        $this->actingAs($this->signers[EmployeePosition::TeamLeaderPemeliharaan->value])
            ->post(route('report-workflow.reject', 'har'), $this->target() + ['reason' => 'Resume statistik belum sesuai'])
            ->assertSessionHasNoErrors();

        $workflow = $this->workflow();
        $this->assertSame(ReportStatus::Ditolak, $workflow->status);
        $this->assertTrue($workflow->steps->every(fn ($step): bool => $step->signed_at === null));
    }

    // 5. Tanda tangan → FINAL; tanda tangan tampil hanya setelah FINAL

    public function test_the_report_is_final_after_every_signature_and_only_then_prints_them(): void
    {
        $managerSignature = UploadedFile::fake()->image('ttd.png')->store('signatures', 'public');
        Employee::query()->where('position', EmployeePosition::ManagerUl->value)->update(['signature_path' => $managerSignature]);

        $this->submitHar();
        $this->actingAs($this->verifier)->post(route('report-workflow.verify', 'har'), $this->target());
        foreach ([EmployeePosition::KoordinatorPemeliharaan, EmployeePosition::TeamLeaderPemeliharaan, EmployeePosition::ManagerUl] as $position) {
            $this->sign($position)->assertRedirect();
        }

        $this->assertStringNotContainsString('data:image/png;base64,', $this->signatureBlocks());

        $this->sign(EmployeePosition::OfficePemeliharaan)->assertForbidden();
        $this->sign(EmployeePosition::ProjectLeader)->assertRedirect();
        $this->assertSame(ReportStatus::Ditandatangani, $this->workflow()->status);
        $this->assertStringNotContainsString('Ditandatangani secara elektronik', $this->signatureBlocks());

        $this->actingAs($this->signers[EmployeePosition::OfficePemeliharaan->value])
            ->get(route('har.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page->where('workflow.can.sign', 'Tanda Tangani'));
        $this->sign(EmployeePosition::OfficePemeliharaan)->assertRedirect();

        $workflow = $this->workflow();
        $this->assertSame(ReportStatus::Final, $workflow->status);
        $this->assertNotNull($workflow->finalized_at);
        $this->assertSame(['ajukan', 'verifikasi', 'setujui', 'setujui', 'sahkan', 'tanda_tangan', 'tanda_tangan'], $workflow->logs->pluck('action')->all());

        $blocks = $this->signatureBlocks();
        $this->assertStringContainsString('data:image/png;base64,', $blocks);
        $this->assertStringContainsString('Ditandatangani secara elektronik', $blocks);

        // FINAL stays locked and nothing more can be signed or rejected.
        $this->actingAs($this->maker)->post(route('har.laporan.document.store'), $this->target() + ['format' => 'html', 'content_html' => '<p>x</p>'])->assertForbidden();
        $this->actingAs($this->verifier)->post(route('report-workflow.reject', 'har'), $this->target() + ['reason' => 'Terlambat ditolak'])->assertForbidden();
    }

    // 6. Signer sesuai unit + jabatan + divisi

    public function test_the_signers_come_from_the_report_unit_by_jabatan_and_division(): void
    {
        $otherUnit = Unit::factory()->forServiceUnit($this->unit->serviceUnit)->create();
        $otherKoordinator = $this->signer(EmployeePosition::KoordinatorPemeliharaan, RoleName::TeamLeaderPemeliharaan, $otherUnit, 'Koordinator Unit Lain');
        Employee::factory()->create(['unit_id' => $this->unit->id, 'position' => 'Koordinator Operasi', 'name' => 'Koordinator Divisi Lain', 'is_active' => true]);
        $officeOperasi = $this->signer(EmployeePosition::OfficeOperasi, RoleName::TeamLeaderOperasi, $this->unit, 'Office Divisi Operasi');

        $this->submitHar();
        $workflow = $this->workflow();

        $this->assertSame(
            ['Amir Koordinator', 'Isyak TL Har', 'Zul Manager', 'Herwin Project Leader', 'Olla Office Har'],
            $workflow->steps->map(fn ($step): string => $step->employee->name)->all(),
        );
        $this->assertSame('pemeliharaan', $workflow->steps->last()->employee->division);

        $this->actingAs($this->verifier)->post(route('report-workflow.verify', 'har'), $this->target());
        // Same jabatan in another unit, or another divisi in this unit: not a signer.
        $this->actingAs($otherKoordinator)->post(route('report-workflow.sign', 'har'), $this->target())->assertForbidden();
        $this->actingAs($officeOperasi)->post(route('report-workflow.sign', 'har'), $this->target())->assertForbidden();
    }

    public function test_a_signer_without_the_module_permission_may_open_the_report_they_sign(): void
    {
        $projectLeader = $this->signers[EmployeePosition::ProjectLeader->value];

        $this->actingAs($projectLeader)->get(route('har.laporan.document.edit', $this->target()))->assertForbidden();

        $this->submitHar();

        $this->actingAs($projectLeader)->get(route('har.laporan.document.edit', $this->target()))->assertOk();
    }

    // 7. PdM: Koordinator Pemeliharaan + PIC PDM

    public function test_the_pdm_report_is_signed_by_the_koordinator_pemeliharaan_and_the_pic_pdm(): void
    {
        PdmDocumentRecord::query()->create(['unit_id' => $this->unit->id, 'type' => 'bulanan', ...self::PERIOD, 'format' => 'html', 'content_html' => '<p>PdM</p><table id="ttd-laporan"><tr><td></td></tr></table>', 'content_version' => 2]);
        $pdmMaker = $this->userWithRole(RoleName::TeamLeaderPdm, $this->unit);

        $this->actingAs($pdmMaker)->post(route('report-workflow.submit', 'pdm'), $this->target())->assertSessionHasNoErrors();

        $workflow = ReportWorkflow::query()->where('module', 'pdm')->firstOrFail();
        $this->assertSame(
            [['Mengetahui', 'Koordinator Pemeliharaan', 'Amir Koordinator'], ['Dibuat', 'PIC PDM', 'Pia PIC PDM']],
            $workflow->steps->where('stage', 'tanda_tangan')->map(fn ($step): array => [$step->caption, $step->position, $step->employee->name])->values()->all(),
        );

        $this->actingAs($pdmMaker)
            ->get(route('pdm.laporan.document.edit', $this->target()))
            ->assertInertia(fn ($page) => $page
                ->where('content', fn (string $content): bool => str_contains($content, 'id="ttd-laporan"') && str_contains($content, 'PIA PIC PDM') && ! str_contains($content, 'HERWIN PROJECT LEADER')));
    }

    // 8. & 9. Satu Project Leader per unit, satu Office per unit + divisi

    public function test_a_unit_has_at_most_one_active_project_leader(): void
    {
        $this->expectException(QueryException::class);

        Employee::factory()->create(['unit_id' => $this->unit->id, 'position' => 'Project Leader', 'is_active' => true]);
    }

    public function test_an_inactive_or_other_unit_project_leader_is_allowed(): void
    {
        Employee::factory()->create(['unit_id' => $this->unit->id, 'position' => 'Project Leader', 'is_active' => false]);
        Employee::factory()->create(['unit_id' => Unit::factory()->create()->id, 'position' => 'Project Leader', 'is_active' => true]);

        $this->assertSame(3, Employee::query()->where('position', 'Project Leader')->count());
    }

    public function test_a_unit_has_at_most_one_office_per_division(): void
    {
        // Another divisi in the same unit is fine …
        Employee::factory()->create(['unit_id' => $this->unit->id, 'position' => 'Office K3', 'is_active' => true]);

        // … the same divisi is not.
        $this->expectException(QueryException::class);
        Employee::factory()->create(['unit_id' => $this->unit->id, 'position' => 'Office Pemeliharaan', 'is_active' => true]);
    }

    public function test_master_pegawai_rejects_a_second_holder_with_a_clear_message(): void
    {
        $admin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), ['name' => 'Office Kedua', 'unit_id' => $this->unit->id, 'position' => 'Office Pemeliharaan', 'is_active' => true])
            ->assertSessionHasErrors(['position' => 'Jabatan Office Pemeliharaan di unit ini sudah dipegang pegawai aktif Olla Office Har. Nonaktifkan pegawai tersebut terlebih dahulu.']);

        $this->actingAs($admin)
            ->post(route('admin.employees.store'), ['name' => 'Office Operasi Baru', 'unit_id' => $this->unit->id, 'position' => 'Office Operasi', 'is_active' => true, 'user_id' => $this->maker->id])
            ->assertSessionHasNoErrors();
        $this->assertSame('Office Operasi Baru', $this->maker->fresh()->employee->name);
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
        // The unit that already has its signers keeps them (no second holder added).
        $this->assertSame(1, Employee::query()->where('unit_id', $this->unit->id)->where('position', 'Project Leader')->count());
        $this->assertSame(Employee::query()->count(), Employee::query()->distinct()->count('nip'));
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

    private function saveHarDocument(): void
    {
        $this->actingAs($this->maker)->post(route('har.laporan.document.store'), $this->target() + ['format' => 'html', 'content_html' => '<p>Laporan HAR</p><table id="ttd-pengesahan"><tr><td></td></tr></table><table id="ttd-laporan"><tr><td></td></tr></table>'])
            ->assertRedirect();
    }

    private function submitHar(): void
    {
        $this->saveHarDocument();
        $this->actingAs($this->maker)->post(route('report-workflow.submit', 'har'), $this->target())->assertSessionHasNoErrors();
    }

    private function sign(EmployeePosition $position): TestResponse
    {
        return $this->actingAs($this->signers[$position->value])->post(route('report-workflow.sign', 'har'), $this->target());
    }

    private function workflow(): ?ReportWorkflow
    {
        return ReportWorkflow::query()->where('module', 'har')->where('unit_id', $this->unit->id)->with(['steps.employee', 'logs'])->first();
    }

    /**
     * The signature blocks of the saved document as the editor shows them.
     */
    private function signatureBlocks(): string
    {
        $content = '';
        $this->actingAs($this->maker)
            ->get(route('har.laporan.document.edit', $this->target()))
            ->assertInertia(function ($page) use (&$content) {
                $content = $page->toArray()['props']['content'];
            });

        $this->assertStringContainsString('id="ttd-pengesahan"', $content);

        return $content;
    }
}
