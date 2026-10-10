<?php

namespace Tests\Feature\Reports;

use App\Enums\EmployeePosition;
use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\HarDocumentRecord;
use App\Models\K3DocumentRecord;
use App\Models\OperasiReportDocument;
use App\Models\ReportWorkflow;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The Laporan Pengusahaan workflow (Akses 2) of Operasi, Pemeliharaan and K3:
 * the divisi's Staf mengajukan → Team Leader menyetujui → Manager UL
 * mengesahkan → FINAL. No Koordinator and no Project Leader take part.
 */
class PengusahaanWorkflowTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const PERIOD = ['month' => 8, 'year' => 2026];

    private Unit $unit;

    /** @var array<string, User> jabatan => the account linked to its employee */
    private array $signers = [];

    /** @var array<string, User> module => the divisi's Staf */
    private array $staf = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();

        $serviceUnit = ServiceUnit::factory()->create();
        $this->unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['name' => 'PLTD Poasia']);

        $this->signers['Manager UL'] = $this->signer(EmployeePosition::ManagerUl, RoleName::ManagerUl, $serviceUnit, 'Citra Manager');
        foreach ([
            [EmployeePosition::TeamLeaderOperasi, RoleName::TeamLeaderOperasi, 'Rudi TL Operasi'],
            [EmployeePosition::TeamLeaderPemeliharaan, RoleName::TeamLeaderPemeliharaan, 'Budi TL Har'],
            [EmployeePosition::TeamLeaderK3, RoleName::TeamLeaderK3, 'Sari TL K3'],
            [EmployeePosition::KoordinatorOperasi, RoleName::KoordinatorOperasi, 'Ahmad Koordinator Operasi'],
        ] as [$position, $role, $name]) {
            $this->signers[$position->value] = $this->signer($position, $role, $this->unit, $name);
        }

        $this->staf = [
            'operasi-pengusahaan' => $this->userWithRole(RoleName::StafOperasi, $this->unit),
            'har-pengusahaan' => $this->userWithRole(RoleName::StafPemeliharaan, $this->unit),
            'k3-pengusahaan' => $this->userWithRole(RoleName::StafK3, $this->unit),
        ];
    }

    public function test_the_staf_submits_the_team_leader_approves_and_the_manager_ul_ratifies(): void
    {
        $module = 'operasi-pengusahaan';
        $staf = $this->staf[$module];

        $this->actingAs($staf)
            ->get(route('operasi.laporan.pengusahaan.edit', $this->target()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('workflow.status', 'draft')
                ->where('workflow.can.submit', true)
                ->where('can_write', true)
                // No Koordinator and no in-report signers: only Team Leader and Manager UL.
                ->has('workflow.steps', 2)
                ->where('workflow.steps.0.caption', 'Menyetujui')
                ->where('workflow.steps.0.name', 'Rudi TL Operasi')
                ->where('workflow.steps.1.caption', 'Mengesahkan'));

        // Only a saved document can be diajukan.
        $this->actingAs($staf)->post(route('report-workflow.submit', $module), $this->target())->assertSessionHasErrors('workflow');
        $this->submit($module);

        $workflow = $this->workflow($module);
        $this->assertSame(ReportStatus::Verifikasi, $workflow->status);
        $this->assertSame(
            [['pengesahan', 2, 'Menyetujui', 'Team Leader Operasi'], ['pengesahan', 3, 'Mengesahkan', 'Manager UL']],
            $workflow->steps->map(fn ($step): array => [$step->stage, $step->sequence, $step->caption, $step->position])->all(),
        );
        $this->actingAs($staf)
            ->get(route('operasi.laporan.pengusahaan.edit', $this->target()))
            ->assertInertia(fn ($page) => $page
                ->where('workflow.status_label', 'Diajukan — Menunggu Persetujuan Team Leader')
                ->where('workflow.can.submit', false)
                ->where('can_write', false));

        // Locked while in process.
        $this->actingAs($staf)->post(route('operasi.laporan.pengusahaan.store'), $this->target() + ['format' => 'html', 'content_html' => '<p>ubah</p>'])->assertForbidden();
        $this->actingAs($staf)->post(route('operasi.laporan.pengusahaan.regenerate'), $this->target())->assertForbidden();

        // The Koordinator has no step; nobody may skip the Team Leader.
        $this->act('verify', $module, EmployeePosition::KoordinatorOperasi)->assertForbidden();
        $this->act('ratify', $module, EmployeePosition::ManagerUl)->assertForbidden();

        $this->act('approve', $module, EmployeePosition::TeamLeaderOperasi)->assertSessionHasNoErrors();
        $this->assertSame(ReportStatus::Disetujui, $this->workflow($module)->status);

        $this->act('ratify', $module, EmployeePosition::ManagerUl)->assertSessionHasNoErrors();
        $workflow = $this->workflow($module);
        $this->assertSame(ReportStatus::Final, $workflow->status);
        $this->assertSame(['ajukan', 'setujui', 'sahkan', 'final'], $workflow->logs->pluck('action')->all());
    }

    public function test_each_signer_sees_their_turn_is_notified_and_finds_it_in_portal_and_monitoring(): void
    {
        $module = 'operasi-pengusahaan';
        $staf = $this->staf[$module];
        $tl = $this->signers[EmployeePosition::TeamLeaderOperasi->value];
        $manager = $this->signers['Manager UL'];
        $edit = fn (User $user) => $this->actingAs($user)->get(route('operasi.laporan.pengusahaan.edit', $this->target()))->assertOk();
        $documentUrl = route('operasi.laporan.pengusahaan.edit', $this->target(), false);

        $this->submit($module);

        // Team Leader: notified, may approve or reject, nothing else.
        $notice = $tl->notifications()->sole()->data;
        $this->assertSame('Laporan menunggu persetujuan Anda', $notice['title']);
        $this->assertSame('operasi', $notice['module']);
        $this->assertSame($documentUrl, $notice['url']);
        $edit($tl)->assertInertia(fn ($page) => $page
            ->where('workflow.can.approve', true)
            ->where('workflow.can.reject', true)
            ->where('workflow.can.verify', false)
            ->where('workflow.can.ratify', false)
            ->where('workflow.steps.0.current', true));
        $edit($manager)->assertInertia(fn ($page) => $page->where('workflow.can.ratify', false)->where('workflow.can.reject', false));
        $edit($staf)->assertInertia(fn ($page) => $page->where('workflow.can.approve', false)->where('workflow.can.reject', false));

        // Monitoring → Verifikasi Laporan lists it with a link to the document.
        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))->get(route('monitoring.laporan', self::PERIOD))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('modules', fn ($modules): bool => collect($modules)->contains('key', $module))
                ->where('matrix', fn ($rows): bool => collect($rows)->contains(fn ($row): bool => ($row['cells'][$module]['status'] ?? null) === 'verifikasi'
                    && ($row['cells'][$module]['url'] ?? null) === $documentUrl)));

        $this->act('approve', $module, EmployeePosition::TeamLeaderOperasi)->assertSessionHasNoErrors();

        // Manager UL: notified, finds it on the Portal ("menunggu saya") and may sahkan; the Staf hears it was approved.
        $this->assertSame('Laporan menunggu pengesahan Anda', $manager->notifications()->sole()->data['title']);
        $this->assertSame('Laporan Anda sudah disetujui', $staf->notifications()->sole()->data['title']);
        $this->actingAs($manager)->get(route('portal.index', self::PERIOD))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('my_turn', fn ($items): bool => collect($items)->contains(fn ($item): bool => $item['title'] === 'Laporan Pengusahaan Operasi'
                && $item['document_url'] === $documentUrl)));
        $edit($manager)->assertInertia(fn ($page) => $page->where('workflow.can.ratify', true)->where('workflow.status', 'disetujui'));
        $edit($tl)->assertInertia(fn ($page) => $page->where('workflow.can.approve', false));

        $this->act('ratify', $module, EmployeePosition::ManagerUl)->assertSessionHasNoErrors();
        $this->assertTrue($staf->notifications()->get()->contains(fn ($n): bool => $n->data['title'] === 'Laporan Anda sudah disahkan (FINAL)'));
        $edit($staf)->assertInertia(fn ($page) => $page
            ->where('workflow.status', 'final')
            ->where('workflow.can.submit', false)
            ->where('can_write', false)
            ->where('workflow.steps', fn ($steps): bool => collect($steps)->every(fn ($step): bool => $step['signed'])));
    }

    public function test_the_team_leader_may_reject_and_the_staf_submits_again(): void
    {
        $module = 'operasi-pengusahaan';
        $this->submit($module);

        $this->act('reject', $module, EmployeePosition::TeamLeaderOperasi)->assertSessionHasNoErrors();
        $this->assertSame(ReportStatus::Ditolak, $this->workflow($module)->status);
        $this->actingAs($this->staf[$module])
            ->get(route('operasi.laporan.pengusahaan.edit', $this->target()))
            ->assertInertia(fn ($page) => $page->where('can_write', true)->where('workflow.can.submit', true));

        $this->actingAs($this->staf[$module])->post(route('report-workflow.submit', $module), $this->target())->assertSessionHasNoErrors();
        $this->assertSame(ReportStatus::Verifikasi, $this->workflow($module)->status);
        $this->assertSame('ajukan_kembali', $this->workflow($module)->logs->last()->action);
    }

    public function test_a_team_leader_does_not_approve_a_report_they_submitted(): void
    {
        $module = 'operasi-pengusahaan';
        $tl = $this->signers[EmployeePosition::TeamLeaderOperasi->value];
        $this->saveDocument($module);

        $this->actingAs($tl)->post(route('report-workflow.submit', $module), $this->target())->assertSessionHasNoErrors();
        $this->actingAs($tl)->post(route('report-workflow.approve', $module), $this->target())->assertForbidden();
    }

    public function test_pemeliharaan_and_k3_are_approved_by_their_own_team_leader(): void
    {
        foreach ([
            'har-pengusahaan' => [EmployeePosition::TeamLeaderPemeliharaan, 'har.laporan.pengusahaan.edit'],
            'k3-pengusahaan' => [EmployeePosition::TeamLeaderK3, 'k3.laporan.pengusahaan.edit'],
        ] as $module => [$teamLeader, $route]) {
            $this->actingAs($this->staf[$module])
                ->get(route($route, $this->target()))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->has('workflow.steps', 2)->where('workflow.steps.0.position', $teamLeader->value));

            $this->submit($module);
            // Another divisi's Team Leader cannot approve it.
            $this->act('approve', $module, EmployeePosition::TeamLeaderOperasi)->assertForbidden();
            $this->act('approve', $module, $teamLeader)->assertSessionHasNoErrors();
            $this->act('ratify', $module, EmployeePosition::ManagerUl)->assertSessionHasNoErrors();
            $this->assertSame(ReportStatus::Final, $this->workflow($module)->status);
        }
    }

    public function test_the_laporan_pembangkit_chain_is_unchanged(): void
    {
        $chain = array_map(fn (array $s): array => [$s['sequence'], $s['position']->value], ReportModule::Operasi->pengesahanSigners());

        $this->assertSame([[1, 'Koordinator Operasi'], [2, 'Team Leader Operasi'], [3, 'Manager UL']], $chain);
        $this->assertCount(2, ReportModule::Operasi->reportSigners());
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

    private function saveDocument(string $module): void
    {
        $attributes = ['unit_id' => $this->unit->id, ...self::PERIOD, 'format' => 'html', 'content_html' => '<p>Laporan Pengusahaan</p>'];

        match ($module) {
            'operasi-pengusahaan' => OperasiReportDocument::query()->create($attributes + ['report_code' => 'pengusahaan', 'content_version' => 4]),
            'har-pengusahaan' => HarDocumentRecord::query()->create($attributes + ['type' => 'pengusahaan']),
            'k3-pengusahaan' => K3DocumentRecord::query()->create($attributes + ['type' => 'pengusahaan']),
        };
    }

    private function submit(string $module): void
    {
        $this->saveDocument($module);
        $this->actingAs($this->staf[$module])->post(route('report-workflow.submit', $module), $this->target())->assertSessionHasNoErrors();
    }

    private function act(string $action, string $module, EmployeePosition $position): TestResponse
    {
        $payload = $action === 'reject' ? ['reason' => 'Data belum lengkap, mohon diperbaiki'] : [];

        return $this->actingAs($this->signers[$position->value])->post(route('report-workflow.'.$action, $module), $this->target() + $payload);
    }

    private function workflow(string $module): ?ReportWorkflow
    {
        return ReportWorkflow::query()->where('module', $module)->where('unit_id', $this->unit->id)->with(['steps', 'logs'])->first();
    }
}
