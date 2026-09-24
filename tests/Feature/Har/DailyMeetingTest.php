<?php

namespace Tests\Feature\Har;

use App\Enums\EmployeePosition;
use App\Enums\RoleName;
use App\Http\Controllers\Har\DailyMeetingController;
use App\Models\Employee;
use App\Models\HarDailyMeeting;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

class DailyMeetingTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
    }

    public function test_the_page_lists_the_month_and_opens_a_new_meeting(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        HarDailyMeeting::factory()->create(['unit_id' => $unit->id, 'tanggal' => '2026-08-31', 'month' => 8, 'year' => 2026]);
        HarDailyMeeting::factory()->create(['unit_id' => $unit->id, 'tanggal' => '2026-07-15', 'month' => 7, 'year' => 2026]);

        $this->actingAs($this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit))
            ->get(route('har.formulir.daily-meeting.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('har/formulir/daily-meeting/index')
                ->has('meetings', 1)
                ->where('meetings.0.tanggal', '2026-08-31')
                ->where('meeting', null)
                ->where('options.min_rows', DailyMeetingController::MIN_ROWS),
            );
    }

    public function test_a_meeting_is_saved_with_attendees_and_photos_then_edited(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)
            ->post(route('har.formulir.daily-meeting.store'), [
                'unit_id' => $unit->id,
                'tanggal' => '2026-08-31',
                'acara' => 'Meeting HAR',
                'waktu' => '16.00',
                'tempat' => 'Containerized Poasia',
                'peserta' => [
                    ['nama' => 'Amirullah', 'asal' => 'PT MKP', 'jabatan' => 'Koord HAR'],
                    ['nama' => 'Aswadi', 'asal' => 'PT MKP', 'jabatan' => 'Operator'],
                    ['nama' => '', 'asal' => '', 'jabatan' => ''],
                ],
                'eviden' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $meeting = HarDailyMeeting::query()->sole();
        $this->assertSame(8, $meeting->month);
        $this->assertCount(2, $meeting->peserta);
        $this->assertCount(2, $meeting->eviden);
        Storage::disk('public')->assertExists($meeting->eviden[0]);

        // Edit: keep one photo, drop the other, change the acara.
        [$keptPhoto, $droppedPhoto] = $meeting->eviden;
        $this->actingAs($user)
            ->post(route('har.formulir.daily-meeting.store'), [
                'unit_id' => $unit->id,
                'id' => $meeting->id,
                'tanggal' => '2026-08-31',
                'acara' => 'Meeting HAR Mingguan',
                'peserta' => $meeting->peserta,
                'keep_eviden' => [$meeting->eviden[0], 'har-daily-meeting/lain/palsu.jpg'],
            ])
            ->assertSessionHasNoErrors();

        $meeting->refresh();
        $this->assertSame('Meeting HAR Mingguan', $meeting->acara);
        // The foreign path in keep_eviden is ignored; only the meeting's own photo survives.
        $this->assertSame([$keptPhoto], $meeting->eviden);
        Storage::disk('public')->assertExists($keptPhoto);
        Storage::disk('public')->assertMissing($droppedPhoto);
        $this->assertSame(1, HarDailyMeeting::query()->count());
    }

    public function test_the_pdf_prints_the_attendance_sheet_then_the_eviden_page(): void
    {
        Storage::fake('public');
        $unit = Unit::factory()->create(['is_active' => true, 'name' => 'PLTD Containerized Poasia 6 Site']);
        Employee::factory()->forUnit($unit)->create(['name' => 'Herwin Syahputra', 'position' => EmployeePosition::ProjectLeader->value]);
        Employee::factory()->forUnit($unit)->create(['name' => 'Amirullah', 'position' => EmployeePosition::KoordinatorPemeliharaan->value]);
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->post(route('har.formulir.daily-meeting.store'), [
            'unit_id' => $unit->id, 'tanggal' => '2026-08-31', 'acara' => 'Meeting HAR', 'waktu' => '16.00', 'tempat' => 'Containerized Poasia',
            'peserta' => [['nama' => 'Mudin', 'asal' => 'MKP', 'jabatan' => 'Operator']],
            'eviden' => [UploadedFile::fake()->image('eviden.jpg')],
        ])->assertSessionHasNoErrors();
        $meeting = HarDailyMeeting::query()->sole();

        [$view, $data] = app(DailyMeetingController::class)->pdfView($unit, collect([$meeting]));
        $html = view($view, $data)->render();
        $this->assertStringContainsString('DAFTAR HADIR MEETING PEMELIHARAAN PEMBANGKIT', $html);
        $this->assertStringContainsString('Senin / 31 Agustus 2026', $html);
        $this->assertStringContainsString('Mudin', $html);
        $this->assertStringContainsString('HERWIN SYAHPUTRA', strtoupper($html));
        $this->assertStringContainsString('Koordinator HAR', $html);
        $this->assertStringContainsString('EVIDEN FOTO MEETING PEMELIHARAAN PEMBANGKIT', $html);
        $this->assertStringContainsString('data:image/', $html);
        // 15 attendance rows at least.
        $this->assertSame(DailyMeetingController::MIN_ROWS, substr_count($html, 'class="ttd-no"'));

        $response = $this->actingAs($user)->get(route('har.formulir.daily-meeting.pdf', $meeting));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $reader = new Fpdi;
        // Lembar 1 (daftar hadir) + lembar 2 (eviden).
        $this->assertSame(2, $reader->setSourceFile(StreamReader::createByString((string) $response->getContent())));
    }

    public function test_photos_are_capped_and_required_fields_checked(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)
            ->post(route('har.formulir.daily-meeting.store'), [
                'unit_id' => $unit->id, 'tanggal' => '31-08-2026', 'acara' => '', 'peserta' => [],
            ])
            ->assertSessionHasErrors(['tanggal', 'acara']);

        $this->actingAs($user)
            ->post(route('har.formulir.daily-meeting.store'), [
                'unit_id' => $unit->id, 'tanggal' => '2026-08-31', 'acara' => 'Meeting', 'peserta' => [],
                'eviden' => array_map(fn (int $i): UploadedFile => UploadedFile::fake()->image("{$i}.jpg"), range(1, DailyMeetingController::MAX_EVIDEN + 1)),
            ])
            ->assertSessionHasErrors('eviden');
    }

    public function test_a_meeting_is_deleted_with_its_photos_and_other_units_are_protected(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('har-daily-meeting/1/foto.jpg', 'x');
        $unit = Unit::factory()->create(['is_active' => true]);
        $other = Unit::factory()->create(['is_active' => true]);
        $meeting = HarDailyMeeting::factory()->create(['unit_id' => $unit->id, 'eviden' => ['har-daily-meeting/1/foto.jpg']]);
        $foreign = HarDailyMeeting::factory()->create(['unit_id' => $other->id]);
        $user = $this->userWithRole(RoleName::TeamLeaderPemeliharaan, $unit);

        $this->actingAs($user)->delete(route('har.formulir.daily-meeting.destroy', $foreign))->assertForbidden();
        $this->actingAs($user)->delete(route('har.formulir.daily-meeting.destroy', $meeting))->assertRedirect();

        $this->assertModelMissing($meeting);
        Storage::disk('public')->assertMissing('har-daily-meeting/1/foto.jpg');
    }

    public function test_manager_ul_can_view_but_not_save(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true]);
        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);

        $this->actingAs($manager)
            ->get(route('har.formulir.daily-meeting.index', ['unit_id' => $unit->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can_write', false));
        $this->actingAs($manager)
            ->post(route('har.formulir.daily-meeting.store'), ['unit_id' => $unit->id, 'tanggal' => '2026-08-31', 'acara' => 'x', 'peserta' => []])
            ->assertForbidden();
    }
}
