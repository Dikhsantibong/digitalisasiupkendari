<?php

namespace App\Services\Notifications;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Employee;
use App\Models\HarDailyMeeting;
use App\Models\HarLembarRow;
use App\Models\K3ActivityPlan;
use App\Models\LogistikJadwalRow;
use App\Models\Operasi5s5rJadwal;
use App\Models\OperasiFlmJadwal;
use App\Models\OperasiFlmMonitoring;
use App\Models\OperasiMaterialPeralatan;
use App\Models\OperasiMeetingShiftJadwal;
use App\Models\OperasiProgram5s5rEvidence;
use App\Models\OperasiProgram5s5rItem;
use App\Models\OperatorMutasi;
use App\Models\PdmFormDocument;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkScheduleEntry;
use App\Services\Operator\PresenceRecorder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves personal and regu-level jadwal assignments for a date and tracks
 * whether required field inputs have been completed.
 *
 * Shared by {@see ScheduleReminders} (cron), {@see EventNotifier} (instant save),
 * and {@see HandleInertiaRequests} (live UI banners & cards).
 */
class ScheduleTaskTracker
{
    public function __construct(private readonly ScheduleCatalog $catalog) {}

    /**
     * All today's scheduled duties assigned to the given user (by employee_id or regu),
     * ordered with incomplete required inputs first.
     *
     * @return list<array{
     *     id: string,
     *     table_key: string,
     *     module: string,
     *     module_label: string,
     *     label: string,
     *     title: string,
     *     unit_id: int,
     *     unit_name: string,
     *     date: string,
     *     assignment_type: 'personal'|'regu',
     *     regu: string|null,
     *     leader_name: string|null,
     *     requires_input: bool,
     *     completed: bool,
     *     action_label: string|null,
     *     url: string|null,
     * }>
     */
    public function forUser(User $user, CarbonInterface $now): array
    {
        if (! $user->is_active || $user->isSuperAdmin() || $user->usesPortal() || $user->isReadOnly()) {
            return [];
        }

        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first(['id', 'unit_id', 'name', 'regu', 'is_shift_leader']);

        if ($employee === null || $employee->unit_id === null || ! $user->canAccessUnit($employee->unit_id)) {
            return [];
        }

        $date = Carbon::instance($now)->timezone(PresenceRecorder::TIMEZONE)->startOfDay();
        $unitId = (int) $employee->unit_id;
        $tasks = [];

        foreach ($this->catalog->tables() as $table) {
            if (! $this->supportsAssignee($table)) {
                continue;
            }

            foreach ($this->dutiesForTableOnDate($table, $date, $unitId) as [$recipient, $task]) {
                if ($recipient->id === $user->id) {
                    $tasks[] = $task;
                }
            }
        }

        return $this->deduplicateAndSort($tasks);
    }

