<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Employee;
use App\Models\HarDailyMeeting;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkScheduleEntry;
use App\Notifications\ReminderNotification;
use App\Services\Operator\PresenceRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Scheduled reminders for each module's jadwal:
 * - a morning digest per module & unit of today's jadwal, once per day from each
 *   user's digest time (default 06:30 WITA until DIGEST_UNTIL_HOUR), to accounts
 *   with {module}.input.write at a unit they can access;
 * - personal & regu duty reminders (employee_id, regu A–D cells, or row shift):
 *   standby duties (on call / piket on call) remind once per day, while tasks
 *   requiring field input (5S5R, FLM, Patrol Check, etc.) remind at shift start
 *   (or digest_time) and repeat every {@see self::REPEAT_INTERVAL_MINUTES} minutes
 *   until the input is recorded.
 */
class ScheduleReminders
{
    /** No "today" digest is sent after this hour (WITA), e.g. when the server was down in the morning. */
    public const DIGEST_UNTIL_HOUR = 18;

    /** Cut-off hour (WITA) for repeating reminders of non-shift personal duties. */
    public const PERSONAL_UNTIL_HOUR = 22;

    /** Repeat interval (minutes) while an assigned input task has not been submitted yet. */
    public const REPEAT_INTERVAL_MINUTES = 120;

    private const BODY_LIMIT = 240;

    public function __construct(
        private ScheduleCatalog $catalog,
        private ReminderSender $sender,
        private ScheduleTaskTracker $tracker,
        private AttendanceReminders $attendance,
    ) {}

    /**
     * Returns how many reminders were sent.
     */
    public function run(Carbon $now): int
    {
        $local = $now->copy()->timezone(PresenceRecorder::TIMEZONE);
        $date = $local->copy()->startOfDay();
        $clock = $local->format('H:i');

        $eligibleUsers = User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => ! $user->isSuperAdmin() && $user->wantsNotification(NotificationCategory::Jadwal))
            ->values();

        if ($eligibleUsers->isEmpty()) {
            return 0;
        }

        $sent = 0;

        // 1. Morning module digest (until DIGEST_UNTIL_HOUR WITA).
        if ($local->hour < self::DIGEST_UNTIL_HOUR) {
            $dueDigestUsers = $eligibleUsers
                ->filter(fn (User $user): bool => $clock >= $user->digestTime())
                ->values();

            if ($dueDigestUsers->isNotEmpty()) {
                ['units' => $byUnit] = $this->plan($date);
                $unitNames = Unit::query()
                    ->whereIn('id', collect($byUnit)->flatMap(fn (array $units) => array_keys($units))->unique())
                    ->pluck('name', 'id');

                foreach ($byUnit as $module => $units) {
                    [$moduleLabel, $writePermission, , $hubRoute] = ScheduleCatalog::MODULES[$module];

                    foreach ($units as $unitId => $items) {
                        $recipients = $dueDigestUsers->filter(
                            fn (User $user): bool => $user->hasPermissionTo($writePermission) && $user->canAccessUnit($unitId),
                        );

                        if ($recipients->isEmpty()) {
                            continue;
                        }

                        $urls = collect($items)->pluck('url')->unique();
                        $reminder = new ReminderNotification(
                            NotificationCategory::Jadwal,
                            $module,
                            "Jadwal {$moduleLabel} hari ini · ".($unitNames[$unitId] ?? 'Unit'),
                            $this->digestBody($items),
                            $urls->count() === 1 ? $urls->first() : route($hubRoute, ['unit_id' => $unitId, 'month' => $date->month, 'year' => $date->year], false),
                            "jadwal:{$module}:{$unitId}:{$date->toDateString()}",
                        );

                        foreach ($recipients as $user) {
                            $sent += $this->sender->send($user, $reminder) ? 1 : 0;
                        }
                    }
                }
            }
        }

        // 2. Personal & regu duty reminders (shift-aware + periodic repeat until completed).
        $eligibleById = $eligibleUsers->keyBy('id');
        $shiftWindows = $this->shiftWindowsFor($eligibleUsers, $date);

