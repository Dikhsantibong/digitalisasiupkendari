<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\AuthorizesFieldInput;
use App\Http\Controllers\Controller;
use App\Models\Operasi5s5rJadwal;
use App\Models\OperasiProgram5s5rEvidence;
use App\Models\OperasiProgram5s5rItem;
use App\Models\OperasiReportDocument;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Reports\ScopedHtmlFragment;
use App\Support\Indonesian;
use App\Support\JadwalPdf;
use App\Support\OperasiProgram5s5r;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Input Program 5S 5R Pengoperasian KIT (Modul OPERASI):
 * per shift per hari (Pagi, Sore, Malam) lima program (Ringkas … Rajin) dengan kondisi awal,
 * tindakan, progres, kondisi akhir, jumlah, keterangan, dan foto eviden per shift per hari.
 */
class Program5s5rInputController extends Controller
{
    use AuthorizesFieldInput;

    private const WEEKDAYS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeView($user);
        [$units, $unit, $month, $year] = $this->target($request);

        $daysInMonth = OperasiProgram5s5r::daysInMonth($month, $year);
        $now = Carbon::now('Asia/Makassar');
        $defaultDay = ($month === (int) $now->month && $year === (int) $now->year)
            ? min($daysInMonth, (int) $now->day)
            : 1;
        $requestedDay = $request->integer('day');
        $activeDay = ($requestedDay >= 1 && $requestedDay <= $daysInMonth) ? $requestedDay : $defaultDay;

        $activeDate = sprintf('%04d-%02d-%02d', $year, $month, $activeDay);
        $userContext = OperasiProgram5s5r::resolveUserContext($user, $unit->id, $activeDate);

        $requestedShift = trim((string) $request->query('shift', ''));
        $activeShift = in_array($requestedShift, OperasiProgram5s5r::SHIFTS, true)
            ? $requestedShift
            : $userContext['shift'];

        $hasSaved = OperasiProgram5s5rItem::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->exists()
            || OperasiProgram5s5rEvidence::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->where('month', $month)
                ->exists();