    /**
     * Resolve all (User, task) assignments for one jadwal table on a calendar date.
     *
     * @param  array<string, mixed>  $table
     * @return list<array{0: User, 1: array{
     *     id: string,
     *     table_key: string,
     *     module: string,
     *     module_label: string,
     *     label: string,
     *     title: string,
     *     unit_id: int,
     *     unit_name: string,
     *     date: string,
     *     assignment_type: 'personal'|'regu',
     *     regu: string|null,
     *     leader_name: string|null,
     *     requires_input: bool,
     *     completed: bool,
     *     action_label: string|null,
     *     url: string|null,
     * }}>
     */
    public function dutiesForTableOnDate(array $table, CarbonInterface $date, ?int $unitId = null): array
    {
        if (! $this->supportsAssignee($table)) {
            return [];
        }

        $localDate = Carbon::instance($date)->timezone(PresenceRecorder::TIMEZONE)->startOfDay();
        $key = self::cellKey($table['period'], $table['match'] ?? 'day', $localDate);
        if ($key === null) {
            return [];
        }

        ['year' => $year, 'month' => $month] = self::periodFor($table, $localDate);

        $query = $table['model']::query()->where('year', $year);
        if ($table['period'] === 'month') {
            $query->where('month', $month);
        }
        if ($unitId !== null) {
            $query->where('unit_id', $unitId);
        }
        if (isset($table['where'])) {
            ($table['where'])($query);
        }
        if (isset($table['with'])) {
            $query->with($table['with']);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $matchedRows = [];
        $employeeIds = [];
        $reguUnitIds = [];

        foreach ($rows as $row) {
            $details = [];
            foreach ($table['cells'] as $column) {
                $value = self::cellValue(
                    data_get($row, $column),
                    $key,
                    $table['codes'] ?? [],
                    $table['exclude_codes'] ?? [],
                );

                if ($value !== null) {
                    $details[] = str_starts_with($column, 'beban_') ? 'beban '.substr($column, 6).'%' : $value;
                }
            }

            if ($details === []) {
                continue;
            }

            $personColumn = $table['person'] ?? null;
            $employeeId = $personColumn !== null && $row->{$personColumn} !== null ? (int) $row->{$personColumn} : null;

            $reguCodes = [];
            if (! empty($table['regu_cell'])) {
                foreach ($details as $detail) {
                    $reguCodes = [...$reguCodes, ...ScheduleCatalog::extractReguCodes($detail)];
                }
            }
            if ($reguCodes === [] && $employeeId === null && isset($table['regu_column'])) {
                $reguCodes = ScheduleCatalog::extractReguCodes((string) ($row->{$table['regu_column']} ?? ''));
            }
            $reguCodes = array_values(array_unique($reguCodes));

            if ($employeeId === null && $reguCodes === []) {
                continue;
            }

            if ($employeeId !== null) {
                $employeeIds[] = $employeeId;
            }
            if ($reguCodes !== []) {
                $reguUnitIds[] = (int) $row->unit_id;
            }

            $matchedRows[] = [
                'row' => $row,
                'details' => $details,
                'employee_id' => $employeeId,
                'regu_codes' => $reguCodes,
            ];
        }

        if ($matchedRows === []) {
            return [];
        }

        $allUnitIds = collect($matchedRows)->pluck('row.unit_id')->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $unitNames = Unit::query()->whereIn('id', $allUnitIds)->pluck('name', 'id');

        $employeesById = $employeeIds === []
            ? collect()
            : Employee::query()
                ->whereIn('id', array_values(array_unique($employeeIds)))
                ->where('is_active', true)
                ->whereNotNull('user_id')
                ->with('user')
                ->get()
                ->keyBy('id');

        $reguEmployeesByUnitAndCode = $reguUnitIds === []
            ? collect()
            : Employee::query()
                ->whereIn('unit_id', array_values(array_unique($reguUnitIds)))
                ->where('is_active', true)
                ->whereNotNull('regu')
                ->where('regu', '!=', '')
                ->with('user')
                ->get()
                ->groupBy(fn (Employee $e): string => $e->unit_id.'|'.strtoupper(trim((string) $e->regu)));

        [$moduleLabel] = ScheduleCatalog::MODULES[$table['module']];
        $requiresInput = (bool) ($table['requires_input'] ?? false);
        $completionCache = [];
        $duties = [];
        $seenAssignment = [];

        foreach ($matchedRows as $entry) {
            /** @var Model $row */
            $row = $entry['row'];
            $rowUnitId = (int) $row->unit_id;
            $unitName = (string) ($unitNames[$rowUnitId] ?? 'Unit');
            $rawTitle = trim(($table['title'])($row)) ?: $table['label'];
            $shown = array_values(array_filter(
                $entry['details'],
                fn (string $d): bool => $d !== '' && ! in_array(strtoupper($d), ['1', 'R', 'D', 'TRUE', 'X', 'V'], true),
            ));
            $title = $shown === [] ? $rawTitle : $rawTitle.' ('.implode(', ', $shown).')';

            if ($entry['employee_id'] !== null) {
                /** @var Employee|null $employee */
                $employee = $employeesById->get($entry['employee_id']);
                $user = $employee?->user;

                if ($user instanceof User && $this->canAssignToUser($user, $rowUnitId)) {
                    $regu = $employee->regu !== null && trim($employee->regu) !== '' ? strtoupper(trim($employee->regu)) : null;
                    $dedup = $table['key'].'|'.$rowUnitId.'|'.$user->id.'|'.$title;
                    if (! isset($seenAssignment[$dedup])) {
                        $seenAssignment[$dedup] = true;
                        $completed = $this->checkCompleted(
                            $table['key'],
                            $row,
                            $user,
                            $employee,
                            $regu,
                            $rowUnitId,
                            $localDate,
                            $requiresInput,
                            $completionCache,
                        );

                        $duties[] = [
                            $user,
                            $this->buildTaskPayload(
                                $table,
                                $moduleLabel,
                                $title,
                                $rowUnitId,
                                $unitName,
                                $localDate,
                                'personal',
                                $regu,
                                null,
                                $requiresInput,
                                $completed,
                                $user,
                            ),
                        ];
                    }
                }

                continue;
            }

            foreach ($entry['regu_codes'] as $reguCode) {
                /** @var Collection<int, Employee> $members */
                $members = $reguEmployeesByUnitAndCode->get($rowUnitId.'|'.$reguCode, collect());
                $leader = $members->first(fn (Employee $e): bool => (bool) $e->is_shift_leader);
                $leaderName = $leader?->name;

                foreach ($members as $member) {
                    $user = $member->user;
                    if (! $user instanceof User || ! $this->canAssignToUser($user, $rowUnitId)) {
                        continue;
                    }

                    $reguTitle = str_contains($title, $reguCode) ? $title : "{$title} (Regu {$reguCode})";
                    $dedup = $table['key'].'|'.$rowUnitId.'|'.$user->id.'|'.$reguTitle;
                    if (isset($seenAssignment[$dedup])) {
                        continue;
                    }
                    $seenAssignment[$dedup] = true;

                    $completed = $this->checkCompleted(
                        $table['key'],
                        $row,
                        $user,
                        $member,
                        $reguCode,
                        $rowUnitId,
                        $localDate,
                        $requiresInput,
                        $completionCache,
                    );

                    $duties[] = [
                        $user,
                        $this->buildTaskPayload(
                            $table,
                            $moduleLabel,
                            $reguTitle,
                            $rowUnitId,
                            $unitName,
                            $localDate,
                            'regu',
                            $reguCode,
                            $leaderName,
                            $requiresInput,
                            $completed,
                            $user,
                        ),
                    ];
                }
            }
        }

        return $duties;
    }

    /**
     * Resolve period (year, month) for calendar date, accounting for cut-off
     * tables (16th previous month through 15th selected month).
     *
     * @param  array<string, mixed>  $table
     * @return array{year: int, month: int}
     */
    public static function periodFor(array $table, CarbonInterface $date): array
    {
        $local = Carbon::instance($date)->timezone(PresenceRecorder::TIMEZONE);

        if (! empty($table['cutoff']) && $local->day >= 16) {
            $target = $local->copy()->addMonthNoOverflow();

            return ['year' => $target->year, 'month' => $target->month];
        }

        return ['year' => $local->year, 'month' => $local->month];
    }

    /**
     * The cell key a date is planned in.
     */
    public static function cellKey(string $period, string $match, CarbonInterface $date): int|string|null
    {
        $local = Carbon::instance($date)->timezone(PresenceRecorder::TIMEZONE);

        if ($match === 'date') {
            return $local->toDateString();
        }

        if ($match === 'w_week') {
            return in_array($local->day, [1, 8, 15, 22], true)
                ? 'w'.(intdiv($local->day - 1, 7) + 1)
                : null;
        }

        if ($match === 'mw_week') {
            return in_array($local->day, [1, 8, 15, 22], true)
                ? 'm'.$local->month.'w'.(intdiv($local->day - 1, 7) + 1)
                : null;
        }

        if ($period === 'month') {
            return $local->day;
        }

        return match ($match) {
            'month' => $local->day === 1 ? $local->month : null,
            'week' => in_array($local->day, [1, 8, 15, 22], true) ? $local->month.'-'.(intdiv($local->day - 1, 7) + 1) : null,
            default => null,
        };
    }

    /**
     * The value planned in a cell, '' for a plain tick, or null when empty / excluded.
     *
     * @param  list<string>  $codes
     * @param  list<string>  $excludeCodes
     */
    public static function cellValue(mixed $cells, int|string $key, array $codes = [], array $excludeCodes = []): ?string
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
        $upper = strtoupper($value);

        if ($codes !== [] && ! in_array($upper, array_map('strtoupper', $codes), true)) {
            return null;
        }

        if ($excludeCodes !== [] && in_array($upper, array_map('strtoupper', $excludeCodes), true)) {
            return null;
        }

        return $value;
    }

