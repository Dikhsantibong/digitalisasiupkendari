<?php

namespace Tests\Feature\Reports;

use App\Enums\EmployeePosition;
use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\UlReportDocument;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Laporan UL approval (user, 2026-11-18): the Admin UL saves & submits the
 * document, one TL per bidang of the UL's induk unit approves in turn, the
 * Manager UL ratifies (FINAL); a rejection sends it back for fixing. The unit
 * reports' own workflow is untouched.
 */
class LaporanUlWorkflowTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const PERIOD = ['month' => 10, 'year' => 2026];

    private ServiceUnit $ul;

    private Unit $induk;

    private Unit $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        Carbon::setTestNow('2026-11-18 08:00:00');

        $this->ul = ServiceUnit::factory()->create(['name' => 'UL PLTD Poasia']);
        $this->induk = Unit::factory()->forServiceUnit($this->ul)->create(['name' => 'PLTD Poasia', 'is_active' => true]);
        $this->second = Unit::factory()->forServiceUnit($this->ul)->create(['name' => 'PLTD Poasia Containerized', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_admin_ul_submits_then_each_tl_approves_and_the_manager_ul_ratifies(): void
    {
        $admin = $this->userWithRole(RoleName::AdminUl, $this->ul);
        $tlOperasi = $this->signer(EmployeePosition::TeamLeaderOperasi, RoleName::TeamLeaderOperasi, $this->induk, 'Adi Yusuf');
        $tlHar = $this->signer(EmployeePosition::TeamLeaderPemeliharaan, RoleName::TeamLeaderPemeliharaan, $this->induk, 'Muh Isyak');
        $tlK3 = $this->signer(EmployeePosition::TeamLeaderK3, RoleName::TeamLeaderK3, $this->induk, 'Muh Amin');
        $manager = $this->signer(EmployeePosition::ManagerUl, RoleName::ManagerUl, $this->ul, 'Zulkifli');
        // The other unit's TL is not in the chain (one TL per bidang, from the induk unit).
        $otherTl = $this->signer(EmployeePosition::TeamLeaderOperasi, RoleName::TeamLeaderOperasi, $this->second, 'Kurniawan');
        $query = ['kind' => 'pengusahaan', 'ul_id' => $this->ul->id, ...self::PERIOD];

        // The chain is shown before submitting.
        $this->actingAs($admin)->get(route('laporan-ul.show', $query))->assertInertia(fn ($page) => $page
            ->where('workflow.steps', fn ($steps): bool => collect($steps)->pluck('position')->all() === ['Team Leader Operasi', 'Team Leader Pemeliharaan', 'Team Leader K3 & Keamanan', 'Manager UL']
                && collect($steps)->pluck('name')->all() === ['Adi Yusuf', 'Muh Isyak', 'Muh Amin', 'Zulkifli']));

        // Save, then submit: the document is frozen.
        $this->actingAs($admin)->post(route('laporan-ul.store', $query), ['format' => 'html', 'content_html' => '<div class="ul-section"><p>Isi diedit Admin UL</p><table id="ul-ttd"><tr><td></td></tr></table></div>'])->assertRedirect();
        $this->actingAs($admin)->post(route('laporan-ul.workflow.submit', $query))->assertSessionHasNoErrors();
        $document = UlReportDocument::query()->sole();
        $this->assertSame(ReportStatus::Diajukan, $document->status);
        $this->assertStringContainsString('Isi diedit Admin UL', (string) $document->content_html);
        $this->actingAs($admin)->post(route('laporan-ul.store', $query), ['format' => 'html', 'content_html' => '<p>ubah</p>'])->assertStatus(422);

        // Turn by turn: only the open step's TL; the next TL, the other unit's TL and the Manager UL wait.
        $this->actingAs($tlHar)->post(route('laporan-ul.workflow.approve', $query))->assertForbidden();
        $this->actingAs($otherTl)->get(route('laporan-ul.show', $query))->assertForbidden();
        $this->actingAs($manager)->post(route('laporan-ul.workflow.ratify', $query))->assertForbidden();

        // The TL finds it in the Persetujuan inbox, with the Laporan UL action URL.
        $this->actingAs($tlOperasi)->get(route('approvals.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('queue', fn ($items): bool => collect($items)->contains(fn ($item): bool => $item['title'] === 'Laporan Pengusahaan Pembangkit (Laporan UL)'
                && str_contains($item['action_url'], '/laporan-ul/pengusahaan/setujui') && $item['action'] === 'approve')));

        $this->actingAs($tlOperasi)->post(route('laporan-ul.workflow.approve', $query))->assertSessionHasNoErrors();
        $this->assertSame(ReportStatus::Diajukan, $document->fresh()->status);
        $this->actingAs($tlHar)->post(route('laporan-ul.workflow.approve', $query))->assertSessionHasNoErrors();
        $this->actingAs($tlK3)->post(route('laporan-ul.workflow.approve', $query))->assertSessionHasNoErrors();
        $this->assertSame(ReportStatus::Disetujui, $document->fresh()->status);

        $this->actingAs($manager)->get(route('laporan-ul.show', $query))->assertOk()->assertInertia(fn ($page) => $page
            ->where('workflow.can.ratify', true)->where('workflow.can.approve', false)->where('can_write', false));
        $this->actingAs($manager)->post(route('laporan-ul.workflow.ratify', $query))->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(ReportStatus::Final, $document->status);
        $this->assertNotNull($document->finalized_at);
        $this->assertSame(['ajukan', 'setujui', 'setujui', 'setujui', 'sahkan'], $document->logs()->pluck('action')->all());

        // The PDF of the final document prints the signers' marks.
        $response = $this->actingAs($manager)->get(route('laporan-ul.pdf', $query));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        $this->assertGreaterThan(0, $reader->setSourceFile(StreamReader::createByString((string) $response->getContent())));
    }

    public function test_a_rejection_returns_the_report_to_the_admin_ul_who_resubmits(): void
    {
        $admin = $this->userWithRole(RoleName::AdminUl, $this->ul);
        $tlK3 = $this->signer(EmployeePosition::TeamLeaderK3, RoleName::TeamLeaderK3, $this->induk, 'Muh Amin');
        $this->signer(EmployeePosition::ManagerUl, RoleName::ManagerUl, $this->ul, 'Zulkifli');
        $query = ['kind' => 'k3l', 'ul_id' => $this->ul->id, ...self::PERIOD];

        // Submitting without saving first freezes the template.
        $this->actingAs($admin)->post(route('laporan-ul.workflow.submit', $query))->assertSessionHasNoErrors();
        $document = UlReportDocument::query()->sole();
        $this->assertStringContainsString('LAPORAN K3 LINGKUNGAN', (string) $document->content_html);

        $this->actingAs($tlK3)->post(route('laporan-ul.workflow.reject', $query), [])->assertSessionHasErrors('reason');
        $this->actingAs($tlK3)->post(route('laporan-ul.workflow.reject', $query), ['reason' => 'Data APAR belum lengkap.'])->assertSessionHasNoErrors();
        $this->assertSame(ReportStatus::Ditolak, $document->fresh()->status);

        $this->actingAs($admin)->get(route('laporan-ul.show', $query))->assertInertia(fn ($page) => $page
            ->where('can_write', true)
            ->where('workflow.rejection.reason', 'Data APAR belum lengkap.')
            ->where('workflow.can.submit', true));

        $this->actingAs($admin)->post(route('laporan-ul.workflow.submit', $query))->assertSessionHasNoErrors();
        $this->assertSame(ReportStatus::Diajukan, $document->fresh()->status);
        $this->assertNull($document->fresh()->steps()->whereNotNull('signed_at')->first());
    }

    public function test_submitting_needs_every_approver_and_only_the_admin_ul_submits(): void
    {
        $admin = $this->userWithRole(RoleName::AdminUl, $this->ul);
        $tlHar = $this->signer(EmployeePosition::TeamLeaderPemeliharaan, RoleName::TeamLeaderPemeliharaan, $this->induk, 'Muh Isyak');
        $query = ['kind' => 'logistik', 'ul_id' => $this->ul->id, ...self::PERIOD];

        // No Manager UL yet: the submission says who is missing.
        $this->actingAs($admin)->post(route('laporan-ul.workflow.submit', $query))->assertSessionHasErrors('workflow');
        $this->assertSame(ReportStatus::Draft, UlReportDocument::query()->sole()->status);

        // A TL views the report it approves but neither edits nor submits it.
        $this->actingAs($tlHar)->get(route('laporan-ul.show', $query))->assertOk()->assertInertia(fn ($page) => $page
            ->where('can_write', false)->where('workflow.can.submit', false));
        $this->actingAs($tlHar)->post(route('laporan-ul.workflow.submit', $query))->assertForbidden();
        $this->actingAs($tlHar)->post(route('laporan-ul.store', $query), ['format' => 'html', 'content_html' => '<p>x</p>'])->assertForbidden();
        $this->actingAs($tlHar)->get(route('laporan-ul.index'))->assertForbidden();
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
}