        return Inertia::render('operasi/input/program-5s5r/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name, 'service_unit_name' => $unit->serviceUnit?->name],
            'filters' => [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
                'day' => $activeDay,
                'shift' => $activeShift,
            ],
            'user_context' => $userContext,
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range((int) now()->year - 3, (int) now()->year + 1),
                'days_in_month' => $daysInMonth,
                'shifts' => OperasiProgram5s5r::SHIFTS,
                'programs' => collect(OperasiProgram5s5r::PROGRAMS)->map(fn (array $p, string $key): array => ['key' => $key, ...$p])->values()->all(),
                'tindakan' => collect(OperasiProgram5s5r::TINDAKAN)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values()->all(),
                'progres' => OperasiProgram5s5r::PROGRES,
                'kondisi' => OperasiProgram5s5r::KONDISI,
                'max_evidence' => OperasiProgram5s5r::MAX_EVIDENCE,
            ],
            'days' => $this->days($unit, $month, $year, embed: true),
            'has_saved' => $hasSaved,
            'can_write' => $this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganProgram5s5r),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->allowsFieldInput($user, PermissionName::OperasiInputWrite, PermissionName::OperasiLapanganProgram5s5r), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $month = max(1, min(12, $request->integer('month')));
        $year = max(2000, min(2100, $request->integer('year')));
        $daysInMonth = OperasiProgram5s5r::daysInMonth($month, $year);
        $maxRows = $daysInMonth * count(OperasiProgram5s5r::SHIFTS) * count(OperasiProgram5s5r::PROGRAMS);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['present', 'array', 'max:'.$maxRows],
            'rows.*.day' => ['required', 'integer', 'between:1,'.$daysInMonth],
            'rows.*.shift' => ['required', Rule::in(OperasiProgram5s5r::SHIFTS)],
            'rows.*.program' => ['required', Rule::in(array_keys(OperasiProgram5s5r::PROGRAMS))],
            'rows.*.detail' => ['nullable', 'string', 'max:1000'],
            'rows.*.pic' => ['nullable', 'string', 'max:150'],
            'rows.*.kondisi_awal' => ['nullable', Rule::in(OperasiProgram5s5r::KONDISI)],
            'rows.*.kondisi_akhir' => ['nullable', Rule::in(OperasiProgram5s5r::KONDISI)],
            'rows.*.progres' => ['nullable', Rule::in(OperasiProgram5s5r::PROGRES)],
            'rows.*.jumlah' => ['nullable', 'integer', 'between:0,999'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:1000'],
            ...collect(OperasiProgram5s5r::TINDAKAN)->keys()->mapWithKeys(fn (string $key): array => ["rows.*.{$key}" => ['nullable', 'boolean']])->all(),
            'synced_slots' => ['nullable', 'array'],
            'synced_slots.*' => ['string'],
            'keep_evidence' => ['nullable', 'array'],
            'keep_evidence.*' => ['nullable', 'array'],
            'keep_evidence.*.*' => ['integer'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['nullable', 'array', 'max:'.OperasiProgram5s5r::MAX_EVIDENCE],
            'evidence.*.*' => ['image', 'max:5120'],
        ]);

        $evidenceSlotKeys = array_values(array_unique([
            ...($validated['synced_slots'] ?? []),
            ...array_keys($validated['keep_evidence'] ?? []),
            ...array_keys($validated['evidence'] ?? []),
        ]));

        foreach ($evidenceSlotKeys as $slotKey) {
            $parsed = OperasiProgram5s5r::parseSlotKey($slotKey, $daysInMonth);
            if ($parsed === null) {
                throw ValidationException::withMessages([
                    "evidence.{$slotKey}" => 'Slot hari dan shift foto eviden tidak valid.',
                ]);
            }

            $total = count((array) ($validated['keep_evidence'][$slotKey] ?? [])) + count((array) ($validated['evidence'][$slotKey] ?? []));
            if ($total > OperasiProgram5s5r::MAX_EVIDENCE) {
                throw ValidationException::withMessages([
                    "evidence.{$slotKey}" => "Foto eviden tanggal {$parsed['day']} shift {$parsed['shift']} maksimal ".OperasiProgram5s5r::MAX_EVIDENCE.' foto.',
                ]);
            }
        }

        DB::transaction(function () use ($validated, $unit, $month, $year, $daysInMonth, $evidenceSlotKeys, $user, $request): void {
            $period = ['unit_id' => $unit->id, 'year' => $year, 'month' => $month];

            foreach ($validated['rows'] as $row) {
                $day = (int) $row['day'];
                $shift = (string) $row['shift'];
                $key = [
                    ...$period,
                    'day' => $day,
                    'shift' => $shift,
                    'program' => (string) $row['program'],
                ];
                $values = [
                    'tanggal' => sprintf('%04d-%02d-%02d', $year, $month, $day),
                    'minggu' => max(1, (int) ceil($day / 7)),
                    'detail' => $this->text($row['detail'] ?? null),
                    'pic' => $this->text($row['pic'] ?? null),
                    'kondisi_awal' => $row['kondisi_awal'] ?? null,
                    'progres' => $row['progres'] ?? null,
                    'kondisi_akhir' => $row['kondisi_akhir'] ?? null,
                    'jumlah' => isset($row['jumlah']) ? (int) $row['jumlah'] : null,
                    'keterangan' => $this->text($row['keterangan'] ?? null),
                    ...collect(OperasiProgram5s5r::TINDAKAN)->keys()->mapWithKeys(fn (string $k): array => [$k => (bool) ($row[$k] ?? false)])->all(),
                ];

                if (OperasiProgram5s5r::isFilled($values)) {
                    OperasiProgram5s5rItem::query()->updateOrCreate($key, [...$values, 'input_by' => $user->id]);
                } else {
                    OperasiProgram5s5rItem::query()->where($key)->delete();
                }
            }

            foreach ($evidenceSlotKeys as $slotKey) {
                $parsed = OperasiProgram5s5r::parseSlotKey($slotKey, $daysInMonth);
                if ($parsed === null) {
                    continue;
                }

                $this->syncEvidence(
                    [
                        ...$period,
                        'day' => $parsed['day'],
                        'shift' => $parsed['shift'],
                    ],
                    array_map('intval', (array) ($validated['keep_evidence'][$slotKey] ?? [])),
                    array_values(array_filter((array) $request->file("evidence.{$slotKey}", []), fn ($file): bool => $file instanceof UploadedFile)),
                    $user->id,
                );
            }
        });

        $this->syncLaporanProjectDocument($unit, $month, $year);

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Program 5S 5R Pengoperasian KIT {$unit->name} ".Indonesian::monthName($month)." {$year}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Program 5S 5R berhasil disimpan.']);

        return back();
    }

    public function pdf(Request $request): HttpResponse
    {
        $this->authorizeView($request->user());
        [, $unit, $month, $year] = $this->target($request);
        [$view, $data] = $this->pdfView($unit, $month, $year);

        $filename = sprintf('Program_5S5R_Pengoperasian_KIT_%s_%02d_%d.pdf', str_replace(' ', '_', $unit->name), $month, $year);
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape')->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
        ]);
    }

    /**
     * The PDF view & data of one month (photos embedded).
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    public function pdfView(Unit $unit, int $month, int $year): array
    {
        $unit->loadMissing('serviceUnit');
        [$slots, $hasSaved] = $this->pdfSlots($unit, $month, $year);

        return ['operasi.input.program-5s5r-pdf', [
            'unit' => $unit,
            'periodLabel' => 'Bulan '.Indonesian::monthName($month).' '.$year,
            'slots' => $slots,
            'has_saved' => $hasSaved,
            'programs' => OperasiProgram5s5r::PROGRAMS,
            'tindakan' => OperasiProgram5s5r::TINDAKAN,
            'progres' => OperasiProgram5s5r::PROGRES,
            ...JadwalPdf::logos(),
        ]];
    }

    /**
     * Every day of the month with its 3 shifts (Pagi, Sore, Malam), each containing
     * the 5 program rows (saved values over the template) and evidence photos.
     *
     * @return list<array<string, mixed>>
     */
    private function days(Unit $unit, int $month, int $year, bool $embed): array
    {
        $saved = OperasiProgram5s5rItem::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy(fn (OperasiProgram5s5rItem $item): string => "{$item->day}:{$item->shift}:{$item->program}");

        $evidence = OperasiProgram5s5rEvidence::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (OperasiProgram5s5rEvidence $photo): string => "{$photo->day}:{$photo->shift}");

        $monthName = Indonesian::monthName($month);
        $daysInMonth = OperasiProgram5s5r::daysInMonth($month, $year);

        return array_map(function (int $day) use ($saved, $evidence, $year, $month, $monthName, $embed): array {
            $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $dateLabel = sprintf('%02d %s %04d', $day, $monthName, $year);
            $shortLabel = sprintf('%02d/%02d/%04d', $day, $month, $year);
            $weekday = self::WEEKDAYS[Carbon::create($year, $month, $day)->dayOfWeek];
            $minggu = max(1, (int) ceil($day / 7));

            $shifts = array_map(function (string $shift) use ($saved, $evidence, $day, $date, $dateLabel, $shortLabel, $weekday, $minggu, $embed): array {
                $filledCount = 0;
                $rows = array_map(function (string $program) use ($saved, $day, $shift, &$filledCount): array {
                    $item = $saved->get("{$day}:{$shift}:{$program}");
                    if ($item === null) {
                        return OperasiProgram5s5r::blankRow($day, $shift, $program);
                    }

                    $filledCount++;

                    return [
                        ...OperasiProgram5s5r::blankRow($day, $shift, $program),
                        ...$item->only(['detail', 'pic', 'kondisi_awal', 'progres', 'kondisi_akhir', 'jumlah', 'keterangan', ...array_keys(OperasiProgram5s5r::TINDAKAN)]),
                        'saved' => true,
                    ];
                }, array_keys(OperasiProgram5s5r::PROGRAMS));

                $photos = ($evidence->get("{$day}:{$shift}") ?? collect())
                    ->map(fn (OperasiProgram5s5rEvidence $photo): array => [
                        'id' => $photo->id,
                        'url' => $embed ? $this->embed($photo->path) : Storage::disk('public')->url($photo->path),
                    ])
                    ->filter(fn (array $photo): bool => $photo['url'] !== '')
                    ->values()
                    ->all();

                return [
                    'day' => $day,
                    'date' => $date,
                    'date_label' => $dateLabel,
                    'short_label' => $shortLabel,
                    'weekday' => $weekday,
                    'minggu' => $minggu,
                    'shift' => $shift,
                    'slot_key' => OperasiProgram5s5r::slotKey($day, $shift),
                    'saved' => $filledCount > 0 || $photos !== [],
                    'filled_count' => $filledCount,
                    'rows' => $rows,
                    'evidence' => $photos,
                ];
            }, OperasiProgram5s5r::SHIFTS);

            $savedShiftsCount = count(array_filter($shifts, fn (array $s): bool => (bool) $s['saved']));
            $savedProgramsCount = array_sum(array_column($shifts, 'filled_count'));

            return [
                'day' => $day,
                'date' => $date,
                'date_label' => $dateLabel,
                'short_label' => $shortLabel,
                'weekday' => $weekday,
                'minggu' => $minggu,
                'saved' => $savedShiftsCount > 0,
                'saved_shifts_count' => $savedShiftsCount,
                'saved_programs_count' => $savedProgramsCount,
                'shifts' => $shifts,
            ];
        }, range(1, $daysInMonth));
    }

    /**
     * Slots rendered in the PDF & Laporan Project: all saved shift-day slots in
     * chronological order, or Tanggal 1 (Pagi, Sore, Malam) when none is saved yet.
     *
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private function pdfSlots(Unit $unit, int $month, int $year): array
    {
        $days = $this->days($unit, $month, $year, embed: true);
        $allSlots = collect($days)->flatMap(fn (array $d): array => $d['shifts'])->values();
        $savedSlots = $allSlots->filter(fn (array $s): bool => (bool) $s['saved'])->values()->all();

        if ($savedSlots !== []) {
            return [$savedSlots, true];
        }

        return [$days[0]['shifts'] ?? [], false];
    }

    /**
     * Keep the listed photos of a day+shift slot, delete the others (files too) and add
     * new uploads up to {@see OperasiProgram5s5r::MAX_EVIDENCE}.
     *
     * @param  array{unit_id: int, year: int, month: int, day: int, shift: string}  $slot
     * @param  list<int>  $keep
     * @param  list<UploadedFile>  $uploads
     */
    private function syncEvidence(array $slot, array $keep, array $uploads, int $userId): void
    {
        /** @var Collection<int, OperasiProgram5s5rEvidence> $existing */
        $existing = OperasiProgram5s5rEvidence::query()->where($slot)->orderBy('sort_order')->get();

        foreach ($existing->reject(fn (OperasiProgram5s5rEvidence $photo): bool => in_array($photo->id, $keep, true)) as $photo) {
            Storage::disk('public')->delete($photo->path);
            $photo->delete();
        }

        $count = $existing->filter(fn (OperasiProgram5s5rEvidence $photo): bool => in_array($photo->id, $keep, true))->count();
        $tanggal = sprintf('%04d-%02d-%02d', $slot['year'], $slot['month'], $slot['day']);
        $minggu = max(1, (int) ceil($slot['day'] / 7));

        foreach (array_slice($uploads, 0, max(0, OperasiProgram5s5r::MAX_EVIDENCE - $count)) as $file) {
            OperasiProgram5s5rEvidence::query()->create([
                ...$slot,
                'tanggal' => $tanggal,
                'minggu' => $minggu,
                'path' => $file->store("operasi-5s5r/{$slot['unit_id']}/{$slot['year']}-{$slot['month']}", 'public'),
                'sort_order' => $count++,
                'input_by' => $userId,
            ]);
        }
    }

    /**
     * Update section #sec-4-14 (14. Laporan 5S5R) inside any saved OperasiReportDocument
     * for this unit, month, and year so Laporan Project stays in sync immediately after save.
     */
    private function syncLaporanProjectDocument(Unit $unit, int $month, int $year): void
    {
        $documents = OperasiReportDocument::query()
            ->where('unit_id', $unit->id)
            ->where('month', $month)
            ->where('year', $year)
            ->get();

        if ($documents->isEmpty()) {
            return;
        }

        $replacementSection = $this->renderLaporanProjectSectionHtml($unit, $month, $year);
        $pattern = '/<div class="op-section op-landscape" id="sec-4-14">.*?<\/div>\s*(?=<div class="page-break"><\/div>)/s';

        foreach ($documents as $doc) {
            if (! is_string($doc->content_html) || $doc->content_html === '') {
                continue;
            }

            if (preg_match($pattern, $doc->content_html) === 1) {
                $doc->content_html = (string) preg_replace($pattern, $replacementSection."\n\n", $doc->content_html, 1);
                $doc->save();
            }
        }
    }

    private function renderLaporanProjectSectionHtml(Unit $unit, int $month, int $year): string
    {
        $unit->loadMissing('serviceUnit');
        $periodLabel = Indonesian::monthName($month).' '.$year;
        $kop = [
            'unitName' => $unit->name,
            ...JadwalPdf::logos(),
        ];

        $jadwalRows = Operasi5s5rJadwal::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (Operasi5s5rJadwal $r): array {
                $rencana = array_map('intval', (array) ($r->rencana ?? []));
                $realisasi = array_map('intval', (array) ($r->realisasi ?? []));

                return [
                    'pelaksana' => $r->displayPelaksana(),
                    'rencana' => $rencana,
                    'realisasi' => $realisasi,
                    'rencana_count' => count($rencana),
                    'target' => (int) $r->target,
                    'realisasi_count' => count($realisasi),
                    'performance' => $r->target > 0 ? (int) round(count($realisasi) / $r->target * 100) : 0,
                ];
            })->all();

        [$view, $data] = $this->pdfView($unit, $month, $year);
        $inputBody = '';
        $inputScope = 'op-v-operasi-input-program-5s5r-pdf';
        if (! empty($data['has_saved'])) {
            $fragment = app(ScopedHtmlFragment::class)->extract(View::make($view, $data)->render(), $inputScope);
            $inputBody = $fragment['body'];
        }

        $html = '<div class="op-section op-landscape" id="sec-4-14">'."\n";
        if ($inputBody !== '') {
            $html .= View::make('operasi.laporan.partials.table-fragment', [
                'part' => ['title' => 'Laporan 5S5R', 'scope' => $inputScope, 'body' => $inputBody],
            ])->render()."\n";
        }

        if ($jadwalRows !== []) {
            if ($inputBody === '') {
                $html .= View::make('operasi.laporan.partials.kop', $kop + ['title' => 'LAPORAN 5S5R'])->render()."\n";
                $html .= '<div class="op-sub-title">14. Laporan Pelaksanaan 5S5R — '.e($periodLabel).'</div>'."\n";
            } else {
                $html .= '<div class="op-sub-title" style="margin-top: 10px;">Rekapitulasi Jadwal Pelaksanaan 5S5R — '.e($periodLabel).'</div>'."\n";
            }
            $html .= '<table class="op-data"><tr><th style="width: 30px;">No</th><th>Pelaksana</th><th style="width: 70px;">Target</th><th style="width: 70px;">Rencana</th><th style="width: 70px;">Realisasi</th><th style="width: 80px;">Kinerja</th></tr>';
            foreach ($jadwalRows as $idx => $row) {
                $html .= '<tr><td class="c">'.($idx + 1).'</td><td>'.e($row['pelaksana']).'</td><td class="c">'.$row['target'].'</td><td class="c">'.$row['rencana_count'].'</td><td class="c">'.$row['realisasi_count'].'</td><td class="c">'.$row['performance'].'%</td></tr>';
            }
            $html .= '</table>'."\n";
        }

        if ($inputBody === '' && $jadwalRows === []) {
            $html .= View::make('operasi.laporan.partials.kop', $kop + ['title' => 'LAPORAN 5S5R'])->render()."\n";
            $html .= '<div class="op-sub-title">14. Laporan Pelaksanaan 5S5R — '.e($periodLabel).'</div>'."\n";
            $html .= View::make('operasi.laporan.partials.no-data', ['message' => 'Belum ada pelaksanaan 5S5R pada periode ini.'])->render()."\n";
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit, 2: int, 3: int}
     */
    private function target(Request $request): array
    {
        $user = $request->user();
        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = Unit::query()->with('serviceUnit')->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();

        return [
            $units,
            $unit,
            max(1, min(12, $request->integer('month') ?: (int) $now->month)),
            max(2000, min(2100, $request->integer('year') ?: (int) $now->year)),
        ];
    }

    private function authorizeView(User $user): void
    {
        abort_unless(($this->allowsFieldInput($user, PermissionName::OperasiInputView, PermissionName::OperasiLapanganProgram5s5r) || $user->hasPermissionTo(PermissionName::OperasiLaporanView)), 403);
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function embed(string $path): string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return '';
        }

        return 'data:'.($disk->mimeType($path) ?: 'image/jpeg').';base64,'.base64_encode((string) $disk->get($path));
    }
}
