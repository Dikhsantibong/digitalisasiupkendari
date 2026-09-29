<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3JadwalPembuatanIk;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Jadwal Pembuatan IK K3 (Akses 1): the same yearly R / ✓ month matrix as the
 * HAR Pembuatan IK, stored separately for K3.
 */
class JadwalPembuatanIkTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_koordinator_k3_opens_the_default_thirty_rows(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorK3, $unit))
            ->get(route('k3.jadwal.pembuatan-ik.index', ['unit_id' => $unit->id, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/jadwal/pembuatan-ik/index')
                ->has('rows', 30)
                ->where('filters.year', 2026)
                ->where('can_write', true));
    }

    public function test_saving_stores_the_k3_rows_apart_from_har_and_feeds_the_report(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $this->actingAs($user)->post(route('k3.jadwal.pembuatan-ik.store'), [
            'unit_id' => $unit->id,
            'year' => 2026,
            'rows' => [
                ['no_urut' => 1, 'instruksi_kerja' => 'IK Pelaksanaan Evakuasi', 'pic_pembuat' => 'Koordinator K3', 'rencana_bulan' => [8, 9], 'realisasi_bulan' => [8, 13], 'keterangan' => ''],
                ['no_urut' => 2, 'instruksi_kerja' => 'IK Penggunaan APAR', 'pic_pembuat' => '', 'rencana_bulan' => [10], 'realisasi_bulan' => [], 'keterangan' => null],
            ],
        ])->assertRedirect()->assertSessionHas('toast.type', 'success');

        $first = K3JadwalPembuatanIk::query()->where('unit_id', $unit->id)->orderBy('sort_order')->firstOrFail();
        $this->assertSame([8, 9], $first->rencana_bulan);
        // Months outside 1..12 are dropped.
        $this->assertSame([8], $first->realisasi_bulan);
        $this->assertDatabaseCount('har_jadwal_pembuatan_iks', 0);

        $this->actingAs($user)->get(route('k3.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('content', fn (string $html): bool => str_contains($html, 'IK Pelaksanaan Evakuasi')));
    }

    public function test_the_pdf_downloads_and_other_units_are_forbidden(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $foreign = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorK3, $unit);

        $this->actingAs($user)->get(route('k3.jadwal.pembuatan-ik.pdf', ['unit_id' => $unit->id, 'year' => 2026]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($user)->get(route('k3.jadwal.pembuatan-ik.index', ['unit_id' => $foreign->id]))->assertForbidden();
        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))->get(route('k3.jadwal.pembuatan-ik.index'))->assertForbidden();
    }
}
