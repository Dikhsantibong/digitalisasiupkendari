<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Models\MaintenanceAttachment;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class AttachmentInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        Storage::fake('public');
    }

    public function test_a_role_without_har_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('har.input.attachment.index'))
            ->assertForbidden();
    }

    public function test_uploading_a_photo_stores_the_file_and_record(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.attachment.store'), [
            'unit_id' => $unit->id,
            'month' => 8,
            'year' => 2026,
            'title' => 'Ganti bearing',
            'caption' => 'Sebelum',
            'photo' => UploadedFile::fake()->image('foto.jpg'),
        ])->assertRedirect();

        $attachment = MaintenanceAttachment::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame('Ganti bearing', $attachment->title);
        Storage::disk('public')->assertExists($attachment->photo_path);
        $this->assertDatabaseHas('report_periods', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]);
    }

    public function test_the_photo_is_required_and_must_be_an_image(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.attachment.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'title' => 'X',
        ])->assertSessionHasErrors('photo');

        $this->actingAs($user)->post(route('har.input.attachment.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'title' => 'X',
            'photo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('photo');
    }

    public function test_an_attachment_can_be_deleted_with_its_file(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.input.attachment.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'title' => 'X',
            'photo' => UploadedFile::fake()->image('foto.jpg'),
        ])->assertRedirect();

        $attachment = MaintenanceAttachment::query()->where('unit_id', $unit->id)->firstOrFail();
        $path = $attachment->photo_path;

        $this->actingAs($user)->delete(route('har.input.attachment.destroy', $attachment))->assertRedirect();

        $this->assertDatabaseMissing('maintenance_attachments', ['id' => $attachment->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_user_cannot_upload_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $ownUnit))
            ->post(route('har.input.attachment.store'), [
                'unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026, 'title' => 'X',
                'photo' => UploadedFile::fake()->image('foto.jpg'),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('maintenance_attachments', 0);
    }
}
