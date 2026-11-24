<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\OperasiPatrolCheckMesin;
use App\Models\OperasiPatrolCheckTemplate;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Template Patrol Check (Master Data Operasi): configures the form format
 * ("monthly" matrix N/T vs "checklist" per-shift parameter form), form metadata
 * (judul, nomor form, revisi, petunjuk), equipment items / parameter flags,
 * and machine assignments. Shared across units, so Super Admin edits
 * (`setting.manage`) while Master Operasi viewers read.
 */
class PatrolCheckTemplateController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(
            $user->hasPermissionTo(PermissionName::OperasiMasterViewAny) || $user->hasPermissionTo(PermissionName::SettingManage),
            403,
        );

        $templates = OperasiPatrolCheckTemplate::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $machines = Machine::query()
            ->visibleTo($user)
            ->with('unit:id,name')
            ->where('is_active', true)
            ->orderBy('unit_id')
            ->orderBy('name')
            ->get(['id', 'unit_id', 'name', 'patrol_check_template_id']);

        return Inertia::render('operasi/patrol-check-template/index', [
            'templates' => $templates->map(fn (OperasiPatrolCheckTemplate $template): array => [
                'id' => $template->id,
                'code' => $template->code,
                'name' => $template->name,
                'description' => $template->description,
                'is_default' => $template->is_default,
                'format' => $template->resolvedFormat(),
                'meta' => $template->resolvedMeta(),
                'items' => array_values($template->items ?? []),
            ])->all(),
            'machines' => $machines->map(fn (Machine $m): array => [
                'id' => $m->id,
                'name' => $m->name,
                'unit' => $m->unit?->name,
                'template_id' => $m->patrol_check_template_id,
            ])->all(),
            'param_options' => OperasiPatrolCheckMesin::CHECKLIST_PARAMS,
            'default_monthly_items_count' => count(OperasiPatrolCheckMesin::DEFAULT_ITEMS),
            'can_manage' => $user->hasPermissionTo(PermissionName::SettingManage) && ! $user->isReadOnly(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'copy_from' => ['nullable', 'integer', 'exists:operasi_patrol_check_templates,id'],
            'format' => ['nullable', Rule::in([OperasiPatrolCheckMesin::FORMAT_MONTHLY, OperasiPatrolCheckMesin::FORMAT_CHECKLIST])],
        ]);

        $source = isset($validated['copy_from'])
            ? OperasiPatrolCheckTemplate::query()->find($validated['copy_from'])
            : null;

        $format = $source?->resolvedFormat() ?? ($validated['format'] ?? OperasiPatrolCheckMesin::FORMAT_CHECKLIST);

        $template = OperasiPatrolCheckTemplate::query()->create([
            'code' => $this->uniqueCode($validated['name']),
            'name' => trim($validated['name']),
            'description' => $source?->description,
            'format' => $format,
            'meta' => $source?->meta ?? ($format === OperasiPatrolCheckMesin::FORMAT_CHECKLIST ? OperasiPatrolCheckMesin::POASIA_META : null),
            'items' => $source?->items,
            'is_default' => false,
        ]);

        $this->activityLogger->log(ActivityEvent::Created, "Membuat template Patrol Check {$template->name}", $template);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template Patrol Check dibuat.']);

        return to_route('operasi.patrol-check-template.index', ['template' => $template->id]);
    }

    public function update(Request $request, OperasiPatrolCheckTemplate $template): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'format' => ['required', Rule::in([OperasiPatrolCheckMesin::FORMAT_MONTHLY, OperasiPatrolCheckMesin::FORMAT_CHECKLIST])],
            'meta' => ['nullable', 'array'],
            'meta.title' => ['nullable', 'string', 'max:160'],
            'meta.form_number' => ['nullable', 'string', 'max:60'],
            'meta.revision' => ['nullable', 'string', 'max:40'],
            'meta.instruction' => ['nullable', 'string', 'max:500'],
            'items' => ['array'],
            'items.*.system' => ['required', 'string', 'max:120'],
            'items.*.peralatan' => ['required', 'string', 'max:160'],
            'items.*.params' => ['nullable', 'array'],
            'items.*.params.*' => ['boolean'],
            'machine_ids' => ['array'],
            'machine_ids.*' => ['integer'],
        ], [
            'name.required' => 'Nama template wajib diisi.',
            'items.*.system.required' => 'Area / Sistem wajib diisi.',
            'items.*.peralatan.required' => 'Komponen / Peralatan wajib diisi.',
        ]);

        $paramKeys = array_column(OperasiPatrolCheckMesin::CHECKLIST_PARAMS, 'key');
        $format = $validated['format'];

        $items = array_values(array_map(function (array $item) use ($format, $paramKeys): array {
            $row = [
                'system' => trim($item['system']),
                'peralatan' => trim($item['peralatan']),
            ];
            if ($format === OperasiPatrolCheckMesin::FORMAT_CHECKLIST) {
                $rawParams = is_array($item['params'] ?? null) ? $item['params'] : [];
                $params = [];
                foreach ($paramKeys as $key) {
                    $params[$key] = $rawParams === [] ? true : (bool) ($rawParams[$key] ?? false);
                }
                $row['params'] = $params;
            }

            return $row;
        }, $validated['items'] ?? []));

        $rawMeta = $validated['meta'] ?? [];
        $meta = array_filter([
            'title' => self::clean($rawMeta['title'] ?? null),
            'form_number' => self::clean($rawMeta['form_number'] ?? null),
            'revision' => self::clean($rawMeta['revision'] ?? null),
            'instruction' => self::clean($rawMeta['instruction'] ?? null),
        ], fn (?string $v): bool => $v !== null);

        DB::transaction(function () use ($template, $validated, $format, $meta, $items, $request): void {
            $template->update([
                'name' => trim($validated['name']),
                'description' => self::clean($validated['description'] ?? null),
                'format' => $format,
                'meta' => $meta !== [] ? $meta : null,
                'items' => $items !== [] ? $items : null,
            ]);

            if (! $template->is_default) {
                $visible = Machine::query()->visibleTo($request->user())->pluck('id')->all();
                $chosen = array_values(array_intersect($visible, array_map('intval', $validated['machine_ids'] ?? [])));

                Machine::query()
                    ->whereIn('id', $visible)
                    ->where('patrol_check_template_id', $template->id)
                    ->whereNotIn('id', $chosen)
                    ->update(['patrol_check_template_id' => null]);

                Machine::query()
                    ->whereIn('id', $chosen)
                    ->update(['patrol_check_template_id' => $template->id]);
            }
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Mengubah template Patrol Check {$template->name}", $template);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template Patrol Check disimpan.']);

        return to_route('operasi.patrol-check-template.index', ['template' => $template->id]);
    }

    public function destroy(Request $request, OperasiPatrolCheckTemplate $template): RedirectResponse
    {
        $this->authorizeManage($request);
        abort_if($template->is_default, 422, 'Template bawaan tidak dapat dihapus.');

        DB::transaction(function () use ($template): void {
            Machine::query()->where('patrol_check_template_id', $template->id)->update(['patrol_check_template_id' => null]);
            $template->delete();
        });

        $this->activityLogger->log(ActivityEvent::Deleted, "Menghapus template Patrol Check {$template->name}", $template);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template Patrol Check dihapus; mesinnya kembali ke template bawaan.']);

        return to_route('operasi.patrol-check-template.index');
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::SettingManage) && ! $user->isReadOnly(), 403);
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'template';
        $code = $base;
        for ($i = 2; OperasiPatrolCheckTemplate::query()->where('code', $code)->exists(); $i++) {
            $code = "{$base}-{$i}";
        }

        return $code;
    }

    private static function clean(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