    /**
     * Check whether the user is eligible to receive schedule task reminders / cards.
     */
    private function canAssignToUser(User $user, int $unitId): bool
    {
        return $user->is_active
            && ! $user->isSuperAdmin()
            && ! $user->usesPortal()
            && ! $user->isReadOnly()
            && $user->canAccessUnit($unitId);
    }

    /**
     * @param  array<string, mixed>  $table
     */
    private function supportsAssignee(array $table): bool
    {
        return isset($table['person']) || ! empty($table['regu_cell']) || isset($table['regu_column']);
    }

    /**
     * @param  array<string, bool>  $cache
     */
    private function checkCompleted(
        string $tableKey,
        Model $row,
        User $user,
        Employee $employee,
        ?string $regu,
        int $unitId,
        Carbon $date,
        bool $requiresInput,
        array &$cache,
    ): bool {
        if (! $requiresInput) {
            return false;
        }

        $cacheKey = implode('|', [
            $tableKey,
            $unitId,
            $date->toDateString(),
            $row->getKey() ?? '0',
            $user->id,
            $regu ?? '-',
        ]);

        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        return $cache[$cacheKey] = $this->computeCompleted($tableKey, $row, $user, $employee, $regu, $unitId, $date);
    }

    private function computeCompleted(
        string $tableKey,
        Model $row,
        User $user,
        Employee $employee,
        ?string $regu,
        int $unitId,
        Carbon $date,
    ): bool {
        $year = $date->year;
        $month = $date->month;
        $day = $date->day;
        $dateString = $date->toDateString();

        return match ($tableKey) {
            'operasi:5s-5r' => $this->isOperasi5s5rCompleted($row, $user, $employee, $regu, $unitId, $year, $month, $day, $dateString),
            'operasi:flm' => $this->isOperasiFlmCompleted($regu, $user, $unitId, $year, $month, $day, $dateString),
            'operasi:meeting-shift' => $this->isOperasiMeetingShiftCompleted($row, $regu, $unitId, $year, $month, $day, $dateString),
            'operasi:inventarisasi-tools' => $this->isOperasiInventarisCompleted($row, $unitId, $year, $month, $day, $dateString),
            'har:patrol-check' => $this->isHarPatrolCompleted($row, $unitId, $year, $month, $day),
            'har:meeting-pemeliharaan' => $this->isHarMeetingCompleted($row, $unitId, $day, $dateString),
            'pdm:patrol-check' => $this->isPdmPatrolCompleted($unitId, $year, $month, $dateString),
            'pdm:5s-5r' => $this->isPdm5s5rCompleted($row, $unitId, $year, $month, $day, $dateString),
            'pdm:meeting' => isset($row->realisasi) && self::cellValue($row->realisasi, $day) !== null,
            'k3:patrol-check', 'k3:pekerjaan-rutin' => isset($row->realisasi) && self::cellValue($row->realisasi, $day) !== null,
            'k3:time-frame' => $row instanceof K3ActivityPlan && self::cellValue($row->real_days, $day) !== null,
            'logistik:5s5r' => $this->isLogistikSheetCompleted($row, 'inspeksi-5s5r', $unitId, $year, $month, $day, $dateString, checkDayCell: false),
            'logistik:piket-patrol-check' => $this->isLogistikSheetCompleted($row, 'patrol-check', $unitId, $year, $month, $day, $dateString, checkDayCell: true),
            'logistik:meeting', 'logistik:pemeliharaan', 'logistik:inventarisasi' => $row instanceof LogistikJadwalRow && strtoupper((string) self::cellValue($row->days, $day)) === 'D',
            default => false,
        };
    }

