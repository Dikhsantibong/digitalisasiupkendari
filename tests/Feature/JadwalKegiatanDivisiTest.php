<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\HarJadwalHarian;
use App\Models\K3KegiatanRutin;
use App\Models\OperasiJadwalKegiatan;
use App\Models\PdmJadwalHarian;
use App\Models\Unit;
use App\Services\Har\HarReportBuilder;
use App\Support\LogistikJadwal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Every divisi plans its Jadwal Kegiatan as I. Harian, II. Mingguan,
 * III. Bulanan and IV. Non Rutin (user, 2026-11-25).
 */
class JadwalKegiatanDivisiTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const GROUPS = ['harian', 'mingguan', 'bulanan', 'non_rutin'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccessControl();
    }

    /**
     * @return array<string, array{0: RoleName, 1: string, 2: string, 3: class-string}>
     */
    public static function divisi(): array
    {
        return [
            'operasi' => [RoleName::KoordinatorOperasi, 'operasi.jadwal.kegiatan', 'operasi/jadwal/kegiatan/index', OperasiJadwalKegiatan::class],
            'pemeliharaan' => [RoleName::KoordinatorPemeliharaan, 'har.jadwal.harian', 'har/jadwal/harian/index', HarJadwalHarian::class],
            'k3' => [RoleName::KoordinatorK3, 'k3.jadwal.kegiatan-rutin', 'k3/jadwal/kegiatan-rutin/index', K3KegiatanRutin::class],
        ];
    }

    /**
     * @param  class-string  $model
     */
    #[DataProvider('divisi')]
    public function test_the_sheet_has_the_four_groups_saves_and_prints(RoleName $role, string $route, string $component, string $model): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole($role, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)->get(route("{$route}.index", $period))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($component): void {
                $page->component($component);
                $groups = array_values(array_unique(array_column($page->toArray()['props']['rows'], 'grup')));
                $this->assertSame(self::GROUPS, $groups);
            });

        $this->actingAs($user)->post(route("{$route}.store"), $period + ['rows' => [
            ['grup' => 'harian', 'kegiatan' => 'Absensi', 'target' => 21, 'jadwal' => [3, 4, 4, 40]],
            ['grup' => 'non_rutin', 'kegiatan' => 'Penanganan gangguan', 'target' => 0, 'jadwal' => [12]],
            // A page opened before the groups sends none: harian.
            ['kegiatan' => 'Tanpa grup', 'target' => 21, 'jadwal' => []],
        ]])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(['harian', 'non_rutin', 'harian'], $model::query()->where('unit_id', $unit->id)->orderBy('sort_order')->pluck('grup')->all());
        $this->assertSame([3, 4], $model::query()->where('kegiatan', 'Absensi')->sole()->jadwal);

        $this->actingAs($user)->get(route("{$route}.index", $period))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 3)->where('rows.1.grup', 'non_rutin')->where('rows.1.realisasi_count', 1));

        $this->actingAs($user)->post(route("{$route}.store"), $period + ['rows' => [['grup' => 'tahunan', 'kegiatan' => 'X', 'target' => 1]]])
            ->assertSessionHasErrors('rows.0.grup');

        $this->actingAs($user)->get(route("{$route}.pdf", $period))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_operasi_jadwal_kegiatan_is_closed_to_other_divisi_and_units(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $other = Unit::factory()->create(['is_active' => true]);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))->get(route('operasi.jadwal.kegiatan.index', $period))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $other))->get(route('operasi.jadwal.kegiatan.index', $period))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))->post(route('operasi.jadwal.kegiatan.store'), $period + ['rows' => []])->assertForbidden();
    }

    public function test_pemeliharaan_rows_saved_before_the_groups_stay_harian_and_reach_the_laporan(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        HarJadwalHarian::query()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 8, 'no_urut' => 1, 'kegiatan' => 'Absensi lama', 'target' => 20, 'rencana_count' => 20, 'realisasi_count' => 2, 'jadwal' => [3, 4], 'sort_order' => 0]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->get(route('har.jadwal.harian.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 1)->where('rows.0.grup', 'harian')->where('rows.0.kegiatan', 'Absensi lama'));

        $sections = app(HarReportBuilder::class)->buildJadwalSections($unit, 8, 2026);
        $this->assertSame('Harian', $sections['harian']['groups'][0]['label']);
        $this->assertSame('Absensi lama', $sections['harian']['groups'][0]['rows'][0]['kegiatan']);
    }

    public function test_pdm_and_logistik_use_harian_mingguan_bulanan_and_non_rutin(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];
        $pdm = $this->userWithRole(RoleName::KoordinatorPdm, $unit);

        $this->actingAs($pdm)->get(route('pdm.jadwal.harian.index', $period))
            ->assertInertia(function (AssertableInertia $page): void {
                $headings = array_column(array_filter($page->toArray()['props']['rows'], fn (array $r): bool => $r['is_category_header']), 'kategori');
                $this->assertSame(['I. Harian', 'II. Mingguan', 'III. Bulanan', 'IV. Non Rutin'], $headings);
            });

        // A month saved before the Non Rutin group gets its empty heading.
        PdmJadwalHarian::query()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 9, 'kategori' => 'I. MESIN', 'is_category_header' => true, 'no_urut' => 'I', 'kegiatan' => 'MESIN', 'target' => 20, 'rencana_count' => 20, 'jadwal' => [], 'sort_order' => 0]);
        $this->actingAs($pdm)->get(route('pdm.jadwal.harian.index', ['month' => 9] + $period))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('rows', 2)->where('rows.0.kategori', 'I. MESIN')->where('rows.1.kategori', 'IV. Non Rutin')->where('rows.1.id', null));

        $this->assertSame(['Harian', 'Mingguan', 'Bulanan', 'Non Rutin'], array_column(LogistikJadwal::sections('kegiatan'), 'title'));
    }
}
