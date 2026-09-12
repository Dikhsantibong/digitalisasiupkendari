<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo login accounts, one per role, scoped to the organisation:
 *  - Manager UL     : one per service unit
 *  - TL Operasi     : one per unit
 *  - TL Pemeliharaan: one per unit
 *  - TL K3          : one per unit
 *  - Site Leader    : one per unit
 *  - Operator       : four per unit (shift A–D)
 *
 * Idempotent: re-running updates the same accounts (keyed by e-mail) and
 * re-applies the role assignment. Every account uses the password "password".
 * The Super Admin account is seeded separately by {@see SuperAdminSeeder}.
 */
class DemoAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    private const PASSWORD = 'password';

    private const DOMAIN = 'upkendari.co.id';

    public function run(): void
    {
        foreach (ServiceUnit::query()->orderBy('id')->get() as $serviceUnit) {
            $slug = $this->slug($serviceUnit->code ?? $serviceUnit->name ?? (string) $serviceUnit->id);
            $this->account(
                "manager.{$slug}@".self::DOMAIN,
                'Manager '.($serviceUnit->name ?? $slug),
                'Manajer Unit Layanan',
                RoleName::ManagerUl,
                $serviceUnit,
            );
        }

        foreach (Unit::query()->orderBy('id')->get() as $unit) {
            $slug = $this->slug($unit->code ?? $unit->name ?? (string) $unit->id);
            $name = $unit->name ?? $slug;

            $this->account("tl-operasi.{$slug}@".self::DOMAIN, "TL Operasi {$name}", 'Team Leader Operasi', RoleName::TeamLeaderOperasi, $unit);
            $this->account("tl-har.{$slug}@".self::DOMAIN, "TL Pemeliharaan {$name}", 'Team Leader Pemeliharaan', RoleName::TeamLeaderPemeliharaan, $unit);
            $this->account("tl-k3.{$slug}@".self::DOMAIN, "TL K3 & Keamanan {$name}", 'Team Leader K3 & Keamanan', RoleName::TeamLeaderK3, $unit);
            $this->account("site-leader.{$slug}@".self::DOMAIN, "Site Leader {$name}", 'Site Leader', RoleName::SiteLeader, $unit);

            for ($n = 1; $n <= 4; $n++) {
                $this->account("operator{$n}.{$slug}@".self::DOMAIN, "Operator {$n} {$name}", 'Operator (Shift '.chr(64 + $n).')', RoleName::Operator, $unit);
            }
        }

        $this->command?->info('Akun demo dibuat/diperbarui. Kata sandi semua akun: "'.self::PASSWORD.'".');
    }

    private function account(string $email, string $name, string $position, RoleName $role, ServiceUnit|Unit $scope): void
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'position' => $position,
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);
        if (! $user->exists) {
            $user->employee_id = 'DEMO-'.strtoupper(Str::random(8));
            $user->password = Hash::make(self::PASSWORD);
        }
        $user->save();

        $user->assignRole($role, $scope);
    }

    private function slug(string $value): string
    {
        return Str::slug($value) ?: 'unit';
    }
}
