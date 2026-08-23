<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_sees_activity_from_every_unit(): void
    {
        ActivityLog::factory()->forUnit(Unit::factory()->create())->create();
        ActivityLog::factory()->forUnit(Unit::factory()->create())->create();
        ActivityLog::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/activity-logs/index')
                ->has('logs.data', 3),
            );
    }

    public function test_a_site_leader_only_sees_activity_from_their_own_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();

        $visible = ActivityLog::factory()->forUnit($ownUnit)->create();
        ActivityLog::factory()->forUnit($otherUnit)->create();
        ActivityLog::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::SiteLeader, $ownUnit))
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.id', $visible->id),
            );
    }

    public function test_a_manager_sees_activity_from_every_unit_beneath_their_service_unit(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $first = Unit::factory()->forServiceUnit($serviceUnit)->create();
        $second = Unit::factory()->forServiceUnit($serviceUnit)->create();

        ActivityLog::factory()->forUnit($first)->create();
        ActivityLog::factory()->forUnit($second)->create();
        ActivityLog::factory()->forUnit(Unit::factory()->create())->create();

        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $serviceUnit))
            ->get(route('admin.activity-logs.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('logs.data', 2));
    }

    public function test_an_operator_cannot_open_the_activity_log(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('admin.activity-logs.index'))
            ->assertForbidden();
    }

    public function test_the_log_can_be_filtered_by_event(): void
    {
        $unit = Unit::factory()->create();

        ActivityLog::factory()->forUnit($unit)->event(ActivityEvent::Created)->create();
        ActivityLog::factory()->forUnit($unit)->event(ActivityEvent::Deleted)->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('admin.activity-logs.index', ['event' => ActivityEvent::Deleted->value]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.event', ActivityEvent::Deleted->value),
            );
    }

    public function test_signing_in_is_recorded_and_stamps_the_account(): void
    {
        $user = User::factory()->create(['password' => 'kata-sandi-rahasia']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'kata-sandi-rahasia',
        ]);

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event' => ActivityEvent::LoggedIn->value,
        ]);
    }
}
