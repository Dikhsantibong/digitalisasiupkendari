<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Models\Unit;
use App\Models\User;
use App\Services\Master\MasterRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reusable engine behind every module's generic master CRUD screen. The
 * using controller supplies its registry, permissions, and page; the field
 * schema drives validation and the form. Shared by the operasi and pemeliharaan
 * master controllers so the logic lives in one place.
 *
 * The using class must expose an `$activityLogger` and implement the four
 * accessors below.
 */
trait ManagesMasterResources
{
    abstract protected function masterRegistry(): MasterRegistry;

    abstract protected function masterViewPermission(): PermissionName;

    abstract protected function masterManagePermission(): PermissionName;

    /** The Inertia page that renders the master screen. */
    abstract protected function masterPage(): string;

    public function index(Request $request, string $resource): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo($this->masterViewPermission()), 403);

        $definition = $this->masterRegistry()->find($resource);
        abort_if($definition === null, 404);

        $unit = null;
        $units = [];

        if ($definition['unit_scoped']) {
            $unitModels = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
            abort_if($unitModels->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');
            $units = $unitModels->all();
            $unit = $unitModels->firstWhere('id', (int) $request->integer('unit_id')) ?? $unitModels->first();
        }

        /** @var class-string<Model> $model */
        $model = $definition['model'];
        $query = $model::query();
        if ($definition['unit_scoped']) {
            $query->where('unit_id', $unit->id);
        }
        foreach ($definition['order'] as $column) {
            $query->orderBy($column);
        }

        $keys = array_map(fn (array $field): string => $field['key'], $definition['fields']);
        $rows = $query->get()->map(fn (Model $item): array => Arr::only(
            $item->toArray(),
            [...$keys, 'id'],
        ))->all();

        return Inertia::render($this->masterPage(), [
            'resource' => [
                'slug' => $resource,
                'label' => $definition['label'],
                'unit_scoped' => $definition['unit_scoped'],
                'fields' => $this->fieldsWithOptions($definition['fields'], $unit),
            ],
            'resources' => $this->masterRegistry()->summaries(),
            'rows' => $rows,
            'filters' => ['unit_id' => $unit?->id],
            'units' => $units,
            'can_manage' => $user->hasPermissionTo($this->masterManagePermission()),
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        return $this->persistMaster($request, $resource, null);
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        return $this->persistMaster($request, $resource, $id);
    }

    public function destroy(Request $request, string $resource, int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo($this->masterManagePermission()), 403);

        $definition = $this->masterRegistry()->find($resource);
        abort_if($definition === null, 404);

        $item = $this->findScopedMaster($definition, $id, $user);
        $item->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus master {$definition['label']}",
            unit: $definition['unit_scoped'] ? $item->unit_id : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data dihapus.']);

        return back();
    }

    private function persistMaster(Request $request, string $resource, ?int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo($this->masterManagePermission()), 403);

        $definition = $this->masterRegistry()->find($resource);
        abort_if($definition === null, 404);

        $unitId = null;
        if ($definition['unit_scoped']) {
            $unitId = (int) $request->integer('unit_id');
            abort_unless($user->canAccessUnit($unitId), 403);
        }

        $data = $request->validate($this->masterRules($definition, $unitId));

        if ($definition['unit_scoped']) {
            $data['unit_id'] = $unitId;
        }

        /** @var class-string<Model> $model */
        $model = $definition['model'];

        if ($id === null) {
            $model::query()->create($data);
            $event = ActivityEvent::Created;
            $message = "Menambah master {$definition['label']}";
        } else {
            $item = $this->findScopedMaster($definition, $id, $user);
            $item->update($data);
            $event = ActivityEvent::Updated;
            $message = "Mengubah master {$definition['label']}";
        }

        $this->activityLogger->log($event, $message, unit: $unitId);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data disimpan.']);

        return back();
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function findScopedMaster(array $definition, int $id, User $user): Model
    {
        /** @var class-string<Model> $model */
        $model = $definition['model'];
        $item = $model::query()->findOrFail($id);

        if ($definition['unit_scoped']) {
            abort_unless($user->canAccessUnit($item->unit_id), 403);
        }

        return $item;
    }

    /**
     * Build validation rules from the field schema.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, array<int, mixed>>
     */
    private function masterRules(array $definition, ?int $unitId): array
    {
        $rules = [];

        if ($definition['unit_scoped']) {
            $rules['unit_id'] = ['required', 'integer', 'exists:units,id'];
        }

        foreach ($definition['fields'] as $field) {
            $required = $field['required'] ? 'required' : 'nullable';

            $rules[$field['key']] = match ($field['type']) {
                'text' => [$required, 'string', 'max:255'],
                'number' => [$required, 'numeric'],
                'date' => [$required, 'date'],
                'bool' => ['boolean'],
                'select' => [$required, Rule::in(array_column($field['options'], 'value'))],
                'relation' => $this->relationRule($field, $unitId),
                default => [$required],
            };
        }

        return $rules;
    }

    /**
     * The validation rule for a relation field: the referenced row must exist,
     * scoped to the current unit when the relation is unit-scoped.
     *
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private function relationRule(array $field, ?int $unitId): array
    {
        $exists = Rule::exists($field['source'], 'id');
        if ($field['relation_unit_scoped'] ?? true) {
            $exists->where('unit_id', $unitId);
        }

        return [$field['required'] ? 'required' : 'nullable', 'integer', $exists];
    }

    /**
     * Attach runtime options to relation fields — the referenced table's rows,
     * scoped to the current unit for unit-scoped relations (e.g. machines) and
     * unfiltered for global ones (e.g. apd_categories).
     *
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    private function fieldsWithOptions(array $fields, ?Unit $unit): array
    {
        return array_map(function (array $field) use ($unit): array {
            if ($field['type'] !== 'relation') {
                return $field;
            }

            $labelColumn = $field['label_column'] ?? 'name';
            $query = DB::table($field['source'])->orderBy($labelColumn);

            if ($field['relation_unit_scoped'] ?? true) {
                if ($unit === null) {
                    $field['options'] = [];

                    return $field;
                }
                $query->where('unit_id', $unit->id);
            }

            $field['options'] = $query->get(['id', $labelColumn])
                ->map(fn ($row): array => ['value' => $row->id, 'label' => $row->{$labelColumn}])
                ->all();

            return $field;
        }, $fields);
    }
}
