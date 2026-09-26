<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Every Input Pemeliharaan page lives in its own folder
 * resources/js/pages/har/input/{page}/index.tsx and is reachable from its
 * route (ensure_pages_exist checks the file).
 */
class InputPagesTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>, 2: string}>
     */
    public static function pages(): array
    {
        return [
            'work order' => ['har.input.work-order.index', [], 'har/input/work-order/index'],
            'service request' => ['har.input.service-request.index', [], 'har/input/service-request/index'],
            'log kegiatan' => ['har.input.activity.index', [], 'har/input/activity/index'],
            'rencana vs realisasi' => ['har.input.schedule.index', [], 'har/input/schedule/index'],
            'lampiran foto' => ['har.input.attachment.index', [], 'har/input/attachment/index'],
            'unsafe condition' => ['har.input.unsafe-condition.index', [], 'har/input/unsafe-condition/index'],
            'rekap laporan gangguan' => ['har.input.laporan-gangguan.index', [], 'har/input/laporan-gangguan/index'],
            'abnormal dan gangguan' => ['har.input.abnormal-gangguan.index', [], 'har/input/abnormal-gangguan/index'],
            'program 5s5r' => ['har.input.program-5s5r.index', [], 'har/input/program-5s5r/index'],
            'patrol check' => ['har.input.lembar.index', ['lembar' => 'patrol-check-pemeliharaan'], 'har/input/patrol-check-pemeliharaan/index'],
        ];
    }

    /**
     * @param  array<string, string>  $parameters
     */
    #[DataProvider('pages')]
    public function test_the_input_page_opens_from_its_own_folder(string $route, array $parameters, string $component): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        Machine::factory()->forUnit($unit)->create();

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->get(route($route, [...$parameters, 'unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component($component));
    }

    public function test_the_input_hub_still_opens(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->get(route('har.input.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('har/input/index'));
    }
}
