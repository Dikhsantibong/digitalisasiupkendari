<?php

namespace Tests\Feature\Operator;

use App\Enums\RoleName;
use App\Models\OperatorAbsensiDocument;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Database\Seeders\AttendanceCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class AbsensiReportTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    /** @var array<string, mixed> */
    private array $params = ['unit_id' => 0, 'month' => 9, 'year' => 2026, 'group_type' => 'shift'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        $this->seed(AttendanceCodeSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function q(Unit $unit): array
    {
        return [...$this->params, 'unit_id' => $unit->id];
    }

    public function test_a_role_without_absensi_permission_is_forbidden(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('operator.laporan.absensi.edit', $this->q($unit)))
            ->assertForbidden();
    }

    public function test_the_document_editor_opens(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operator.laporan.absensi.edit', $this->q($unit)))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operator/laporan/absensi-document')
                ->where('format', 'html')
                ->where('has_saved', false)
                ->has('content')
                ->has('grid.rows')
                ->has('pdf_url'));
    }

    public function test_the_project_leader_saves_and_reopens_saved(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::ProjectLeaderOperasi, $unit);

        $this->actingAs($user)
            ->post(route('operator.laporan.absensi.store'), [...$this->q($unit), 'format' => 'grid', 'content_grid' => ['rows' => [], 'cols' => 3]])
            ->assertRedirect();

        $record = OperatorAbsensiDocument::query()->where('unit_id', $unit->id)->first();
        $this->assertNotNull($record);
        $this->assertSame('grid', $record->format);

        $this->actingAs($user)
            ->get(route('operator.laporan.absensi.edit', $this->q($unit)))
            ->assertInertia(fn ($page) => $page->where('has_saved', true)->where('format', 'grid'));
    }

    public function test_the_pdf_streams(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operator.laporan.absensi.pdf', $this->q($unit)))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_operator_cannot_save_absensi(): void
    {
        // The plain operator has absensi.view but not absensi.write.
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->post(route('operator.laporan.absensi.store'), [...$this->q($unit), 'format' => 'html', 'content_html' => '<p>x</p>'])
            ->assertForbidden();
    }

    public function test_manager_can_view_but_not_write(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->create(['service_unit_id' => $serviceUnit->id]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('operator.laporan.absensi.edit', $this->q($unit)))
            ->assertInertia(fn ($page) => $page->where('can_write', false));
    }

    public function test_a_foreign_unit_is_forbidden(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $ownUnit))
            ->get(route('operator.laporan.absensi.edit', $this->q($foreignUnit)))
            ->assertForbidden();
    }
}
