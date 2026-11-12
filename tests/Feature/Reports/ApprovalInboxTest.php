<?php

namespace Tests\Feature\Reports;

use App\Enums\AttendanceRequestStatus;
use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AttendanceCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The approval inbox of accounts that both input and approve: the Project
 * Leader decides the operators' pengajuan from the dashboard / Persetujuan
 * page; accounts that approve nothing keep their screens unchanged.
 */
class ApprovalInboxTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        $this->seed(AttendanceCodeSeeder::class);
        $this->unit = Unit::factory()->create(['is_active' => true]);
    }

    private function projectLeader(): User
    {
        $user = $this->userWithRole(RoleName::ProjectLeaderOperasi, $this->unit);
        Employee::factory()->create([
            'unit_id' => $this->unit->id, 'user_id' => $user->id, 'name' => 'Firman',
            'position' => EmployeePosition::ProjectLeader->value, 'is_active' => true,
        ]);

        return $user->fresh();
    }

    public function test_the_project_leader_decides_the_operators_pengajuan_from_the_inbox(): void
    {
        $leader = $this->projectLeader();
        $operator = Employee::factory()->create(['unit_id' => $this->unit->id, 'name' => 'Andi', 'regu' => 'A', 'is_active' => true]);
        $request = AttendanceRequest::factory()->create(['unit_id' => $this->unit->id, 'employee_id' => $operator->id]);
        AttendanceRequest::factory()->create(['employee_id' => $operator->id]); // another unit: not his

        $this->actingAs($leader)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.approvals', 1)
                ->has('approvals.requests', 1)
                ->where('approvals.requests.0.employee', 'Andi')
                ->where('approvals.requests.0.id', $request->id));
        $this->actingAs($leader)->get(route('approvals.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('laporan/persetujuan')->where('decides_requests', true)->has('requests', 1));

        // The inbox's buttons post to the existing routes.
        $this->actingAs($leader)->post(route('operator.pengajuan.approve', $request), ['note' => 'Ok'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(AttendanceRequestStatus::Disetujui, $request->fresh()->status);
        $this->actingAs($leader)->get(route('approvals.index'))->assertInertia(fn ($page) => $page->has('requests', 0));
    }

    public function test_accounts_that_approve_nothing_keep_their_screens(): void
    {
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);

        $this->actingAs($operator)->get(route('approvals.index'))->assertForbidden();
        $this->actingAs($operator)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.approvals', null)->where('approvals', null));

        $admin = $this->userWithRole(RoleName::SuperAdmin);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.approvals', null)->where('approvals', null));
    }
}
