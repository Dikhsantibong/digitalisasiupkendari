<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Http\Controllers\Controller;
use App\Models\K3DokumenIk;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\K3IkTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Input Dokumen IK K3 Lingkungan Pembangkit (Akses 1): the Instruksi Kerja
 * documents of a unit and report period, written from a template and printed
 * in the official layout. The documents are attached to the Laporan K3.
 */
class DokumenIkController extends Controller
{
    use EmbedsReportLogo;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        [$units, $unit] = $this->resolveUnit($request);
        [$month, $year] = $this->period($request);

        $docs = $this->docs($unit, $month, $year);

        return Inertia::render('k3/input/dokumen-ik/index', [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'options' => [
                'units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->values(),
                'years' => range(Carbon::now()->year - 3, Carbon::now()->year + 1),
            ],
            'docs' => $docs->map(fn (K3DokumenIk $doc): array => $this->present($doc))->values(),
            'selected_id' => $request->integer('doc') ?: null,
            'templates' => K3IkTemplates::all(),
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'id' => ['nullable', 'integer'],
            'sistem' => ['required', 'string', 'max:60'],
            'judul' => ['required', 'string', 'max:255'],
            'no_dokumen' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date'],
            'revisi' => ['nullable', 'string', 'max:20'],
            'halaman' => ['nullable', 'string', 'max:20'],
            'sections' => ['required', 'array', 'min:1', 'max:20'],
            'sections.*.judul' => ['required', 'string', 'max:150'],
            'sections.*.gaya' => ['required', Rule::in(K3DokumenIk::STYLES)],
            'sections.*.pengantar' => ['nullable', 'string', 'max:2000'],
            'sections.*.butir' => ['nullable', 'array', 'max:60'],
            'sections.*.butir.*' => ['nullable', 'string', 'max:1000'],
        ], [
            'judul.required' => 'Judul IK wajib diisi.',
            'sections.*.judul.required' => 'Setiap bagian harus punya judul (mis. ALAT).',
        ]);

        $unit = Unit::query()->findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $doc = ! empty($validated['id'])
            ? K3DokumenIk::query()->where('unit_id', $unit->id)->findOrFail($validated['id'])
            : new K3DokumenIk([
                'unit_id' => $unit->id,
                'year' => $validated['year'],
                'month' => $validated['month'],
                'sort_order' => (int) K3DokumenIk::query()->where('unit_id', $unit->id)->where('year', $validated['year'])->where('month', $validated['month'])->max('sort_order') + 1,
            ]);

        $doc->fill([
            'sistem' => trim($validated['sistem']),
            'judul' => trim($validated['judul']),
            'no_dokumen' => $this->text($validated['no_dokumen'] ?? null),
            'tanggal' => $validated['tanggal'] ?? null,
            'revisi' => $this->text($validated['revisi'] ?? null),
            'halaman' => $this->text($validated['halaman'] ?? null),
            'sections' => collect($validated['sections'])->map(fn (array $section): array => [
                'judul' => trim($section['judul']),
                'gaya' => $section['gaya'],
                'pengantar' => trim((string) ($section['pengantar'] ?? '')),
                'butir' => collect($section['butir'] ?? [])
                    ->map(fn ($point): string => trim((string) $point))
                    ->filter(fn (string $point): bool => $point !== '')
                    ->values()
                    ->all(),
            ])->values()->all(),
            'input_by' => $user->id,
        ])->save();

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Dokumen IK K3 \"{$doc->judul}\" {$unit->name} {$doc->month}/{$doc->year}",
            $doc,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen IK disimpan.']);

        return to_route('k3.input.dokumen-ik.index', [
            'unit_id' => $unit->id,
            'month' => $doc->month,
            'year' => $doc->year,
            'doc' => $doc->id,
        ]);
    }

    public function destroy(Request $request, K3DokumenIk $dokumenIk): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);
        abort_unless($user->canAccessUnit($dokumenIk->unit_id), 403);

        $dokumenIk->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dokumen IK dihapus.']);

        return to_route('k3.input.dokumen-ik.index', ['unit_id' => $dokumenIk->unit_id, 'month' => $dokumenIk->month, 'year' => $dokumenIk->year]);
    }

    /**
     * One IK (?doc=id) or every IK of the period, printed A4 portrait.
     */
    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        [, $unit] = $this->resolveUnit($request);
        [$month, $year] = $this->period($request);

        $docs = $this->docs($unit, $month, $year)
            ->when($request->filled('doc'), fn (Collection $docs): Collection => $docs->where('id', $request->integer('doc')))
            ->values();
        abort_if($docs->isEmpty(), 404, 'Dokumen IK tidak ditemukan.');

        $html = view('k3.dokumen-ik.pdf', [
            'docs' => $docs->map(fn (K3DokumenIk $doc): array => $this->present($doc))->all(),
            'unitName' => $unit->name,
        ])->render();

        $name = $docs->count() === 1 ? str($docs->first()->judul)->slug('_') : "Dokumen_IK_K3_{$month}_{$year}";

        return Pdf::loadHTML($this->embedAssets($html))->setPaper('a4', 'portrait')
            ->stream("{$name}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function present(K3DokumenIk $doc): array
    {
        return [
            'id' => $doc->id,
            'sistem' => $doc->sistem,
            'judul' => $doc->judul,
            'no_dokumen' => (string) $doc->no_dokumen,
            'tanggal' => $doc->tanggal?->format('Y-m-d') ?? '',
            'revisi' => (string) $doc->revisi,
            'halaman' => (string) $doc->halaman,
            'sections' => $doc->sections,
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return Collection<int, K3DokumenIk>
     */
    private function docs(Unit $unit, int $month, int $year): Collection
    {
        return K3DokumenIk::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderBy('sort_order')->orderBy('id')
            ->get();
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit}
     */
    private function resolveUnit(Request $request): array
    {
        $user = $request->user();
        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail((int) ($request->integer('unit_id') ?: $units->first()->id));
        abort_unless($user->canAccessUnit($unit), 403);

        return [$units, $unit];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function period(Request $request): array
    {
        $now = Carbon::now();

        return [
            max(1, min(12, (int) ($request->integer('month') ?: $now->month))),
            (int) ($request->integer('year') ?: $now->year),
        ];
    }

    private function canView(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::K3InputView) || $user->hasPermissionTo(PermissionName::K3LaporanView);
    }

    private function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
