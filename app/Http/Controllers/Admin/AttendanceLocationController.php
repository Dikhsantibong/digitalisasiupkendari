<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttendanceLocationRequest;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu "Lokasi Absensi": the office coordinates and absen radius of every unit.
 * Guarded by setting.manage, which only Super Admin holds by default.
 */
class AttendanceLocationController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermissionTo(PermissionName::SettingManage), 403);

        $units = Unit::query()->with('serviceUnit:id,name')->orderBy('name')
            ->get(['id', 'service_unit_id', 'code', 'name', 'location', 'latitude', 'longitude', 'attendance_radius_m', 'is_active']);

        return Inertia::render('admin/attendance-locations/index', [
            'units' => $units->map(fn (Unit $unit): array => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'location' => $unit->location,
                'service_unit' => $unit->serviceUnit?->name,
                'latitude' => $unit->latitude === null ? null : (float) $unit->latitude,
                'longitude' => $unit->longitude === null ? null : (float) $unit->longitude,
                'attendance_radius_m' => $unit->attendance_radius_m,
                'is_active' => $unit->is_active,
            ])->all(),
        ]);
    }

    public function update(AttendanceLocationRequest $request, Unit $unit): RedirectResponse
    {
        abort_unless($request->user()->hasPermissionTo(PermissionName::SettingManage), 403);

        $unit->update($request->validated());

        $this->activityLogger->log(
            ActivityEvent::Updated,
            "Mengatur lokasi absensi {$unit->name} (radius {$unit->attendance_radius_m} m)",
            subject: $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Lokasi absensi {$unit->name} disimpan."]);

        return back();
    }
}