        foreach ($this->catalog->tables() as $table) {
            $duties = $this->tracker->dutiesForTableOnDate($table, $date);

            foreach ($duties as [$recipient, $task]) {
                $user = $eligibleById->get($recipient->id);
                if (! $user instanceof User) {
                    continue;
                }

                // Standby duties (Piket On Call, On Call K3L): one reminder per day before DIGEST_UNTIL_HOUR.
                if (! $task['requires_input']) {
                    if ($local->hour >= self::DIGEST_UNTIL_HOUR || $clock < $user->digestTime()) {
                        continue;
                    }

                    $sent += $this->sender->send($user, new ReminderNotification(
                        NotificationCategory::Jadwal,
                        $task['module'],
                        "Hari ini Anda terjadwal: {$task['label']}",
                        "{$task['module_label']} · {$task['unit_name']} — {$task['title']}.",
                        $task['url'],
                        'jadwal-pribadi:'.$task['module'].':'.Str::slug($task['label']).':'.$date->toDateString(),
                    )) ? 1 : 0;

                    continue;
                }

                // Input-required duties stop immediately once field input / realisation is recorded.
                if ($task['completed']) {
                    continue;
                }

                [$windowStart, $windowEnd] = $shiftWindows[$user->id] ?? [
                    Carbon::parse($date->toDateString().' '.$user->digestTime(), PresenceRecorder::TIMEZONE),
                    $date->copy()->setTime(self::PERSONAL_UNTIL_HOUR, 0),
                ];

                if (! $local->betweenIncluded($windowStart, $windowEnd)) {
                    continue;
                }

                $slot = intdiv((int) $windowStart->diffInMinutes($local), self::REPEAT_INTERVAL_MINUTES);
                $baseKey = 'jadwal-pribadi:'.$task['module'].':'.Str::slug($task['label']).':'.$date->toDateString();
                $key = $slot === 0 ? $baseKey : "{$baseKey}:r{$slot}";

                if ($slot === 0) {
                    $title = $task['assignment_type'] === 'regu'
                        ? "Hari ini Regu {$task['regu']} terjadwal: {$task['label']}"
                        : "Hari ini Anda terjadwal: {$task['label']}";
                    $leaderInfo = $task['leader_name'] ? " Leader Shift: {$task['leader_name']}." : '';
                    $body = "{$task['module_label']} · {$task['unit_name']} — {$task['title']}.{$leaderInfo}";
                } else {
                    $title = "Pengingat: {$task['label']} belum diinput";
                    $body = "{$task['module_label']} · {$task['unit_name']} — {$task['title']} belum dilakukan penginputan. Segera isi sekarang.";
                }

                $sent += $this->sender->send($user, new ReminderNotification(
                    NotificationCategory::Jadwal,
                    $task['module'],
                    $title,
                    Str::limit($body, self::BODY_LIMIT),
                    $task['url'],
                    $key,
                )) ? 1 : 0;
            }
        }

