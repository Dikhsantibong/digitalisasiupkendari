<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\MaintenanceAttachment;
use App\Models\ReportPeriod;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Photo attachments for maintenance work (modul HAR, lampiran). Files live in
 * the public storage disk; only the path is stored.
 */
class AttachmentController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);

        $period = ReportPeriod::query()
            ->where('unit_id', $unit->id)->where('month', $month)->where('year', $year)->first();

        $attachments = $period === null ? collect() : MaintenanceAttachment::query()
            ->where('unit_id', $unit->id)
            ->where('report_period_id', $period->id)
            ->with(['engine:id,name', 'workOrder:id,wonum'])
            ->orderBy('sort_order')->orderByDesc('id')
            ->get();

        return Inertia::render('har/input/attachments', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'attachments' => $attachments->map(fn (MaintenanceAttachment $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'caption' => $a->caption,
                'taken_date' => $a->taken_date?->toDateString(),
                'engine_name' => $a->engine?->name,
                'wonum' => $a->workOrder?->wonum,
                'url' => Storage::disk('public')->url($a->photo_path),
            ])->all(),
            'options' => [
                'units' => $units->all(),
                'years' => range($year - 3, $year + 1),
                'machines' => Machine::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'work_orders' => $period === null ? [] : WorkOrder::query()->where('unit_id', $unit->id)->where('report_period_id', $period->id)->orderBy('wonum')->get(['id', 'wonum']),
            ],
            'can_write' => $user->hasPermissionTo(PermissionName::HarInputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'title' => ['required', 'string', 'max:150'],
            'caption' => ['nullable', 'string', 'max:500'],
            'taken_date' => ['nullable', 'date'],
            'engine_id' => ['nullable', 'integer', Rule::exists('machines', 'id')->where('unit_id', $unit->id)],
            'wo_id' => ['nullable', 'integer', Rule::exists('work_orders', 'id')->where('unit_id', $unit->id)],
            'activity_id' => ['nullable', 'integer', Rule::exists('maintenance_activities', 'id')->where('unit_id', $unit->id)],
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $totalDays = (int) Carbon::create($year, $month, 1)->daysInMonth;
        $period = ReportPeriod::query()->firstOrCreate(
            ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            ['total_days' => $totalDays, 'total_hours' => $totalDays * 24],
        );

        $path = $request->file('photo')->store('har-attachments', 'public');

        MaintenanceAttachment::query()->create([
            'unit_id' => $unit->id,
            'report_period_id' => $period->id,
            'wo_id' => $validated['wo_id'] ?? null,
            'activity_id' => $validated['activity_id'] ?? null,
            'engine_id' => $validated['engine_id'] ?? null,
            'title' => $validated['title'],
            'photo_path' => $path,
            'caption' => $validated['caption'] ?? null,
            'taken_date' => $validated['taken_date'] ?? null,
            'input_by' => $user->id,
        ]);

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menambah lampiran foto {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto lampiran ditambahkan.']);

        return back();
    }

    public function destroy(Request $request, MaintenanceAttachment $attachment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::HarInputWrite), 403);
        abort_unless($user->canAccessUnit($attachment->unit_id), 403);

        Storage::disk('public')->delete($attachment->photo_path);
        $attachment->delete();

        $this->activityLogger->log(ActivityEvent::Deleted, 'Menghapus lampiran foto', unit: $attachment->unit_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto lampiran dihapus.']);

        return back();
    }
}
