<?php

namespace Tests\Feature\Operasi;

use App\Enums\RoleName;
use App\Models\HarUnsafeCondition;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * The Unsafe Action & Unsafe Condition sheet saved at once (Tambah Baris +
 * Simpan) from Input Operasi and Input Pemeliharaan.
 */
class UnsafeConditionSheetTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        Storage::fake('public');
        $this->unit = Unit::factory()->create(['name' => 'PLTD Uji', 'is_active' => true]);
    }

    public function test_one_save_adds_edits_and_deletes_findings_and_skips_blank_rows(): void
    {
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $kept = $this->finding(['temuan' => 'Kabel terkelupas', 'sort_order' => 1]);
        $removed = $this->finding(['temuan' => 'Oli tercecer', 'sort_order' => 2]);
        $otherMonth = $this->finding(['temuan' => 'Bulan lain', 'month' => 9]);

        $this->sync($koordinator, 'operasi', [
            'rows' => [
                ['temuan' => 'Pagar pengaman lepas', 'kategori' => 'UNSAFE CONDITION', 'periode' => 'MINGGU KE - 2', 'lokasi' => 'Area tangki', 'keterangan' => 'open'],
                ['id' => $kept->id, 'temuan' => 'Kabel terkelupas di panel', 'kategori' => 'UNSAFE ACTION', 'periode' => 'MINGGU KE - 1', 'keterangan' => 'open', 'rekomendasi' => 'Ganti kabel'],
                ['temuan' => '', 'kategori' => 'UNSAFE CONDITION', 'periode' => 'MINGGU KE - 1', 'keterangan' => 'open'],
            ],
            'deleted_ids' => [$removed->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $rows = HarUnsafeCondition::query()->where('month', 8)->orderBy('sort_order')->get();
        $this->assertSame(['Pagar pengaman lepas', 'Kabel terkelupas di panel'], $rows->pluck('temuan')->all());
        $this->assertSame([1, 2], $rows->pluck('sort_order')->all());
        $this->assertSame('operasi', $rows[0]->source);
        $this->assertSame($koordinator->id, $rows[0]->input_by);
        $this->assertSame('UNSAFE ACTION', $rows[1]->kategori);
        $this->assertSame('Ganti kabel', $rows[1]->rekomendasi);
        $this->assertSame('har', $rows[1]->source, 'An edit keeps where the finding came from.');
        $this->assertModelMissing($removed);
        $this->assertModelExists($otherMonth);

        $this->actingAs($koordinator)->get(route('operasi.input.unsafe-condition.index', ['unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->component('operasi/input/unsafe-condition/index')
                ->has('rows', 2)
                ->where('summary.total', 2)
                ->where('summary.unsafe_action_count', 1));
    }

    public function test_the_rows_are_validated(): void
    {
        $koordinator = $this->userWithRole(RoleName::KoordinatorOperasi, $this->unit);
        $existing = $this->finding(['temuan' => 'Kabel terkelupas']);
        $otherUnit = $this->finding(['unit_id' => Unit::factory()->create()->id]);
        $row = ['kategori' => 'UNSAFE CONDITION', 'periode' => 'MINGGU KE - 1', 'keterangan' => 'open'];

        // A saved finding cannot lose its temuan; a new row with content needs one too.
        $this->sync($koordinator, 'operasi', ['rows' => [['id' => $existing->id, 'temuan' => ' '] + $row]])->assertSessionHasErrors('rows.0.temuan');
        $this->sync($koordinator, 'operasi', ['rows' => [['temuan' => '', 'lokasi' => 'Ruang panel'] + $row]])->assertSessionHasErrors('rows.0.temuan');
        // A finding of another unit (or month) is not touched.
        $this->sync($koordinator, 'operasi', ['rows' => [['id' => $otherUnit->id, 'temuan' => 'Diambil alih'] + $row]])->assertSessionHasErrors('rows.0.id');
        $this->sync($koordinator, 'operasi', ['rows' => [['temuan' => 'X', 'kategori' => 'UNSAFE'] + $row]])->assertSessionHasErrors('rows.0.kategori');
        $this->sync($koordinator, 'operasi', ['rows' => [['temuan' => 'X', 'keterangan' => 'selesai'] + $row]])->assertSessionHasErrors('rows.0.keterangan');

        $this->assertSame('Kabel terkelupas', $existing->fresh()->temuan);
        $this->assertSame(2, HarUnsafeCondition::query()->count());

        // Deleting an id of another unit does nothing.
        $this->sync($koordinator, 'operasi', ['rows' => [], 'deleted_ids' => [$otherUnit->id]])->assertSessionHasNoErrors();
        $this->assertModelExists($otherUnit);
    }

    public function test_photos_are_uploaded_kept_replaced_and_removed(): void
    {
        $koordinator = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $row = ['temuan' => 'Kabel terkelupas', 'kategori' => 'UNSAFE CONDITION', 'periode' => 'MINGGU KE - 1', 'keterangan' => 'open'];

        $this->sync($koordinator, 'har', ['rows' => [$row + [
            'foto_sebelum_file' => UploadedFile::fake()->image('sebelum.jpg'),
            'foto_sesudah_file' => UploadedFile::fake()->image('sesudah.jpg'),
        ]]])->assertSessionHasNoErrors();

        $finding = HarUnsafeCondition::query()->sole();
        $this->assertSame('har', $finding->source);
        Storage::disk('public')->assertExists([$finding->foto_sebelum, $finding->foto_sesudah]);
        [$sebelum, $sesudah] = [$finding->foto_sebelum, $finding->foto_sesudah];

        // A path the finding does not have is not adopted; the saved one is kept.
        $this->sync($koordinator, 'har', ['rows' => [['id' => $finding->id, 'foto_sebelum' => $sebelum, 'foto_sesudah' => 'har-unsafe/lain.jpg'] + $row]]);
        $finding->refresh();
        $this->assertSame($sebelum, $finding->foto_sebelum);
        $this->assertNull($finding->foto_sesudah);
        Storage::disk('public')->assertMissing($sesudah);

        // A new upload replaces the photo and its old file.
        $this->sync($koordinator, 'har', ['rows' => [['id' => $finding->id, 'foto_sebelum' => $sebelum, 'foto_sebelum_file' => UploadedFile::fake()->image('baru.jpg')] + $row]]);
        $finding->refresh();
        $this->assertNotSame($sebelum, $finding->foto_sebelum);
        Storage::disk('public')->assertExists($finding->foto_sebelum);
        Storage::disk('public')->assertMissing($sebelum);

        // Deleting the finding deletes its photo.
        $photo = $finding->foto_sebelum;
        $this->sync($koordinator, 'har', ['rows' => [], 'deleted_ids' => [$finding->id]])->assertSessionHasNoErrors();
        $this->assertSame(0, HarUnsafeCondition::query()->count());
        Storage::disk('public')->assertMissing($photo);
    }

    public function test_new_findings_reach_k3_and_closing_one_tells_its_reporter(): void
    {
        $reporter = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $closer = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $this->unit);
        $koordinatorK3 = $this->userWithRole(RoleName::KoordinatorK3, $this->unit);
        $row = ['temuan' => 'Kabel terkelupas di panel', 'kategori' => 'UNSAFE CONDITION', 'periode' => 'MINGGU KE - 1', 'lokasi' => 'Ruang panel', 'keterangan' => 'open'];

        $this->sync($reporter, 'har', ['rows' => [$row]])->assertSessionHasNoErrors();
        $this->assertSame(['Unsafe Condition baru · PLTD Uji'], $this->titles($koordinatorK3));

        // Saving the sheet again (no new row) announces nothing.
        $finding = HarUnsafeCondition::query()->sole();
        $this->sync($reporter, 'har', ['rows' => [['id' => $finding->id] + $row]])->assertSessionHasNoErrors();
        $this->assertCount(1, $this->titles($koordinatorK3));

        $this->sync($closer, 'har', ['rows' => [['id' => $finding->id, 'keterangan' => 'close'] + $row]])->assertSessionHasNoErrors();
        $this->assertSame(['Temuan Anda sudah ditutup'], $this->titles($reporter));

        // Saving it again while closed does not repeat it.
        $this->sync($closer, 'har', ['rows' => [['id' => $finding->id, 'keterangan' => 'close'] + $row]]);
        $this->assertCount(1, $this->titles($reporter));
    }

    public function test_only_writers_of_the_unit_save_the_sheet(): void
    {
        $payload = ['rows' => [['temuan' => 'Kabel terkelupas', 'kategori' => 'UNSAFE CONDITION', 'periode' => 'MINGGU KE - 1', 'keterangan' => 'open']]];

        $this->sync($this->userWithRole(RoleName::TeamLeaderOperasi, $this->unit), 'operasi', $payload)->assertForbidden();
        $this->sync($this->userWithRole(RoleName::KoordinatorOperasi, Unit::factory()->create()), 'operasi', $payload)->assertForbidden();
        $this->sync($this->userWithRole(RoleName::KoordinatorOperasi, $this->unit), 'har', $payload)->assertForbidden();
        $this->assertSame(0, HarUnsafeCondition::query()->count());

        $this->sync($this->userWithRole(RoleName::Operator, $this->unit), 'operasi', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, HarUnsafeCondition::query()->count());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sync(User $user, string $module, array $data): TestResponse
    {
        return $this->actingAs($user)->post(route("{$module}.input.unsafe-condition.sync"), [
            'unit_id' => $this->unit->id, 'month' => 8, 'year' => 2026, ...$data,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function finding(array $attributes = []): HarUnsafeCondition
    {
        return HarUnsafeCondition::query()->create([
            'unit_id' => $this->unit->id, 'year' => 2026, 'month' => 8, 'periode' => 'MINGGU KE - 1',
            'kategori' => 'UNSAFE CONDITION', 'temuan' => 'Temuan', 'keterangan' => 'open', 'sort_order' => 1, 'source' => 'har',
            ...$attributes,
        ]);
    }

    /**
     * @return list<string>
     */
    private function titles(User $user): array
    {
        return $user->notifications()->get()->map(fn ($n): string => $n->data['title'])->values()->all();
    }
}
