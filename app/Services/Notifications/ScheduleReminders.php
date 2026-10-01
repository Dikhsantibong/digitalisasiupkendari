<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Employee;
use App\Models\HarDailyMeeting;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\ReminderNotification;
use App\Services\Operator\PresenceRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The morning schedule reminders, sent once per day from each user's digest
 * time (default 06:30 WITA):
 * - a digest per module & unit of today's jadwal, to the accounts that run
 *   that module's jadwal ({module}.input.write) at a unit they can access;
 * - a personal reminder to the employee named on a duty row (piket, on call,
 *   patrol check) through the account linked to that employee.
 */
class ScheduleReminders
{
    /** No "today" digest is sent after this hour (WITA), e.g. when the server was down in the morning. */
    public const DIGEST_UNTIL_HOUR = 18;

    private const BODY_LIMIT = 240;

    public function __construct(private ScheduleCatalog $catalog, private ReminderSender $sender) {}

    /**
     * Returns how many reminders were sent.
     */
    public function run(Carbon $now): int
    {
        $local = $now->copy()->timezone(PresenceRecorder::TIMEZONE);

        if ($local->hour >= self::DIGEST_UNTIL_HOUR) {
            return 0;
        }

        $clock = $local->format('H:i');
        $due = User::query()->where('is_active', true)->get()
            ->filter(fn (User $user): bool => $clock >= $user->digestTime() && ! $user->isSuperAdmin() && $user->wantsNotification(NotificationCategory::Jadwal))
            ->values();

        if ($due->isEmpty()) {
            return 0;
        }

        $date = $local->copy()->startOfDay();
        ['units' => $byUnit, 'personal' => $personal] = $this->plan($date);
        $unitNames = Unit::query()->whereIn('id', collect($byUnit)->flatMap(fn (array $units) => array_keys($units))->merge(collect($personal)->flatten(1)->pluck('unit_id'))->unique())->pluck('name', 'id');
        $sent = 0;

        foreach ($byUnit as $module => $units) {
            [$moduleLabel, $writePermission, , $hubRoute] = ScheduleCatalog::MODULES[$module];

            foreach ($units as $unitId => $items) {
                $recipients = $due->filter(fn (User $user): bool => $user->hasPermissionTo($writePermission) && $user->canAccessUnit($unitId));

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

        $dueById = $due->keyBy('id');
        $accounts = Employee::query()->whereIn('id', array_keys($personal))->where('is_active', true)->whereNotNull('user_id')->pluck('user_id', 'id');

        foreach ($personal as $employeeId => $items) {
            $user = $dueById->get($accounts[$employeeId] ?? 0);

            if (! $user instanceof User) {
                continue;
            }

            foreach ($items as $item) {
                [$moduleLabel, , $viewPermission] = ScheduleCatalog::MODULES[$item['module']];
                $canOpen = $user->hasPermissionTo($viewPermission) && $user->canAccessUnit($item['unit_id']);

                $sent += $this->sender->send($user, new ReminderNotification(
                    NotificationCategory::Jadwal,
                    $item['module'],
                    "Hari ini Anda terjadwal: {$item['label']}",
                    "{$moduleLabel} · ".($unitNames[$item['unit_id']] ?? 'Unit').' — '.$item['title'].'.',
                    $canOpen ? $item['url'] : null,
                    'jadwal-pribadi:'.$item['module'].':'.Str::slug($item['label']).':'.$date->toDateString(),
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

            $query = $table['model']::query()->where('year', $date->year);

            if ($table['period'] === 'month') {
                $query->where('month', $date->month);
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
                    $value = self::cellValue($row->{$column}, $key, $table['codes'] ?? []);

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
                    'url' => route($table['route'], [...($table['route_params'] ?? []), 'unit_id' => $row->unit_id, 'month' => $date->month, 'year' => $date->year], false),
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
     * The cell a date is planned in: the day of the month, the month of a
     * yearly plan (on the 1st), or "{month}-{week}" of a week plan (on the
     * 1st, 8th, 15th and 22nd). Null when this date is not a reminder date.
     */
    public function cellKey(string $period, string $match, Carbon $date): int|string|null
    {
        if ($period === 'month') {
            return $date->day;
        }

        return match ($match) {
            'month' => $date->day === 1 ? $date->month : null,
            'week' => in_array($date->day, [1, 8, 15, 22], true) ? $date->month.'-'.(intdiv($date->day - 1, 7) + 1) : null,
            default => null,
        };
    }

    /**
     * The value planned in a cell, '' for a plain tick, or null when the
     * cell is empty. Cells are either a list of planned keys or a map of key →
     * code; `$codes` limits which codes count as planned.
     *
     * @param  list<string>  $codes
     */
    public static function cellValue(mixed $cells, int|string $key, array $codes = []): ?string
    {
        if (! is_array($cells) || $cells === []) {
            return null;
        }

        if (array_is_list($cells)) {
            foreach ($cells as $cell) {
                if (is_scalar($cell) && (string) $cell === (string) $key) {
                    return '';
                }
            }

            return null;
        }

        $value = $cells[(string) $key] ?? null;

        if ($value === null || $value === '' || $value === false || $value === 0 || $value === '0' || is_array($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($codes !== [] && ! in_array(strtoupper($value), $codes, true)) {
            return null;
        }

        return $value;
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
