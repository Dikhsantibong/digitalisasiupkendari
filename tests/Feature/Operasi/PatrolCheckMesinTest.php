<?php

namespace Tests\Feature\Operasi;

use App\Enums\AttendanceCodeType;
use App\Enums\RoleName;
use App\Enums\ScheduleGroupType;
use App\Http\Controllers\Operasi\PatrolCheckMesinController;
use App\Models\AttendanceCode;
use App\Models\Employee;
use App\Models\Machine;
use App\Models\OperasiPatrolCheckEntry;
use App\Models\OperasiPatrolCheckMesin;
use App\Models\OperasiPatrolCheckTemplate;
use App\Models\OperasiReportDocument;
use App\Models\Unit;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class PatrolCheckMesinTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_super_admin_can_view_patrol_check_mesin_page(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Containerized Poasia', 'is_active' => true]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.input.patrol-check-mesin.index', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'nama_mesin' => 'Cummins #8',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('operasi/input/patrol-check-mesin/index')
            ->has('unit')
            ->has('items', 29)
            ->where('daysInMonth', 31)
            ->where('filters.unit_id', $unit->id)
            ->where('filters.year', 2026)
            ->where('filters.month', 8)
            ->where('filters.nama_mesin', 'Cummins #8')
            ->where('items.0.peralatan', 'Lube Oil Line Pipe')
        );
    }

    public function test_unit_user_can_only_access_their_unit(): void
    {
        $unit1 = Unit::factory()->create(['name' => 'Unit 1', 'is_active' => true]);
        $unit2 = Unit::factory()->create(['name' => 'Unit 2', 'is_active' => true]);

        $user = $this->userWithRole(RoleName::KoordinatorOperasi, $unit1);

        // Accessing unit1 succeeds
        $response = $this->actingAs($user)->get(route('operasi.input.patrol-check-mesin.index', [
            'unit_id' => $unit1->id,
            'year' => 2026,
            'month' => 8,
        ]));
        $response->assertOk();

        // Accessing unit2 is forbidden
        $responseForbidden = $this->actingAs($user)->get(route('operasi.input.patrol-check-mesin.index', [
            'unit_id' => $unit2->id,
            'year' => 2026,
            'month' => 8,
        ]));
        $responseForbidden->assertForbidden();
    }

    public function test_can_save_and_retrieve_patrol_check_mesin(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['is_active' => true]);

        $payload = [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'nama_mesin' => 'Cummins #8',
            'items' => [
                [
                    'no' => 1,
                    'system' => 'LUBRICATING SYSTEM',
                    'peralatan' => 'Lube Oil Line Pipe',
                    'checks' => ['1' => 'N', '2' => 'N', '3' => 'T'],
                ],
                [
                    'no' => 2,
                    'system' => 'LUBRICATING SYSTEM',
                    'peralatan' => 'Oil Line STC',
                    'checks' => ['1' => 'N', '2' => 'N'],
                ],
            ],
            'shift_pagi' => ['1' => 'D', '2' => 'D', '3' => 'C'],
            'shift_sore' => ['1' => 'C', '2' => 'C', '3' => 'A'],
            'shift_malam' => ['1' => 'B', '2' => 'A', '3' => 'D'],
            'catatan' => 'Hari ke-3 ditemukan rembesan oli pada lube line.',
        ];

        $response = $this->actingAs($superAdmin)->post(route('operasi.input.patrol-check-mesin.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('toast.type', 'success');

        $this->assertDatabaseHas('operasi_patrol_check_mesins', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'nama_mesin' => 'Cummins #8',
            'catatan' => 'Hari ke-3 ditemukan rembesan oli pada lube line.',
        ]);

        $record = OperasiPatrolCheckMesin::where('unit_id', $unit->id)->first();
        $this->assertNotNull($record);
        $this->assertEquals('D', $record->shift_pagi['1'] ?? null);
        $this->assertEquals('N', $record->items[0]['checks']['1'] ?? null);
        $this->assertEquals('T', $record->items[0]['checks']['3'] ?? null);
    }

    public function test_can_download_pdf_patrol_check_mesin(): void
    {
        $superAdmin = $this->userWithRole(RoleName::SuperAdmin);
        $unit = Unit::factory()->create(['name' => 'PLTD Poasia', 'is_active' => true]);

        OperasiPatrolCheckMesin::create([
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'nama_mesin' => 'Cummins #8',
            'items' => [
                [
                    'no' => 1,
                    'system' => 'LUBRICATING SYSTEM',
                    'peralatan' => 'Lube Oil Line Pipe',
                    'checks' => ['1' => 'N'],
                ],
            ],
            'shift_pagi' => ['1' => 'D'],
            'shift_sore' => ['1' => 'C'],
            'shift_malam' => ['1' => 'B'],
            'catatan' => 'Kondisi normal',
            'input_by' => $superAdmin->id,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('operasi.input.patrol-check-mesin.pdf', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 8,
            'nama_mesin' => 'Cummins #8',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type') ?? '');
    }

    public function test_pltd_poasia_uses_checklist_format_with_two_shift_entries_tn_note_rule_and_no_signatures(): void
    {
        $unit = Unit::factory()->create(['code' => 'PLTD-POASIA', 'name' => 'PLTD Poasia', 'is_active' => true]);
        $poasiaTemplate = OperasiPatrolCheckTemplate::query()->where('code', 'poasia-checklist')->firstOrFail();
        $machine = Machine::factory()->forUnit($unit)->create([
            'name' => 'MIRRLEES #1',
            'is_active' => true,
            'patrol_check_template_id' => $poasiaTemplate->id,
        ]);
        $operator = $this->userWithRole(RoleName::Operator, $unit);
        $employee = Employee::factory()->forUnit($unit)->create([
            'user_id' => $operator->id,
            'name' => 'Muh. Zainuddin',
            'position' => 'Operator',
            'regu' => 'A',
            'is_active' => true,
        ]);
        $pagiCode = AttendanceCode::query()->firstOrCreate(
            ['code' => 'P'],
            ['label' => 'Pagi', 'type' => AttendanceCodeType::Shift, 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'hitung_hadir' => true, 'sort_order' => 1, 'is_active' => true],
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
            'work_date' => '2026-11-23',
            'attendance_code_id' => $pagiCode->id,
            'regu' => 'A',
        ]);

        // Even if an older monthly record exists for that machine, an assigned checklist template takes precedence.
        OperasiPatrolCheckMesin::query()->create([
            'unit_id' => $unit->id,
            'machine_id' => $machine->id,
            'nama_mesin' => $machine->name,
            'year' => 2026,
            'month' => 11,
            'format' => OperasiPatrolCheckMesin::FORMAT_MONTHLY,
            'items' => [['no' => 1, 'system' => 'OLD', 'peralatan' => 'Old Monthly Row', 'checks' => []]],
        ]);

        // 1. Opening Patrol Check on PLTD Poasia resolves checklist template (25 rows) and user's shift & operator name.
        $pageItems = [];
        $this->actingAs($operator)->get(route('operasi.input.patrol-check-mesin.index', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 11,
            'machine_id' => $machine->id,
            'tanggal' => '2026-11-23',
        ]))
            ->assertOk()
            ->assertInertia(function ($page) use (&$pageItems): void {
                $page->where('patrol_format', 'checklist')
                    ->where('patrol_meta.form_number', 'POA-OPR-CHK-01')
                    ->where('active_entry.shift', 'Shift A (Pagi)')
                    ->where('active_entry.operator_name', 'Muh. Zainuddin')
                    ->has('items', 25)
                    ->where('items.0.system', 'MESIN')
                    ->where('items.0.peralatan', 'Block Cam')
                    ->where('items.0.applicable.getar', true)
                    ->where('items.0.applicable.tek_level', false)
                    ->where('items.24.system', 'ALAT BANTU')
                    ->where('items.24.peralatan', 'COC Mirrlees 01/02/04/05');
                $pageItems = $page->toArray()['props']['items'];
            });

        // 2. Marking a row TN without a note must fail validation ("Kolom TN wajib diberi catatan/tindakan awal").
        $invalidItems = $pageItems;
        $invalidItems[0]['status'] = 'TN';
        $invalidItems[0]['catatan'] = '';

        $this->actingAs($operator)->post(route('operasi.input.patrol-check-mesin.store'), [
            'patrol_format' => 'checklist',
            'unit_id' => $unit->id,
            'machine_id' => $machine->id,
            'nama_mesin' => $machine->name,
            'tanggal' => '2026-11-23',
            'shift' => 'Pagi',
            'jam' => '08:30',
            'operator_name' => 'Andi Operator',
            'items' => $invalidItems,
            'catatan' => '',
        ])->assertSessionHasErrors('items.0.catatan');

        // 3. Submit 1st valid check in Shift Pagi (including TN note, eviden photo & masked inapplicable check).
        Storage::fake('public');
        $savedReportDoc = OperasiReportDocument::query()->create([
            'unit_id' => $unit->id,
            'report_code' => 'bulanan',
            'engine_id' => $machine->id,
            'month' => 11,
            'year' => 2026,
            'format' => 'html',
            'content_version' => 10,
            'content_html' => '<div class="op-section op-landscape" id="sec-4-12"><p>OLD PATROL CHECK</p></div><div class="page-break"></div><div class="op-section op-landscape" id="sec-4-13"><p>SEC 13</p></div>',
        ]);

        $validItems1 = $pageItems;
        $validItems1[0]['checked'] = ['getar' => true, 'bunyi' => true, 'suhu' => true, 'tek_level' => true, 'bocor_visual' => true];
        $validItems1[0]['status'] = 'TN';
        $validItems1[0]['catatan'] = 'Getaran slightly tinggi, monitoring lanjut';
        $validItems1[0]['eviden_file'] = UploadedFile::fake()->image('eviden-block-cam.jpg', 320, 240);
        // A Normal (N) row even if sent with a stray note gets cleared server-side.
        $validItems1[1]['status'] = 'N';
        $validItems1[1]['catatan'] = 'Harus dikosongkan karena Normal';
        foreach (array_slice($validItems1, 2) as $k => $row) {
            $validItems1[$k + 2]['status'] = 'N';
        }

        $this->actingAs($operator)->post(route('operasi.input.patrol-check-mesin.store'), [
            'patrol_format' => 'checklist',
            'unit_id' => $unit->id,
            'machine_id' => $machine->id,
            'nama_mesin' => $machine->name,
            'tanggal' => '2026-11-23',
            'shift' => 'Pagi',
            'jam' => '08:30',
            'operator_name' => 'Andi Operator',
            'items' => $validItems1,
            'catatan' => 'Segera lapor ke HAR jika berlanjut',
        ])->assertRedirect();

        // 4. Submit 2nd valid check in same Shift Pagi (min 2x per shift).
        $validItems2 = $pageItems;
        foreach ($validItems2 as $k => $row) {
            $validItems2[$k]['status'] = 'N';
        }
        $this->actingAs($operator)->post(route('operasi.input.patrol-check-mesin.store'), [
            'patrol_format' => 'checklist',
            'unit_id' => $unit->id,
            'machine_id' => $machine->id,
            'nama_mesin' => $machine->name,
            'tanggal' => '2026-11-23',
            'shift' => 'Pagi',
            'jam' => '13:30',
            'operator_name' => 'Andi Operator',
            'items' => $validItems2,
            'catatan' => 'Kembali normal setelah penyetelan',
        ])->assertRedirect();

        $parent = OperasiPatrolCheckMesin::query()->where('unit_id', $unit->id)->where('nama_mesin', $machine->name)->sole();
        $this->assertSame('checklist', $parent->format);
        $entries = OperasiPatrolCheckEntry::query()->where('patrol_check_mesin_id', $parent->id)->orderBy('jam')->get();
        $this->assertCount(2, $entries);
        // Shift & operator are resolved automatically from the logged-in user's shift schedule & employee profile.
        $this->assertSame('Shift A (Pagi)', $entries[0]->shift);
        $this->assertSame('Muh. Zainuddin', $entries[0]->operator_name);
        // Server enforces applicability mask: Block Cam tek_level stays false.
        $this->assertFalse($entries[0]->items[0]['checked']['tek_level']);
        $this->assertTrue($entries[0]->items[0]['checked']['getar']);
        $this->assertSame('TN', $entries[0]->items[0]['status']);
        $this->assertNotEmpty($entries[0]->items[0]['eviden']);
        Storage::disk('public')->assertExists($entries[0]->items[0]['eviden']);
        // Normal row has no follow-up note or eviden.
        $this->assertSame('', $entries[0]->items[1]['catatan']);
        $this->assertNull($entries[0]->items[1]['eviden']);

        // Saved Laporan Project document (#sec-4-12) is automatically updated with the new Patrol Check data & eviden.
        $savedReportDoc->refresh();
        $this->assertStringNotContainsString('OLD PATROL CHECK', (string) $savedReportDoc->content_html);
        $this->assertStringContainsString('Getaran slightly tinggi, monitoring lanjut', (string) $savedReportDoc->content_html);
        $this->assertStringContainsString('data:image/', (string) $savedReportDoc->content_html);

        // 5. PDF output succeeds and has logos, form code, embedded eviden, no signature section.
        $this->actingAs($operator)->get(route('operasi.input.patrol-check-mesin.pdf', [
            'unit_id' => $unit->id,
            'year' => 2026,
            'month' => 11,
            'machine_id' => $machine->id,
            'nama_mesin' => $machine->name,
        ]))->assertOk()->assertHeader('content-type', 'application/pdf');

        [, $pdfData] = app(PatrolCheckMesinController::class)
            ->pdfView($unit, 2026, 11, $machine->id, $machine->name);
        $rendered = view('operasi.input.patrol-check-mesin-pdf', $pdfData)->render();
        $this->assertStringContainsString('POA-OPR-CHK-01', $rendered);
        $this->assertStringContainsString('Block Cam', $rendered);
        $this->assertStringContainsString('chk-eviden-img', $rendered);
        $this->assertStringNotContainsString('Mengetahui,', $rendered);
        $this->assertStringNotContainsString('Koordinator Operasi', $rendered);

        // 6. Deleting all entries removes the parent record and eviden files too.
        $evidenPath = $entries[0]->items[0]['eviden'];
        foreach ($entries as $entry) {
            $this->actingAs($operator)
                ->delete(route('operasi.input.patrol-check-mesin.entry.destroy', $entry))
                ->assertRedirect();
        }
        Storage::disk('public')->assertMissing($evidenPath);
        $this->assertDatabaseCount('operasi_patrol_check_entries', 0);
        $this->assertDatabaseMissing('operasi_patrol_check_mesins', ['id' => $parent->id]);
    }
}
