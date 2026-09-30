<?php

namespace App\Services\Har;

use App\Models\HarDailyMeeting;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Attendance of a Daily Meeting Pemeliharaan: the QR code of the public
 * attendance link and the check-in of one attendee (nama, asal perusahaan,
 * jabatan and a canvas signature stored as PNG on the public disk).
 */
class DailyMeetingAttendance
{
    /** Largest accepted signature PNG (decoded bytes). */
    public const MAX_SIGNATURE_BYTES = 1_500_000;

    /**
     * Validation rules of the attendance form (public QR form and manual entry).
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'asal' => ['required', 'string', 'max:150'],
            'jabatan' => ['required', 'string', 'max:150'],
            'ttd' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:2100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'nama.required' => 'Nama wajib diisi.',
            'asal.required' => 'Asal perusahaan wajib diisi.',
            'jabatan.required' => 'Jabatan wajib diisi.',
            'ttd.required' => 'Tanda tangan wajib dibuat pada kotak tanda tangan.',
            'ttd.starts_with' => 'Format tanda tangan tidak valid.',
        ];
    }

    /**
     * Check one attendee in. A name can sign only once per meeting.
     *
     * @param  array{nama: string, asal: string, jabatan: string, ttd: string}  $data
     * @return array<string, mixed> the stored attendee
     */
    public function checkIn(HarDailyMeeting $meeting, array $data, string $via): array
    {
        $nama = Str::squish($data['nama']);
        $this->ensureNotCheckedIn($meeting, $nama);

        $png = base64_decode(Str::after($data['ttd'], 'base64,'), true);
        if ($png === false || ! str_starts_with($png, "\x89PNG") || strlen($png) > self::MAX_SIGNATURE_BYTES) {
            throw ValidationException::withMessages(['ttd' => 'Tanda tangan tidak valid, silakan ulangi.']);
        }

        $path = "har-daily-meeting/{$meeting->unit_id}/ttd/".Str::random(40).'.png';
        Storage::disk('public')->put($path, $png);

        $attendee = [
            'uid' => (string) Str::ulid(),
            'nama' => $nama,
            'asal' => Str::squish($data['asal']),
            'jabatan' => Str::squish($data['jabatan']),
            'ttd' => $path,
            'hadir_pada' => now()->toIso8601String(),
            'via' => $via,
        ];

        // Many attendees scan at once: append under a row lock so nobody overwrites another check-in.
        try {
            DB::transaction(function () use ($meeting, $attendee, $nama): void {
                $fresh = HarDailyMeeting::query()->lockForUpdate()->findOrFail($meeting->id);
                $this->ensureNotCheckedIn($fresh, $nama);
                $fresh->forceFill(['peserta' => [...$fresh->peserta, $attendee]])->save();
                $meeting->setRawAttributes($fresh->getAttributes(), true);
            });
        } catch (ValidationException $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }

        return $attendee;
    }

    private function ensureNotCheckedIn(HarDailyMeeting $meeting, string $nama): void
    {
        $alreadyIn = collect($meeting->peserta)->contains(fn (array $p): bool => Str::lower(Str::squish((string) ($p['nama'] ?? ''))) === Str::lower($nama));

        if ($alreadyIn) {
            throw ValidationException::withMessages(['nama' => "{$nama} sudah tercatat hadir pada meeting ini."]);
        }
    }

    /**
     * Remove one attendee (and their signature file).
     */
    public function remove(HarDailyMeeting $meeting, string $uid): void
    {
        $removed = collect($meeting->peserta)->firstWhere('uid', $uid);
        abort_if($removed === null, 404);

        if (! empty($removed['ttd'])) {
            Storage::disk('public')->delete($removed['ttd']);
        }

        $meeting->forceFill(['peserta' => collect($meeting->peserta)->reject(fn (array $p): bool => ($p['uid'] ?? null) === $uid)->values()->all()])->save();
    }

    /**
     * Delete every signature file of the meeting (the meeting itself is being deleted).
     */
    public function forget(HarDailyMeeting $meeting): void
    {
        foreach ($meeting->peserta as $p) {
            if (! empty($p['ttd'])) {
                Storage::disk('public')->delete($p['ttd']);
            }
        }
    }

    public function url(HarDailyMeeting $meeting): string
    {
        return route('har.absensi-meeting.show', $meeting->token);
    }

    /**
     * The QR code (SVG markup) of the public attendance link.
     */
    public function qrSvg(HarDailyMeeting $meeting, int $size = 320): string
    {
        $svg = (new Writer(new ImageRenderer(
            new RendererStyle($size, 1, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(15, 23, 42))),
            new SvgImageBackEnd,
        )))->writeString($this->url($meeting));

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }
}
