<?php

namespace App\Http\Controllers\Har;

use App\Http\Controllers\Controller;
use App\Models\HarDailyMeeting;
use App\Services\Har\DailyMeetingAttendance;
use App\Support\Indonesian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public attendance form of a Daily Meeting Pemeliharaan, opened by scanning
 * the meeting's QR code (/hadir/{token}). No login: attendees may come from
 * other companies. The secret token is the only key; the form closes when the
 * organiser closes the attendance.
 */
class MeetingAttendanceController extends Controller
{
    public function __construct(private readonly DailyMeetingAttendance $attendance) {}

    public function show(string $token): Response
    {
        $meeting = $this->find($token);

        return Inertia::render('public/absensi-meeting', [
            'meeting' => [
                'token' => $meeting->token,
                'unit' => $meeting->unit?->name,
                'acara' => $meeting->acara,
                'hari_tanggal' => Indonesian::dayName(Carbon::parse($meeting->tanggal->format('Y-m-d'))).', '.Indonesian::longDate(Carbon::parse($meeting->tanggal->format('Y-m-d'))),
                'waktu' => $meeting->waktu,
                'tempat' => $meeting->tempat,
                'dibuka' => $meeting->absensi_dibuka,
                'jumlah_hadir' => count($meeting->peserta),
            ],
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $meeting = $this->find($token);

        if (! $meeting->absensi_dibuka) {
            throw ValidationException::withMessages(['nama' => 'Absensi meeting ini sudah ditutup oleh penyelenggara.']);
        }

        $validated = $request->validate(DailyMeetingAttendance::rules(), DailyMeetingAttendance::messages());
        $attendee = $this->attendance->checkIn($meeting, $validated, 'qr');

        Inertia::flash('hadir', ['nama' => $attendee['nama'], 'hadir_pada' => $attendee['hadir_pada']]);

        return redirect()->route('har.absensi-meeting.show', $meeting->token);
    }

    private function find(string $token): HarDailyMeeting
    {
        return HarDailyMeeting::query()->with('unit:id,name')->where('token', $token)->firstOrFail();
    }
}
