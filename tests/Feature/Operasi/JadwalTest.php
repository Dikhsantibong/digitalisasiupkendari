<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class JadwalTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_authorized_user_can_view_jadwal_page(): void
    {
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia']);
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        $response = $this->actingAs($user)->get(route('operasi.jadwal.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('operasi/jadwal/index')
            ->has('filters')
            ->has('options.units')
        );
    }

    public function test_unauthorized_user_cannot_view_jadwal_page(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        $response = $this->actingAs($user)->get(route('operasi.jadwal.index'));

        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_input_hub_page(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);

        $response = $this->actingAs($user)->get(route('operasi.input.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('operasi/input/index')
        );
    }
}