        return $sent;
    }

    /**
     * Everything planned on a date: per module & unit, and per employee for
     * personal duties.
     *
     * @return array{
     *     units: array<string, array<int, list<array{label: string, title: string, url: string}>>>,
     *     personal: array<int, list<array{module: string, label: string, title: string, url: string, unit_id: int}>>
     * }
     */
    public function plan(Carbon $date): array
    {
        $units = [];
        $personal = [];

        foreach ($this->catalog->tables() as $table) {
            $key = $this->cellKey($table['period'], $table['match'] ?? 'day', $date);

            if ($key === null) {
                continue;
            }

            ['year' => $year, 'month' => $month] = ScheduleTaskTracker::periodFor($table, $date);
            $query = $table['model']::query()->where('year', $year);

            if ($table['period'] === 'month') {
                $query->where('month', $month);
            }

            if (isset($table['where'])) {
                ($table['where'])($query);
            }

            if (isset($table['with'])) {
                $query->with($table['with']);
            }

            foreach ($query->get() as $row) {
                $details = [];

                foreach ($table['cells'] as $column) {
                    $value = self::cellValue(data_get($row, $column), $key, $table['codes'] ?? [], $table['exclude_codes'] ?? []);

                    if ($value !== null) {
                        $details[] = str_starts_with($column, 'beban_') ? 'beban '.substr($column, 6).'%' : $value;
                    }
                }

                if ($details === []) {
                    continue;
                }

                $title = trim(($table['title'])($row)) ?: $table['label'];
                $shown = array_values(array_filter($details, fn (string $d): bool => $d !== '' && ! in_array(strtoupper($d), ['1', 'R', 'D', 'TRUE', 'X', 'V'], true)));
                $item = [
                    'label' => $table['label'],
                    'title' => $shown === [] ? $title : $title.' ('.implode(', ', $shown).')',
                    'url' => route($table['route'], [...($table['route_params'] ?? []), 'unit_id' => $row->unit_id, 'month' => $month, 'year' => $year], false),
                ];
                $units[$table['module']][$row->unit_id][] = $item;

                if (isset($table['person']) && $row->{$table['person']} !== null) {
                    $personal[(int) $row->{$table['person']}][] = [...$item, 'module' => $table['module'], 'unit_id' => (int) $row->unit_id];
                }
            }
        }

        // Daily Meeting Pemeliharaan (one dated record per meeting).
        HarDailyMeeting::query()->whereDate('tanggal', $date->toDateString())->get()->each(function (HarDailyMeeting $meeting) use (&$units, $date): void {
            $units['har'][$meeting->unit_id][] = [
                'label' => 'Daily Meeting',
                'title' => trim(implode(' · ', array_filter([$meeting->acara, $meeting->waktu, $meeting->tempat]))) ?: 'Daily Meeting',
                'url' => route('har.formulir.daily-meeting.index', ['unit_id' => $meeting->unit_id, 'month' => $date->month, 'year' => $date->year, 'meeting_id' => $meeting->id], false),
            ];
        });

        return ['units' => $units, 'personal' => $personal];
    }

    /**
     * The cell a date is planned in: the day of the month, the calendar date
     * (YYYY-MM-DD), the month of a yearly plan (on the 1st), or "{month}-{week}"
     * of a week plan (on the 1st, 8th, 15th and 22nd). Null when this date is
     * not a reminder date.
     */
    public function cellKey(string $period, string $match, Carbon $date): int|string|null
    {
        return ScheduleTaskTracker::cellKey($period, $match, $date);
    }

    /**
     * The value planned in a cell, '' for a plain tick, or null when the
     * cell is empty / excluded.
     *
     * @param  list<string>  $codes
     * @param  list<string>  $excludeCodes
     */
    public static function cellValue(mixed $cells, int|string $key, array $codes = [], array $excludeCodes = []): ?string
    {
        return ScheduleTaskTracker::cellValue($cells, $key, $codes, $excludeCodes);
    }

    /**
     * Active shift reminder windows [start - 30 min, end] for users with a
     * working shift on $date.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function shiftWindowsFor(Collection $users, Carbon $date): array
    {
        $employeesByUser = Employee::query()
            ->whereIn('user_id', $users->pluck('id')->all())
            ->where('is_active', true)
            ->pluck('user_id', 'id');

        if ($employeesByUser->isEmpty()) {
            return [];
        }

        $entries = WorkScheduleEntry::query()
            ->whereIn('employee_id', $employeesByUser->keys()->all())
            ->whereDate('work_date', $date->toDateString())
            ->whereNotNull('attendance_code_id')
            ->whereHas('attendanceCode', fn ($q) => $q->where('hitung_hadir', true)->whereNotNull('jam_mulai'))
            ->with('attendanceCode')
            ->get();

        $windows = [];
        foreach ($entries as $entry) {
            $userId = $employeesByUser->get($entry->employee_id);
            if ($userId === null || $entry->attendanceCode === null) {
                continue;
            }

            [$shiftStart, $shiftEnd] = $this->attendance->shiftWindow($date->toDateString(), $entry->attendanceCode);
            $windows[(int) $userId] = [
                $shiftStart->copy()->subMinutes(AttendanceReminders::BEFORE_START_MINUTES),
                $shiftEnd,
            ];
        }

        return $windows;
    }

    /**
     * "FLM: Shift A (A) · 5S 5R: Budi, Andi +2" within the push body limit.
     *
     * @param  list<array{label: string, title: string, url: string}>  $items
     */
    private function digestBody(array $items): string
    {
        $parts = collect($items)->groupBy('label')->map(function (Collection $group, string $label): string {
            $titles = $group->pluck('title')->unique()->values();
            $shown = $titles->take(3)->implode(', ');

            return $label.': '.$shown.($titles->count() > 3 ? ' +'.($titles->count() - 3) : '');
        });

        return Str::limit(count($items).' kegiatan — '.$parts->implode(' · '), self::BODY_LIMIT);
    }
}
