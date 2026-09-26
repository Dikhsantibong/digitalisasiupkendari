<?php

namespace Tests\Feature\Operator;

use App\Enums\PermissionGroup;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\ScheduleGroupType;
use App\Models\Employee;
use App\Models\HarTabelRow;
use App\Models\OperasiFlmMonitoring;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Operator\AttendanceRoster;
use Database\Seeders\DemoAccountSeeder;
use Database\Seeders\EmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The field roles of the Operator module: shift operators (Leader Shift
 * included) in divisi Operasi, and the Harmes / Harlist maintenance staff in
 * divisi Pemeliharaan, each seeing only the menus of their role.
 */
class FieldRoleTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_shift_operators_belong_to_operasi_and_harmes_harlist_to_pemeliharaan(): void
    {
        $unit = Unit::factory()->create();

        $leader = Employee::factory()->create(['unit_id' => $unit->id, 'position' => 'Operator (Leader Shift)', 'regu' => 'A', 'is_shift_leader' => true, 'division' => null]);
        $operator = Employee::factory()->create(['unit_id' => $unit->id, 'position' => 'Operator', 'regu' => 'B', 'division' => 'operator']);
        $harmes = Employee::factory()->create(['unit_id' => $unit->id, 'position' => Employee::POSITION_HARMES, 'regu' => null, 'division' => null]);
        $harlist = Employee::factory()->create(['unit_id' => $unit->id, 'position' => Employee::POSITION_HARLIST, 'regu' => null, 'division' => null]);
        $koordinator = Employee::factory()->create(['unit_id' => $unit->id, 'position' => 'Koordinator Operasi', 'regu' => null]);
        $staff = Employee::factory()->create(['unit_id' => $unit->id, 'position' => 'Staf K3', 'regu' => null, 'division' => 'k3']);

        $this->assertSame('operasi', $leader->division);
        $this->assertSame('operasi', $operator->division);
        $this->assertSame('pemeliharaan', $harmes->division);
        $this->assertSame('pemeliharaan', $harlist->division);
        // Signer jabatan and other free-text staff keep their own divisi.
        $this->assertSame('operasi', $koordinator->division);
        $this->assertSame('k3', $staff->division);
    }

    public function test_the_seeders_create_harmes_and_harlist_per_unit_linked_to_their_accounts(): void
    {
        $units = Unit::factory()->count(2)->create();

        $this->seed(EmployeeSeeder::class);
        $this->seed(DemoAccountSeeder::class);
        // Idempotent: a second run creates nothing new.
        $this->seed(EmployeeSeeder::class);
        $this->seed(DemoAccountSeeder::class);

        foreach ($units as $unit) {
            $this->assertSame(3, Employee::query()->where('unit_id', $unit->id)->where('position', 'Harmes')->where('division', 'pemeliharaan')->count());
            $this->assertSame(2, Employee::query()->where('unit_id', $unit->id)->where('position', 'Harlist')->where('division', 'pemeliharaan')->count());
            $this->assertSame(0, Employee::query()->where('unit_id', $unit->id)->whereNotNull('regu')->where('division', '!=', 'operasi')->count());

            $harmes = Employee::query()->where('nip', EmployeeSeeder::nip($unit, 25))->sole();
            $this->assertNotNull($harmes->user_id);
            $this->assertTrue($harmes->user->hasRole(RoleName::Harmes));

            $harlist = Employee::query()->where('nip', EmployeeSeeder::nip($unit, 29))->sole();
            $this->assertTrue($harlist->user->hasRole(RoleName::Harlist));
            $this->assertTrue($harlist->user->canAccessUnit($unit));
        }

        $this->assertSame(6, User::query()->where('email', 'like', 'harmes%')->count());
        $this->assertSame(4, User::query()->where('email', 'like', 'harlist%')->count());
    }

    public function test_harmes_and_harlist_reach_absensi_and_every_har_input_page(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        foreach ([RoleName::Harmes, RoleName::Harlist] as $role) {
            $user = $this->userWithRole($role, $unit);
            $query = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

            $this->actingAs($user)->get(route('operator.presensi.index'))->assertOk();

            // The HAR field pages open with write access.
            foreach ([
                route('har.input.abnormal-gangguan.index', $query),
                route('har.input.patrol-check-parameter.index', $query),
                route('har.input.lembar.index', ['lembar' => 'patrol-check-pemeliharaan', ...$query]),
                route('har.input.program-5s5r.index', $query),
                route('har.input.unsafe-condition.index', $query),
            ] as $url) {
                $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn ($page) => $page->where('can_write', true));
            }

            // Every other HAR input & formulir page opens too, each through its own permission.
            $this->actingAs($user)->get(route('har.input.laporan-gangguan.index', $query))->assertOk();
            $this->actingAs($user)->get(route('har.input.work-order.index', $query))->assertOk();
            $this->actingAs($user)->get(route('har.formulir.prelube-test.index', $query))->assertOk();
            $this->actingAs($user)->get(route('har.formulir.logbook-mutasi.index', $query))->assertOk();

            // The TL's hubs, jadwal, and the operator's own menus stay closed.
            $this->actingAs($user)->get(route('har.input.index'))->assertForbidden();
            $this->actingAs($user)->get(route('har.jadwal.lembar.index', ['lembar' => 'inventarisasi-tools', ...$query]))->assertForbidden();
            $this->actingAs($user)->get(route('operator.logsheet.index'))->assertForbidden();
            $this->actingAs($user)->get(route('operator.absensi.index'))->assertForbidden();
        }
    }

    public function test_harmes_input_lands_in_the_same_har_tables(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $harmes = $this->userWithRole(RoleName::Harmes, $unit);

        $this->actingAs($harmes)
            ->post(route('har.input.abnormal-gangguan.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [
                ['uraian' => 'Bocor BBM pada transfer pump', 'jenis' => 'MESIN', 'tanggal' => '2026-08-20', 'abnormal' => 1, 'durasi_abnormal' => 2],
            ]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, HarTabelRow::query()->where('unit_id', $unit->id)->where('tabel', 'abnormal-gangguan')->count());

        // The TL Pemeliharaan sees the same row on the HAR page.
        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->get(route('har.input.abnormal-gangguan.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.uraian', 'Bocor BBM pada transfer pump'));
    }

    public function test_harmes_cannot_write_har_input_of_another_unit(): void
    {
        $own = Unit::factory()->create(['is_active' => true]);
        $other = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Harmes, $own))
            ->post(route('har.input.abnormal-gangguan.store'), ['unit_id' => $other->id, 'month' => 8, 'year' => 2026, 'rows' => [['uraian' => 'x', 'abnormal' => 1]]])
            ->assertForbidden();
    }

    public function test_operators_reach_every_operasi_input_page(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $query = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        foreach ([RoleName::Operator, RoleName::ProjectLeaderOperasi] as $role) {
            $user = $this->userWithRole($role, $unit);

            foreach ([
                route('operasi.input.patrol-check-mesin.index', $query),
                route('operasi.input.unsafe-condition.index', $query),
                route('operasi.input.program-5s5r.index', $query),
                route('operasi.input.flm-monitoring.index', $query),
            ] as $url) {
                $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn ($page) => $page->where('can_write', true));
            }

            // Every other Input Operasi page opens too, each through its own permission.
            foreach (['daily-report', 'star-stop', 'feeder', 'auxiliary', 'fuel-receipt', 'kondisi-abnormal', 'resource-pembangkit', 'material-peralatan', 'permit-to-work', 'checklist-commissioning-mesin'] as $page) {
                $this->actingAs($user)->get(route("operasi.input.{$page}.index", $query))->assertOk();
            }

            // The Koordinator's hub stays closed, and the HAR pages belong to Harmes / Harlist
            // (the Project Leader, reading every Laporan Project, only sees them read-only).
            $this->actingAs($user)->get(route('operasi.input.index'))->assertForbidden();
            if ($role === RoleName::Operator) {
                $this->actingAs($user)->get(route('har.input.abnormal-gangguan.index', $query))->assertForbidden();
            } else {
                $this->actingAs($user)->get(route('har.input.abnormal-gangguan.index', $query))->assertInertia(fn ($page) => $page->where('can_write', false));
            }
        }

        $harmes = $this->userWithRole(RoleName::Harmes, $unit);
        $this->actingAs($harmes)->get(route('operasi.input.flm-monitoring.index', $query))->assertForbidden();
    }

    public function test_an_operator_flm_entry_lands_in_the_operasi_table(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('operasi.input.flm-monitoring.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [
                ['mesin' => 'Fuel Transfer Pump', 'tanggal' => '2026-08-20', 'masalah' => 'Kebocoran BBM', 'kondisi_awal' => ['bersihkan'], 'kondisi_akhir' => 'Ditadah', 'catatan' => '', 'status' => 'open'],
            ]])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, OperasiFlmMonitoring::query()->where('unit_id', $unit->id)->count());

        // The TL Operasi sees the same entry.
        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $unit))
            ->get(route('operasi.input.flm-monitoring.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page->component('operasi/input/flm-monitoring/index')->where('rows.0.mesin', 'Fuel Transfer Pump'));
    }

    public function test_each_input_page_has_its_own_permission_in_role_and_akses(): void
    {
        $this->assertCount(14, PermissionName::operasiLapangan());
        $this->assertCount(27, PermissionName::harLapangan());

        $operator = Role::query()->where('name', RoleName::Operator->value)->sole()->permissions()->pluck('name');
        $harmes = Role::query()->where('name', RoleName::Harmes->value)->sole()->permissions()->pluck('name');

        foreach (PermissionName::operasiLapangan() as $permission) {
            $this->assertTrue($operator->contains($permission->value), $permission->value);
            $this->assertSame(PermissionGroup::OperasiLapangan, $permission->group());
        }
        foreach (PermissionName::harLapangan() as $permission) {
            $this->assertTrue($harmes->contains($permission->value), $permission->value);
            $this->assertSame(PermissionGroup::PemeliharaanLapangan, $permission->group());
        }

        $this->actingAs($this->userWithRole(RoleName::SuperAdmin))
            ->get(route('admin.roles.edit', Role::query()->where('name', RoleName::Operator->value)->sole()))
            ->assertInertia(fn ($page) => $page->where('permissionGroups', fn ($groups) => collect($groups)->pluck('label')->contains('Operasi — Input Lapangan')));
    }

    public function test_withdrawing_a_page_permission_closes_only_that_page(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $harmes = $this->userWithRole(RoleName::Harmes, $unit);
        $query = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        Role::query()->where('name', RoleName::Harmes->value)->sole()
            ->permissions()->detach(Permission::query()->where('name', PermissionName::HarLapanganWorkOrder->value)->value('id'));
        $harmes->forgetAccessCache();
        $harmes = $harmes->fresh();

        $this->actingAs($harmes)->get(route('har.input.work-order.index', $query))->assertForbidden();
        $this->actingAs($harmes)->get(route('har.input.service-request.index', $query))->assertOk();
    }

    public function test_harmes_and_harlist_sit_on_the_non_shift_roster_after_the_koordinator(): void
    {
        $unit = Unit::factory()->create();
        $harlist = Employee::factory()->create(['unit_id' => $unit->id, 'name' => 'A Harlist', 'position' => Employee::POSITION_HARLIST, 'regu' => null, 'is_active' => true]);
        $harmes = Employee::factory()->create(['unit_id' => $unit->id, 'name' => 'B Harmes', 'position' => Employee::POSITION_HARMES, 'regu' => null, 'is_active' => true]);
        $koordinator = Employee::factory()->create(['unit_id' => $unit->id, 'name' => 'C Koordinator', 'position' => 'Koordinator Pemeliharaan', 'regu' => null, 'is_active' => true]);

        $roster = app(AttendanceRoster::class)->employees($unit, ScheduleGroupType::NonShift);

        $this->assertSame([$koordinator->id, $harmes->id, $harlist->id], $roster->pluck('id')->all());
    }
}
