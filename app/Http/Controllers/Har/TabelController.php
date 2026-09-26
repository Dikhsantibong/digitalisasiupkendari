<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\HarTabelRow;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\HarTabel\HarTabel;
use App\Support\HarTabel\HarTabels;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tabel input bebas Modul HAR yang didefinisikan di App\Support\HarTabel —
 * Rekap Laporan Gangguan dan Laporan Kondisi Abnormal & Gangguan Pembangkit.
 * Route tiap tabel memberi parameter `tabel` lewat defaults(); halamannya
 * resources/js/pages/har/input/{key}/index.tsx.
 */
class TabelController extends Controller
{
    use AuthorizesFieldInput;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request, string $tabel): Response
    {
        $definition = $this->definition($tabel);
        $user = $request->user();
        $this->authorizeView($user, $definition);
        [$units, $unit, $month, $year] = $this->target($request);
        $rows = $this->rows($definition, $unit, $month, $year);

        return Inertia::render("har/input/{$definition->key()}/index", [
            'tabel' => $definition->toArray(),
            'kop_lines' => $definition->kopLines($unit->name, $month, $year),
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range((int) now()->year - 3, (int) now()->year + 1),
            ],
            'rows' => $rows,
            'summary' => $rows !== [] ? $definition->summary($rows) : null,
            'urls' => [
                'index' => route("har.input.{$definition->key()}.index"),
                'store' => route("har.input.{$definition->key()}.store"),
                'pdf' => route("har.input.{$definition->key()}.pdf"),
            ],
            'has_saved' => $rows !== [],
            'can_write' => $this->allowsFieldInput($user, PermissionName::HarInputWrite, $definition->fieldPermission()),
        ]);
    }

    public function store(Request $request, string $tabel): RedirectResponse
    {
        $definition = $this->definition($tabel);
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::HarInputWrite, $definition->fieldPermission()), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['present', 'array', 'max:300'],
            'rows.*' => ['array'],
            ...$definition->rules(),
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $period = ['unit_id' => $unit->id, 'tabel' => $definition->key(), 'year' => $year, 'month' => $month];
        $clean = array_values(array_filter(array_map(fn (array $row): ?array => $definition->sanitize($row), $validated['rows'])));

        DB::transaction(function () use ($period, $clean, $user): void {
            HarTabelRow::query()->where($period)->delete();
            foreach ($clean as $index => $data) {
                HarTabelRow::query()->create([...$period, 'sort_order' => $index, 'data' => $data, 'input_by' => $user->id]);
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan {$definition->title()} {$unit->name} ".Indonesian::monthName($month)." {$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$definition->title()} berhasil disimpan."]);

        return back();
    }

    public function pdf(Request $request, string $tabel): HttpResponse
    {
        $definition = $this->definition($tabel);
        $this->authorizeView($request->user(), $definition);
        [, $unit, $month, $year] = $this->target($request);
        [$view, $data] = $this->pdfView($definition, $unit, $month, $year);

        $filename = sprintf('%s_%s_%02d_%d.pdf', str_replace(' ', '_', $definition->title()), str_replace(' ', '_', $unit->name), $month, $year);

        return response(Pdf::loadView($view, $data)->setPaper('a4', $definition->orientation())->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * The PDF view & data of one month — reusable by the Laporan HAR.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(HarTabel $definition, Unit $unit, int $month, int $year): array
    {
        $rows = $this->rows($definition, $unit, $month, $year);

        return ['har.tabel.pdf', [
            'tabel' => $definition,
            'kopLines' => $definition->kopLines($unit->name, $month, $year),
            'unit' => $unit,
            'rows' => $rows,
            'totals' => $definition->totalValues($rows),
            'summary' => $rows !== [] ? $definition->summary($rows) : null,
            ...JadwalPdf::logos(),
        ]];
    }

    private function definition(string $key): HarTabel
    {
        return HarTabels::find($key) ?? abort(404);
    }

    private function authorizeView(User $user, HarTabel $definition): void
    {
        abort_unless(($this->allowsFieldInput($user, PermissionName::HarInputView, $definition->fieldPermission()) || $user->hasPermissionTo(PermissionName::HarLaporanView)), 403);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(HarTabel $definition, Unit $unit, int $month, int $year): array
    {
        return HarTabelRow::query()
            ->where(['unit_id' => $unit->id, 'tabel' => $definition->key(), 'year' => $year, 'month' => $month])
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (HarTabelRow $row): array => $row->data)
            ->all();
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit, 2: int, 3: int}
     */
    private function target(Request $request): array
    {
        $user = $request->user();
        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', $request->integer('unit_id')) ?? $units->first();
        abort_unless($user->canAccessUnit($unit), 403);
        $now = Carbon::now();

        return [
            $units,
            $unit,
            max(1, min(12, $request->integer('month') ?: (int) $now->month)),
            max(2000, min(2100, $request->integer('year') ?: (int) $now->year)),
        ];
    }
}
