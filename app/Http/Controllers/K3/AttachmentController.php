<?php

namespace App\Http\Controllers\K3;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\K3Attachment;
use App\Models\Unit;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monthly K3 attachments (documents/photos). Files live on the public storage
 * disk; only the path is stored.
 */
class AttachmentController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputView), 403);

        $units = Unit::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');

        $unit = $units->firstWhere('id', (int) $request->integer('unit_id')) ?? $units->first();
        $now = Carbon::now();
        $month = (int) ($request->integer('month') ?: $now->month);
        $year = (int) ($request->integer('year') ?: $now->year);

        $attachments = K3Attachment::query()
            ->where('unit_id', $unit->id)->where('year', $year)->where('month', $month)
            ->orderByDesc('id')->get();

        return Inertia::render('k3/input/attachments', [
            'filters' => ['unit_id' => $unit->id, 'month' => $month, 'year' => $year],
            'attachments' => $attachments->map(fn (K3Attachment $a): array => [
                'id' => $a->id,
                'title' => $a->title,
                'category' => $a->category,
                'url' => Storage::disk('public')->url($a->file_path),
            ])->all(),
            'options' => ['units' => $units->all(), 'years' => range($year - 3, $year + 1)],
            'can_write' => $user->hasPermissionTo(PermissionName::K3InputWrite),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);

        $unit = Unit::query()->findOrFail($request->integer('unit_id'));
        abort_unless($user->canAccessUnit($unit), 403);

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'title' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $path = $request->file('file')->store('k3-attachments', 'public');

        K3Attachment::query()->create([
            'unit_id' => $unit->id,
            'year' => (int) $validated['year'],
            'month' => (int) $validated['month'],
            'title' => $validated['title'],
            'category' => $validated['category'] ?? null,
            'file_path' => $path,
            'input_by' => $user->id,
        ]);

        $this->activityLogger->log(
            ActivityEvent::Created,
            "Menambah lampiran K3 {$unit->name}",
            $unit,
            unit: $unit->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lampiran ditambahkan.']);

        return back();
    }

    public function destroy(Request $request, K3Attachment $attachment): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::K3InputWrite), 403);
        abort_unless($user->canAccessUnit($attachment->unit_id), 403);

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        $this->activityLogger->log(ActivityEvent::Deleted, 'Menghapus lampiran K3', unit: $attachment->unit_id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lampiran dihapus.']);

        return back();
    }
}
