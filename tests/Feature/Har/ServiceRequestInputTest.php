<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Models\Machine;
use App\Models\ServiceRequest;
use App\Models\Unit;
use Database\Seeders\HarMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class ServiceRequestInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(HarMasterSeeder::class);
    }

    public function test_a_role_without_har_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('har.input.service-request.index'))
            ->assertForbidden();
    }

    public function test_tl_pemeliharaan_sees_the_service_request_grid(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.input.service-request.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/input/service-requests')
                ->has('options.categories')
                ->has('options.statuses'),
            );
    }

    public function test_saving_resolves_category_engine_and_status(): void
    {
        $unit = Unit::factory()->create();
        $engine = Machine::factory()->forUnit($unit)->create(['name' => 'MAK #2']);
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.service-request.store'), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'rows' => [[
                'sr_number' => 'SR900',
                'description' => 'Kebocoran',
                'category_code' => 'CM',
                'status' => 'close',
                'engine_name' => 'MAK #2',
            ]],
        ])->assertRedirect();

        $sr = ServiceRequest::query()->where('sr_number', 'SR900')->firstOrFail();
        $this->assertSame('CM', $sr->category->code);
        $this->assertSame($engine->id, $sr->engine_id);
        $this->assertSame(ServiceRequestStatus::Close, $sr->status);
    }

    public function test_an_invalid_status_defaults_to_open(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.service-request.store'), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'rows' => [['sr_number' => 'SRX', 'status' => 'entah', 'category_code' => 'TIDAK-ADA']],
        ])->assertRedirect();

        $sr = ServiceRequest::query()->where('sr_number', 'SRX')->firstOrFail();
        $this->assertSame(ServiceRequestStatus::Open, $sr->status);
        $this->assertNull($sr->sr_category_id);
    }

    public function test_rows_removed_from_the_grid_are_deleted(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);
        $payload = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)->post(route('har.input.service-request.store'), [
            ...$payload, 'rows' => [['sr_number' => 'SR1'], ['sr_number' => 'SR2']],
        ])->assertRedirect();
        $this->assertSame(2, ServiceRequest::query()->where('unit_id', $unit->id)->count());

        $this->actingAs($user)->post(route('har.input.service-request.store'), [
            ...$payload, 'rows' => [['sr_number' => 'SR1']],
        ])->assertRedirect();
        $this->assertSame(1, ServiceRequest::query()->where('unit_id', $unit->id)->count());
    }

    public function test_a_user_cannot_save_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $ownUnit))
            ->post(route('har.input.service-request.store'), [
                'unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026,
                'rows' => [['sr_number' => 'SRZ']],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('service_requests', 0);
    }
}
