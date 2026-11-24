<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\Machine;
use App\Models\OperasiPatrolCheckTemplate;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PatrolCheckTemplateTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_manages_patrol_check_templates_and_machine_links(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $machine = Machine::factory()->forUnit($unit)->create(['name' => 'Wärtsilä #1', 'is_active' => true]);
        $admin = $this->userWithRole(RoleName::SuperAdmin);

        $poasia = OperasiPatrolCheckTemplate::query()->where('code', 'poasia-checklist')->firstOrFail();
        $this->assertSame('checklist', $poasia->format);
        $this->assertCount(25, $poasia->items);

        // Create a copied template.
        $this->actingAs($admin)->post(route('operasi.patrol-check-template.store'), [
            'name' => 'SWD Kolaka Checklist',
            'copy_from' => $poasia->id,
        ])->assertRedirect();

        $created = OperasiPatrolCheckTemplate::query()->where('name', 'SWD Kolaka Checklist')->sole();
        $this->assertSame('checklist', $created->format);
        $this->assertCount(25, $created->items);

        // Update items, parameter flags, metadata, and link a machine.
        $this->actingAs($admin)->put(route('operasi.patrol-check-template.update', $created), [
            'name' => 'SWD Kolaka Checklist',
            'description' => 'Template patrol check SWD',
            'format' => 'checklist',
            'meta' => [
                'title' => 'FORM PATROL CHECK SWD',
                'form_number' => 'KLK-OPR-CHK-01',
                'revision' => 'Rev. 01',
                'instruction' => 'Minimal 2x per shift.',
            ],
            'items' => [
                [
                    'system' => 'MESIN',
                    'peralatan' => 'Turbocharger SWD',
                    'params' => ['getar' => true, 'bunyi' => true, 'suhu' => false, 'tek_level' => false, 'bocor_visual' => true],
                ],
            ],
            'machine_ids' => [$machine->id],
        ])->assertSessionHasNoErrors();

        $created->refresh();
        $this->assertSame('KLK-OPR-CHK-01', $created->resolvedMeta()['form_number']);
        $this->assertFalse($created->items[0]['params']['suhu']);
        $this->assertSame($created->id, $machine->fresh()->patrol_check_template_id);

        // Patrol Check page for that machine reads the assigned template.
        $this->actingAs($admin)->get(route('operasi.input.patrol-check-mesin.index', [
            'unit_id' => $unit->id,
            'machine_id' => $machine->id,
        ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('patrol_format', 'checklist')
                ->where('patrol_meta.form_number', 'KLK-OPR-CHK-01')
                ->has('items', 1)
                ->where('items.0.peralatan', 'Turbocharger SWD')
                ->where('items.0.applicable.suhu', false));

        // Default template cannot be deleted; custom template can and restores machine to default.
        $default = OperasiPatrolCheckTemplate::default();
        $this->actingAs($admin)->delete(route('operasi.patrol-check-template.destroy', $default))->assertStatus(422);

        $this->actingAs($admin)->delete(route('operasi.patrol-check-template.destroy', $created))->assertRedirect();
        $this->assertNull(OperasiPatrolCheckTemplate::query()->find($created->id));
        $this->assertNull($machine->fresh()->patrol_check_template_id);
    }

    public function test_koordinator_reads_but_cannot_modify_shared_patrol_check_templates(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $koord = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);
        $operator = $this->userWithRole(RoleName::Operator, $unit);
        $poasia = OperasiPatrolCheckTemplate::query()->where('code', 'poasia-checklist')->firstOrFail();

        $this->actingAs($koord)->get(route('operasi.patrol-check-template.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can_manage', false));

        $this->actingAs($koord)->post(route('operasi.patrol-check-template.store'), ['name' => 'Hack'])->assertForbidden();
        $this->actingAs($koord)->delete(route('operasi.patrol-check-template.destroy', $poasia))->assertForbidden();

        $this->actingAs($operator)->get(route('operasi.patrol-check-template.index'))->assertForbidden();
    }
}
