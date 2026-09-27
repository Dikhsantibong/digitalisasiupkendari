<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\HarLaporanKegiatan;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Akses 2 — Pengusahaan: Laporan Kegiatan Pemeliharaan Mesin and Listrik &
 * Kontrol Pembangkit (FMKD-314-10.3.3-A3).
 */
class LaporanKegiatanTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_team_leader_and_staf_open_both_reports(): void
    {
        $unit = Unit::factory()->create();
        Machine::factory()->forUnit($unit)->create(['name' => 'MAK#1']);
        HarLaporanKegiatan::factory()->create(['unit_id' => $unit->id]);
        HarLaporanKegiatan::factory()->listrik()->create(['unit_id' => $unit->id]);
        HarLaporanKegiatan::factory()->create(['unit_id' => $unit->id, 'month' => 9]);

        foreach ([RoleName::TeamLeaderPemeliharaan, RoleName::StafPemeliharaan] as $role) {
            $this->actingAs($this->userWithRole($role, $unit))
                ->get(route('har.pengusahaan.laporan-kegiatan.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('pengusahaan/har/laporan-kegiatan/index')
                    ->has('reports.mesin', 1)
                    ->has('reports.listrik', 1)
                    ->where('reports.mesin.0.groups.0.judul', 'PREVENTIV MAINTENANCE P2')
                    ->where('machines', ['MAK#1'])
                    ->where('document.number', 'FMKD-314-10.3.3-A3')
                    ->where('can_write', true));
        }
    }

    public function test_akses_1_and_other_divisions_are_forbidden(): void
    {
        $unit = Unit::factory()->create();

        foreach ([RoleName::KoordinatorPemeliharaan, RoleName::StafOperasi, RoleName::Operator] as $role) {
            $user = $this->userWithRole($role, $unit);
            $this->actingAs($user)->get(route('har.pengusahaan.laporan-kegiatan.index'))->assertForbidden();
            $this->actingAs($user)->post(route('har.pengusahaan.laporan-kegiatan.store'), [])->assertForbidden();
        }
    }

    public function test_saving_replaces_the_report_of_the_month_and_keeps_the_other_report(): void
    {
        $unit = Unit::factory()->create();
        $staf = $this->userWithRole(RoleName::StafPemeliharaan, $unit);
        HarLaporanKegiatan::factory()->create(['unit_id' => $unit->id, 'category' => 'listrik']);
        HarLaporanKegiatan::factory()->count(2)->create(['unit_id' => $unit->id, 'category' => 'mesin']);

        $this->actingAs($staf)->post(route('har.pengusahaan.laporan-kegiatan.store'), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'category' => 'mesin',
            'entries' => [
                [
                    'activity_date' => '2026-08-04',
                    'groups' => [[
                        'mesin' => 'MAK#3',
                        'judul' => 'PREVENTIV MAINTENANCE P2',
                        'jenis_har' => 'PREVENTIV',
                        'uraian' => ['Pengecekan tekanan kompresi semua cyl.head', '', '  Hydrotes dan prelube  '],
                    ]],
                    'hasil_pekerjaan' => 'Baik',
                    'material_nama' => 'Kaos tangan, majun, rinso',
                    'jumlah' => '1 psg, 0,2 kg, 3 bh',
                    'no_wo_spki' => "WO13410\nWO13411",
                ],
                [
                    'activity_date' => '2026-07-23',
                    'groups' => [
                        ['mesin' => 'MAK#3', 'judul' => '', 'jenis_har' => 'P2', 'uraian' => ['Pembersihan casing generator']],
                        ['mesin' => 'MAK#5', 'judul' => '', 'jenis_har' => 'K', 'uraian' => ['Benahi gangguan Kvar hunting']],
                    ],
                ],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $mesin = HarLaporanKegiatan::query()->where('unit_id', $unit->id)->where('category', 'mesin')->orderBy('sort_order')->get();
        $this->assertCount(2, $mesin);
        $this->assertSame(['Pengecekan tekanan kompresi semua cyl.head', 'Hydrotes dan prelube'], $mesin[0]->groups[0]['uraian']);
        $this->assertSame("WO13410\nWO13411", $mesin[0]->no_wo_spki);
        $this->assertSame('2026-07-23', $mesin[1]->activity_date->format('Y-m-d'));
        $this->assertCount(2, $mesin[1]->groups);
        $this->assertNull($mesin[1]->hasil_pekerjaan);
        $this->assertSame(1, HarLaporanKegiatan::query()->where('category', 'listrik')->count());
    }

    public function test_an_entry_needs_a_machine_group_and_a_known_category(): void
    {
        $unit = Unit::factory()->create();
        $tl = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($tl)->post(route('har.pengusahaan.laporan-kegiatan.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'category' => 'lainnya',
            'entries' => [['activity_date' => '2026-08-04', 'groups' => []]],
        ])->assertSessionHasErrors(['category', 'entries.0.groups']);

        $foreign = Unit::factory()->create();
        $this->actingAs($tl)->post(route('har.pengusahaan.laporan-kegiatan.store'), [
            'unit_id' => $foreign->id, 'month' => 8, 'year' => 2026, 'category' => 'mesin', 'entries' => [],
        ])->assertForbidden();
    }
}
