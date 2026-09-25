<?php

namespace Tests\Feature\Admin;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_guests_are_redirected_away_from_the_employee_list(): void
    {
        $this->get(route('admin.employees.index'))->assertRedirect(route('login'));
    }

    public function test_a_user_without_permission_cannot_open_the_employee_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.employees.index'))
            ->assertForbidden();
    }

    public function test_super_admin_sees_every_employee(): void
    {
        Employee::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/employees/index')
                ->has('employees.data', 3),
            );
    }

    public function test_a_manager_only_sees_employees_within_their_service_unit(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $ownUnit = Unit::factory()->forServiceUnit($serviceUnit)->create();
        Employee::factory()->count(2)->forUnit($ownUnit)->create();
        Employee::factory()->count(3)->create();

        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $serviceUnit))
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('employees.data', 2));
    }

    public function test_employees_can_be_filtered_by_position(): void
    {
        Employee::factory()->create(['position' => 'Operator']);
        Employee::factory()->create(['position' => 'Operator']);
        Employee::factory()->create(['position' => 'Team Leader Operasi']);

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('admin.employees.index', ['position' => 'Operator']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/employees/index')
                ->has('employees.data', 2)
                ->where('filters.position', 'Operator')
            );
    }

    public function test_super_admin_can_create_an_employee_and_the_action_is_logged(): void
    {
        $unit = Unit::factory()->create();
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($superAdmin)
            ->post(route('admin.employees.store'), [
                'unit_id' => $unit->id,
                'name' => 'Budi Santoso',
                'nip' => '1990123456',
                'position' => 'Operator',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::query()->where('nip', '1990123456')->firstOrFail();

        $this->assertSame($unit->id, $employee->unit_id);
        $this->assertSame('Budi Santoso', $employee->name);
        $this->assertSame('Operator', $employee->position);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $superAdmin->id,
            'event' => ActivityEvent::Created->value,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_an_operator_is_placed_in_a_regu_as_its_leader_shift(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.employees.store'), [
                'unit_id' => $unit->id,
                'name' => 'Sarono',
                'nip' => '7026007',
                'position' => 'Operator',
                'regu' => 'A',
                'is_shift_leader' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::query()->where('nip', '7026007')->firstOrFail();
        $this->assertSame('A', $employee->regu);
        $this->assertTrue($employee->is_shift_leader);
    }

    public function test_a_regu_has_only_one_active_leader_shift_per_unit(): void
    {
        $unit = Unit::factory()->create();
        $leader = Employee::factory()->forUnit($unit)->create(['regu' => 'A', 'is_shift_leader' => true, 'is_active' => true]);
        $admin = $this->userWithRole(RoleName::SuperAdmin);
        $payload = ['unit_id' => $unit->id, 'name' => 'Rian', 'position' => 'Operator', 'is_shift_leader' => '1', 'is_active' => '1'];

        $this->actingAs($admin)->post(route('admin.employees.store'), [...$payload, 'nip' => '1', 'regu' => 'A'])
            ->assertSessionHasErrors('is_shift_leader');

        // Another regu, or the same regu in another unit, may have its own leader.
        $this->actingAs($admin)->post(route('admin.employees.store'), [...$payload, 'nip' => '2', 'regu' => 'B'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.employees.store'), [...$payload, 'nip' => '3', 'regu' => 'A', 'unit_id' => Unit::factory()->create()->id])
            ->assertSessionHasNoErrors();

        // The current leader can still be edited.
        $this->actingAs($admin)->put(route('admin.employees.update', $leader), [
            'unit_id' => $unit->id, 'name' => $leader->name, 'nip' => $leader->nip, 'position' => 'Operator',
            'regu' => 'A', 'is_shift_leader' => '1', 'is_active' => '1',
        ])->assertSessionHasNoErrors();
    }

    public function test_leader_shift_needs_a_regu_and_non_shift_staff_are_never_leaders(): void
    {
        $unit = Unit::factory()->create();
        $admin = $this->userWithRole(RoleName::SuperAdmin);

        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'unit_id' => $unit->id, 'name' => 'Tanpa Regu', 'nip' => '10', 'is_shift_leader' => '1', 'is_active' => '1',
        ])->assertSessionHasErrors('is_shift_leader');

        $this->actingAs($admin)->post(route('admin.employees.store'), [
            'unit_id' => $unit->id, 'name' => 'Staf', 'nip' => '11', 'regu' => 'X', 'is_active' => '1',
        ])->assertSessionHasErrors('regu');
    }

    public function test_creating_an_employee_requires_a_name(): void
    {
        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.employees.store'), [
                'nip' => '123',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_creating_an_employee_requires_a_unique_nip(): void
    {
        $existing = Employee::factory()->create(['nip' => '555000']);

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.employees.store'), [
                'name' => 'Duplikat NIP',
                'nip' => $existing->nip,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('nip');
    }

    public function test_an_operator_cannot_create_an_employee(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('admin.employees.store'), [
                'unit_id' => $unit->id,
                'name' => 'Pegawai X',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('employees', ['name' => 'Pegawai X']);
    }

    public function test_super_admin_can_create_an_employee_with_signature(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create();
        $signature = UploadedFile::fake()->image('ttd.png', 200, 80);

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.employees.store'), [
                'unit_id' => $unit->id,
                'name' => 'Manager UL Test',
                'nip' => '999888777',
                'position' => 'Manager UL',
                'is_active' => '1',
                'signature' => $signature,
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::query()->where('nip', '999888777')->firstOrFail();
        $this->assertNotNull($employee->signature_path);
        Storage::disk('public')->assertExists($employee->signature_path);
    }

    public function test_super_admin_can_create_an_employee_with_canvas_base64_signature(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create();
        $fakeBase64 = 'data:image/png;base64,'.base64_encode('fake-canvas-png-bytes');

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->post(route('admin.employees.store'), [
                'unit_id' => $unit->id,
                'name' => 'TL K3 Canvas',
                'nip' => '999888666',
                'position' => 'Team Leader K3 & Keamanan',
                'is_active' => '1',
                'signature_base64' => $fakeBase64,
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee = Employee::query()->where('nip', '999888666')->firstOrFail();
        $this->assertNotNull($employee->signature_path);
        Storage::disk('public')->assertExists($employee->signature_path);
        $this->assertSame('fake-canvas-png-bytes', Storage::disk('public')->get($employee->signature_path));
    }

    public function test_super_admin_can_update_employee_signature(): void
    {
        Storage::fake('public');
        $oldPath = 'signatures/old_ttd.png';
        Storage::disk('public')->put($oldPath, 'old content');

        $employee = Employee::factory()->create([
            'position' => 'Team Leader Operasi',
            'signature_path' => $oldPath,
        ]);

        $newSignature = UploadedFile::fake()->image('new_ttd.png', 200, 80);

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->put(route('admin.employees.update', $employee), [
                'name' => $employee->name,
                'position' => $employee->position,
                'is_active' => '1',
                'signature' => $newSignature,
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee->refresh();
        $this->assertNotSame($oldPath, $employee->signature_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($employee->signature_path);
    }

    public function test_super_admin_can_remove_employee_signature(): void
    {
        Storage::fake('public');
        $path = 'signatures/test_ttd.png';
        Storage::disk('public')->put($path, 'signature content');

        $employee = Employee::factory()->create([
            'position' => 'Team Leader K3 & Keamanan',
            'signature_path' => $path,
        ]);

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->put(route('admin.employees.update', $employee), [
                'name' => $employee->name,
                'position' => $employee->position,
                'is_active' => '1',
                'remove_signature' => '1',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee->refresh();
        $this->assertNull($employee->signature_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_super_admin_can_delete_an_employee(): void
    {
        Storage::fake('public');
        $path = 'signatures/deleted_employee_ttd.png';
        Storage::disk('public')->put($path, 'signature content');

        $employee = Employee::factory()->create([
            'signature_path' => $path,
        ]);

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->delete(route('admin.employees.destroy', $employee))
            ->assertRedirect(route('admin.employees.index'));

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame(
            1,
            ActivityLog::query()->where('event', ActivityEvent::Deleted->value)->count(),
        );
    }
}