    private function isHarMeetingCompleted(Model $row, int $unitId, int $day, string $dateString): bool
    {
        if (isset($row->realisasi) && self::cellValue($row->realisasi, $day) !== null) {
            return true;
        }

        return HarDailyMeeting::query()
            ->where('unit_id', $unitId)
            ->whereDate('tanggal', $dateString)
            ->exists();
    }

    private function isPdm5s5rCompleted(Model $row, int $unitId, int $year, int $month, int $day, string $dateString): bool
    {
        if (isset($row->realisasi) && self::cellValue($row->realisasi, $day) !== null) {
            return true;
        }

        return PdmFormDocument::query()
            ->where('unit_id', $unitId)
            ->where('form', 'checklist-5s5r')
            ->where('year', $year)
            ->where('month', $month)
            ->get(['header', 'updated_at'])
            ->contains(function (PdmFormDocument $doc) use ($dateString): bool {
                $headerDate = trim((string) ($doc->header['tanggal'] ?? ''));
                if ($headerDate === $dateString) {
                    return true;
                }

                return $doc->updated_at !== null
                    && $doc->updated_at->copy()->timezone(PresenceRecorder::TIMEZONE)->toDateString() === $dateString;
            });
    }

    private function isLogistikSheetCompleted(
        Model $row,
        string $inputSheetKey,
        int $unitId,
        int $year,
        int $month,
        int $day,
        string $dateString,
        bool $checkDayCell,
    ): bool {
        if ($row instanceof LogistikJadwalRow && strtoupper((string) self::cellValue($row->days, $day)) === 'D') {
            return true;
        }

        $query = LogistikJadwalRow::query()
            ->where('unit_id', $unitId)
            ->where('jadwal', $inputSheetKey)
            ->where('year', $year)
            ->where('month', $month);

        if ($checkDayCell) {
            return $query->get(['days'])
                ->contains(fn (LogistikJadwalRow $r): bool => self::cellValue($r->days, $day) !== null);
        }

        return $query->get(['updated_at'])
            ->contains(fn (LogistikJadwalRow $r): bool => $r->updated_at !== null
                && $r->updated_at->copy()->timezone(PresenceRecorder::TIMEZONE)->toDateString() === $dateString);
    }

