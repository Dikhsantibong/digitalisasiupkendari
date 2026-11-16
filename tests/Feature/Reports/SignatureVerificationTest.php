<?php

namespace Tests\Feature\Reports;

use App\Enums\EmployeePosition;
use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Enums\SignatureMode;
use App\Models\Employee;
use App\Models\OperasiReportDocument;
use App\Models\ReportWorkflow;
use App\Models\ReportWorkflowStep;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class SignatureVerificationTest extends TestCase
{
    use InteractsWithAccessControl;
    use RefreshDatabase;

    private Unit $unit;

    private User $maker;

    private User $stafOperasi;

    private User $koordinator;

    private User $teamLeader;

    private User $managerUl;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        Storage::fake('public');

        $serviceUnit = ServiceUnit::factory()->create();
        $this->unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['name' => 'PLTD Bau-Bau']);

        $this->maker = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $this->stafOperasi = $this->makeSigner('Staf Operasi', RoleName::StafOperasi, $this->unit, 'Rina Staf');
        $this->koordinator = $this->makeSigner(EmployeePosition::KoordinatorOperasi, RoleName::KoordinatorOperasi, $this->unit, 'Erwin Gutawa');
        $this->teamLeader = $this->makeSigner(EmployeePosition::TeamLeaderOperasi, RoleName::TeamLeaderOperasi, $this->unit, 'Zainal Abidin');
        $this->managerUl = $this->makeSigner(EmployeePosition::ManagerUl, RoleName::ManagerUl, $serviceUnit, 'Hendra Kusuma');
        $this->makeSigner(EmployeePosition::ProjectLeader, RoleName::ProjectLeaderOperasi, $this->unit, 'Junaedi Akbar');
        $this->makeSigner(EmployeePosition::OfficeOperasi, RoleName::AkunUnit, $this->unit, 'Kaharuddin Syah');

        OperasiReportDocument::query()->create([
            'unit_id' => $this->unit->id,
            'report_code' => 'laporan-operasi-bulanan',
            'month' => 11,
            'year' => 2026,
            'format' => 'html',
            'content_version' => 8,
            'document_number' => 'LAP-OPS/BB/11-2026',
            'content_html' => '<p>Laporan Operasi</p><table id="ttd-pengesahan"><tr><td></td></tr></table><table id="ttd-laporan"><tr><td></td></tr></table>',
            'updated_by' => $this->maker->id,
        ]);

        OperasiReportDocument::query()->create([
            'unit_id' => $this->unit->id,
            'report_code' => 'pengusahaan',
            'month' => 11,
            'year' => 2026,
            'format' => 'html',
            'content_version' => 4,
            'document_number' => 'LAP-PGS/BB/11-2026',
            'content_html' => '<p>Pengusahaan</p><table id="ttd-pengusahaan" class="sign-table"><tr><td></td></tr></table>',
            'updated_by' => $this->stafOperasi->id,
        ]);
    }

    public function test_approved_steps_render_verifiable_qr_signatures_and_clear_on_rejection(): void
    {
        $target = ['unit_id' => $this->unit->id, 'month' => 11, 'year' => 2026];

        // Draft: zero QR codes.
        $this->assertSame(0, substr_count($this->fetchContent($this->maker, $target), 'data:image/png;base64,'));

        $this->actingAs($this->maker)->post(route('report-workflow.submit', 'operasi'), $target)->assertRedirect();
        $this->actingAs($this->koordinator)->post(route('report-workflow.verify', 'operasi'), $target)->assertRedirect();

        // Step 1 approved -> 1 QR in Memeriksa cell, without extra text clutter underneath.
        $afterVerify = $this->fetchContent($this->maker, $target);
        $this->assertSame(1, substr_count($afterVerify, 'data:image/png;base64,'));
        $this->assertStringNotContainsString('Ditandatangani secara elektronik', $afterVerify);
        $verifyUrls = $this->extractVerifyUrls($afterVerify);
        $this->assertCount(1, $verifyUrls);

        // Public verification page (unauthenticated) validates the signed step.
        auth()->logout();
        $this->get($verifyUrls[0])
            ->assertOk()
            ->assertSee('SAH &amp; TERVERIFIKASI', false)
            ->assertSee('Erwin Gutawa')
            ->assertSee('Koordinator Operasi')
            ->assertSee('PLTD Bau-Bau');

        // Step 2 approved -> 2 QRs; Step 3 ratified (FINAL) -> all 5 QRs (3 pengesahan + 2 laporan).
        $this->actingAs($this->teamLeader)->post(route('report-workflow.approve', 'operasi'), $target)->assertRedirect();
        $this->assertSame(2, substr_count($this->fetchContent($this->maker, $target), 'data:image/png;base64,'));

        $this->actingAs($this->managerUl)->post(route('report-workflow.ratify', 'operasi'), $target)->assertRedirect();
        $finalHtml = $this->fetchContent($this->maker, $target);
        $this->assertSame(5, substr_count($finalHtml, 'data:image/png;base64,'));
        $this->assertCount(5, $this->extractVerifyUrls($finalHtml));

        // Tampered token shows invalid state.
        auth()->logout();
        $tampered = preg_replace('/\/[a-f0-9]+$/', '/0000000000000000', $verifyUrls[0]);
        $this->get((string) $tampered)
            ->assertOk()
            ->assertSee('TIDAK VALID');

        // Exported PDF completes cleanly with embedded QR signatures.
        $this->actingAs($this->maker)
            ->get(route('operasi.laporan.document.pdf', ['report' => 'laporan-operasi-bulanan'] + $target))
            ->assertOk();
    }

    public function test_rejecting_report_revokes_qr_signatures_and_marks_verification_invalid(): void
    {
        $target = ['unit_id' => $this->unit->id, 'month' => 11, 'year' => 2026];

        $this->actingAs($this->maker)->post(route('report-workflow.submit', 'operasi'), $target)->assertRedirect();
        $this->actingAs($this->koordinator)->post(route('report-workflow.verify', 'operasi'), $target)->assertRedirect();

        $beforeReject = $this->fetchContent($this->maker, $target);
        $verifyUrls = $this->extractVerifyUrls($beforeReject);
        $this->assertCount(1, $verifyUrls);

        $this->actingAs($this->teamLeader)->post(route('report-workflow.reject', 'operasi'), $target + [
            'reason' => 'Data jam operasi perlu koreksi.',
        ])->assertRedirect();

        $afterReject = $this->fetchContent($this->maker, $target);
        $this->assertSame(0, substr_count($afterReject, 'data:image/png;base64,'));

        auth()->logout();
        $this->get($verifyUrls[0])
            ->assertOk()
            ->assertSee('TIDAK BERLAKU (DITOLAK)')
            ->assertSee('Data jam operasi perlu koreksi.');
    }

    public function test_signature_mode_config_switches_between_qr_image_and_text_flexibly(): void
    {
        $target = ['unit_id' => $this->unit->id, 'month' => 11, 'year' => 2026];

        $path = UploadedFile::fake()->image('manager_ttd.png')->store('signatures', 'public');
        Employee::query()->where('position', EmployeePosition::ManagerUl->value)->update(['signature_path' => $path]);

        $this->actingAs($this->maker)->post(route('report-workflow.submit', 'operasi'), $target)->assertRedirect();
        $this->actingAs($this->koordinator)->post(route('report-workflow.verify', 'operasi'), $target)->assertRedirect();
        $this->actingAs($this->teamLeader)->post(route('report-workflow.approve', 'operasi'), $target)->assertRedirect();
        $this->actingAs($this->managerUl)->post(route('report-workflow.ratify', 'operasi'), $target)->assertRedirect();

        // Image mode: Manager UL renders uploaded image while others gracefully fall back to QR.
        config(['reports.signature_mode' => SignatureMode::Image->value]);
        $imageModeHtml = $this->fetchContent($this->maker, $target);
        $this->assertStringContainsString('alt="Tanda tangan Manager UL"', $imageModeHtml);
        $this->assertSame(1, substr_count($imageModeHtml, 'alt="Tanda tangan '));
        $this->assertSame(4, substr_count($imageModeHtml, 'alt="QR Verifikasi '));

        // Text mode: zero PNG images anywhere, only electronic timestamps.
        config(['reports.signature_mode' => SignatureMode::Text->value]);
        $textModeHtml = $this->fetchContent($this->maker, $target);
        $this->assertSame(0, substr_count($textModeHtml, 'data:image/png;base64,'));
        $this->assertSame(5, substr_count($textModeHtml, 'Ditandatangani secara elektronik'));
    }

    public function test_operasi_pengusahaan_also_supports_verifiable_qr_signatures(): void
    {
        $target = ['unit_id' => $this->unit->id, 'month' => 11, 'year' => 2026];

        $this->actingAs($this->stafOperasi)->post(route('report-workflow.submit', ReportModule::OperasiPengusahaan->value), $target)->assertRedirect();
        $this->actingAs($this->teamLeader)->post(route('report-workflow.approve', ReportModule::OperasiPengusahaan->value), $target)->assertRedirect();

        $workflow = ReportWorkflow::query()
            ->where('module', ReportModule::OperasiPengusahaan)
            ->where('unit_id', $this->unit->id)
            ->where('month', 11)
            ->where('year', 2026)
            ->firstOrFail();

        $this->assertSame(ReportStatus::Disetujui, $workflow->status);

        $html = '';
        $this->actingAs($this->stafOperasi)
            ->get(route('operasi.laporan.pengusahaan.edit', $target))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$html): void {
                $page->loadDeferredProps('document', function (AssertableInertia $doc) use (&$html): void {
                    $html = (string) ($doc->toArray()['props']['content'] ?? '');
                });
            });

        $this->assertStringContainsString('id="ttd-pengusahaan"', $html);
        // Submitted creator (staf) + approved TL = 2 QR codes; Manager UL remains pending.
        $this->assertSame(2, substr_count($html, 'data:image/png;base64,'));
        $this->assertStringNotContainsString('Ditandatangani secara elektronik', $html);

        $urls = $this->extractVerifyUrls($html);
        $this->assertCount(2, $urls);

        auth()->logout();
        foreach ($urls as $url) {
            $this->get($url)->assertOk()->assertSee('SAH &amp; TERVERIFIKASI', false);
        }

        $this->assertStringContainsString('alt="QR Verifikasi Team Leader Operasi"', $html);
        $this->assertStringNotContainsString('alt="QR Verifikasi Manager UL"', $html);

        // Step 3 (Manager UL in Pengusahaan chain) remains unsigned until ratify.
        $step = ReportWorkflowStep::query()->where('report_workflow_id', $workflow->id)->where('sequence', 3)->firstOrFail();
        $this->assertNull($step->signed_at);
    }

    /**
     * @param  array<string, int>  $target
     */
    private function fetchContent(User $user, array $target): string
    {
        $html = '';
        $this->actingAs($user)
            ->get(route('operasi.laporan.document.edit', ['report' => 'laporan-operasi-bulanan'] + $target))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$html): void {
                $page->loadDeferredProps('document', function (AssertableInertia $doc) use (&$html): void {
                    $html = (string) ($doc->toArray()['props']['content'] ?? '');
                });
            });

        return $html;
    }

    /**
     * @return list<string>
     */
    private function extractVerifyUrls(string $html): array
    {
        preg_match_all('/href="([^"]*\/verifikasi-ttd\/[^"]+)"/', $html, $matches);

        return $matches[1] ?? [];
    }

    private function makeSigner(EmployeePosition|string $position, RoleName $role, ServiceUnit|Unit $scope, string $name): User
    {
        $user = $this->userWithRole($role, $scope);
        Employee::factory()->create([
            'unit_id' => $scope instanceof Unit ? $scope->id : null,
            'service_unit_id' => $scope instanceof ServiceUnit ? $scope->id : null,
            'name' => $name,
            'position' => $position instanceof EmployeePosition ? $position->value : $position,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return $user->fresh();
    }
}
