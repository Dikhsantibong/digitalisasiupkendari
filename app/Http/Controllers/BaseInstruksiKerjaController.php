<?php

namespace App\Http\Controllers;

use App\Enums\ActivityEvent;
use App\Enums\EmployeePosition;
use App\Http\Controllers\Concerns\EmbedsReportLogo;
use App\Models\HarInstruksiKerja;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared Instruksi Kerja (IK) input of a module (Pemeliharaan, Operasi …):
 * the unit's IK documents, written from a template, freely adjusted and
 * printed in the MKP layout (resources/views/har/instruksi-kerja/*, shared by
 * every module). A module controller only declares its model, page, routes,
 * permissions, templates and default signatories.
 */
abstract class BaseInstruksiKerjaController extends Controller
{
    use EmbedsReportLogo;

    public function __construct(protected readonly ActivityLogger $activityLogger) {}

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /** Inertia page, e.g. 'har/input/instruksi-kerja/index'. */
    abstract protected function page(): string;

    /** Route name prefix, e.g. 'har.input.instruksi-kerja'. */
    abstract protected function routePrefix(): string;

    /** Module name used in the activity log and the PDF file name, e.g. 'Pemeliharaan'. */
    abstract protected function moduleName(): string;

    abstract protected function canView(User $user): bool;

    abstract protected function canWrite(User $user): bool;

    /**
     * @return list<array<string, mixed>>
     */
    abstract protected function templates(): array;

    /**
     * Jabatan & position of the default "Dibuat" signer (the module's Koordinator).
     *
     * @return array{0: string, 1: EmployeePosition}
     */
    abstract protected function preparedBy(): array;

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        [$units, $unit] = $this->resolveUnit($request);

        return Inertia::render($this->page(), [
            'unit' => ['id' => $unit->id, 'name' => $unit->name],
            'filters' => ['unit_id' => $unit->id],
            'options' => ['units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->values()],
            'docs' => $this->docs($unit)->map(fn (Model $doc): array => $this->present($doc))->values(),
            'selected_id' => $request->integer('doc') ?: null,
            'templates' => $this->templates(),
            'signatories' => $this->signatories($unit),
            'can_write' => $this->canWrite($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'id' => ['nullable', 'integer'],
            'kop' => ['required', 'string', 'max:255'],
            'judul' => ['required', 'string', 'max:500'],
            'mesin' => ['nullable', 'string', 'max:100'],
            'no_dokumen' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date'],
            'revisi' => ['nullable', 'string', 'max:20'],
            'sections' => ['required', 'array', 'min:1', 'max:30'],
            'sections.*.judul' => ['required', 'string', 'max:200'],
            'sections.*.bernomor' => ['required', 'boolean'],
            'sections.*.gaya' => ['required', Rule::in(HarInstruksiKerja::STYLES)],
            'sections.*.lanjut' => ['required', 'boolean'],
            'sections.*.pengantar' => ['nullable', 'string', 'max:2000'],
            'sections.*.butir' => ['nullable', 'array', 'max:80'],
            'sections.*.butir.*.teks' => ['nullable', 'string', 'max:1500'],
            'sections.*.butir.*.sub' => ['nullable', 'array', 'max:40'],
            'sections.*.butir.*.sub.*' => ['nullable', 'string', 'max:1500'],
            'dibuat_jabatan' => ['nullable', 'string', 'max:100'],
            'dibuat_nama' => ['nullable', 'string', 'max:150'],
            'disetujui_jabatan' => ['nullable', 'string', 'max:100'],
            'disetujui_nama' => ['nullable', 'string', 'max:150'],
        ], [
            'judul.required' => 'Judul pekerjaan IK wajib diisi.',
            'sections.*.judul.required' => 'Setiap bagian harus punya judul (mis. ALAT).',
        ]);

        $unit = Unit::query()->findOrFail($validated['unit_id']);
        abort_unless($user->canAccessUnit($unit), 403);

        $model = $this->modelClass();
        $doc = ! empty($validated['id'])
            ? $this->query()->where('unit_id', $unit->id)->findOrFail($validated['id'])
            : new $model([
                'unit_id' => $unit->id,
                'sort_order' => (int) $this->query()->where('unit_id', $unit->id)->max('sort_order') + 1,
            ]);

        $doc->fill([
            'kop' => trim($validated['kop']),
            'judul' => trim($validated['judul']),
            'mesin' => $this->text($validated['mesin'] ?? null),
            'no_dokumen' => $this->text($validated['no_dokumen'] ?? null),
            'tanggal' => $validated['tanggal'] ?? null,
            'revisi' => $this->text($validated['revisi'] ?? null),
            'sections' => collect($validated['sections'])->map(fn (array $section): array => [
                'judul' => trim($section['judul']),
                'bernomor' => (bool) $section['bernomor'],
                'gaya' => $section['gaya'],
                'lanjut' => (bool) $section['lanjut'],
                'pengantar' => trim((string) ($section['pengantar'] ?? '')),
                'butir' => collect($section['butir'] ?? [])
                    ->map(fn (array $point): array => [
                        'teks' => trim((string) ($point['teks'] ?? '')),
                        'sub' => collect($point['sub'] ?? [])->map(fn ($s): string => trim((string) $s))->filter()->values()->all(),
                    ])
                    ->filter(fn (array $point): bool => $point['teks'] !== '')
                    ->values()
                    ->all(),
            ])->values()->all(),
            'dibuat_jabatan' => $this->text($validated['dibuat_jabatan'] ?? null),
            'dibuat_nama' => $this->text($validated['dibuat_nama'] ?? null),
            'disetujui_jabatan' => $this->text($validated['disetujui_jabatan'] ?? null),
            'disetujui_nama' => $this->text($validated['disetujui_nama'] ?? null),
            'input_by' => $user->id,
        ])->save();

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Menyimpan Instruksi Kerja {$this->moduleName()} \"".str_replace("\n", ' ', $doc->judul)."\" {$unit->name}",
            $doc,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Instruksi Kerja disimpan.']);

        return to_route("{$this->routePrefix()}.index", ['unit_id' => $unit->id, 'doc' => $doc->id]);
    }

    public function destroy(Request $request, int $instruksiKerja): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canWrite($user), 403);

        $doc = $this->query()->findOrFail($instruksiKerja);
        abort_unless($user->canAccessUnit($doc->unit_id), 403);

        $doc->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Instruksi Kerja dihapus.']);

        return to_route("{$this->routePrefix()}.index", ['unit_id' => $doc->unit_id]);
    }

    /**
     * One IK (?doc=id) or all IK of the unit, A4 portrait.
     */
    public function pdf(Request $request): HttpResponse
    {
        $user = $request->user();
        abort_unless($this->canView($user), 403);

        [, $unit] = $this->resolveUnit($request);
        $docs = $this->docs($unit)
            ->when($request->filled('doc'), fn (Collection $docs): Collection => $docs->where('id', $request->integer('doc')))
            ->values();
        abort_if($docs->isEmpty(), 404, 'Instruksi Kerja tidak ditemukan.');

        $html = view('har.instruksi-kerja.pdf', [
            'docs' => $docs->map(fn (Model $doc): array => $this->present($doc))->all(),
            'unitName' => $unit->name,
        ])->render();

        $name = $docs->count() === 1
            ? 'IK_'.str(str_replace("\n", ' ', $docs->first()->judul))->slug('_')
            : "Instruksi_Kerja_{$this->moduleName()}_".str($unit->name)->slug('_');

        return Pdf::loadHTML($this->embedAssets($html))->setPaper('a4', 'portrait')->stream("{$name}.pdf");
    }

    /**
     * @return Builder<Model>
     */
    protected function query(): Builder
    {
        return $this->modelClass()::query();
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(Model $doc): array
    {
        return [
            'id' => $doc->id,
            'kop' => $doc->kop,
            'judul' => $doc->judul,
            'mesin' => (string) $doc->mesin,
            'no_dokumen' => (string) $doc->no_dokumen,
            'tanggal' => $doc->tanggal?->format('Y-m-d') ?? '',
            'revisi' => (string) $doc->revisi,
            'sections' => $doc->sections,
            'dibuat_jabatan' => (string) $doc->dibuat_jabatan,
            'dibuat_nama' => (string) $doc->dibuat_nama,
            'disetujui_jabatan' => (string) $doc->disetujui_jabatan,
            'disetujui_nama' => (string) $doc->disetujui_nama,
            'updated_at' => $doc->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Default Dibuat (the module's Koordinator) & Disetujui (Project Leader) of a new IK: the unit's jabatan holders.
     *
     * @return array{dibuat_jabatan: string, dibuat_nama: string, disetujui_jabatan: string, disetujui_nama: string}
     */
    protected function signatories(Unit $unit): array
    {
        $holder = fn (EmployeePosition $position): string => (string) $unit->employees()
            ->where('is_active', true)->where('position', $position->value)->orderBy('id')->value('name');
        [$jabatan, $position] = $this->preparedBy();

        return [
            'dibuat_jabatan' => $jabatan,
            'dibuat_nama' => $holder($position),
            'disetujui_jabatan' => 'Project Leader',
            'disetujui_nama' => $holder(EmployeePosition::ProjectLeader),
        ];
    }

    /**
     * @return Collection<int, Model>
     */
    protected function docs(Unit $unit): Collection
    {
        return $this->query()->where('unit_id', $unit->id)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * @return array{0: Collection<int, Unit>, 1: Unit}
     */
    protected function resolveUnit(Request $request): array
    {
        $user = $request->user();
        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = Unit::query()->findOrFail((int) ($request->integer('unit_id') ?: $units->first()->id));
        abort_unless($user->canAccessUnit($unit), 403);

        return [$units, $unit];
    }

    private function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
