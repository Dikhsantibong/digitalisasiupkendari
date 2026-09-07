<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\BeritaAcaraType;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the Berita Acara letter-number settings: the global defaults and the
 * optional per-unit overrides. The number is fixed here (never generated); a
 * unit override wins over the global default at print time.
 */
class DocumentTemplateController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiMasterManage), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);

        // Scope: null = global defaults, otherwise a specific unit's overrides.
        $unitId = $request->integer('unit_id') ?: null;
        if ($unitId !== null) {
            abort_unless($user->canAccessUnit($unitId), 403);
        }

        $globals = DocumentTemplate::query()->whereNull('unit_id')->get()->keyBy('type');
        $overrides = $unitId === null
            ? collect()
            : DocumentTemplate::query()->where('unit_id', $unitId)->get()->keyBy('type');

        $templates = collect(BeritaAcaraType::cases())->map(function (BeritaAcaraType $type) use ($globals, $overrides, $unitId): array {
            $global = $globals->get($type->value);
            $override = $overrides->get($type->value);
            $effective = $override ?? $global;

            return [
                'type' => $type->value,
                'label' => $type->label(),
                'is_override' => $unitId !== null && $override !== null,
                'document_number' => $effective?->document_number ?? $type->defaultDocumentNumber(),
                'title' => $effective?->title ?? $type->documentTitle(),
                'revision' => $effective?->revision ?? '00',
                'revision_date' => $effective?->revision_date?->toDateString(),
            ];
        })->all();

        return Inertia::render('operasi/document-template/index', [
            'scope' => ['unit_id' => $unitId],
            'templates' => $templates,
            'units' => $units->all(),
        ]);
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiMasterManage), 403);

        $beritaAcaraType = BeritaAcaraType::tryFrom($type);
        abort_if($beritaAcaraType === null, 404);

        $validated = $request->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'document_number' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'revision' => ['required', 'string', 'max:20'],
            'revision_date' => ['nullable', 'date'],
        ]);

        $unitId = $validated['unit_id'] ?? null;
        if ($unitId !== null) {
            abort_unless($user->canAccessUnit($unitId), 403);
        }

        DocumentTemplate::query()->updateOrCreate(
            ['type' => $beritaAcaraType->value, 'unit_id' => $unitId],
            [
                'module_code' => 'operasi',
                'title' => $validated['title'],
                'document_number' => $validated['document_number'],
                'revision' => $validated['revision'],
                'revision_date' => $validated['revision_date'] ?? null,
            ],
        );

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Mengatur nomor surat {$beritaAcaraType->label()}".($unitId ? ' (override unit)' : ' (global)'),
            unit: $unitId,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template dokumen disimpan.']);

        return back();
    }
}
