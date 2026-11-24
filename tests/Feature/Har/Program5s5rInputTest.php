<?php

namespace Tests\Feature\Har;

use App\Enums\AttendanceCodeType;
use App\Enums\RoleName;
use App\Enums\ScheduleGroupType;
use App\Http\Controllers\Har\Program5s5rController;
use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\HarProgram5s5rEvidence;
use App\Models\HarProgram5s5rItem;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleEntry;
use App\Support\HarProgram5s5r;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class Program5s5rInputTest extends TestCase
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
        return [...HarProgram5s5r::blankRow($day, $shift, $program), ...$values];
    }

    public function test_the_page_lists_every_day_and_shift_with_the_five_programs_and_empty_results(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->get(route('har.input.program-5s5r.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/input/program-5s5r/index')
                ->where('has_saved', false)
                ->where('can_write', true)
                // August 2026 has 31 days × 3 shifts × 5 programs.
                ->has('days', 31)
                ->has('days.0.shifts', 3)
                ->has('days.0.shifts.0.rows', 5)
                ->where('days.0.shifts.0.shift', 'Pagi')
                ->where('days.0.shifts.0.rows.0.program', 'ringkas')
                ->where('days.0.shifts.0.rows.0.pic', HarProgram5s5r::DEFAULT_PIC)
                ->where('days.0.shifts.0.rows.4.detail', HarProgram5s5r::PROGRAMS['rajin']['detail'])
                // No pre-filled results.
                ->where('days.0.shifts.0.rows.0.kondisi_awal', null)
                ->where('days.0.shifts.0.rows.0.membersihkan', false)
                ->where('days.0.shifts.0.rows.0.progres', null),
            );
    }

    public function test_only_filled_rows_are_saved_per_shift_per_day_and_shown_over_the_template(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post(route('har.input.program-5s5r.store'), $period + ['rows' => [
                $this->row(1, 'Pagi', 'ringkas', ['kondisi_awal' => 'Baik', 'membersihkan' => true, 'merapikan' => true, 'progres' => '76-100', 'kondisi_akhir' => 'Baik', 'jumlah' => 2]),
                $this->row(1, 'Pagi', 'rapi'),
                $this->row(3, 'Malam', 'resik', ['keterangan' => 'Area workshop dibersihkan shift malam']),
            ]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $items = HarProgram5s5rItem::query()->where('unit_id', $unit->id)->orderBy('day')->orderBy('shift')->get();
        $this->assertCount(2, $items);
        $this->assertSame(1, $items[0]->day);
        $this->assertSame('Pagi', $items[0]->shift);
        $this->assertTrue($items[0]->membersihkan);
        $this->assertFalse($items[0]->mengecat);
        $this->assertSame('76-100', $items[0]->progres);
        $this->assertSame(3, $items[1]->day);
        $this->assertSame('Malam', $items[1]->shift);

        $this->actingAs($user)
            ->get(route('har.input.program-5s5r.index', $period))
            ->assertInertia(fn ($page) => $page
                ->where('has_saved', true)
                ->where('days.0.shifts.0.rows.0.saved', true)
                ->where('days.0.shifts.0.rows.0.jumlah', 2)
                ->where('days.0.shifts.0.rows.1.saved', false)
                ->where('days.2.shifts.2.rows.2.keterangan', 'Area workshop dibersihkan shift malam'),
            );

        // Clearing a row removes it.
        $this->actingAs($user)
            ->post(route('har.input.program-5s5r.store'), $period + ['rows' => [$this->row(3, 'Malam', 'resik')]])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, HarProgram5s5rItem::query()->where('unit_id', $unit->id)->count());
    }

    public function test_evidence_photos_are_kept_replaced_and_capped_per_shift_per_day(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)
            ->post(route('har.input.program-5s5r.store'), $period + [
                'rows' => [],
                'evidence' => ['1_Pagi' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]],
            ])
            ->assertSessionHasNoErrors();

        $photos = HarProgram5s5rEvidence::query()->where('day', 1)->where('shift', 'Pagi')->orderBy('sort_order')->get();
        $this->assertCount(2, $photos);
        Storage::disk('public')->assertExists($photos[0]->path);

        // Keep the first, drop the second, add three more: capped at 3.
        $this->actingAs($user)
            ->post(route('har.input.program-5s5r.store'), $period + [
                'rows' => [],
                'keep_evidence' => ['1_Pagi' => [$photos[0]->id]],
                'evidence' => ['1_Pagi' => [UploadedFile::fake()->image('c.jpg'), UploadedFile::fake()->image('d.jpg'), UploadedFile::fake()->image('e.jpg')]],
            ])
            ->assertSessionHasErrors('evidence.1_Pagi');

        $this->actingAs($user)
            ->post(route('har.input.program-5s5r.store'), $period + [
                'rows' => [],
                'keep_evidence' => ['1_Pagi' => [$photos[0]->id]],
                'evidence' => ['1_Pagi' => [UploadedFile::fake()->image('c.jpg'), UploadedFile::fake()->image('d.jpg')]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, HarProgram5s5rEvidence::query()->where('day', 1)->where('shift', 'Pagi')->count());
        Storage::disk('public')->assertMissing($photos[1]->path);
    }

    public function test_invalid_values_are_rejected(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->post(route('har.input.program-5s5r.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => [
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
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $period = ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026];

        $this->actingAs($user)->post(route('har.input.program-5s5r.store'), $period + [
            'rows' => [$this->row(15, 'Pagi', 'ringkas', ['kondisi_awal' => 'Baik', 'mengecat' => true, 'progres' => '51-75', 'keterangan' => 'Pengecatan rak tools workshop'])],
            'evidence' => ['15_Pagi' => [UploadedFile::fake()->image('eviden.jpg')]],
        ])->assertSessionHasNoErrors();

        [$view, $data] = app(Program5s5rController::class)->pdfView($unit, 8, 2026);
        $html = view($view, $data)->render();
        $this->assertStringContainsString('JADWAL PROGRAM 5S 5R PEMELIHARAAN', $html);
        $this->assertStringContainsString('PLTD CONTAINERIZED POASIA', $html);
        $this->assertStringContainsString('AGUSTUS 2026', $html);
        $this->assertStringContainsString('15 Agustus 2026', $html);
        $this->assertStringContainsString('Shift Pagi', $html);
        $this->assertStringContainsString('Pengecatan rak tools workshop', $html);
        $this->assertStringContainsString('✓', $html);
        $this->assertStringContainsString('data:image/', $html);

        $this->actingAs($user)
            ->get(route('har.input.program-5s5r.pdf', $period + ['download' => 1]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_manager_ul_can_view_but_not_save_and_other_roles_are_forbidden(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('har.input.program-5s5r.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($manager)
            ->post(route('har.input.program-5s5r.store'), ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'rows' => []])
            ->assertForbidden();

        $this->actingAs($this->userWithRole(RoleName::Operator, $unit))
            ->get(route('har.input.program-5s5r.index', ['unit_id' => $unit->id]))
            ->assertForbidden();
    }

    public function test_resolves_user_shift_from_work_schedule_entry_attendance_code(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);
        $employee = Employee::factory()->forUnit($unit)->create([
            'user_id' => $user->id,
            'name' => 'Andi Har',
            'regu' => 'C',
            'is_active' => true,
        ]);
        $malamCode = AttendanceCode::query()->firstOrCreate(
            ['code' => 'M'],
            ['label' => 'Malam', 'type' => AttendanceCodeType::Shift, 'jam_mulai' => '23:00', 'jam_selesai' => '07:00', 'hitung_hadir' => true, 'sort_order' => 3, 'is_active' => true],
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
            'attendance_code_id' => $malamCode->id,
            'regu' => 'C',
        ]);

        $this->actingAs($user)
            ->get(route('har.input.program-5s5r.index', ['unit_id' => $unit->id, 'month' => 11, 'year' => 2026, 'day' => 24]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.shift', 'Malam')
                ->where('user_context.shift', 'Malam')
                ->where('user_context.operator_name', 'Andi Har')
                ->where('user_context.regu', 'Shift C'),
            );
    }
}