    private function isOperasi5s5rCompleted(
        Model $row,
        User $user,
        Employee $employee,
        ?string $regu,
        int $unitId,
        int $year,
        int $month,
        int $day,
        string $dateString,
    ): bool {
        if ($row instanceof Operasi5s5rJadwal && self::cellValue($row->realisasi, $day) !== null) {
            return true;
        }

        $scheduledShift = $this->scheduledShiftName($employee->id, $dateString);

        $itemQuery = OperasiProgram5s5rItem::query()
            ->where('unit_id', $unitId)
            ->where('year', $year)
            ->where('month', $month)
            ->where('day', $day);

        $evidQuery = OperasiProgram5s5rEvidence::query()
            ->where('unit_id', $unitId)
            ->where('year', $year)
            ->where('month', $month)
            ->where('day', $day);

        $scopeCheck = function ($q) use ($user, $scheduledShift, $row, $unitId, $regu): void {
            $q->where('input_by', $user->id);

            if ($scheduledShift !== null) {
                $q->orWhere('shift', $scheduledShift);
            }

            if ($row instanceof Operasi5s5rJadwal && $row->employee_id === null && $regu !== null) {
                $reguUserIds = $this->reguUserIds($unitId, $regu);
                if ($reguUserIds !== []) {
                    $q->orWhereIn('input_by', $reguUserIds);
                }
            }
        };

        return $itemQuery->where($scopeCheck)->exists()
            || $evidQuery->where($scopeCheck)->exists();
    }

    private function isOperasiFlmCompleted(
        ?string $regu,
        User $user,
        int $unitId,
        int $year,
        int $month,
        int $day,
        string $dateString,
    ): bool {
        $hasJadwalMark = OperasiFlmJadwal::query()
            ->where('unit_id', $unitId)
            ->where('year', $year)
            ->where('month', $month)
            ->where('section', 'rutin')
            ->where('row_type', 'mark')
            ->get(['days'])
            ->contains(fn (OperasiFlmJadwal $r): bool => self::cellValue($r->days, $day) !== null);

        if ($hasJadwalMark) {
            return true;
        }

        $reguUserIds = $regu !== null ? $this->reguUserIds($unitId, $regu) : [$user->id];
        if (! in_array($user->id, $reguUserIds, true)) {
            $reguUserIds[] = $user->id;
        }

        return OperasiFlmMonitoring::query()
            ->where('unit_id', $unitId)
            ->whereDate('tanggal', $dateString)
            ->where(function ($q) use ($reguUserIds, $regu): void {
                $q->whereIn('input_by', $reguUserIds)
                    ->orWhereIn('reported_by', $reguUserIds);

                if ($regu !== null) {
                    $q->orWhere('reporter_shift', 'like', "%Regu {$regu}%");
                }
            })
            ->exists();
    }

