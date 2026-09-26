<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\HarUnsafeCondition;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class OperasiUnsafeConditionTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_unsafe_condition_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia', 'is_active' => true]);

        HarUnsafeCondition::query()->create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'periode' => 'MINGGU KE - 1',
            'kategori' => 'UNSAFE CONDITION',
            'temuan' => 'Kebocoran line pipa dan valve bbm',
            'kondisi' => 'Bocor',
            'tindak_lanjut' => 'dilakukan perbaikan dan penambalan',
            'rekomendasi' => 'Pengelasan line bbm yg bocor dan penggantian valve baru',
            'lokasi' => 'area samping cummins #6',
            'keterangan' => 'close',
            'sort_order' => 1,
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.input.unsafe-condition.index', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('operasi/input/unsafe-condition/index')
            ->has('unit')
            ->has('rows', 1)
            ->where('summary.total', 1)
            ->where('summary.unsafe_condition_count', 1)
            ->where('summary.close_count', 1)
            ->where('rows.0.temuan', 'Kebocoran line pipa dan valve bbm')
        );
    }

    public function test_can_store_update_and_delete_unsafe_condition(): void
    {
        Storage::fake('public');

        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);

        $fileSebelum = UploadedFile::fake()->image('sebelum.jpg');
        $fileSesudah = UploadedFile::fake()->image('sesudah.jpg');

        $payload = [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'periode' => 'MINGGU KE - 4',
            'kategori' => 'UNSAFE CONDITION',
            'temuan' => 'Cerobong gas buang engine miring',
            'kondisi' => 'Patah',
            'tindak_lanjut' => 'dilakukan perbaikan',
            'rekomendasi' => 'pengelasan cerobong dan penambahan plat',
            'lokasi' => 'cumm #6',
            'keterangan' => 'close',
            'foto_sebelum' => $fileSebelum,
            'foto_sesudah' => $fileSesudah,
        ];

        // Store
        $response = $this->actingAs($superAdmin)->post(route('operasi.input.unsafe-condition.store'), $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('har_unsafe_conditions', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'periode' => 'MINGGU KE - 4',
            'temuan' => 'Cerobong gas buang engine miring',
            'kondisi' => 'Patah',
            'keterangan' => 'close',
        ]);

        $record = HarUnsafeCondition::query()->where('unit_id', $unit->id)->firstOrFail();
        $this->assertNotNull($record->foto_sebelum);
        $this->assertNotNull($record->foto_sesudah);

        // Update
        $updatePayload = [
            'periode' => 'MINGGU KE - 4',
            'kategori' => 'UNSAFE ACTION',
            'temuan' => 'Temuan diubah',
            'kondisi' => 'Sudah diperbaiki',
            'tindak_lanjut' => 'Selesai perbaikan',
            'rekomendasi' => 'Tetap monitoring',
            'lokasi' => 'cumm #6',
            'keterangan' => 'close',
        ];

        $responseUpdate = $this->actingAs($superAdmin)->post(route('operasi.input.unsafe-condition.update', $record), $updatePayload);
        $responseUpdate->assertRedirect();

        $this->assertDatabaseHas('har_unsafe_conditions', [
            'id' => $record->id,
            'kategori' => 'UNSAFE ACTION',
            'temuan' => 'Temuan diubah',
        ]);

        // Destroy
        $responseDelete = $this->actingAs($superAdmin)->delete(route('operasi.input.unsafe-condition.destroy', $record));
        $responseDelete->assertRedirect();

        $this->assertDatabaseMissing('har_unsafe_conditions', [
            'id' => $record->id,
        ]);
    }

    public function test_can_download_pdf(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia', 'is_active' => true]);

        HarUnsafeCondition::query()->create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'periode' => 'MINGGU KE - 1',
            'kategori' => 'UNSAFE CONDITION',
            'temuan' => 'Pipa BBM bocor',
            'kondisi' => 'Bocor',
            'tindak_lanjut' => 'Perbaikan',
            'rekomendasi' => 'Ganti pipa',
            'lokasi' => 'Area #1',
            'keterangan' => 'open',
            'sort_order' => 1,
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.input.unsafe-condition.pdf', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
        ]));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_unit_user_cannot_access_other_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit1);

        $responseAllowed = $this->actingAs($user)->get(route('operasi.input.unsafe-condition.index', [
            'unit_id' => $unit1->id,
            'year' => 2026,
            'month' => 8,
        ]));
        $responseAllowed->assertOk();

        $responseForbidden = $this->actingAs($user)->get(route('operasi.input.unsafe-condition.index', [
            'unit_id' => $unit2->id,
            'year' => 2026,
            'month' => 8,
        ]));
        $responseForbidden->assertForbidden();
    }
}
