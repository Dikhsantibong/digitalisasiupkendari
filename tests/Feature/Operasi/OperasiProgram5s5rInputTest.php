<?php

namespace Tests\Feature\Operasi;

use App\Enums\AttendanceCodeType;
use App\Enums\RoleName;
use App\Enums\ScheduleGroupType;
use App\Http\Controllers\Operasi\Program5s5rInputController;
use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\OperasiProgram5s5rEvidence;
use App\Models\OperasiProgram5s5rItem;
use App\Models\Unit;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleEntry;
use App\Support\OperasiProgram5s5r;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class OperasiProgram5s5rInputTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function row(int $day, string $shift, string $program, array $values = []): array
    {
        return [...OperasiProgram5s5r::blankRow($day, $shift, $program), ...$values];
    }

    public function test_the_page_lists_every_day_and_shift_with_the_five_programs_and_empty_results(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $unit))
            ->get(route('operasi.input.program-5s5r.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('operasi/input/program-5s5r/index')
                ->where('has_saved', false)
                ->where('can_write', true)
                // August 2026 has 31 days × 3 shifts × 5 programs.
                ->has('days', 31)
                ->has('days.0.shifts', 3)
                ->has('days.0.shifts.0.rows', 5)
                ->where('days.0.shifts.0.shift', 'Pagi')
                ->where('days.0.shifts.0.rows.0.program', 'ringkas')
                ->where('days.0.shifts.0.rows.0.pic', OperasiProgram5s5r::DEFAULT_PIC)
                ->where('days.0.shifts.0.rows.4.detail', OperasiProgram5s5r::PROGRAMS['rajin']['detail'])
                // No pre-filled results.
                ->where('days.0.shifts.0.rows.0.kondisi_awal', null)
                ->where('days.0.shifts.0.rows.0.membersihkan', false)
                ->where('days.0.shifts.0.rows.0.progres', null),
            );
    }

    public function test_only_filled_rows_are_saved_per_shift_per_day_and_shown_over_the_template(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post(route('operasi.input.program-5s5r.store'), $period + ['rows' => [
                $this->row(1, 'Pagi', 'ringkas', ['kondisi_awal' => 'Baik', 'membersihkan' => true, 'merapikan' => true, 'progres' => '76-100', 'kondisi_akhir' => 'Baik', 'jumlah' => 2]),
                $this->row(1, 'Pagi', 'rapi'),
                $this->row(2, 'Sore', 'resik', ['keterangan' => 'Area genset dibersihkan shift sore']),
            ]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $items = OperasiProgram5s5rItem::query()->where('unit_id', $unit->id)->orderBy('day')->orderBy('shift')->get();
        $this->assertCount(2, $items);
        $this->assertSame(1, $items[0]->day);
        $this->assertSame('Pagi', $items[0]->shift);
        $this->assertTrue($items[0]->membersihkan);
        $this->assertFalse($items[0]->mengecat);
        $this->assertSame('76-100', $items[0]->progres);
        $this->assertSame(2, $items[1]->day);
        $this->assertSame('Sore', $items[1]->shift);

        $this->actingAs($user)
            ->get(route('operasi.input.program-5s5r.index', $period))
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->where('days.0.shifts.0.rows.0.saved', true)
                ->where('days.0.shifts.0.rows.0.jumlah', 2)
                ->where('days.0.shifts.0.rows.1.saved', false)
                ->where('days.1.shifts.1.rows.2.keterangan', 'Area genset dibersihkan shift sore'),
            );

        // Clearing a row removes it.
        $this->actingAs($user)
            ->post(route('operasi.input.program-5s5r.store'), $period + ['rows' => [$this->row(2, 'Sore', 'resik')]])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, OperasiProgram5s5rItem::query()->where('unit_id', $unit->id)->count());
    }

    public function test_evidence_photos_are_kept_replaced_and_capped_per_shift_per_day(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post(route('operasi.input.program-5s5r.store'), $period + [
                'rows' => [],
                'evidence' => ['1_Pagi' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]],
            ])
            ->assertSessionHasNoErrors();

        $photos = OperasiProgram5s5rEvidence::query()->where('day', 1)->where('shift', 'Pagi')->orderBy('sort_order')->get();
        $this->assertCount(2, $photos);
        Storage::disk('public')->assertExists($photos[0]->path);

        // Keep the first, drop the second, add three more: exceeds capped 3.
        $this->actingAs($user)
            ->post(route('operasi.input.program-5s5r.store'), $period + [
                'rows' => [],
                'keep_evidence' => ['1_Pagi' => [$photos[0]->id]],
                'evidence' => ['1_Pagi' => [UploadedFile::fake()->image('c.jpg'), UploadedFile::fake()->image('d.jpg'), UploadedFile::fake()->image('e.jpg')]],
            ])
            ->assertSessionHasErrors('evidence.1_Pagi');

        $this->actingAs($user)
            ->post(route('operasi.input.program-5s5r.store'), $period + [
                'rows' => [],
                'keep_evidence' => ['1_Pagi' => [$photos[0]->id]],
                'evidence' => ['1_Pagi' => [UploadedFile::fake()->image('c.jpg'), UploadedFile::fake()->image('d.jpg')]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, OperasiProgram5s5rEvidence::query()->where('day', 1)->where('shift', 'Pagi')->count());
        Storage::disk('public')->assertMissing($photos[1]->path);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorOperasi, $unit))
            ->post(route('operasi.input.program-5s5r.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [
                $this->row(1, 'Pagi', 'ringkas', ['progres' => '90%', 'kondisi_awal' => 'Mantap']),
                $this->row(32, 'Pagi', 'rapi'),
                $this->row(1, 'Subuh', 'rapi'),
                ['day' => 1, 'shift' => 'Pagi', 'program' => 'bersinar'],
            ]])
            ->assertSessionHasErrors(['rows.0.progres', 'rows.0.kondisi_awal', 'rows.1.day', 'rows.2.shift', 'rows.3.program']);
    }

    public function test_the_pdf_prints_the_saved_results_and_photos_per_shift_per_day(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Containerized Poasia']);
        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)->post(route('operasi.input.program-5s5r.store'), $period + [
            'rows' => [$this->row(12, 'Sore', 'ringkas', ['kondisi_awal' => 'Baik', 'mengecat' => true, 'progres' => '51-75', 'keterangan' => 'Pengecatan rak tools shift sore'])],
            'evidence' => ['12_Sore' => [UploadedFile::fake()->image('eviden.jpg')]],
        ])->assertSessionHasNoErrors();

        [$view, $data] = app(Program5s5rInputController::class)->pdfView($unit, 8, 2026);
        $html = view($view, $data)->render();
        $this->assertStringContainsString('JADWAL PROGRAM 5S 5R PENGOPERASIAN KIT', $html);
        $this->assertStringContainsString('PLTD CONTAINERIZED POASIA', $html);
        $this->assertStringContainsString('12 Agustus 2026', $html);
        $this->assertStringContainsString('Shift Sore', $html);
        $this->assertStringContainsString('Pengecatan rak tools shift sore', $html);
        $this->assertStringContainsString('✓', $html);
        $this->assertStringContainsString('data:image/', $html);

        $this->actingAs($user)
            ->get(route('operasi.input.program-5s5r.pdf', $period + ['download' => 1]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_unit_scoping_and_forbidden_access(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit1);

        $this->actingAs($user)
            ->get(route('operasi.input.program-5s5r.index', ['unit_id' => $unit1->id]))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('operasi.input.program-5s5r.index', ['unit_id' => $unit2->id]))
            ->assertForbidden();
    }

    public function test_resolves_user_shift_and_regu_from_work_schedule_entry_attendance_code(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::Operator, $unit);
        $employee = Employee::factory()->forUnit($unit)->create([
            'user_id' => $user->id,
            'name' => 'Budi Santoso',
            'regu' => 'A',
            'is_active' => true,
        ]);
        $soreCode = AttendanceCode::query()->firstOrCreate(
            ['code' => 'S'],
            ['label' => 'Sore', 'type' => AttendanceCodeType::Shift, 'jam_mulai' => '15:00', 'jam_selesai' => '23:00', 'hitung_hadir' => true, 'sort_order' => 2, 'is_active' => true],
        );
        $schedule = WorkSchedule::query()->create([
            'unit_id' => $unit->id,
            'group_type' => ScheduleGroupType::Shift,
            'year' => 2026,
            'month' => 11,
        ]);
        WorkScheduleEntry::query()->create([
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-11-24',
            'attendance_code_id' => $soreCode->id,
            'regu' => 'B',
        ]);

        $this->actingAs($user)
            ->get(route('operasi.input.program-5s5r.index', ['unit_id' => $unit->id, 'month' => 11, 'year' => 2026, 'day' => 24]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.shift', 'Sore')
                ->where('user_context.shift', 'Sore')
                ->where('user_context.operator_name', 'Budi Santoso')
                ->where('user_context.regu', 'Shift B'),
            );
    }
}
