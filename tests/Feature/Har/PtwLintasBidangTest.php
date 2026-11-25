<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\OperasiPermitToWork;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * PTW lintas bidang (user, 2026-11-24): the Permit to Work the operators /
 * Operasi enter is visible to Pemeliharaan, read-only.
 */
class PtwLintasBidangTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    public function test_pemeliharaan_reads_the_operasi_ptw_but_does_not_write_it(): void
    {
        $this->seedAccessControl();
        $unit = Unit::factory()->create(['is_active' => true]);
        OperasiPermitToWork::query()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 11, 'no_urut' => 1, 'uraian' => 'Penggantian filter BBM Mesin #2', 'tanggal' => '2026-11-20', 'status' => 'open']);
        $period = ['unit_id' => $unit->id, 'month' => 11, 'year' => 2026];

        foreach ([RoleName::TeamLeaderPemeliharaan, RoleName::KoordinatorPemeliharaan] as $role) {
            $user = $this->userWithRole($role, $unit);
            $this->actingAs($user)->get(route('operasi.input.permit-to-work.index', $period))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component('operasi/input/permit-to-work/index')
                    ->where('rows.0.uraian', 'Penggantian filter BBM Mesin #2')->where('summary.total_open', 1)->where('can_write', false));
            $this->actingAs($user)->get(route('operasi.input.permit-to-work.pdf', $period))->assertOk();
            $this->actingAs($user)->post(route('operasi.input.permit-to-work.store'), $period + ['rows' => []])->assertForbidden();
        }

        // Still closed to roles outside Operasi & Pemeliharaan.
        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))->get(route('operasi.input.permit-to-work.index', $period))->assertForbidden();
    }
}
