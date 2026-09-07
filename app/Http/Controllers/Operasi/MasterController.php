<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Operasi\Master\OperasiMasterRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One generic CRUD screen for every operasi master (feeders, tanks, lubricants,
 * calibration factors, status codes). The {@see OperasiMasterRegistry} field
 * schema drives both the validation and the form, so a new master needs no new
 * controller or page.
 */
class MasterController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly OperasiMasterRegistry $registry,
    ) {}

    public function index(Request $request, string $resource): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiMasterViewAny), 403);

        $definition = $this->registry->find($resource);
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

        return Inertia::render('operasi/master/index', [
            'resource' => [
                'slug' => $resource,
                'label' => $definition['label'],
                'unit_scoped' => $definition['unit_scoped'],
                'fields' => $this->fieldsWithOptions($definition['fields'], $unit),
            ],
            'resources' => $this->registry->summaries(),
            'rows' => $rows,
            'filters' => ['unit_id' => $unit?->id],
            'units' => $units,
            'can_manage' => $user->hasPermissionTo(PermissionName::OperasiMasterManage),
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        return $this->persist($request, $resource, null);
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        return $this->persist($request, $resource, $id);
    }

    public function destroy(Request $request, string $resource, int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiMasterManage), 403);

        $definition = $this->registry->find($resource);
        abort_if($definition === null, 404);

        $item = $this->findScoped($definition, $id, $user);
        $item->delete();

        $this->activityLogger->log(
            ActivityEvent::Deleted,
            "Menghapus master {$definition['label']}",
            unit: $definition['unit_scoped'] ? $item->unit_id : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data dihapus.']);

        return back();
    }

    private function persist(Request $request, string $resource, ?int $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiMasterManage), 403);

        $definition = $this->registry->find($resource);
        abort_if($definition === null, 404);

        $unitId = null;
        if ($definition['unit_scoped']) {
            $unitId = (int) $request->integer('unit_id');
            abort_unless($user->canAccessUnit($unitId), 403);
        }

        $data = $request->validate($this->rules($definition, $unitId));

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
            $item = $this->findScoped($definition, $id, $user);
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
    private function findScoped(array $definition, int $id, User $user): Model
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
    private function rules(array $definition, ?int $unitId): array
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
                'relation' => ['nullable', 'integer', Rule::exists('machines', 'id')->where('unit_id', $unitId)],
                default => [$required],
            };
        }

        return $rules;
    }

    /**
     * Attach runtime options (e.g. the unit's machines) to relation fields.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return list<array<string, mixed>>
     */
    private function fieldsWithOptions(array $fields, ?Unit $unit): array
    {
        return array_map(function (array $field) use ($unit): array {
            if ($field['type'] === 'relation' && $field['source'] === 'machines' && $unit !== null) {
                $field['options'] = Machine::query()
                    ->where('unit_id', $unit->id)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Machine $m): array => ['value' => $m->id, 'label' => $m->name])
                    ->all();
            }

            return $field;
        }, $fields);
    }
}
