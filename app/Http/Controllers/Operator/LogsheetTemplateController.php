<?php

namespace App\Http\Controllers\Operator;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\LogsheetParameter;
use App\Models\LogsheetTemplate;
use App\Models\Machine;
use App\Models\OperatorLogsheetReading;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Template Form Mesin (user, 2026-11-21): the logsheet form of each jenis mesin
 * — its parameters (group → name → sub, unit, Normal / Min-Alarm / Max-Trip,
 * input type), its time slots, the Patrol Check Mesin items and the machines
 * that use it. Templates are shared by every unit, so only Super Admin
 * (setting.manage) changes them; Master Operasi viewers can read them.
 *
 * A parameter that already has readings is never deleted, only deactivated,
 * so stored logsheets keep their values.
 */
class LogsheetTemplateController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::OperasiMasterViewAny) || $user->hasPermissionTo(PermissionName::SettingManage), 403);

        $templates = LogsheetTemplate::query()->orderByDesc('is_default')->orderBy('name')->get();
        $machines = Machine::query()->visibleTo($user)->with('unit:id,name')->where('is_active', true)
            ->orderBy('unit_id')->orderBy('name')->get(['id', 'unit_id', 'name', 'logsheet_template_id']);

        return Inertia::render('operator/logsheet-template/index', [
            'templates' => $templates->map(fn (LogsheetTemplate $template): array => [
                'id' => $template->id,
                'code' => $template->code,
                'name' => $template->name,
                'description' => $template->description,
                'is_default' => $template->is_default,
                'time_slots' => $template->slots(),
                'patrol_items' => array_values($template->patrol_items ?? []),
                'parameters' => LogsheetParameter::query()->ofTemplate($template)->orderBy('sort_order')->orderBy('id')->get()
                    ->map(fn (LogsheetParameter $p): array => [
                        'id' => $p->id,
                        'code' => $p->code,
                        'group_label' => $p->group_label,
                        'name' => $p->name,
                        'sub_channel' => $p->sub_channel,
                        'unit_of_measure' => $p->unit_of_measure,
                        'input_type' => $p->input_type ?: 'number',
                        'options' => implode(', ', $p->options ?? []),
                        'normal_value' => $p->normal_value,
                        'alarm_value' => $p->alarm_value,
                        'trip_value' => $p->trip_value,
                        'is_active' => $p->is_active,
                    ])->all(),
            ])->all(),
            'machines' => $machines->map(fn (Machine $m): array => [
                'id' => $m->id,
                'name' => $m->name,
                'unit' => $m->unit?->name,
                'template_id' => $m->logsheet_template_id,
            ])->all(),
            'slot_options' => self::slotOptions(),
            'can_manage' => $user->hasPermissionTo(PermissionName::SettingManage) && ! $user->isReadOnly(),
        ]);
    }

    /**
     * A new template, blank or copied from another one.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'copy_from' => ['nullable', 'integer', 'exists:logsheet_templates,id'],
        ]);

        $source = isset($validated['copy_from']) ? LogsheetTemplate::query()->find($validated['copy_from']) : null;
        $template = DB::transaction(function () use ($validated, $source): LogsheetTemplate {
            $template = LogsheetTemplate::query()->create([
                'code' => $this->uniqueCode($validated['name']),
                'name' => $validated['name'],
                'time_slots' => $source?->time_slots,
                'patrol_items' => $source?->patrol_items,
                'is_default' => false,
            ]);
            if ($source !== null) {
                LogsheetParameter::query()->ofTemplate($source)->orderBy('sort_order')->orderBy('id')->get()
                    ->each(fn (LogsheetParameter $p) => $template->parameters()->create($p->only([
                        'plant_type', 'code', 'group_label', 'name', 'sub_channel', 'unit_of_measure', 'input_type', 'options',
                        'normal_value', 'alarm_value', 'trip_value', 'sort_order', 'is_active',
                    ])));
            }

            return $template;
        });

        $this->activityLogger->log(ActivityEvent::Created, "Membuat template logsheet {$template->name}", $template);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template dibuat.']);

        return to_route('operator.logsheet-template.index', ['template' => $template->id]);
    }

    public function update(Request $request, LogsheetTemplate $template): RedirectResponse
    {
        $this->authorizeManage($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'time_slots' => ['required', 'array', 'min:1'],
            'time_slots.*' => ['string', Rule::in(self::slotOptions())],
            'patrol_items' => ['array'],
            'patrol_items.*.system' => ['required', 'string', 'max:120'],
            'patrol_items.*.peralatan' => ['required', 'string', 'max:160'],
            'parameters' => ['required', 'array', 'min:1'],
            'parameters.*.id' => ['nullable', 'integer'],
            'parameters.*.code' => ['nullable', 'string', 'max:60'],
            'parameters.*.group_label' => ['nullable', 'string', 'max:120'],
            'parameters.*.name' => ['required', 'string', 'max:120'],
            'parameters.*.sub_channel' => ['nullable', 'string', 'max:40'],
            'parameters.*.unit_of_measure' => ['nullable', 'string', 'max:30'],
            'parameters.*.input_type' => ['required', Rule::in(LogsheetParameter::INPUT_TYPES)],
            'parameters.*.options' => ['nullable', 'string', 'max:200'],
            'parameters.*.normal_value' => ['nullable', 'string', 'max:40'],
            'parameters.*.alarm_value' => ['nullable', 'string', 'max:40'],
            'parameters.*.trip_value' => ['nullable', 'string', 'max:40'],
            'parameters.*.is_active' => ['boolean'],
            'machine_ids' => ['array'],
            'machine_ids.*' => ['integer'],
        ], [
            'parameters.*.name.required' => 'Nama parameter wajib diisi.',
            'patrol_items.*.system.required' => 'Sistem wajib diisi.',
            'patrol_items.*.peralatan.required' => 'Peralatan wajib diisi.',
        ]);

        // Keep the slot order of the day (01:00 … 24:00), whatever order they were ticked in.
        $slots = array_values(array_intersect(self::slotOptions(), $validated['time_slots']));

        DB::transaction(function () use ($template, $validated, $slots, $request): void {
            $template->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                // The default template keeps "no slots" = the standard set while it equals it.
                'time_slots' => $template->is_default && $slots === LogsheetTemplate::DEFAULT_SLOTS ? null : $slots,
                'patrol_items' => array_values(array_map(fn (array $item): array => ['system' => trim($item['system']), 'peralatan' => trim($item['peralatan'])], $validated['patrol_items'] ?? [])) ?: null,
            ]);

            $this->syncParameters($template, $validated['parameters']);

            // Machines of the units the user sees: ticked ones use this template; unticked ones go back to the default.
            if (! $template->is_default) {
                $visible = Machine::query()->visibleTo($request->user())->pluck('id')->all();
                $chosen = array_values(array_intersect($visible, array_map('intval', $validated['machine_ids'] ?? [])));
                Machine::query()->whereIn('id', $visible)->where('logsheet_template_id', $template->id)->whereNotIn('id', $chosen)->update(['logsheet_template_id' => null]);
                Machine::query()->whereIn('id', $chosen)->update(['logsheet_template_id' => $template->id]);
            }
        });

        $this->activityLogger->log(ActivityEvent::Updated, "Mengubah template logsheet {$template->name}", $template);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template disimpan.']);

        return to_route('operator.logsheet-template.index', ['template' => $template->id]);
    }

    public function destroy(Request $request, LogsheetTemplate $template): RedirectResponse
    {
        $this->authorizeManage($request);
        abort_if($template->is_default, 422, 'Template bawaan tidak dapat dihapus.');

        $used = OperatorLogsheetReading::query()->whereIn('parameter_id', $template->parameters()->pluck('id'))->exists();
        if ($used) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Template sudah dipakai pada logsheet tersimpan, tidak dapat dihapus.']);

            return back();
        }

        DB::transaction(function () use ($template): void {
            Machine::query()->where('logsheet_template_id', $template->id)->update(['logsheet_template_id' => null]);
            $template->parameters()->delete();
            $template->delete();
        });

        $this->activityLogger->log(ActivityEvent::Deleted, "Menghapus template logsheet {$template->name}", null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template dihapus.']);

        return to_route('operator.logsheet-template.index');
    }

    /**
     * Update / create the template's parameters in the sent order. A removed
     * parameter is deleted, or only deactivated when it already has readings.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncParameters(LogsheetTemplate $template, array $rows): void
    {
        $existing = LogsheetParameter::query()->ofTemplate($template)->get()->keyBy('id');
        $kept = [];
        $codes = [];

        foreach (array_values($rows) as $order => $row) {
            $code = Str::upper(Str::slug((string) ($row['code'] ?? ''), '_'));
            if ($code === '') {
                $code = Str::upper(Str::slug(implode(' ', array_filter([$row['group_label'] ?? null, $row['name'], $row['sub_channel'] ?? null])), '_'));
            }
            $base = Str::limit($code, 55, '');
            $suffix = 2;
            while (in_array($code, $codes, true)) {
                $code = $base.'_'.$suffix++;
            }
            $codes[] = $code;

            $options = $row['input_type'] === 'choice'
                ? array_values(array_filter(array_map('trim', explode(',', (string) ($row['options'] ?? ''))), fn (string $o): bool => $o !== ''))
                : null;

            $attributes = [
                'template_id' => $template->id,
                'code' => $code,
                'group_label' => self::clean($row['group_label'] ?? null),
                'name' => trim((string) $row['name']),
                'sub_channel' => self::clean($row['sub_channel'] ?? null),
                'unit_of_measure' => self::clean($row['unit_of_measure'] ?? null),
                'input_type' => $row['input_type'],
                'options' => $options ?: null,
                'normal_value' => self::clean($row['normal_value'] ?? null),
                'alarm_value' => self::clean($row['alarm_value'] ?? null),
                'trip_value' => self::clean($row['trip_value'] ?? null),
                'sort_order' => $order,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            $parameter = isset($row['id']) ? $existing->get((int) $row['id']) : null;
            if ($parameter !== null) {
                $parameter->update($attributes);
                $kept[] = $parameter->id;
            } else {
                $kept[] = LogsheetParameter::query()->create(['plant_type' => 'all', ...$attributes])->id;
            }
        }

        foreach ($existing->except($kept) as $removed) {
            if (OperatorLogsheetReading::query()->where('parameter_id', $removed->id)->exists()) {
                $removed->update(['is_active' => false, 'sort_order' => 9999]);
            } else {
                $removed->delete();
            }
        }
    }

    /**
     * Every slot a template may use: each half-hour of the day, 00:30 … 24:00.
     *
     * @return list<string>
     */
    public static function slotOptions(): array
    {
        $slots = [];
        foreach (range(0, 23) as $hour) {
            $slots[] = sprintf('%02d:30', $hour);
            $slots[] = sprintf('%02d:00', $hour + 1);
        }

        return $slots;
    }

    private static function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'template';
        $code = $base;
        $i = 2;
        while (LogsheetTemplate::query()->where('code', $code)->exists()) {
            $code = $base.'-'.$i++;
        }

        return $code;
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->hasPermissionTo(PermissionName::SettingManage) && ! $request->user()->isReadOnly(), 403);
    }
}
