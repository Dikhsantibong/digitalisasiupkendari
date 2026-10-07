<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\User;
use App\Services\Operasi\JamMesinSheet;
use App\Support\Indonesian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengusahaan Operasi — Jam Siap Operasi: never typed in, it is 24 jam minus
 * Jam Operasi, Jam Pemeliharaan and Jam Gangguan of the day, per mesin.
 */
class PengusahaanJamSiapOpsController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(private readonly JamMesinSheet $sheet) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail(($units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first())->id);
        [$month, $year] = $this->period($request);
        $machines = $this->sheet->machines($unit);
        $siap = $this->sheet->siapOperasi($unit, $month, $year, $machines);

        return Inertia::render('pengusahaan/operasi/jam-siap-ops/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'units' => $units,
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'period_label' => Indonesian::monthName($month).' '.$year,
            'machines' => $machines,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
            'readings' => (object) $siap['readings'],
            'totals_by_machine' => (object) $siap['totals_by_machine'],
            'grand_total' => $siap['grand_total'],
            'over' => $siap['over'],
            'filled' => $siap['filled'],
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        $unit = Unit::query()->findOrFail((int) $request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        [$month, $year] = $this->period($request);
        $machines = $this->sheet->machines($unit);
        $siap = $this->sheet->siapOperasi($unit, $month, $year, $machines);

        $pdf = Pdf::loadView('operasi.jam-mesin.pdf', [
            'unit' => $unit,
            'title' => 'JAM SIAP OPERASI',
            'period_label' => Indonesian::monthName($month).' '.$year,
            'machines' => $machines,
            'days_in_month' => Carbon::create($year, $month, 1)->daysInMonth,
            'readings' => $siap['readings'],
            'totals_by_machine' => $siap['totals_by_machine'],
            'grand_total' => $siap['grand_total'],
            'blank_zero' => false,
            'catatan' => null,
        ])->setPaper('a4', 'portrait');

        $disposition = $request->boolean('download') ? 'download' : 'stream';

        return $pdf->{$disposition}("Jam_Siap_Operasi_{$unit->name}_{$month}_{$year}.pdf");
    }

    private function canView(User $user): bool
    {
        return $this->allowsFieldInput($user, PermissionName::OperasiPengusahaanView)
            || $user->hasPermissionTo(PermissionName::OperasiLaporanView);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $now = Carbon::now();

        return [
            max(1, min(12, (int) ($request->integer('month') ?: $now->month))),
            max(2020, min(2100, (int) ($request->integer('year') ?: $now->year))),
        ];
    }
}
