<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only monitoring of everything users have done, scoped to what the viewer
 * is entitled to review.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ActivityLog::class);

        $viewer = $request->user();

        $logs = ActivityLog::query()
            ->visibleTo($viewer)
            ->with(['user:id,name,email', 'unit:id,name'])
            ->when($request->string('search')->trim()->value(), function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->input('event'), fn (Builder $query, $event) => $query->where('event', $event))
            ->when($request->input('user_id'), fn (Builder $query, $userId) => $query->where('user_id', $userId))
            ->when($request->input('unit_id'), fn (Builder $query, $unitId) => $query->where('unit_id', $unitId))
            ->when($request->date('from'), fn (Builder $query, $from) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('to'), fn (Builder $query, $to) => $query->where('created_at', '<=', $to->endOfDay()))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/activity-logs/index', [
            'logs' => $logs->through(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'event' => $log->event->value,
                'event_label' => $log->event->label(),
                'tone' => $log->event->tone(),
                'description' => $log->description,
                'user' => $log->user === null ? null : [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ],
                'unit' => $log->unit?->name,
                'ip_address' => $log->ip_address,
                'properties' => $log->properties,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'filters' => $request->only(['search', 'event', 'user_id', 'unit_id', 'from', 'to']),
            'options' => [
                'events' => collect(ActivityEvent::cases())
                    ->map(fn (ActivityEvent $event): array => [
                        'value' => $event->value,
                        'label' => $event->label(),
                    ])
                    ->all(),
                'units' => Unit::query()
                    ->visibleTo($viewer)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->all(),
                'users' => User::query()
                    ->whereIn('id', ActivityLog::query()->visibleTo($viewer)->distinct()->pluck('user_id')->filter())
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->all(),
            ],
        ]);
    }
}
