<?php

namespace Tests\Feature\Har;

use App\Enums\RoleName;
use App\Http\Controllers\Har\DailyMeetingController;
use App\Models\HarDailyMeeting;
use App\Models\ServiceUnit;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithAccessControl;
use Tests\TestCase;

/**
 * Daily Meeting Pemeliharaan with QR attendance: the organiser creates the
 * meeting and shows its QR code; attendees sign in on the public form
 * (/hadir/{token}) with nama, asal perusahaan, jabatan and a canvas signature.
 */
class DailyMeetingAbsensiTest extends TestCase
{
    use InteractsWithAccessControl, RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedAccessControl();
        Storage::fake('public');
    }

    /**
     * @return array<string, string>
     */
    private function attendee(string $nama = 'Aswadi'): array
    {
        return ['nama' => $nama, 'asal' => 'PT MKP', 'jabatan' => 'Mekanik', 'ttd' => self::PNG];
    }

    public function test_creating_a_meeting_opens_its_qr_attendance(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $koordinator = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        $this->actingAs($koordinator)->post(route('har.formulir.daily-meeting.store'), [
            'unit_id' => $unit->id, 'tanggal' => '2026-08-31', 'acara' => 'Meeting HAR', 'waktu' => '08.00 - 09.00 WITA', 'tempat' => 'Ruang Meeting',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $meeting = HarDailyMeeting::query()->sole();
        $this->assertSame(32, strlen($meeting->token));
        $this->assertTrue($meeting->absensi_dibuka);
        $this->assertSame([], $meeting->peserta);

        $this->actingAs($koordinator)
            ->get(route('har.formulir.daily-meeting.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'meeting_id' => $meeting->id]))
            ->assertInertia(fn ($page) => $page
                ->component('har/formulir/daily-meeting/index')
                ->where('meeting.absensi_url', route('har.absensi-meeting.show', $meeting->token))
                ->where('meeting.qr_svg', fn (string $svg): bool => str_starts_with($svg, '<svg'))
                ->where('meeting.hari', 'Senin')
                ->where('meetings.0.absensi_dibuka', true));
    }

    public function test_the_project_leader_can_create_a_meeting(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);

        $this->actingAs($this->userWithRole(RoleName::ProjectLeaderOperasi, $unit))
            ->post(route('har.formulir.daily-meeting.store'), ['unit_id' => $unit->id, 'tanggal' => '2026-08-31', 'acara' => 'Meeting HAR'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, HarDailyMeeting::query()->count());
    }

    public function test_a_guest_signs_in_through_the_qr_form(): void
    {
        $meeting = HarDailyMeeting::factory()->create(['peserta' => []]);

        $this->get(route('har.absensi-meeting.show', $meeting->token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/absensi-meeting')
                ->where('meeting.acara', 'Meeting HAR')
                ->where('meeting.dibuka', true)
                ->where('meeting.jumlah_hadir', 0));

        $this->post(route('har.absensi-meeting.store', $meeting->token), $this->attendee())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('har.absensi-meeting.show', $meeting->token));

        $peserta = $meeting->fresh()->peserta;
        $this->assertCount(1, $peserta);
        $this->assertSame('Aswadi', $peserta[0]['nama']);
        $this->assertSame('qr', $peserta[0]['via']);
        $this->assertNotEmpty($peserta[0]['uid']);
        Storage::disk('public')->assertExists($peserta[0]['ttd']);
    }

    public function test_the_same_name_cannot_sign_twice_and_bad_input_is_rejected(): void
    {
        $meeting = HarDailyMeeting::factory()->create(['peserta' => []]);
        $url = route('har.absensi-meeting.store', $meeting->token);

        $this->post($url, $this->attendee())->assertSessionHasNoErrors();
        $this->post($url, $this->attendee('  aswadi '))->assertSessionHasErrors('nama');
        $this->post($url, ['nama' => '', 'asal' => '', 'jabatan' => '', 'ttd' => ''])->assertSessionHasErrors(['nama', 'asal', 'jabatan', 'ttd']);
        $this->post($url, [...$this->attendee('Budi'), 'ttd' => 'data:image/png;base64,'.base64_encode('not a png')])->assertSessionHasErrors('ttd');

        $this->assertCount(1, $meeting->fresh()->peserta);
        $this->assertCount(1, Storage::disk('public')->allFiles("har-daily-meeting/{$meeting->unit_id}/ttd"));
    }

    public function test_a_closed_attendance_and_an_unknown_token_accept_nobody(): void
    {
        $meeting = HarDailyMeeting::factory()->create(['peserta' => [], 'absensi_dibuka' => false]);

        $this->get(route('har.absensi-meeting.show', $meeting->token))->assertInertia(fn ($page) => $page->where('meeting.dibuka', false));
        $this->post(route('har.absensi-meeting.store', $meeting->token), $this->attendee())->assertSessionHasErrors('nama');
        $this->get(route('har.absensi-meeting.show', str_repeat('x', 32)))->assertNotFound();

        $this->assertSame([], $meeting->fresh()->peserta);
    }

    public function test_the_organiser_closes_the_attendance_adds_and_removes_attendees(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $meeting = HarDailyMeeting::factory()->create(['unit_id' => $unit->id, 'peserta' => []]);
        $koordinator = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        $this->actingAs($koordinator)->post(route('har.formulir.daily-meeting.peserta.store', $meeting), $this->attendee('Amirullah'))->assertSessionHasNoErrors();
        $peserta = $meeting->fresh()->peserta[0];
        $this->assertSame('manual', $peserta['via']);

        $this->actingAs($koordinator)->post(route('har.formulir.daily-meeting.absensi', $meeting), ['dibuka' => false])->assertRedirect();
        $this->assertFalse($meeting->fresh()->absensi_dibuka);

        $this->actingAs($koordinator)->delete(route('har.formulir.daily-meeting.peserta.destroy', [$meeting, $peserta['uid']]))->assertRedirect();
        $this->assertSame([], $meeting->fresh()->peserta);
        Storage::disk('public')->assertMissing($peserta['ttd']);

        $this->actingAs($koordinator)->delete(route('har.formulir.daily-meeting.peserta.destroy', [$meeting, 'unknown']))->assertNotFound();
    }

    public function test_other_units_and_view_only_roles_cannot_manage_the_attendance(): void
    {
        $serviceUnit = ServiceUnit::factory()->create();
        $unit = Unit::factory()->forServiceUnit($serviceUnit)->create(['is_active' => true]);
        $foreign = HarDailyMeeting::factory()->create();
        $meeting = HarDailyMeeting::factory()->create(['unit_id' => $unit->id]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->post(route('har.formulir.daily-meeting.absensi', $foreign), ['dibuka' => false])
            ->assertForbidden();

        $manager = $this->userWithRole(RoleName::ManagerUl, $serviceUnit);
        $this->actingAs($manager)->post(route('har.formulir.daily-meeting.absensi', $meeting), ['dibuka' => false])->assertForbidden();
        $this->actingAs($manager)->post(route('har.formulir.daily-meeting.peserta.store', $meeting), $this->attendee())->assertForbidden();
    }

    public function test_editing_the_details_keeps_the_attendance_and_photos(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $meeting = HarDailyMeeting::factory()->create(['unit_id' => $unit->id, 'peserta' => [], 'eviden' => ['har-daily-meeting/1/foto.jpg']]);
        $this->post(route('har.absensi-meeting.store', $meeting->token), $this->attendee());

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->post(route('har.formulir.daily-meeting.store'), ['unit_id' => $unit->id, 'id' => $meeting->id, 'tanggal' => '2026-08-30', 'acara' => 'Meeting Diubah', 'waktu' => '10.00 WITA'])
            ->assertSessionHasNoErrors();

        $meeting->refresh();
        $this->assertSame('Meeting Diubah', $meeting->acara);
        $this->assertCount(1, $meeting->peserta);
        $this->assertSame(['har-daily-meeting/1/foto.jpg'], $meeting->eviden);
    }

    public function test_older_attendees_get_an_id_when_the_meeting_is_opened(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $meeting = HarDailyMeeting::factory()->create(['unit_id' => $unit->id, 'peserta' => [['nama' => 'Lama', 'asal' => 'PT MKP', 'jabatan' => 'Operator']]]);

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->get(route('har.formulir.daily-meeting.index', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026, 'meeting_id' => $meeting->id]))
            ->assertInertia(fn ($page) => $page->where('meeting.peserta.0.nama', 'Lama')->where('meeting.peserta.0.uid', fn (?string $uid): bool => $uid !== null));

        $this->assertNotEmpty($meeting->fresh()->peserta[0]['uid']);
    }

    public function test_the_pdf_prints_the_signatures_and_the_month_export_works(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $meeting = HarDailyMeeting::factory()->create(['unit_id' => $unit->id, 'peserta' => []]);
        HarDailyMeeting::factory()->create(['unit_id' => $unit->id, 'tanggal' => '2026-08-10']);
        $this->post(route('har.absensi-meeting.store', $meeting->token), $this->attendee());
        $koordinator = $this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit);

        [$view, $data] = app(DailyMeetingController::class)->pdfView($unit, collect([$meeting->fresh()]));
        $html = view($view, $data)->render();
        $this->assertStringContainsString('alt="TTD Aswadi"', $html);
        $this->assertStringContainsString('src="data:image/png;base64,', $html);

        $this->actingAs($koordinator)->get(route('har.formulir.daily-meeting.pdf', $meeting))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($koordinator)
            ->get(route('har.formulir.daily-meeting.pdf-bulan', ['unit_id' => $unit->id, 'month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($koordinator)
            ->get(route('har.formulir.daily-meeting.pdf-bulan', ['unit_id' => $unit->id, 'month' => 1, 'year' => 2026]))
            ->assertNotFound();
    }

    public function test_deleting_a_meeting_removes_the_signatures(): void
    {
        $unit = Unit::factory()->create(['is_active' => true]);
        $meeting = HarDailyMeeting::factory()->create(['unit_id' => $unit->id, 'peserta' => []]);
        $this->post(route('har.absensi-meeting.store', $meeting->token), $this->attendee());
        $ttd = $meeting->fresh()->peserta[0]['ttd'];

        $this->actingAs($this->userWithRole(RoleName::KoordinatorPemeliharaan, $unit))
            ->delete(route('har.formulir.daily-meeting.destroy', $meeting))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($ttd);
        $this->get(route('har.absensi-meeting.show', $meeting->token))->assertNotFound();
    }
}
