<?php

namespace App\Http\Controllers\Keuangan;

use App\Enums\ActivityEvent;
use App\Enums\PermissionName;
use App\Http\Controllers\Concerns\HandlesKeuangan;
use App\Http\Controllers\Controller;
use App\Models\CashCardEntry;
use App\Models\ServiceUnit;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Master Supplier of a UL (user, 2026-11-20): the shops where the cash card
 * buys — nama, email, NPWP and the KTP photo, which go automatically into the
 * lampiran of every cash card row bought there.
 */
class SupplierController extends Controller
{
    use HandlesKeuangan;

    public function __construct(private readonly ActivityLogger $activityLogger) {}

    public function index(Request $request): Response
    {
        [$uls, $ul] = $this->keuanganTarget($request);

        return Inertia::render('keuangan/supplier/index', [
            'ul' => ['id' => $ul->id, 'name' => $ul->name],
            'filters' => ['ul_id' => $ul->id],
            'options' => $this->keuanganOptions($uls),
            'suppliers' => Supplier::query()->where('service_unit_id', $ul->id)->orderByDesc('is_active')->orderBy('name')->get()
                ->map(fn (Supplier $supplier): array => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'email' => $supplier->email,
                    'npwp' => $supplier->npwp,
                    'ktp_url' => $supplier->ktpUrl(),
                    'is_active' => $supplier->is_active,
                    'used' => CashCardEntry::query()->where('supplier_id', $supplier->id)->count(),
                ])->all(),
            'can_manage' => $request->user()->hasPermissionTo(PermissionName::KeuanganSupplierManage) && ! $request->user()->isReadOnly(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ul = $this->writableUl($request);
        $validated = $this->validated($request);

        $supplier = new Supplier(['service_unit_id' => $ul->id, ...$validated, 'is_active' => true]);
        $supplier->ktp_path = $request->hasFile('ktp') ? $request->file('ktp')->store("keuangan/supplier/{$ul->id}", 'public') : null;
        $supplier->save();

        $this->activityLogger->log(ActivityEvent::Created, "Menambah supplier {$supplier->name} ({$ul->name})", $supplier);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Supplier ditambahkan.']);

        return back();
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->writableUl($request, $supplier->service_unit_id);
        $validated = $this->validated($request);

        $supplier->fill([...$validated, 'is_active' => $request->boolean('is_active', true)]);
        if ($request->hasFile('ktp')) {
            $previous = $supplier->ktp_path;
            $supplier->ktp_path = $request->file('ktp')->store("keuangan/supplier/{$supplier->service_unit_id}", 'public');
            if ($previous !== null) {
                Storage::disk('public')->delete($previous);
            }
        }
        $supplier->save();

        $this->activityLogger->log(ActivityEvent::Updated, "Mengubah supplier {$supplier->name}", $supplier);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Supplier diperbarui.']);

        return back();
    }

    /**
     * A supplier already used by a cash card row is only deactivated (its
     * lampiran must keep printing); an unused one is deleted.
     */
    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->writableUl($request, $supplier->service_unit_id);

        if (CashCardEntry::query()->where('supplier_id', $supplier->id)->exists()) {
            $supplier->forceFill(['is_active' => false])->save();
            $message = 'Supplier sudah dipakai di cash card, sehingga dinonaktifkan (tidak dihapus).';
        } else {
            if ($supplier->ktp_path !== null) {
                Storage::disk('public')->delete($supplier->ktp_path);
            }
            $supplier->delete();
            $message = 'Supplier dihapus.';
        }

        $this->activityLogger->log(ActivityEvent::Deleted, "Menghapus/menonaktifkan supplier {$supplier->name}", $supplier);
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function writableUl(Request $request, ?int $serviceUnitId = null): ServiceUnit
    {
        $user = $request->user();
        abort_unless($user->hasPermissionTo(PermissionName::KeuanganSupplierManage) && ! $user->isReadOnly(), 403);

        $id = $serviceUnitId ?? (int) $request->validate(['ul_id' => ['required', 'integer', 'exists:service_units,id']])['ul_id'];
        abort_unless($this->canSeeUl($user, $id), 403);

        return ServiceUnit::query()->findOrFail($id);
    }

    /**
     * @return array{name: string, email: string|null, npwp: string|null}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'npwp' => ['nullable', 'string', 'max:40'],
            'ktp' => ['nullable', 'image', 'max:5120'],
        ], [], ['name' => 'nama supplier', 'npwp' => 'NPWP', 'ktp' => 'foto KTP']);

        return ['name' => trim($validated['name']), 'email' => $validated['email'] ?? null, 'npwp' => $validated['npwp'] ?? null];
    }
}
