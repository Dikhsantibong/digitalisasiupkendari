<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleName;
use App\Enums\ScheduleGroupType;
use App\Models\Employee;
use App\Models\Unit;
use App\Models\User;
use App\Services\Operator\AttendanceRoster;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\PoasiaContainerizedStaffSeeder;
use Database\Seeders\PoasiaStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The real PLTD Poasia staff replace the roster placeholders, each with a
 * linked login account, without touching the TL or the other units.
 */
class PoasiaStaffSeederTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    public function test_the_poasia_staff_get_their_jabatan_regu_and_linked_accounts(): void
    {
        $this->seedAccessControl();
        $poasia = Unit::factory()->create(['code' => 'PLTD-POASIA', 'name' => 'PLTD Poasia', 'is_active' => true]);
        $kolaka = Unit::factory()->create(['code' => 'PLTD-KOLAKA', 'name' => 'PLTD Kolaka', 'is_active' => true]);
        $this->seed(EmployeeSeeder::class);
        $this->seed(DemoAccountSeeder::class);

        $this->seed(PoasiaStaffSeeder::class);
        $this->seed(PoasiaStaffSeeder::class);

        $employee = fn (int $suffix): Employee => Employee::query()->where('nip', EmployeeSeeder::nip($poasia, $suffix))->sole();

        // Three per regu A–D, one Leader Shift each, every operator with an Operator account.
        $operators = Employee::query()->where('unit_id', $poasia->id)->where('is_active', true)->whereNotNull('regu')->get();
        $this->assertSame(['A' => 3, 'B' => 3, 'C' => 3, 'D' => 3], $operators->countBy('regu')->sortKeys()->all());
        $this->assertSame(['A' => 1, 'B' => 1, 'C' => 1, 'D' => 1], $operators->where('is_shift_leader', true)->countBy('regu')->sortKeys()->all());
        $operators->each(fn (Employee $operator) => $this->assertTrue($operator->user->hasRole(RoleName::Operator) && $operator->user->canAccessUnit($poasia)));
        $this->assertSame('Jemmy', $operators->where('regu', 'D')->firstWhere('is_shift_leader', true)->name);
        $this->assertSame(1, User::query()->where('email', 'muh-zainuddin.pltd-poasia@upkendari.co.id')->count());

        // Signer jabatan keep their account, which takes the person's name.
        $picPdm = $employee(24);
        $this->assertSame('Laode Irsad', $picPdm->name);
        $this->assertSame('pic-pdm.pltd-poasia@upkendari.co.id', $picPdm->user->email);
        $this->assertSame('Laode Irsad', $picPdm->user->name);
        $this->assertSame('pdm', $picPdm->division);
        $this->assertNotNull($picPdm->singleton_key);
        $this->assertSame('Firman', $employee(18)->user->name);

        // Maintenance: Amiruddin as Harmes, three ASME & Elec in Pemeliharaan.
        $this->assertSame('Amiruddin', $employee(25)->name);
        foreach ([26, 27, 28] as $suffix) {
            $this->assertSame(Employee::POSITION_ASME, $employee($suffix)->position);
            $this->assertSame('pemeliharaan', $employee($suffix)->division);
            $this->assertTrue($employee($suffix)->user->hasRole(RoleName::Harmes));
        }
        $nonShift = app(AttendanceRoster::class)->employees($poasia, ScheduleGroupType::NonShift)->pluck('name');
        $this->assertContains('Ismail', $nonShift);

        // Fuel facility technicians in Operasi as Staf Operasi.
        $this->assertSame('operasi', $employee(36)->division);
        $this->assertTrue($employee(36)->user->hasRole(RoleName::StafOperasi));

        // Placeholders without a person are deactivated, never deleted.
        $this->assertFalse($employee(29)->is_active);
        $this->assertFalse(User::query()->where('email', 'operator1.pltd-poasia@upkendari.co.id')->sole()->is_active);
        $this->assertFalse(User::query()->where('email', 'harmes2.pltd-poasia@upkendari.co.id')->sole()->is_active);

        // The TL, the unnamed signers and the other units stay as they were.
        $this->assertStringStartsWith('TL Operasi', $employee(8)->name);
        $this->assertTrue($employee(15)->is_active);
        $this->assertStringStartsWith('Koordinator K3', $employee(15)->name);
        $this->assertStringStartsWith('Operator 1', Employee::query()->where('nip', EmployeeSeeder::nip($kolaka, 1))->sole()->name);
        $this->assertTrue(User::query()->where('email', 'operator1.pltd-kolaka@upkendari.co.id')->sole()->is_active);
    }

    public function test_the_containerized_staff_get_two_operators_per_regu_and_three_harmes(): void
    {
        $this->seedAccessControl();
        $poasia = Unit::factory()->create(['code' => 'PLTD-POASIA', 'name' => 'PLTD Poasia', 'is_active' => true]);
        $container = Unit::factory()->create(['code' => 'PLTD-POASIA-CONT', 'name' => 'PLTD Poasia Containerized', 'is_active' => true]);
        $this->seed(EmployeeSeeder::class);
        $this->seed(DemoAccountSeeder::class);

        $this->seed(PoasiaContainerizedStaffSeeder::class);
        $this->seed(PoasiaContainerizedStaffSeeder::class);

        $employee = fn (int $suffix): Employee => Employee::query()->where('nip', EmployeeSeeder::nip($container, $suffix))->sole();

        $operators = Employee::query()->where('unit_id', $container->id)->where('is_active', true)->whereNotNull('regu')->get();
        $this->assertSame(['A' => 2, 'B' => 2, 'C' => 2, 'D' => 2], $operators->countBy('regu')->sortKeys()->all());
        $this->assertSame(['Sarono', 'Aswadi', 'R. Yusuf', 'Muhlis'], $operators->where('is_shift_leader', true)->sortBy('regu')->pluck('name')->values()->all());
        $operators->each(fn (Employee $operator) => $this->assertTrue($operator->user->hasRole(RoleName::Operator) && $operator->user->canAccessUnit($container)));
        $this->assertSame('muhlis.pltd-poasia-cont@upkendari.co.id', $employee(30)->user->email);

        $this->assertSame('Herwin Syahputra', $employee(18)->user->name);
        $this->assertSame('Sudoyo Age', $employee(21)->name);
        $this->assertSame('Amirullah', $employee(13)->user->name);
        $this->assertSame(['Dominikus Kofi', 'Ridwan Bahudi', 'Yonatan Linggi Allo'], array_map(fn (int $suffix): string => $employee($suffix)->name, [25, 26, 27]));
        $this->assertTrue($employee(27)->user->hasRole(RoleName::Harmes));

        // Harlist without a person are deactivated; unnamed signers (PIC PDM, Office Logistik) and Poasia stay.
        $this->assertFalse($employee(28)->is_active);
        $this->assertFalse($employee(29)->is_active);
        $this->assertTrue($employee(24)->is_active);
        $this->assertStringStartsWith('Office Logistik', $employee(23)->name);
        $this->assertStringStartsWith('Operator 1', Employee::query()->where('nip', EmployeeSeeder::nip($poasia, 1))->sole()->name);
    }

    public function test_it_changes_nothing_without_the_poasia_unit(): void
    {
        $this->seedAccessControl();
        Unit::factory()->create(['code' => 'PLTD-KOLAKA']);

        $this->seed(PoasiaStaffSeeder::class);

        $this->assertSame(0, Employee::query()->count());
    }
}
