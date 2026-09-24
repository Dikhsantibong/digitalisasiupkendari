<?php

namespace App\Http\Controllers\Logistik;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\HandlesLogistikInput;
use App\Http\Controllers\Concerns\RendersReportPdf;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LogistikJadwalRow;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use App\Support\LogistikJadwal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every Logistik & Gudang grid sheet ({@see LogistikJadwal}) — the jadwal
 * (Kegiatan, Shift Operator, Piket Patrol Check, 5S5R, Meeting, Inventarisasi,
 * yearly Pembuatan IK) and the grid-shaped inputs (Patrol Checklist, Inspeksi
 * 5S5R, Input Data Aplikasi, Maturity Level). Rows are saved per unit & month
 * (month 0 for the yearly IK sheet); before anything is saved a sheet starts
 * from its default rows. PDF A4 (landscape; maturity portrait) from
 * resources/views/logistik/jadwal/{layout}-pdf, Excel from the page.
 */
class JadwalSheetController extends Controller
{
    use HandlesLogistikInput;
    use RendersReportPdf;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request, string $jadwal): Response
    {
        $this->ensureSheet($jadwal);
        [$units, $unit, $month, $year] = $this->logistikReadTarget($request);

        // Each sheet has its own page: resources/js/pages/logistik/{jadwal|input}/{sheet}/index.tsx.
        $menu = LogistikJadwal::sheet($jadwal)['menu'];

        return Inertia::render("logistik/{$menu}/{$jadwal}/index", [
            'sheet' => ['key' => $jadwal, ...LogistikJadwal::sheet($jadwal), 'yearly' => LogistikJadwal::yearly($jadwal), 'evidence' => LogistikJadwal::hasEvidence($jadwal) ? LogistikJadwal::EVIDENCE_PER_ROW : 0],
            'sections' => LogistikJadwal::sections($jadwal),
            'codes' => LogistikJadwal::codes($jadwal),
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => $this->logistikFilterOptions($units),
            'columns' => LogistikJadwal::columns($jadwal, $month, $year),
            'rows' => $this->rows($jadwal, $unit, $month, $year),
            'has_saved' => $this->saved($jadwal, $unit, $month, $year)->isNotEmpty(),
            'can_write' => $request->user()->hasPermissionTo(PermissionName::LogistikInputWrite),
        ]);
    }

    public function store(Request $request, string $jadwal): RedirectResponse
    {
        $this->ensureSheet($jadwal);
        [$unit, $month, $year] = $this->logistikWriteTarget($request);

        $validated = $request->validate([
            'rows' => ['present', 'array', 'max:200'],
            'rows.*.section' => ['nullable', Rule::in(array_column(LogistikJadwal::sections($jadwal), 'key'))],
            'rows.*.nama' => ['nullable', 'string', 'max:255'],
            'rows.*.pic' => ['nullable', 'string', 'max:150'],
            'rows.*.days' => ['nullable', 'array'],
            'rows.*.days.*' => ['nullable', Rule::in(array_keys(LogistikJadwal::codes($jadwal)))],
            'rows.*.target' => ['nullable', 'integer', 'min:0', 'max:999'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:255'],
            'rows.*.evidence' => ['nullable', 'array', 'max:'.LogistikJadwal::EVIDENCE_PER_ROW],
            'rows.*.evidence.*' => ['string', 'max:255'],
            'rows.*.evidence_files' => ['nullable', 'array', 'max:'.LogistikJadwal::EVIDENCE_PER_ROW],
            'rows.*.evidence_files.*' => ['image', 'max:5120'],
        ]);

        $validColumns = array_map(fn (array $c): string => (string) $c['col'], LogistikJadwal::columns($jadwal, $month, $year));
        $maturity = LogistikJadwal::layout($jadwal) === 'maturity';
        $withEvidence = LogistikJadwal::hasEvidence($jadwal);

        DB::transaction(function () use ($validated, $request, $jadwal, $unit, $month, $year, $validColumns, $maturity, $withEvidence): void {
            $this->periodQuery($jadwal, $unit, $month, $year)->delete();

            $order = 0;
            foreach ($validated['rows'] as $index => $row) {
                $days = collect($row['days'] ?? [])
                    ->filter(fn (?string $code, int|string $col): bool => $code !== null && $code !== '' && in_array((string) $col, $validColumns, true))
                    ->mapWithKeys(fn (string $code, int|string $col): array => [(string) $col => $code]);
                // A maturity item has a single level.
                $days = ($maturity ? $days->take(-1) : $days)->all();

                $evidence = $withEvidence ? $this->evidence($jadwal, $unit, (array) ($row['evidence'] ?? []), (array) $request->file("rows.{$index}.evidence_files", [])) : [];
                $nama = trim((string) ($row['nama'] ?? ''));
                $pic = trim((string) ($row['pic'] ?? ''));
                $keterangan = trim((string) ($row['keterangan'] ?? ''));

                if ($nama === '' && $pic === '' && $keterangan === '' && $days === [] && $evidence === []) {
                    continue;
                }

                LogistikJadwalRow::query()->create([
                    'unit_id' => $unit->id,
                    'jadwal' => $jadwal,
                    'year' => $year,
                    'month' => $this->storedMonth($jadwal, $month),
                    'section' => $row['section'] ?? null,
                    'nama' => $nama ?: null,
                    'pic' => $pic ?: null,
                    'days' => $days,
                    'target' => $row['target'] ?? null,
                    'keterangan' => $keterangan ?: null,
                    'evidence' => $evidence ?: null,
                    'sort_order' => $order++,
                    'input_by' => $request->user()->id,
                ]);
            }
        });

        $title = LogistikJadwal::sheet($jadwal)['title'];
        $this->activityLogger->log(ActivityEvent::Updated, "Menyimpan {$title} {$unit->name} {$month}/{$year}", $unit, unit: $unit->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$title} berhasil disimpan."]);

        return back();
    }

    public function pdf(Request $request, string $jadwal): HttpResponse
    {
        $this->ensureSheet($jadwal);
        [, $unit, $month, $year] = $this->logistikReadTarget($request);
        [$view, $data] = $this->pdfView($jadwal, $unit, $month, $year);

        $period = LogistikJadwal::yearly($jadwal) ? (string) $year : sprintf('%02d_%d', $month, $year);
        $filename = sprintf('%s_%s_%s.pdf', preg_replace('/[^A-Za-z0-9]+/', '_', LogistikJadwal::sheet($jadwal)['title']), str_replace(' ', '_', $unit->name), $period);

        return $this->streamReportPdf($request, $view, $data, $filename, LogistikJadwal::orientation($jadwal));
    }

    /**
     * The PDF view and its data — reusable by the Laporan Logistik.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(string $jadwal, Unit $unit, int $month, int $year): array
    {
        $sheet = LogistikJadwal::sheet($jadwal);

        return ["logistik.jadwal.{$sheet['layout']}-pdf", [
            'sheet' => $sheet,
            'unit' => $unit,
            'month' => $month,
            'year' => $year,
            'periodLabel' => LogistikJadwal::yearly($jadwal) ? 'Tahun '.$year : Indonesian::monthName($month).' '.$year,
            'columns' => LogistikJadwal::columns($jadwal, $month, $year),
            'rows' => $this->rows($jadwal, $unit, $month, $year, embedEvidence: true),
            'sections' => LogistikJadwal::sections($jadwal),
            'codes' => LogistikJadwal::codes($jadwal),
            'orientation' => LogistikJadwal::orientation($jadwal),
            ...JadwalPdf::logos(),
        ]];
    }

    private function ensureSheet(string $jadwal): void
    {
        abort_unless(LogistikJadwal::exists($jadwal), 404);
    }

    /**
     * Saved rows, or the sheet's default rows, with their computed summary
     * and eviden photo URLs (data URIs for the PDF). The IK sheet always lists
     * at least {@see LogistikJadwal::IK_ROWS} rows.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $jadwal, Unit $unit, int $month, int $year, bool $embedEvidence = false): array
    {
        $columns = LogistikJadwal::columns($jadwal, $month, $year);
        $saved = $this->saved($jadwal, $unit, $month, $year);
        $rows = $saved->isEmpty()
            ? LogistikJadwal::defaultRows($jadwal, $columns, $this->officerName($unit))
            : $saved->map(fn (LogistikJadwalRow $r): array => [
                'section' => $r->section,
                'nama' => (string) ($r->nama ?? ''),
                'pic' => (string) ($r->pic ?? ''),
                'days' => (array) ($r->days ?? []),
                'target' => $r->target,
                'keterangan' => (string) ($r->keterangan ?? ''),
                'evidence' => array_values((array) ($r->evidence ?? [])),
            ])->all();

        if (LogistikJadwal::yearly($jadwal)) {
            for ($n = count($rows); $n < LogistikJadwal::IK_ROWS; $n++) {
                $rows[] = ['section' => null, 'nama' => '', 'pic' => '', 'days' => [], 'target' => null, 'keterangan' => '', 'evidence' => []];
            }
        }

        return array_map(fn (array $row): array => [
            ...$row,
            'days' => (object) $row['days'],
            'evidence_urls' => array_values(array_filter(array_map(fn (string $path): ?string => $this->evidenceUrl($path, $embedEvidence), $row['evidence']))),
            'summary' => LogistikJadwal::summary($jadwal, $row['days'], $row['target'], count($columns)),
        ], $rows);
    }

    /**
     * The kept photo paths (only this sheet's own folder) plus the new uploads.
     *
     * @param  list<string>  $kept
     * @param  list<UploadedFile>  $uploads
     * @return list<string>
     */
    private function evidence(string $jadwal, Unit $unit, array $kept, array $uploads): array
    {
        $folder = "logistik/{$jadwal}/{$unit->id}";
        $paths = array_values(array_filter($kept, fn (string $path): bool => str_starts_with($path, $folder.'/') && Storage::disk('public')->exists($path)));

        foreach ($uploads as $file) {
            $paths[] = $file->store($folder, 'public');
        }

        return array_slice($paths, 0, LogistikJadwal::EVIDENCE_PER_ROW);
    }

    private function evidenceUrl(string $path, bool $embed): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        return $embed
            ? 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'.base64_encode((string) $disk->get($path))
            : $disk->url($path);
    }

    /**
     * @return Collection<int, LogistikJadwalRow>
     */
    private function saved(string $jadwal, Unit $unit, int $month, int $year): Collection
    {
        return $this->periodQuery($jadwal, $unit, $month, $year)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * @return Builder<LogistikJadwalRow>
     */
    private function periodQuery(string $jadwal, Unit $unit, int $month, int $year): Builder
    {
        return LogistikJadwalRow::query()->where('unit_id', $unit->id)->where('jadwal', $jadwal)
            ->where('year', $year)->where('month', $this->storedMonth($jadwal, $month));
    }

    /** The yearly IK sheet is stored with month 0. */
    private function storedMonth(string $jadwal, int $month): int
    {
        return LogistikJadwal::yearly($jadwal) ? 0 : $month;
    }

    /**
     * The unit's logistics officer, the default name on the shift sheet.
     */
    private function officerName(Unit $unit): string
    {
        return (string) Employee::query()->where('unit_id', $unit->id)->where('is_active', true)
            ->where('position', 'like', '%logistik%')->orderBy('name')->value('name');
    }
}
