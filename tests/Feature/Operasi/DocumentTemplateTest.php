<?php

namespace Tests\Feature\Operasi;

use App\Enums\BeritaAcaraType;
use App\Enums\RoleName;
use App\Models\Unit;
use App\Services\Operasi\BeritaAcaraBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class DocumentTemplateTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_a_role_without_manage_permission_is_forbidden(): void
    {
        $unit = Unit::factory()->create();

        // Operator lacks operasi.master.manage.
        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('operasi.document-template.index'))
            ->assertForbidden();
    }

    public function test_global_scope_lists_the_three_documents_with_defaults(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->get(route('operasi.document-template.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/document-template/index')
                ->where('scope.unit_id', null)
                ->has('templates', 3)
                ->where('templates.0.document_number', BeritaAcaraType::Hsd->defaultDocumentNumber()),
            );
    }

    public function test_saving_a_global_template_updates_the_default(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->put(route('operasi.document-template.update', BeritaAcaraType::Hsd->value), [
                'document_number' => '100/GLOBAL',
                'title' => 'BA HSD',
                'revision' => '01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('document_templates', [
            'type' => BeritaAcaraType::Hsd->value,
            'unit_id' => null,
            'document_number' => '100/GLOBAL',
            'revision' => '01',
        ]);
    }

    public function test_a_unit_override_takes_effect_in_the_built_document(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->put(route('operasi.document-template.update', BeritaAcaraType::Hsd->value), [
                'unit_id' => $unit->id,
                'document_number' => '777/UNIT',
                'title' => 'BA HSD Unit',
                'revision' => '00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('document_templates', [
            'type' => BeritaAcaraType::Hsd->value,
            'unit_id' => $unit->id,
            'document_number' => '777/UNIT',
        ]);

        $data = app(BeritaAcaraBuilder::class)->build($unit, BeritaAcaraType::Hsd, 8, 2026);
        $this->assertSame('777/UNIT', $data['document']['number']);
    }

    public function test_an_unknown_type_is_not_found(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $unit))
            ->put(route('operasi.document-template.update', 'tidak-ada'), [
                'document_number' => 'X',
                'title' => 'X',
                'revision' => '00',
            ])
            ->assertNotFound();
    }

    public function test_an_override_cannot_be_saved_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderOperasi, $ownUnit))
            ->put(route('operasi.document-template.update', BeritaAcaraType::Hsd->value), [
                'unit_id' => $foreignUnit->id,
                'document_number' => '999',
                'title' => 'X',
                'revision' => '00',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('document_templates', ['unit_id' => $foreignUnit->id]);
    }
}
