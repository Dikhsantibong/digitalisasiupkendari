<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\PermissionName;
use App\Models\AttendanceCode;
use App\Models\EmployeePresence;
use App\Models\User;
use App\Models\WorkScheduleEntry;
use App\Notifications\ReminderNotification;
use App\Services\Operator\PresenceRecorder;
use Illuminate\Support\Carbon;

/**
 * Absen reminders from Jadwal Shift (the plan) and the presensi, for the
 * account linked to each scheduled employee:
 * - 30 minutes before a working shift starts, when not checked in yet;
 * - from 15 minutes after the start, when still not checked in;
 * - from 15 minutes after the end, when checked in but not checked out;
 * - from 19:00 (WITA), the working shift of tomorrow.
 *
 * Every reminder has a per-shift key, so running this every few minutes sends
 * each one once. Only users who may take the absen (operator.presensi) and who
 * receive absen reminders get them.
 */
class AttendanceReminders
{
    public const BEFORE_START_MINUTES = 30;

    public const LATE_AFTER_MINUTES = 15;

    public const LATE_WINDOW_HOURS = 3;

    public const CHECKOUT_AFTER_MINUTES = 15;

    public const CHECKOUT_WINDOW_HOURS = 4;

    public const TOMORROW_FROM_HOUR = 19;

    public function __construct(private ReminderSender $sender) {}

    /**
     * Returns how many reminders were sent.
     */
    public function run(Carbon $now): int
    {
        $local = $now->copy()->timezone(PresenceRecorder::TIMEZONE);
        $today = $local->copy()->startOfDay();

        $entries = WorkScheduleEntry::query()
            ->whereDate('work_date', '>=', $today->copy()->subDay()->toDateString())
            ->whereDate('work_date', '<=', $today->copy()->addDay()->toDateString())
            ->whereNotNull('attendance_code_id')
            ->whereHas('attendanceCode', fn ($q) => $q->where('hitung_hadir', true)->whereNotNull('jam_mulai'))
            ->whereHas('employee', fn ($q) => $q->where('is_active', true)->whereNotNull('user_id'))
            ->with(['attendanceCode', 'employee.user'])
            ->get();

        $presences = EmployeePresence::query()
            ->whereIn('employee_id', $entries->pluck('employee_id')->unique())
            ->whereDate('work_date', '>=', $today->copy()->subDay()->toDateString())
            ->whereDate('work_date', '<=', $today->copy()->addDay()->toDateString())
            ->get()
            ->keyBy(fn (EmployeePresence $p): string => $p->employee_id.'|'.$p->work_date->toDateString());

        $sent = 0;

        foreach ($entries as $entry) {
            $user = $entry->employee->user;

            if (! $user instanceof User || ! $user->hasPermissionTo(PermissionName::OperatorPresensi)) {
                continue;
            }

            $code = $entry->attendanceCode;
            $date = $entry->work_date->toDateString();
            [$start, $end] = $this->shiftWindow($date, $code);
            $presence = $presences->get($entry->employee_id.'|'.$date);
            $shift = "Shift {$code->label} ({$start->format('H:i')}–{$end->format('H:i')})";

            $reminder = match (true) {
                $presence === null && $local->betweenIncluded($start->copy()->subMinutes(self::BEFORE_START_MINUTES), $start->copy()->subSecond()) => $this->reminder(
                    "absen-segera:{$date}",
                    "{$shift} segera dimulai",
                    'Jangan lupa absen masuk dari area kantor unit sebelum shift dimulai.',
                ),
                $presence === null && $local->betweenIncluded($start->copy()->addMinutes(self::LATE_AFTER_MINUTES), $start->copy()->addHours(self::LATE_WINDOW_HOURS)) => $this->reminder(
                    "absen-masuk:{$date}",
                    'Anda belum absen masuk',
                    "{$shift} sudah dimulai pukul {$start->format('H:i')}. Segera absen masuk; tanpa absen Anda tercatat tidak hadir.",
                ),
                $presence !== null && $presence->check_out_at === null && $local->betweenIncluded($end->copy()->addMinutes(self::CHECKOUT_AFTER_MINUTES), $end->copy()->addHours(self::CHECKOUT_WINDOW_HOURS)) => $this->reminder(
                    "absen-pulang:{$date}",
                    'Jangan lupa absen pulang',
                    "{$shift} sudah selesai pukul {$end->format('H:i')}, tetapi Anda belum absen pulang.",
                ),
                $date === $today->copy()->addDay()->toDateString() && $local->hour >= self::TOMORROW_FROM_HOUR => $this->reminder(
                    "shift-besok:{$date}",
                    "Besok Anda {$shift}",
                    $start->locale('id')->isoFormat('dddd, D MMMM YYYY').'. Absen masuk dibuka dari area kantor unit.',
                ),
                default => null,
            };

            if ($reminder !== null && $this->sender->send($user, $reminder)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Start and end (WITA) of a shift on a work date; an end at or before the
     * start (Sore until 24:00) falls on the next day.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function shiftWindow(string $date, AttendanceCode $code): array
    {
        $at = function (?string $time) use ($date): Carbon {
            [$hour, $minute] = array_map('intval', array_pad(explode(':', (string) $time), 2, '0'));

            return Carbon::parse($date, PresenceRecorder::TIMEZONE)->startOfDay()->addHours($hour)->addMinutes($minute);
        };

        $start = $at($code->jam_mulai);
        $end = $code->jam_selesai === null ? $start->copy()->addHours(8) : $at($code->jam_selesai);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    private function reminder(string $key, string $title, string $body): ReminderNotification
    {
        return new ReminderNotification(
            NotificationCategory::Absensi,
            'operator',
            $title,
            $body,
            route('operator.presensi.index', absolute: false),
            $key,
        );
    }
}