    private function isOperasiMeetingShiftCompleted(
        Model $row,
        ?string $regu,
        int $unitId,
        int $year,
        int $month,
        int $day,
        string $dateString,
    ): bool {
        $rowLabel = $row instanceof OperasiMeetingShiftJadwal ? strtoupper(trim($row->label)) : '';
        $periodHint = match (true) {
            str_contains($rowLabel, 'PAGI') => 'PAGI',
            str_contains($rowLabel, 'SORE') => 'SORE',
            str_contains($rowLabel, 'MALAM') => 'MALAM',
            default => null,
        };

        $markRows = OperasiMeetingShiftJadwal::query()
            ->where('unit_id', $unitId)
            ->where('year', $year)
            ->where('month', $month)
            ->where('row_type', 'mark')
            ->get(['label', 'days']);

        if ($periodHint !== null) {
            $matchingMarks = $markRows->filter(fn (OperasiMeetingShiftJadwal $r): bool => str_contains(strtoupper($r->label), $periodHint));
            if ($matchingMarks->isNotEmpty()) {
                $markRows = $matchingMarks;
            }
        }

        if ($markRows->contains(fn (OperasiMeetingShiftJadwal $r): bool => self::cellValue($r->days, $day) !== null)) {
            return true;
        }

        if ($regu === null) {
            return false;
        }

        return OperatorMutasi::query()
            ->where('unit_id', $unitId)
            ->whereDate('tanggal', $dateString)
            ->where(fn ($q) => $q->where('regu_penyerah', $regu)->orWhere('regu_penerima', $regu))
            ->exists();
    }

    private function isOperasiInventarisCompleted(
        Model $row,
        int $unitId,
        int $year,
        int $month,
        int $day,
        string $dateString,
    ): bool {
        if (isset($row->realisasi) && self::cellValue($row->realisasi, $day) !== null) {
            return true;
        }

        return OperasiMaterialPeralatan::query()
            ->where('unit_id', $unitId)
            ->where('year', $year)
            ->where('month', $month)
            ->whereDate('checked_at', $dateString)
            ->exists();
    }

    private function isHarPatrolCompleted(
        Model $row,
        int $unitId,
        int $year,
        int $month,
        int $day,
    ): bool {
        if (isset($row->realisasi) && self::cellValue($row->realisasi, $day) !== null) {
            return true;
        }

        return HarLembarRow::query()
            ->where('unit_id', $unitId)
            ->where('lembar', 'patrol-check-pemeliharaan')
            ->where('year', $year)
            ->where('month', $month)
            ->get(['cells'])
            ->contains(fn (HarLembarRow $r): bool => self::cellValue($r->cells['main'] ?? null, $day) !== null);
    }

    private function isPdmPatrolCompleted(
        int $unitId,
        int $year,
        int $month,
        string $dateString,
    ): bool {
        return PdmFormDocument::query()
            ->where('unit_id', $unitId)
            ->whereIn('form', ['patrol-check-pdm', 'checklist-patrol-check-pdm'])
            ->where('year', $year)
            ->where('month', $month)
            ->get(['header', 'updated_at'])
            ->contains(function (PdmFormDocument $doc) use ($dateString): bool {
                $headerDate = trim((string) ($doc->header['tanggal'] ?? ''));
                if ($headerDate === $dateString) {
                    return true;
                }

                return $doc->updated_at !== null
                    && $doc->updated_at->copy()->timezone(PresenceRecorder::TIMEZONE)->toDateString() === $dateString;
            });
    }

    private function scheduledShiftName(int $employeeId, string $dateString): ?string
    {
        $entry = WorkScheduleEntry::query()
            ->with('attendanceCode')
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', $dateString)
            ->first();

        $code = $entry?->attendanceCode;
        if ($code === null || ! $code->hitung_hadir) {
            return null;
        }

        $short = strtoupper(trim($code->code));
        $label = trim($code->label);

        return match (true) {
            $short === 'P' || strcasecmp($label, 'Pagi') === 0 => 'Pagi',
            $short === 'S' || strcasecmp($label, 'Sore') === 0 => 'Sore',
            $short === 'M' || strcasecmp($label, 'Malam') === 0 => 'Malam',
            default => null,
        };
    }

