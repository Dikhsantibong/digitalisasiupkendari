<?php

namespace Tests\Feature\K3;

use App\Enums\RoleName;
use App\Models\K3Attachment;
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

    public function test_a_role_without_k3_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Operator, Unit::factory()->create()))
            ->get(route('k3.input.attachment.index'))
            ->assertForbidden();
    }

    public function test_uploading_stores_the_file_and_row(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.input.attachment.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'title' => 'Foto Apel', 'category' => 'Keamanan',
            'file' => UploadedFile::fake()->image('apel.jpg'),
        ])->assertRedirect();

        $attachment = K3Attachment::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertSame('Foto Apel', $attachment->title);
        Storage::disk('public')->assertExists($attachment->file_path);
    }

    public function test_a_pdf_document_is_accepted(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.input.attachment.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'title' => 'Berita Acara', 'file' => UploadedFile::fake()->create('ba.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseHas('k3_attachments', ['title' => 'Berita Acara']);
    }

    public function test_deleting_removes_the_file(): void
    {
        $unit = Unit::factory()->create();
        $user = $this->userWithRole(RoleName::TeamLeaderK3, $unit);

        $this->actingAs($user)->post(route('k3.input.attachment.store'), [
            'unit_id' => $unit->id, 'month' => 8, 'year' => 2026,
            'title' => 'Hapus Saya', 'file' => UploadedFile::fake()->image('x.png'),
        ])->assertRedirect();

        $attachment = K3Attachment::query()->firstOrFail();
        $path = $attachment->file_path;

        $this->actingAs($user)->delete(route('k3.input.attachment.destroy', $attachment))->assertRedirect();

        $this->assertDatabaseMissing('k3_attachments', ['id' => $attachment->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_user_cannot_upload_for_a_foreign_unit(): void
    {
        $ownUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderK3, $ownUnit))
            ->post(route('k3.input.attachment.store'), [
                'unit_id' => $foreignUnit->id, 'month' => 8, 'year' => 2026,
                'title' => 'X', 'file' => UploadedFile::fake()->image('x.png'),
            ])
            ->assertForbidden();
    }
}
