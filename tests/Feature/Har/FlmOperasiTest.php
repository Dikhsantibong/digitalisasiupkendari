<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\Employee;
use App\Models\OperasiFlmMonitoring;
use App\Models\Unit;
use App\Models\User;
use App\Services\Notifications\FlmReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * FLM dari Operator (user, 2026-11-24): Pemeliharaan sees the operators'
 * Monitoring FLM findings with the reporter's name & shift, closes the open
 * ones, and is reminded of findings open for several days.
 */
class FlmOperasiTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
        $this->withoutDefer();
        $this->unit = Unit::factory()->create(['is_active' => true]);
    }

    private function reporter(string $name, string $regu): User
    {
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        Employee::factory()->create(['unit_id' => $this->unit->id, 'user_id' => $user->id, 'name' => $name, 'regu' => $regu, 'is_active' => true]);

        return $user;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function saveFlm(User $user, array $rows): void
    {
        $this->actingAs($user)->post(route('operasi.input.flm-monitoring.store'), [
            'unit_id' => $this->unit->id, 'month' => 11, 'year' => 2026, 'rows' => $rows,
        ])->assertSessionHasNoErrors();
    }

    public function test_the_reporter_name_and_shift_survive_later_saves_and_pemeliharaan_closes_it(): void
    {
        // 17:30 WITA = Sore.
        $this->travelTo(Carbon::parse('2026-11-20 17:30', 'Asia/Makassar'));
        $budi = $this->reporter('Budi', 'B');
        $this->saveFlm($budi, [['mesin' => 'MIRRLEES #1', 'tanggal' => '2026-11-20', 'masalah' => 'Baut longgar', 'kondisi_awal' => ['kencangkan'], 'status' => 'open']]);

        $flm = OperasiFlmMonitoring::query()->sole();
        $this->assertSame(['Budi', 'Sore · Regu B'], [$flm->reporter_name, $flm->reporter_shift]);

        // Another operator saves the month again (adding a row): the first reporter stays.
        $this->travelTo(Carbon::parse('2026-11-21 09:00', 'Asia/Makassar'));
        $andi = $this->reporter('Andi', 'A');
        $this->saveFlm($andi, [
            ['uid' => $flm->uid, 'mesin' => 'MIRRLEES #1', 'tanggal' => '2026-11-20', 'masalah' => 'Baut longgar', 'kondisi_awal' => ['kencangkan'], 'status' => 'open'],
            ['mesin' => 'MIRRLEES #2', 'tanggal' => '2026-11-21', 'masalah' => 'Rembes oli', 'status' => 'open'],
        ]);
        $rows = OperasiFlmMonitoring::query()->orderBy('no_urut')->get();
        $this->assertSame(['Budi', 'Andi'], $rows->pluck('reporter_name')->all());
        $this->assertSame(['Sore · Regu B', 'Pagi · Regu A'], $rows->pluck('reporter_shift')->all());

        // Pemeliharaan sees the open findings with the reporter & shift.
        $tl = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $this->unit);
        $this->actingAs($tl)->get(route('har.flm-operasi.index', ['unit_id' => $this->unit->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('har/flm-operasi/index')
                ->has('rows', 2)->where('rows.0.reporter_name', 'Budi')->where('rows.0.reporter_shift', 'Sore · Regu B')
                ->where('rows.0.open_days', 1)->where('counts.open', 2)->where('can_close', true));

        // Close needs the kondisi akhir.
        $first = $rows->first();
        $this->actingAs($tl)->post(route('har.flm-operasi.close', $first), ['kondisi_akhir' => ''])->assertSessionHasErrors('kondisi_akhir');
        $this->actingAs($tl)->post(route('har.flm-operasi.close', $first), ['kondisi_akhir' => 'Sudah dikencangkan'])->assertSessionHasNoErrors();
        $first->refresh();
        $this->assertSame(['close', 'Sudah dikencangkan', $tl->name], [$first->status, $first->kondisi_akhir, $first->closed_by_name]);
        $this->actingAs($tl)->post(route('har.flm-operasi.close', $first), ['kondisi_akhir' => 'lagi'])->assertStatus(422);

        // The Operasi page saving again keeps the closure.
        $this->saveFlm($andi, $rows->map(fn (OperasiFlmMonitoring $r): array => ['uid' => $r->uid, 'mesin' => $r->mesin, 'tanggal' => $r->tanggal?->format('Y-m-d'), 'masalah' => $r->masalah, 'kondisi_akhir' => $r->is($first) ? 'Sudah dikencangkan' : null, 'status' => $r->is($first) ? 'close' : 'open'])->all());
        $this->assertSame($tl->name, OperasiFlmMonitoring::query()->where('uid', $first->uid)->value('closed_by_name'));
    }

    public function test_open_findings_are_reminded_after_three_days(): void
    {
        $this->travelTo(Carbon::parse('2026-11-20 10:00', 'Asia/Makassar'));
        $this->saveFlm($this->reporter('Budi', 'B'), [['mesin' => 'MIRRLEES #1', 'tanggal' => '2026-11-20', 'masalah' => 'Baut longgar', 'status' => 'open']]);
        $tl = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $this->unit);
        $operator = $this->userWithRole(RoleName::Operator, $this->unit);

        // Two days open: no reminder yet.
        $this->assertSame(0, app(FlmReminders::class)->run(Carbon::parse('2026-11-22 08:00', 'Asia/Makassar')));

        // Three days: once a day to Pemeliharaan, never to the operator.
        $this->assertGreaterThan(0, app(FlmReminders::class)->run(Carbon::parse('2026-11-23 08:00', 'Asia/Makassar')));
        app(FlmReminders::class)->run(Carbon::parse('2026-11-23 12:00', 'Asia/Makassar'));
        $this->assertSame(['1 FLM masih open · '.$this->unit->name], $tl->notifications()->get()->pluck('data.title')->all());
        $this->assertSame(0, $operator->notifications()->count());
    }

    public function test_who_sees_and_who_closes(): void
    {
        $flm = OperasiFlmMonitoring::query()->create(['unit_id' => $this->unit->id, 'year' => 2026, 'month' => 11, 'no_urut' => 1, 'mesin' => 'M1', 'masalah' => 'x', 'status' => 'open']);

        $this->actingAs($this->userWithRole(RoleName::Operator, $this->unit))->get(route('har.flm-operasi.index'))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit))->get(route('har.flm-operasi.index'))->assertOk();
        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, Unit::factory()->create()))
            ->post(route('har.flm-operasi.close', $flm), ['kondisi_akhir' => 'ok'])->assertForbidden();
        $this->assertSame('open', $flm->fresh()->status);
    }
}
