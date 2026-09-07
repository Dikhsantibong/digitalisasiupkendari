<?php

namespace Tests\Feature\Operasi;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Feeder;
use App\Models\Unit;
use Database\Seeders\WorkModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class OperasiFoundationTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_tl_operasi_holds_every_operasi_permission(): void
    {
        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, Unit::factory()->create());

        $this->assertTrue($user->hasPermissionTo(PermissionName::OperasiInputWrite));
        $this->assertTrue($user->hasPermissionTo(PermissionName::OperasiLaporanView));
        $this->assertTrue($user->hasPermissionTo(PermissionName::OperasiBeritaAcaraCreate));
        $this->assertTrue($user->hasPermissionTo(PermissionName::OperasiMasterManage));
    }

    public function test_tl_pemeliharaan_holds_no_operasi_permission(): void
    {
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, Unit::factory()->create());

        $this->assertFalse($user->hasPermissionTo(PermissionName::OperasiInputWrite));
        $this->assertFalse($user->hasPermissionTo(PermissionName::OperasiBeritaAcaraCreate));
    }

    public function test_work_module_seeder_registers_the_operasi_module(): void
    {
        $this->seed(WorkModuleSeeder::class);

        $this->assertDatabaseHas('work_modules', ['code' => 'operasi', 'is_active' => true]);
    }

    public function test_unit_scoped_records_are_only_visible_within_the_users_units(): void
    {
        $ownUnit = Unit::factory()->create();
        $otherUnit = Unit::factory()->create();

        Feeder::factory()->count(2)->forUnit($ownUnit)->create();
        Feeder::factory()->count(3)->forUnit($otherUnit)->create();

        $user = $this->userWithRole(RoleName::TeamLeaderOperasi, $ownUnit);

        $visible = Feeder::query()->visibleTo($user)->get();

        $this->assertCount(2, $visible);
        $this->assertTrue($visible->every(fn (Feeder $feeder): bool => $feeder->unit_id === $ownUnit->id));
    }
}
