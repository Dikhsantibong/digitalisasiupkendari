<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\HarDocumentRecord;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class DocumentTest extends TestCase
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
            ->get(route('har.laporan.document.edit'))
            ->assertForbidden();
    }

    public function test_regenerate_rebuilds_a_stale_saved_document_from_the_template(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        // A document saved with an old, hand-edited body (no cover, no sections).
        HarDocumentRecord::query()->create([
            'unit_id' => $unit->id, 'type' => 'bulanan', 'month' => 9, 'year' => 2026,
            'format' => 'html', 'content_html' => '<p>Isi lama</p>', 'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('har.laporan.document.regenerate'), ['unit_id' => $unit->id, 'month' => 9, 'year' => 2026])
            ->assertRedirect();

        $record = HarDocumentRecord::query()->where('unit_id', $unit->id)->where('month', 9)->firstOrFail();
        $this->assertStringNotContainsString('Isi lama', (string) $record->content_html);
        $this->assertStringContainsString('Executive Summary', (string) $record->content_html);
        $this->assertStringContainsString('har-cover', (string) $record->content_html);
        $this->assertStringContainsString('14. Lampiran', (string) $record->content_html);
    }

    public function test_regenerate_requires_write_permission(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->create(['service_unit_id' => $serviceUnit->id]);

        // Manager UL may view the report but not write it.
        $this->actingAs($this->userWithRole(RoleName::ManagerUl, $serviceUnit))
            ->post(route('har.laporan.document.regenerate'), ['unit_id' => $unit->id, 'month' => 9, 'year' => 2026])
            ->assertForbidden();
    }

    public function test_tl_pemeliharaan_sees_the_generated_document(): void
    {
        $unit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/laporan/document')
                ->where('document_number', 'FMKD-314-10.3.3')
                ->where('format', 'html')
                ->where('has_saved', false)
                ->where('can_write', true)
                ->has('content')
                ->has('grid'),
            );
    }

    public function test_tl_pemeliharaan_can_save_the_document(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.laporan.document.store'), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'format' => 'html',
            'content_html' => '<p>Laporan diedit</p>',
        ])->assertRedirect();

        $record = HarDocumentRecord::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame('html', $record->format);
        $this->assertStringContainsString('Laporan diedit', (string) $record->content_html);
        $this->assertSame('FMKD-314-10.3.3', $record->document_number);
    }

    public function test_a_saved_document_is_reloaded_on_next_open(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        // Save through the endpoint so the document is stamped with the current
        // template version (a document saved against the current layout reloads).
        $this->actingAs($user)->post(route('har.laporan.document.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'format' => 'html', 'content_html' => '<p>Versi tersimpan</p>',
        ])->assertRedirect();

        $this->actingAs($user)
            ->get(route('har.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->where('content', '<p>Versi tersimpan</p>'),
            );
    }

    public function test_manager_ul_can_view_but_not_save(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->create(['service_unit_id' => $serviceUnit->id]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('har.laporan.document.edit', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));

        $this->actingAs($manager)->post(route('har.laporan.document.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'format' => 'html', 'content_html' => '<p>x</p>',
        ])->assertForbidden();

        $this->assertDatabaseCount('har_document_records', 0);
    }

    public function test_the_document_exports_to_pdf(): void
    {
        $unit = Unit::factory()->create();

        $response = $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.laporan.document.pdf', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_a_document_cannot_be_opened_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $ownUnit))
            ->get(route('har.laporan.document.edit', ['unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026]))
            ->assertForbidden();
    }
}
