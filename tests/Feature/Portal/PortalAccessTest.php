<?php

namespace Tests\Feature\Portal;

use App\Enums\ReportModule;
use App\Enums\ReportStatus;
use App\Enums\RoleName;
use App\Enums\RoleScope;
use App\Models\ReportWorkflow;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Kantor induk UP Kendari accounts (Manager UP, TL & Asman bidang) use the
 * Portal Pemantauan: all units, only their bidang, and view / download only.
 */
class PortalAccessTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const UP_ROLES = [
        RoleName::ManagerUp,
        RoleName::TeamLeaderOperasiUp,
        RoleName::TeamLeaderPemeliharaanUp,
        RoleName::TeamLeaderK3Up,
        RoleName::AsmanOperasi,
        RoleName::AsmanPemeliharaan,
        RoleName::AsmanK3,
    ];

    private Unit $unit;

    private Unit $otherUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $serviceUnit = ServiceUnit::factory()->create();
        $this->unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true, 'name' => 'PLTD Uji']);
        $this->otherUnit = Unit::factory()->forServiceUnit(ServiceUnit::factory()->create())->create(['is_active' => true, 'name' => 'PLTD Lain']);
    }

    public function test_every_up_role_is_global_read_only_and_lands_on_the_portal(): void
    {
        foreach (self::UP_ROLES as $role) {
            $this->assertSame(RoleScope::Global, $role->scope(), $role->value);
            $user = $this->userWithRole($role);

            $this->assertTrue($user->isReadOnly(), $role->value);
            $this->assertTrue($user->usesPortal(), $role->value);
            $this->assertTrue($user->canAccessUnit($this->unit) && $user->canAccessUnit($this->otherUnit), "{$role->value} sees every unit");

            $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('portal.index'));
            $this->actingAs($user)->get(route('portal.index'))->assertOk()
                ->assertInertia(fn ($page) => $page->component('portal/index')->where('read_only', true)->where('unit_count', 2));
        }
    }

    public function test_each_account_sees_only_its_bidang(): void
    {
        $expected = [
            RoleName::TeamLeaderOperasiUp->value => ['operasi'],
            RoleName::AsmanOperasi->value => ['operasi'],
            RoleName::TeamLeaderPemeliharaanUp->value => ['pemeliharaan', 'logistik', 'pdm'],
            RoleName::AsmanPemeliharaan->value => ['pemeliharaan', 'logistik', 'pdm'],
            RoleName::TeamLeaderK3Up->value => ['k3'],
            RoleName::AsmanK3->value => ['k3'],
            RoleName::ManagerUp->value => ['operasi', 'pemeliharaan', 'k3', 'logistik', 'pdm'],
        ];

        foreach ($expected as $role => $areas) {
            $user = $this->userWithRole(RoleName::from($role));
            $this->actingAs($user)->get(route('portal.index'))
                ->assertInertia(fn ($page) => $page->where('areas', fn ($list): bool => collect($list)->pluck('key')->sort()->values()->all() === collect($areas)->sort()->values()->all()));
        }

        // Monitoring follows the same bidang.
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasiUp))->get(route('monitoring.input'))
            ->assertInertia(fn ($page) => $page->where('groups', fn ($groups): bool => collect($groups)->pluck('key')->all() === ['operasi', 'operasi_pengusahaan', 'operator']));
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3Up))->get(route('monitoring.laporan'))
            // The bidang's Laporan Pembangkit and its Laporan Pengusahaan.
            ->assertInertia(fn ($page) => $page->has('modules', 2)->where('modules.0.key', 'k3')->where('modules.1.key', 'k3-pengusahaan'));
    }

    public function test_input_pages_open_read_only_and_other_bidang_stay_closed(): void
    {
        $tlOperasi = $this->userWithRole(RoleName::TeamLeaderOperasiUp);
        $query = ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($tlOperasi)->get(route('operasi.input.kondisi-abnormal.index', $query))->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($tlOperasi)->get(route('operasi.pengusahaan.daily-report.index', $query))->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($tlOperasi)->get(route('operator.logsheet.index', $query))->assertOk();

        $this->actingAs($tlOperasi)->get(route('har.input.work-order.index', $query))->assertForbidden();
        $this->actingAs($tlOperasi)->get(route('k3.input.accident.index', $query))->assertForbidden();
    }

    public function test_no_write_request_of_a_view_only_account_is_accepted(): void
    {
        $manager = $this->userWithRole(RoleName::ManagerUp);
        $target = ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($manager)->post(route('operasi.input.kondisi-abnormal.store'), $target + ['rows' => [['no_urut' => 1, 'is_abnormal' => 1]]])->assertForbidden();
        $this->actingAs($manager)->post(route('operasi.laporan.document.store', 'laporan-operasi-bulanan'), $target + ['format' => 'html', 'content_html' => '<p>x</p>'])->assertForbidden();
        $this->actingAs($manager)->post(route('report-workflow.submit', 'operasi'), $target)->assertForbidden();
        $this->actingAs($manager)->post(route('har.input.work-order.store'), $target + ['rows' => []])->assertForbidden();
        $this->actingAs($manager)->delete(route('admin.users.destroy', User::factory()->create()))->assertForbidden();
        $this->assertDatabaseCount('kondisi_abnormals', 0);

        // Their own account still works: notifications & logout.
        $this->actingAs($manager)->postJson(route('notifications.read-all'))->assertOk();
        $this->actingAs($manager)->post(route('logout'))->assertRedirect();
    }

    public function test_the_operasi_report_document_is_read_only_for_them(): void
    {
        $tlOperasi = $this->userWithRole(RoleName::TeamLeaderOperasiUp);

        $this->actingAs($tlOperasi)
            ->get(route('operasi.laporan.document.edit', ['report' => 'laporan-operasi-bulanan', 'unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false)->where('workflow.can.submit', false));
    }

    public function test_the_laporan_page_lists_final_reports_and_pengusahaan_reports_per_bidang(): void
    {
        ReportWorkflow::factory()->create(['module' => ReportModule::K3, 'unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026, 'status' => ReportStatus::Final, 'finalized_at' => now()]);
        ReportWorkflow::factory()->create(['module' => ReportModule::Har, 'unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026, 'status' => ReportStatus::Diajukan]);
        $tlK3 = $this->userWithRole(RoleName::TeamLeaderK3Up);
        $manager = $this->userWithRole(RoleName::ManagerUp);

        $this->actingAs($tlK3)->get(route('portal.laporan', ['month' => 8, 'year' => 2026]))->assertOk()
            ->assertInertia(fn ($page) => $page->component('portal/laporan')->has('rows', 1)->where('rows.0.module', 'k3')
                ->where('rows.0.view_url', fn (string $url): bool => str_starts_with($url, '/k3/laporan/dokumen/pdf?')));

        // Only final by default; "semua" shows the HAR report in progress for the Manager UP.
        $this->actingAs($manager)->get(route('portal.laporan', ['month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page->has('rows', 1));
        $this->actingAs($manager)->get(route('portal.laporan', ['month' => 8, 'year' => 2026, 'status' => 'semua', 'unit_id' => $this->unit->id]))
            ->assertInertia(fn ($page) => $page->has('rows', count(ReportModule::cases())));

        // Pengusahaan: TL K3 UP sees the K3 one per unit; the Manager UP every bidang.
        $this->actingAs($tlK3)->get(route('portal.laporan', ['month' => 8, 'year' => 2026, 'jenis' => 'pengusahaan']))
            ->assertInertia(fn ($page) => $page->has('rows', 2)->where('rows.0.module', 'k3'));
        $this->actingAs($manager)->get(route('portal.laporan', ['month' => 8, 'year' => 2026, 'jenis' => 'pengusahaan']))
            ->assertInertia(fn ($page) => $page->has('rows', 6));
    }

    public function test_the_data_input_page_lists_the_inputs_of_one_unit(): void
    {
        $tlHar = $this->userWithRole(RoleName::TeamLeaderPemeliharaanUp);

        $this->actingAs($tlHar)->get(route('portal.input', ['unit_id' => $this->unit->id, 'bidang' => 'pemeliharaan', 'month' => 8, 'year' => 2026]))->assertOk()
            ->assertInertia(fn ($page) => $page->component('portal/input')
                ->where('filters.bidang', 'pemeliharaan')
                ->where('groups', fn ($groups): bool => collect($groups)->pluck('key')->all() === ['har', 'har_pengusahaan'])
                ->where('groups.0.items.0.url', fn (string $url): bool => str_contains($url, 'unit_id='.$this->unit->id)));

        // A bidang the account cannot see falls back to its own.
        $this->actingAs($tlHar)->get(route('portal.input', ['bidang' => 'operasi']))
            ->assertInertia(fn ($page) => $page->where('filters.bidang', 'pemeliharaan'));
    }

    public function test_the_manager_ul_uses_the_portal_but_keeps_its_actions_and_ul_scope(): void
    {
        $manager = $this->userWithRole(RoleName::ManagerUl, $this->unit->serviceUnit);

        $this->assertTrue($manager->usesPortal());
        $this->assertFalse($manager->isReadOnly(), 'The Manager UL still mengesahkan reports and manages master data.');
        $this->actingAs($manager)->get(route('dashboard'))->assertRedirect(route('portal.index'));
        $this->actingAs($manager)->get(route('portal.index'))->assertOk()->assertInertia(fn ($page) => $page->where('unit_count', 1)->where('read_only', false));
    }

    public function test_other_roles_keep_the_normal_app(): void
    {
        foreach ([RoleName::TeamLeaderOperasi, RoleName::KoordinatorPemeliharaan, RoleName::Operator] as $role) {
            $user = $this->userWithRole($role, $this->unit);
            $this->assertFalse($user->usesPortal());
            $this->assertFalse($user->isReadOnly());
            $this->actingAs($user)->get(route('dashboard'))->assertOk();
            $this->actingAs($user)->get(route('portal.index'))->assertForbidden();
        }

        $admin = $this->userWithRole(RoleName::SuperAdmin);
        $this->assertFalse($admin->isReadOnly());
        $this->assertFalse($admin->usesPortal());
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    }
}
