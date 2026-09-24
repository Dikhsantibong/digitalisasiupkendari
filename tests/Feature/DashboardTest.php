<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_super_admin_sees_every_module_scope(): void
    {
        $this->seedAccessControl();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('scope.operasi', true)
                ->where('scope.har', true)
                ->where('scope.k3', true)
                ->where('scope.logistik', true)
                ->where('scope.pdm', true)
                ->where('scope.units', true)
                ->where('isDummy', true)
                ->has('operasi.kpis', 5)
                ->has('har.backlog')
                ->has('k3.s_curve.points')
                ->has('logistik.tanks')
                ->has('pdm.vibration.series', 3)
                ->has('moduleHealth', 5),
            );
    }

    public function test_tl_operasi_dashboard_shows_only_operasi_module(): void
    {
        $this->seedAccessControl();
        $unit = Unit::factory()->create(['name' => 'PLTD Uji']);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scope.operasi', true)
                ->where('scope.har', false)
                ->where('scope.k3', false)
                ->where('scope.logistik', false)
                ->where('scope.pdm', false)
                ->has('operasi.daily.series', 2)
                // Per-unit rows are labelled with the units the viewer can see.
                ->where('operasi.units.0.unit', 'PLTD Uji')
                ->where('har', null)
                ->where('k3', null)
                ->where('logistik', null)
                ->where('pdm', null)
                ->has('moduleHealth', 1),
            );
    }

    public function test_tl_logistik_and_tl_pdm_dashboards_are_scoped_to_their_module(): void
    {
        $this->seedAccessControl();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderLogistik, Unit::factory()->create()))
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('scope.logistik', true)
                ->has('logistik.critical')
                ->where('operasi', null)
                ->where('pdm', null),
            );

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPdm, Unit::factory()->create()))
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('scope.pdm', true)
                ->has('pdm.assets')
                ->where('logistik', null)
                ->where('har', null),
            );
    }

    public function test_operator_dashboard_is_scoped_to_operasi_only(): void
    {
        $this->seedAccessControl();

        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('scope.operasi', true)
                ->where('scope.har', false)
                ->where('scope.k3', false)
                ->has('operasi')
                // HAR/K3 sections are withheld from the operator.
                ->where('har', null)
                ->where('k3', null),
            );
    }

    public function test_tl_k3_dashboard_shows_k3_not_har(): void
    {
        $this->seedAccessControl();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, Unit::factory()->create()))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scope.k3', true)
                ->where('scope.har', false)
                ->has('k3.s_curve')
                ->where('har', null),
            );
    }
}
