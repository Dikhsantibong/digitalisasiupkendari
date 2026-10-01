<?php

namespace Tests\Feature\Notifications;

use App\Enums\RoleName;
use App\Models\HarUnsafeCondition;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use App\Models\WoStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class EventNotificationsTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    private ServiceUnit $serviceUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutDefer();
        $this->seedAccessControl();
        $this->serviceUnit = ServiceUnit::factory()->create();
        $this->unit = Unit::factory()->forServiceUnit($this->serviceUnit)->create(['is_active' => true, 'name' => 'PLTD Uji']);
    }

    public function test_a_kondisi_abnormal_reaches_operasi_but_not_the_actor_or_other_divisions(): void
    {
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $tlOperasi = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $koordinatorHar = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $koordinatorK3 = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);
        $tlElsewhere = $this->userWithRole(RoleName::TeamLeaderOperasi, Unit::factory()->create(['is_active' => true]));

        $this->saveAbnormal($koordinator, ['is_abnormal' => 1, 'uraian_kondisi' => 'Suhu exhaust tinggi']);

        $this->assertSame(['Kondisi abnormal · PLTD Uji'], $this->titles($tlOperasi));
        $this->assertStringContainsString('Suhu exhaust tinggi', $tlOperasi->notifications()->first()->data['body']);
        $this->assertSame('kejadian', $tlOperasi->notifications()->first()->data['category']);
        $this->assertSame([], $this->titles($koordinator), 'The one who saved it is not notified.');
        $this->assertSame([], $this->titles($koordinatorHar), 'An abnormal (not a gangguan) stays with Operasi.');
        $this->assertSame([], $this->titles($koordinatorK3));
        $this->assertSame([], $this->titles($tlElsewhere));

        // Saving the same month again does not repeat it.
        $this->saveAbnormal($koordinator, ['is_abnormal' => 1, 'uraian_kondisi' => 'Suhu exhaust tinggi', 'durasi_abnormal' => 2]);
        $this->assertCount(1, $this->titles($tlOperasi));
    }

    public function test_a_gangguan_also_reaches_pemeliharaan(): void
    {
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $koordinatorHar = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $tlHar = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $this->unit);

        $this->saveAbnormal($koordinator, ['is_gangguan' => 1, 'uraian_kondisi' => 'Mesin trip']);

        $this->assertSame(['Gangguan mesin · PLTD Uji'], $this->titles($koordinatorHar));
        $this->assertSame(['Gangguan mesin · PLTD Uji'], $this->titles($tlHar));
        $this->assertNull($tlHar->notifications()->first()->data['url'], 'TL Pemeliharaan cannot open the Operasi input page, so no link.');
    }

    public function test_an_unsafe_finding_reaches_the_module_and_k3_and_closing_tells_the_reporter(): void
    {
        $reporter = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $koordinatorK3 = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);
        $tlHar = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $this->unit);
        $koordinatorOperasi = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);

        $payload = [
            'unit_id' => $this->unit->id, 'month' => 10, 'year' => 2026, 'periode' => 'Minggu 1',
            'kategori' => 'UNSAFE CONDITION', 'temuan' => 'Kabel terkelupas di panel', 'lokasi' => 'Ruang panel', 'keterangan' => 'open',
        ];
        $this->actingAs($reporter)->post(route('har.input.unsafe-condition.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame(['Unsafe Condition baru · PLTD Uji'], $this->titles($koordinatorK3));
        $this->assertSame(['Unsafe Condition baru · PLTD Uji'], $this->titles($tlHar));
        $this->assertSame([], $this->titles($koordinatorOperasi));
        $this->assertSame([], $this->titles($reporter));

        $finding = HarUnsafeCondition::query()->firstOrFail();
        $closer = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $this->actingAs($closer)->post(route('har.input.unsafe-condition.update', $finding), ['keterangan' => 'close'] + $payload)->assertSessionHasNoErrors();

        $this->assertSame(['Temuan Anda sudah ditutup'], $this->titles($reporter));
    }

    public function test_new_and_changed_work_orders_send_one_summary_per_save(): void
    {
        $open = WoStatus::query()->create(['code' => 'WAPPR', 'name' => 'Waiting approval', 'is_closed' => false, 'sort_order' => 1, 'is_active' => true]);
        WoStatus::query()->create(['code' => 'COMP', 'name' => 'Complete', 'is_closed' => true, 'sort_order' => 2, 'is_active' => true]);
        $koordinator = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $tlHar = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $this->unit);
        $harmes = $this->userWithRole(RoleName::Harmes, $this->unit);

        $rows = [['wonum' => 'WO-001', 'description' => 'Ganti filter', 'status_code' => $open->code], ['wonum' => 'WO-002', 'description' => 'Cek pompa', 'status_code' => $open->code]];
        $this->actingAs($koordinator)->post(route('har.input.work-order.store'), ['unit_id' => $this->unit->id, 'month' => 10, 'year' => 2026, 'rows' => $rows])->assertSessionHasNoErrors();

        $this->assertSame(['Work Order diperbarui · PLTD Uji'], $this->titles($tlHar));
        $this->assertStringContainsString('2 Work Order baru: WO-001, WO-002', $tlHar->notifications()->first()->data['body']);
        $this->assertSame([], $this->titles($harmes), 'Field staff are not flooded with WO recaps.');

        // Re-saving unchanged rows sends nothing; a status change sends a new summary.
        $this->actingAs($koordinator)->post(route('har.input.work-order.store'), ['unit_id' => $this->unit->id, 'month' => 10, 'year' => 2026, 'rows' => $rows]);
        $this->assertCount(1, $this->titles($tlHar));

        $rows[0]['status_code'] = 'COMP';
        $this->actingAs($koordinator)->post(route('har.input.work-order.store'), ['unit_id' => $this->unit->id, 'month' => 10, 'year' => 2026, 'rows' => $rows]);
        $this->assertCount(2, $this->titles($tlHar));
        $this->assertTrue($tlHar->notifications()->get()->contains(fn ($n): bool => str_contains($n->data['body'], '1 berubah status: WO-001 → COMP')));
    }

    public function test_a_new_service_request_is_announced(): void
    {
        $tlHar = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $this->unit);
        $koordinator = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);

        $this->actingAs($tlHar)->post(route('har.input.service-request.store'), [
            'unit_id' => $this->unit->id, 'month' => 10, 'year' => 2026,
            'rows' => [['sr_number' => 'SR-77', 'description' => 'Lampu mati', 'status' => 'open']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Service Request diperbarui · PLTD Uji'], $this->titles($koordinator));
        $this->assertSame('pekerjaan', $koordinator->notifications()->first()->data['category']);
    }

    public function test_a_new_accident_reaches_k3_once(): void
    {
        $koordinatorK3 = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);
        $tlK3 = $this->userWithRole(RoleName::TeamLeaderK3, $this->unit);
        $manager = $this->userWithRole(RoleName::ManagerUl, $this->serviceUnit);

        $rows = [
            ['category' => 'pak', 'incident_date' => '2026-10-02', 'lokasi' => 'Gudang', 'luka_ringan' => 1, 'is_nihil' => false],
            ['category' => 'masyarakat', 'is_nihil' => true],
        ];
        $payload = ['unit_id' => $this->unit->id, 'month' => 10, 'year' => 2026, 'rows' => $rows];
        $this->actingAs($koordinatorK3)->post(route('k3.input.accident.store'), $payload)->assertSessionHasNoErrors();

        $this->assertCount(1, $this->titles($tlK3), 'The "nihil" row is not an accident.');
        $this->assertStringContainsString('1 luka ringan', $tlK3->notifications()->first()->data['body']);
        $this->assertCount(1, $this->titles($manager), 'The Manager UL oversees K3 of the unit.');

        // The rows are re-created on every save; the same accident is not announced again.
        $this->actingAs($koordinatorK3)->post(route('k3.input.accident.store'), $payload);
        $this->assertCount(1, $this->titles($tlK3));
    }

    public function test_the_kejadian_switch_and_role_permission_are_honoured(): void
    {
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $tlOperasi = $this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit);
        $tlOperasi->forceFill(['notification_settings' => ['disabled' => ['kejadian']]])->save();

        $this->saveAbnormal($koordinator, ['is_abnormal' => 1, 'uraian_kondisi' => 'Getaran tinggi']);

        $this->assertSame([], $this->titles($tlOperasi));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function saveAbnormal(User $user, array $row): void
    {
        $this->actingAs($user)->post(route('operasi.input.kondisi-abnormal.store'), [
            'unit_id' => $this->unit->id, 'month' => 10, 'year' => 2026,
            'rows' => [['no_urut' => 1, 'tanggal' => '2026-10-03'] + $row],
        ])->assertSessionHasNoErrors();
    }

    /**
     * @return list<string>
     */
    private function titles(User $user): array
    {
        return $user->notifications()->get()->map(fn ($n): string => $n->data['title'])->values()->all();
    }
}
