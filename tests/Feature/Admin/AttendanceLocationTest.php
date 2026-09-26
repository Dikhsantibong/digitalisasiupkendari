<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class AttendanceLocationTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_sees_every_unit_with_the_default_radius(): void
    {
        Unit::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('admin.attendance-locations.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/attendance-locations/index')
                ->has('units', 3)
                ->where('units.0.attendance_radius_m', 300)
                ->where('units.0.latitude', null));
    }

    public function test_other_roles_cannot_open_or_change_the_setting(): void
    {
        $unit = Unit::factory()->create();

        foreach ([RoleName::KoordinatorOperasi, RoleName::ProjectLeaderOperasi, RoleName::Operator] as $role) {
            $user = $this->userWithRole($role, $unit);

            $this->actingAs($user)->get(route('admin.attendance-locations.index'))->assertForbidden();
            $this->actingAs($user)
                ->put(route('admin.attendance-locations.update', $unit), ['latitude' => -3.9, 'longitude' => 122.5, 'attendance_radius_m' => 300])
                ->assertForbidden();
        }

        $this->assertNull($unit->fresh()->latitude);
    }

    public function test_super_admin_sets_the_office_pin_and_radius(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->put(route('admin.attendance-locations.update', $unit), ['latitude' => -3.9778123, 'longitude' => 122.5150456, 'attendance_radius_m' => 250])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $unit->refresh();
        $this->assertEqualsWithDelta(-3.9778123, (float) $unit->latitude, 0.0000001);
        $this->assertEqualsWithDelta(122.5150456, (float) $unit->longitude, 0.0000001);
        $this->assertSame(250, $unit->attendance_radius_m);
    }

    public function test_the_location_is_validated(): void
    {
        $unit = Unit::factory()->create();
        $admin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($admin)
            ->put(route('admin.attendance-locations.update', $unit), ['latitude' => 95, 'longitude' => 122.5, 'attendance_radius_m' => 5])
            ->assertSessionHasErrors(['latitude', 'attendance_radius_m']);

        $this->actingAs($admin)
            ->put(route('admin.attendance-locations.update', $unit), ['latitude' => -3.9, 'longitude' => null, 'attendance_radius_m' => 300])
            ->assertSessionHasErrors('longitude');
    }
}