    /**
     * @return list<int>
     */
    private function reguUserIds(int $unitId, string $regu): array
    {
        return Employee::query()
            ->where('unit_id', $unitId)
            ->where('is_active', true)
            ->where('regu', $regu)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $table
     * @return array{
     *     id: string,
     *     table_key: string,
     *     module: string,
     *     module_label: string,
     *     label: string,
     *     title: string,
     *     unit_id: int,
     *     unit_name: string,
     *     date: string,
     *     assignment_type: 'personal'|'regu',
     *     regu: string|null,
     *     leader_name: string|null,
     *     requires_input: bool,
     *     completed: bool,
     *     action_label: string|null,
     *     url: string|null,
     * }
     */
    private function buildTaskPayload(
        array $table,
        string $moduleLabel,
        string $title,
        int $unitId,
        string $unitName,
        Carbon $date,
        string $assignmentType,
        ?string $regu,
        ?string $leaderName,
        bool $requiresInput,
        bool $completed,
        User $user,
    ): array {
        [$url, $actionLabel] = $this->resolveUrlAndLabel($user, $table, $unitId, $date);

        return [
            'id' => $table['key'].':'.$unitId.':'.substr(md5($title), 0, 8),
            'table_key' => $table['key'],
            'module' => $table['module'],
            'module_label' => $moduleLabel,
            'label' => $table['label'],
            'title' => $title,
            'unit_id' => $unitId,
            'unit_name' => $unitName,
            'date' => $date->toDateString(),
            'assignment_type' => $assignmentType,
            'regu' => $regu,
            'leader_name' => $leaderName,
            'requires_input' => $requiresInput,
            'completed' => $completed,
            'action_label' => $actionLabel,
            'url' => $url,
        ];
    }

    /**
     * Prefer the direct field-input route when the user holds any action permission;
     * otherwise fall back to the jadwal view route when the user holds {module}.input.view.
     *
     * @param  array<string, mixed>  $table
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveUrlAndLabel(User $user, array $table, int $unitId, Carbon $date): array
    {
        if (! $user->canAccessUnit($unitId)) {
            return [null, null];
        }

        $action = $table['action'] ?? null;
        if (is_array($action)) {
            $allowed = collect($action['permissions'] ?? [])->contains(fn ($perm): bool => $user->hasPermissionTo($perm));
            if ($allowed) {
                $params = [
                    ...($action['params'] ?? []),
                    'unit_id' => $unitId,
                    'month' => $date->month,
                    'year' => $date->year,
                ];
                if (! empty($action['pass_day'])) {
                    $params['day'] = $date->day;
                }
                if (! empty($action['pass_date'])) {
                    $params['tanggal'] = $date->toDateString();
                }

                return [route($action['route'], $params, false), $action['label'] ?? 'Kerjakan'];
            }
        }

        [, , $viewPermission] = ScheduleCatalog::MODULES[$table['module']];
        if ($user->hasPermissionTo($viewPermission)) {
            ['year' => $periodYear, 'month' => $periodMonth] = self::periodFor($table, $date);
            $url = route($table['route'], [
                ...($table['route_params'] ?? []),
                'unit_id' => $unitId,
                'month' => $periodMonth,
                'year' => $periodYear,
            ], false);

            return [$url, 'Buka Jadwal'];
        }

        return [null, null];
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<array<string, mixed>>
     */
    private function deduplicateAndSort(array $tasks): array
    {
        $unique = collect($tasks)->unique('id')->values()->all();

        usort($unique, function (array $a, array $b): int {
            $rank = fn (array $t): int => match (true) {
                $t['requires_input'] && ! $t['completed'] => 0,
                ! $t['requires_input'] => 1,
                default => 2,
            };

            return ($rank($a) <=> $rank($b))
                ?: strcmp($a['module'], $b['module'])
                ?: strcmp($a['label'], $b['label']);
        });

        return $unique;
    }
}
