<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3DocumentRecord;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class LaporanDocumentTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_laporan_permission_cannot_open_the_document(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('k3.laporan.document.edit'))
            ->assertForbidden();
    }

    public function test_tl_sees_the_generated_document(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('k3/laporan/document')
                ->where('document_number', 'SMT-FM-AK3-00')
                ->where('format', 'html')
                ->where('has_saved', false)
                ->where('can_write', true)
                ->has('content')
                ->has('grid'),
            );
    }

    public function test_tl_can_save_the_document(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.laporan.document.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'format' => 'html', 'content_html' => '<p>Laporan K3 diedit</p>',
        ])->assertRedirect();

        $record = K3DocumentRecord::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame('html', $record->format);
        $this->assertStringContainsString('Laporan K3 diedit', (string) $record->content_html);
        $this->assertSame('SMT-FM-AK3-00', $record->document_number);
    }

    public function test_manager_ul_can_view_but_not_save(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->create(['service_unit_id' => $serviceUnit->id]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('k3.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));

        $this->actingAs($manager)->post(route('k3.laporan.document.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'format' => 'html', 'content_html' => '<p>x</p>',
        ])->assertForbidden();

        $this->assertDatabaseCount('k3_document_records', 0);
    }

    public function test_the_document_exports_to_pdf(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $unit))
            ->get(route('k3.laporan.document.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_a_document_cannot_be_opened_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $ownUnit))
            ->get(route('k3.laporan.document.edit', ['unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026]))
            ->assertForbidden();
    }
}
