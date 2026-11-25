<?php

namespace App\Http\Controllers\Har;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OperasiFlmMonitoring;
use App\Models\Unit;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FLM dari Operator for Pemeliharaan (user, 2026-11-24): the findings the
 * operators enter in Operasi → Monitoring FLM, with who reported them (name &
 * shift) and how long they stay open; Pemeliharaan closes an open finding
 * (kondisi akhir + catatan). Open findings older than
 * {@see FlmOperasiController::REMIND_AFTER_DAYS} are reminded daily
 * (App\Services\Notifications\FlmReminders).
 */
class FlmOperasiController extends Controller
{
    /** An open finding is reminded once it has been open this many days. */
    public const REMIND_AFTER_DAYS = 3;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(self::views($user), 403);

        $units = Unit::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        abort_if($units->isEmpty(), 403, 'Anda belum ditugaskan pada unit manapun.');
        $unit = $units->firstWhere('id', $request->integer('unit_id')) ?? $units->first();
        $status = in_array($request->string('status')->toString(), ['open', 'close', 'semua'], true) ? $request->string('status')->toString() : 'open';

        $now = Carbon::now('Asia/Makassar');
        $rows = OperasiFlmMonitoring::query()->where('unit_id', $unit->id)
            ->when($status !== 'semua', fn ($q) => $q->where('status', $status))
            // Open ones: oldest first; closed ones: latest closure first.
            ->when($status === 'close', fn ($q) => $q->orderByDesc('closed_at'), fn ($q) => $q->orderBy('tanggal')->orderBy('id'))
            ->limit(300)->get();

        return Inertia::render('har/flm-operasi/index', [
            'filters' => ['unit_id' => $unit->id, 'status' => $status],
            'options' => ['units' => $units->map(fn (Unit $u): array => ['id' => $u->id, 'name' => $u->name])->values()->all()],
            'rows' => $rows->map(fn (OperasiFlmMonitoring $r): array => [
                'id' => $r->id,
                'periode' => sprintf('%02d/%d', $r->month, $r->year),
                'tanggal' => $r->tanggal?->format('Y-m-d'),
                'mesin' => $r->mesin,
                'masalah' => $r->masalah,
                'kondisi_awal' => array_values(array_map(fn (string $k): string => OperasiFlmMonitoring::KONDISI_AWAL[$k] ?? $k, (array) ($r->kondisi_awal ?? []))),
                'kondisi_akhir' => $r->kondisi_akhir,
                'catatan' => $r->catatan,
                'status' => $r->status,
                'reporter_name' => $r->reporter_name,
                'reporter_shift' => $r->reporter_shift,
                'closed_by_name' => $r->closed_by_name,
                'closed_at' => $r->closed_at?->timezone('Asia/Makassar')->format('Y-m-d H:i'),
                'open_days' => $r->openDays($now),
                'foto_awal_url' => $r->foto_awal !== null ? Storage::disk('public')->url($r->foto_awal) : null,
            ])->values()->all(),
            'counts' => [
                'open' => OperasiFlmMonitoring::query()->where('unit_id', $unit->id)->where('status', 'open')->count(),
                'overdue' => OperasiFlmMonitoring::query()->where('unit_id', $unit->id)->where('status', 'open')->get()
                    ->filter(fn (OperasiFlmMonitoring $r): bool => ($r->openDays($now) ?? 0) >= self::REMIND_AFTER_DAYS)->count(),
            ],
            'remind_after_days' => self::REMIND_AFTER_DAYS,
            'can_close' => self::closes($user),
        ]);
    }

    /**
     * Close an open finding: kondisi akhir + catatan, who and when.
     */
    public function close(Request $request, OperasiFlmMonitoring $flm): RedirectResponse
    {
        $user = $request->user();
        abort_unless(self::closes($user) && $user->canAccessUnit($flm->unit_id), 403);
        abort_unless($flm->status === 'open', 422, 'FLM ini sudah ditutup.');

        $validated = $request->validate([
            'kondisi_akhir' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['close'])],
        ], ['kondisi_akhir.required' => 'Isi kondisi akhir (hasil perbaikan).']);

        $employee = Employee::query()->where('user_id', $user->id)->where('is_active', true)->first();
        $flm->update([
            'status' => 'close',
            'kondisi_akhir' => trim($validated['kondisi_akhir']),
            'catatan' => trim((string) ($validated['catatan'] ?? '')) ?: $flm->catatan,
            'closed_by' => $user->id,
            'closed_by_name' => $employee?->name ?? $user->name,
            'closed_at' => Carbon::now(),
        ]);

        $this->activityLogger->log(ActivityEvent::Updated, "Menutup FLM {$flm->mesin} ({$flm->masalah})", $flm, unit: $flm->unit_id);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'FLM ditutup.']);

        return back();
    }

    /** Pemeliharaan sees the operators' FLM findings (Akses 1 & 2). */
    public static function views(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::HarInputView)
            || $user->hasPermissionTo(PermissionName::HarPengusahaanView)
            || $user->hasPermissionTo(PermissionName::HarLaporanView);
    }

    /** Pemeliharaan writers close an open finding. */
    public static function closes(User $user): bool
    {
        return ! $user->isReadOnly()
            && ($user->hasPermissionTo(PermissionName::HarInputWrite) || $user->hasPermissionTo(PermissionName::HarPengusahaanWrite));
    }
}
