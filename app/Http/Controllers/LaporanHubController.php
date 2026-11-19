<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\ReportModule;
use App\Models\Unit;
use App\Models\User;
use App\Services\Reports\ModuleResume;
use App\Services\Reports\ReportWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One "Laporan" menu (user, 2026-11-18 — the Project Leader): every report
 * the account may view, grouped by module — the Laporan Project, the Laporan
 * divisi (Akses 2) and the Resume Bulanan — with their approval status and
 * the links to the existing document / PDF pages, for one unit and month.
 */
class LaporanHubController extends Controller
{
    /**
     * module => [judul, Resume Bulanan key, reports].
     *
     * @var array<string, array{0: string, 1: string, 2: list<ReportModule>}>
     */
    private const MODULES = [
        'operasi' => ['Operasi', 'operasi', [ReportModule::Operasi, ReportModule::OperasiPengusahaan]],
        'pemeliharaan' => ['Pemeliharaan', 'pemeliharaan', [ReportModule::Har, ReportModule::HarPengusahaan]],
        'k3' => ['K3 & Keamanan', 'k3', [ReportModule::K3, ReportModule::K3Pengusahaan]],
        'logistik' => ['Logistik & Gudang', 'logistik', [ReportModule::Logistik]],
        'pdm' => ['PdM & Maturity Level', 'pdm', [ReportModule::Pdm]],
    ];

    public function __construct(private readonly ReportWorkflowService $workflows) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::LaporanHub), 403);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
        ]);

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');
        $unit = isset($validated['unit_id']) ? $units->firstWhere('id', (int) $validated['unit_id']) : $units->first();
        abort_if($unit === null, 403);

        $now = Carbon::now();
        $month = (int) ($validated['month'] ?? $now->month);
        $year = (int) ($validated['year'] ?? $now->year);
        $query = ['unit_id' => $unit->id, 'month' => $month, 'year' => $year];

        $groups = [];
        foreach (self::MODULES as $key => [$title, $resumeKey, $reports]) {
            $items = [];
            foreach ($reports as $report) {
                if (! $this->canView($user, $report)) {
                    continue;
                }

                $status = $this->workflows->status($this->workflows->find($report, $unit->id, $month, $year));
                $items[] = [
                    'module' => $report->value,
                    'title' => $report->label(),
                    'kind' => $report->isPengusahaan() ? 'Laporan Divisi' : 'Laporan Project',
                    'status' => $report->statusLabel($status),
                    'status_tone' => $status->tone(),
                    'document_url' => $report->documentUrl($unit->id, $month, $year),
                    'pdf_url' => $report->pdfUrl($unit->id, $month, $year),
                    'download_url' => $report->pdfUrl($unit->id, $month, $year, ['download' => 1]),
                ];
            }

            if ($items === []) {
                continue;
            }

            $groups[] = [
                'key' => $key,
                'title' => $title,
                'reports' => $items,
                'resume_url' => ModuleResume::allows($user, $resumeKey) ? route('laporan.resume.show', ['module' => $resumeKey, ...$query], false) : null,
            ];
        }

        return Inertia::render('laporan/index', [
            'filters' => $query,
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->values()->all(),
                'years' => range($now->year - 3, $now->year + 1),
            ],
            'groups' => $groups,
            'can_sla' => $user->hasPermissionTo(PermissionName::SlaGabungan),
        ]);
    }

    /** The module's laporan view (Laporan Project) or its divisi (Akses 2) view. */
    private function canView(User $user, ReportModule $report): bool
    {
        $permission = $report->isPengusahaan()
            ? $report->pengusahaanPermission()
            : match ($report) {
                ReportModule::Operasi => PermissionName::OperasiLaporanView,
                ReportModule::Har => PermissionName::HarLaporanView,
                ReportModule::K3 => PermissionName::K3LaporanView,
                ReportModule::Logistik => PermissionName::LogistikLaporanView,
                ReportModule::Pdm => PermissionName::PdmLaporanView,
                default => null,
            };

        return $permission !== null && $user->hasPermissionTo($permission);
    }
}
