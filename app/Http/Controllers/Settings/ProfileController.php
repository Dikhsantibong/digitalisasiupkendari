<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing([
            'employee.unit.serviceUnit:id,name',
            'employee.serviceUnit:id,name',
            'roles:id,name,display_name',
            'roleAssignments.unit.serviceUnit:id,name',
            'roleAssignments.serviceUnit:id,name',
        ]);

        $employee = $this->resolveEmployee($user);
        if ($employee !== null) {
            $employee->loadMissing(['unit.serviceUnit:id,name', 'serviceUnit:id,name']);
        }

        $roleLabels = $user->roles
            ->pluck('display_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $unitAssignment = $user->roleAssignments->firstWhere('unit_id', '!=', null);
        $serviceUnitAssignment = $user->roleAssignments->firstWhere('service_unit_id', '!=', null);

        $unitName = $employee?->unit?->name
            ?? $unitAssignment?->unit?->name;

        $serviceUnitName = $employee?->serviceUnit?->name
            ?? $employee?->unit?->serviceUnit?->name
            ?? $serviceUnitAssignment?->serviceUnit?->name
            ?? $unitAssignment?->unit?->serviceUnit?->name;

        $reguLabel = null;
        if (! empty($employee?->regu)) {
            $reguLabel = 'Regu '.$employee->regu.($employee->is_shift_leader ? ' (Leader Shift)' : '');
        }

        $divisionMap = [
            'operasi' => 'Operasi',
            'pemeliharaan' => 'Pemeliharaan',
            'k3' => 'K3',
            'umum' => 'Umum',
            'logistik' => 'Logistik',
            'pdm' => 'PDM',
            'menejemen' => 'Menejemen',
        ];
        $divisionLabel = $employee?->division
            ? ($divisionMap[strtolower($employee->division)] ?? $employee->division)
            : null;

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'accountInfo' => [
                'name' => $employee?->name ?: $user->name,
                'nip' => $employee?->nip ?: ($user->employee_id ?: '-'),
                'position' => $employee?->position ?: ($user->position ?: ($roleLabels[0] ?? '-')),
                'division' => $divisionLabel,
                'unit' => $unitName,
                'service_unit' => $serviceUnitName,
                'regu' => $reguLabel,
                'roles' => $roleLabels,
                'signature_url' => $employee?->signatureUrl(),
                'has_signature' => ! empty($employee?->signature_path),
            ],
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Email & profil berhasil diperbarui.']);

        return to_route('profile.edit');
    }

    /**
     * Save or remove the authenticated user's digital signature (drawn on canvas or uploaded).
     */
    public function updateSignature(Request $request): RedirectResponse
    {
        $request->validate([
            'signature' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'signature_base64' => ['nullable', 'string'],
            'remove_signature' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $employee = $this->resolveOrCreateEmployee($user);

        if ($request->boolean('remove_signature')) {
            if ($employee->signature_path) {
                Storage::disk('public')->delete($employee->signature_path);
            }
            $employee->update(['signature_path' => null]);

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Tanda tangan berhasil dihapus.']);

            return to_route('profile.edit');
        }

        $newPath = null;

        if ($request->filled('signature_base64')) {
            $data = (string) $request->input('signature_base64');
            if (! preg_match('/^data:image\/(\w+);base64,/', $data)) {
                throw ValidationException::withMessages([
                    'signature_base64' => 'Format gambar tanda tangan tidak valid.',
                ]);
            }

            $bytes = base64_decode(substr($data, strpos($data, ',') + 1), true);
            if ($bytes === false || strlen($bytes) === 0 || strlen($bytes) > 2 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'signature_base64' => 'Ukuran tanda tangan melebihi batas maksimal 2 MB.',
                ]);
            }

            if ($employee->signature_path) {
                Storage::disk('public')->delete($employee->signature_path);
            }

            $newPath = 'signatures/'.Str::random(40).'.png';
            Storage::disk('public')->put($newPath, $bytes);
        } elseif ($request->hasFile('signature')) {
            if ($employee->signature_path) {
                Storage::disk('public')->delete($employee->signature_path);
            }

            $newPath = $request->file('signature')->store('signatures', 'public');
        } else {
            throw ValidationException::withMessages([
                'signature_base64' => 'Silakan gambar tanda tangan pada kanvas terlebih dahulu.',
            ]);
        }

        $employee->update(['signature_path' => $newPath]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tanda tangan berhasil disimpan.']);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function resolveEmployee(User $user): ?Employee
    {
        if ($user->relationLoaded('employee') && $user->employee !== null) {
            return $user->employee;
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();
        if ($employee !== null) {
            return $employee;
        }

        if (! empty($user->employee_id)) {
            $byNip = Employee::query()
                ->whereNull('user_id')
                ->where('nip', $user->employee_id)
                ->first();

            if ($byNip !== null) {
                $byNip->update(['user_id' => $user->id]);

                return $byNip;
            }
        }

        return null;
    }

    private function resolveOrCreateEmployee(User $user): Employee
    {
        $employee = $this->resolveEmployee($user);
        if ($employee !== null) {
            return $employee;
        }

        $user->loadMissing(['roles:id,name,display_name', 'roleAssignments']);
        $unitAssignment = $user->roleAssignments->firstWhere('unit_id', '!=', null);
        $serviceUnitAssignment = $user->roleAssignments->firstWhere('service_unit_id', '!=', null);

        $unitId = $unitAssignment?->unit_id ?? Unit::query()->value('id');
        $serviceUnitId = $serviceUnitAssignment?->service_unit_id;

        $baseNip = $user->employee_id ?: ('NIP-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT));
        $nip = $baseNip;
        if (Employee::query()->where('nip', $nip)->exists()) {
            $nip = $baseNip.'-'.$user->id;
        }

        $position = $user->position
            ?: ($user->roles->first()?->display_name ?? 'Pegawai');

        return Employee::query()->create([
            'user_id' => $user->id,
            'unit_id' => $unitId,
            'service_unit_id' => $serviceUnitId,
            'nip' => $nip,
            'name' => $user->name,
            'position' => $position,
            'is_active' => true,
        ]);
    }
}
