<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3MetodePengujianPeralatan;
use App\Models\K3MetodePengujianPeralatanMeta;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\K3\K3FormulirDocumentBuilder;
use App\Support\K3MetodePengujianPeralatanForm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for Formulir Metode Pengujian Peralatan (Modul K3 & Keselamatan Kerja).
 */
class MetodePengujianPeralatanController extends Controller
{
    /**
     * @var array{title: string, body_view: string, orientation: string}
     */
    private const FORM = [
        'title' => 'Formulir Metode Pengujian Peralatan',
        'body_view' => 'k3.formulir.metode-pengujian.body',
        'orientation' => 'landscape',
    ];

    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly K3FormulirDocumentBuilder $documentBuilder,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeView($user);

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'service_unit_id']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unitId = (int) ($request->integer('unit_id') ?: $units->first()->id);
        $unit = $units->firstWhere('id', $unitId) ?? $units->first();
        abort_unless($user->canAccessUnit($unit), 403);

        $now = Carbon::now();
        $month = max(1, min(12, (int) ($request->integer('month') ?: $now->month)));
        $year = (int) ($request->integer('year') ?: $now->year);

        [$rows, $hasSaved] = $this->loadRows($unit, $month, $year);
        $meta = $this->findMeta($unit, $month, $year);

        $data = $this->documentBuilder->buildData($unit, $month, $year, self::FORM, ['rows' => $rows], $this->documentBuilder->metaInput($meta));
        $pdfUrl = route('k3.formulir.metode-pengujian.pdf', ['unit_id' => $unit->id, 'month' => $month, 'year' => $year]);

        return Inertia::render('k3/formulir/metode-pengujian/index', [
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
                'service_unit_name' => $unit->serviceUnit?->name,
            ],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->all(),
                'years' => range($now->year - 3, $now->year + 1),
                'answers' => K3MetodePengujianPeralatanForm::HASIL_UJI,
            ],
            'rows' => $rows,
            'meta' => [
                'catatan' => $meta?->catatan ?? '',
            ],
            'has_saved' => $hasSaved,
            'history' => $this->history($unit),
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
            ...$this->documentBuilder->pageProps($unit, $data, $meta, $pdfUrl),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $unitId = $request->integer('unit_id');
        $unit = Unit::query()->findOrFail($unitId);
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'rows' => ['present', 'array'],
            'rows.*.no_urut' => ['nullable', 'string', 'max:50'],
            'rows.*.nama_peralatan' => ['required', 'string', 'max:255'],
            'rows.*.no_pengesahan' => ['nullable', 'string', 'max:255'],
            'rows.*.nama_kategori_alat' => ['nullable', 'string', 'max:255'],
            'rows.*.uji_visual' => ['nullable', 'string', 'max:100'],
            'rows.*.uji_fungsi' => ['nullable', 'string', 'max:100'],
            'rows.*.uji_beban' => ['nullable', 'string', 'max:100'],
            'rows.*.uji_hydro' => ['nullable', 'string', 'max:100'],
            'rows.*.ndt' => ['nullable', 'string', 'max:100'],
            'rows.*.uji_ultrasonic_thickness' => ['nullable', 'string', 'max:100'],
            'rows.*.uji_ketahanan' => ['nullable', 'string', 'max:100'],
            'rows.*.sertifikasi_terakhir' => ['nullable', 'string', 'max:100'],
            'rows.*.sertifikasi_ulang' => ['nullable', 'string', 'max:100'],
            'rows.*.keterangan' => ['nullable', 'string', 'max:2000'],
            'rows.*.sort_order' => ['nullable', 'integer'],
            'meta' => ['nullable', 'array'],
            'meta.catatan' => ['nullable', 'string', 'max:3000'],
            ...K3FormulirDocumentBuilder::documentRules(),
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $metaAttributes = $this->documentBuilder->metaAttributes($validated);

        DB::transaction(function () use ($unit, $month, $year, $validated, $user, $metaAttributes): void {
            K3MetodePengujianPeralatan::query()
                ->where('unit_id', $unit->id)
                ->where('year', $year)
                ->where('month', $month)
                ->delete();

            foreach ($validated['rows'] as $index => $row) {
                K3MetodePengujianPeralatan::query()->create([
                    'unit_id' => $unit->id,
                    'year' => $year,
                    'month' => $month,
                    'no_urut' => $row['no_urut'] ?? (string) ($index + 1),
                    'nama_peralatan' => $row['nama_peralatan'],
                    'no_pengesahan' => $row['no_pengesahan'] ?? '-',
                    'nama_kategori_alat' => $row['nama_kategori_alat'] ?? '-',
                    'uji_visual' => $row['uji_visual'] ?? '-',
                    'uji_fungsi' => $row['uji_fungsi'] ?? '-',
                    'uji_beban' => $row['uji_beban'] ?? '-',
                    'uji_hydro' => $row['uji_hydro'] ?? '-',
                    'ndt' => $row['ndt'] ?? '-',
                    'uji_ultrasonic_thickness' => $row['uji_ultrasonic_thickness'] ?? '-',
                    'uji_ketahanan' => $row['uji_ketahanan'] ?? '-',
                    'sertifikasi_terakhir' => $row['sertifikasi_terakhir'] ?? '-',
                    'sertifikasi_ulang' => $row['sertifikasi_ulang'] ?? '-',
                    'keterangan' => $row['keterangan'] ?? null,
                    'sort_order' => $row['sort_order'] ?? $index,
                    'input_by' => $user->id,
                ]);
            }

            if ($metaAttributes !== []) {
                K3MetodePengujianPeralatanMeta::query()->updateOrCreate(
                    [
                        'unit_id' => $unit->id,
                        'year' => $year,
                        'month' => $month,
                    ],
                    $metaAttributes,
                );
            }
        });

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Formulir Metode Pengujian Peralatan {$unit->name} {$month}/{$year}",
            $unit,
            unit: $unit->id,
        );

        return redirect()
            ->route('k3.formulir.metode-pengujian.index', [
                'unit_id' => $unit->id,
                'month' => $month,
                'year' => $year,
            ])
            ->with('toast', [
                'type' => 'success',
                'message' => 'Data Formulir Metode Pengujian Peralatan berhasil disimpan.',
            ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        $this->authorizeView($user);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $month = max(1, min(12, $request->integer('month') ?: (int) now()->month));
        $year = $request->integer('year') ?: (int) now()->year;

        [$rows] = $this->loadRows($unit, $month, $year);
        $meta = $this->findMeta($unit, $month, $year);
        $input = $this->documentBuilder->applyPageOverrides($this->documentBuilder->metaInput($meta), $request);

        $data = $this->documentBuilder->buildData($unit, $month, $year, self::FORM, ['rows' => $rows], $input);
        $contentHtml = $meta?->format === 'html' ? $meta->content_html : null;
        $html = $this->documentBuilder->renderHtml($data, $contentHtml);

        $filename = sprintf('Metode-Pengujian-Peralatan-%s-%04d%02d.pdf', str($unit->name)->slug(), $year, $month);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response(Pdf::loadHTML($html)->setPaper('a4', self::FORM['orientation'])->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }

    private function authorizeView(User $user): void
    {
        abort_unless(
            $user->hasPermissionTo(PermissionName::K3InputView) ||
            $user->hasPermissionTo(PermissionName::K3LaporanView),
            403
        );
    }

    /**
     * Baris tersimpan periode ini, atau template standar bila belum ada.
     *
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private function loadRows(Unit $unit, int $month, int $year): array
    {
        $saved = K3MetodePengujianPeralatan::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($saved->isEmpty()) {
            return [K3MetodePengujianPeralatanForm::defaultRows(), false];
        }

        return [$saved->map(fn (K3MetodePengujianPeralatan $r, int $idx): array => [
            'id' => $r->id,
            'no_urut' => $r->no_urut ?? (string) ($idx + 1),
            'nama_peralatan' => $r->nama_peralatan,
            'no_pengesahan' => $r->no_pengesahan ?? '-',
            'nama_kategori_alat' => $r->nama_kategori_alat ?? '-',
            'uji_visual' => $r->uji_visual ?? '-',
            'uji_fungsi' => $r->uji_fungsi ?? '-',
            'uji_beban' => $r->uji_beban ?? '-',
            'uji_hydro' => $r->uji_hydro ?? '-',
            'ndt' => $r->ndt ?? '-',
            'uji_ultrasonic_thickness' => $r->uji_ultrasonic_thickness ?? '-',
            'uji_ketahanan' => $r->uji_ketahanan ?? '-',
            'sertifikasi_terakhir' => $r->sertifikasi_terakhir ?? '-',
            'sertifikasi_ulang' => $r->sertifikasi_ulang ?? '-',
            'keterangan' => $r->keterangan ?? '',
            'sort_order' => $r->sort_order,
        ])->all(), true];
    }

    private function findMeta(Unit $unit, int $month, int $year): ?K3MetodePengujianPeralatanMeta
    {
        return K3MetodePengujianPeralatanMeta::query()
            ->where('unit_id', $unit->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();
    }

    /**
     * Periode yang pernah disimpan untuk unit ini (terbaru dulu).
     *
     * @return list<array{year: int, month: int, items: int, updated_at: string|null}>
     */
    private function history(Unit $unit): array
    {
        return K3MetodePengujianPeralatan::query()
            ->where('unit_id', $unit->id)
            ->selectRaw('year, month, COUNT(*) as items, MAX(updated_at) as last_updated')
            ->groupBy('year', 'month')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(24)
            ->get()
            ->map(fn (K3MetodePengujianPeralatan $row): array => [
                'year' => (int) $row->year,
                'month' => (int) $row->month,
                'items' => (int) $row->getAttribute('items'),
                'updated_at' => $row->getAttribute('last_updated'),
            ])
            ->all();
    }
}
