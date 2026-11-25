<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Har\FlmOperasiController;
use App\Models\OperasiFlmMonitoring;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\ReminderNotification;
use Illuminate\Support\Carbon;

/**
 * Daily reminder of FLM findings still open after
 * {@see FlmOperasiController::REMIND_AFTER_DAYS} days (user, 2026-11-24): one
 * per unit per day to the Pemeliharaan accounts that may close them
 * (har.input.write / har.pengusahaan.write) and can access the unit — from
 * 07:00 WITA, deduplicated by ReminderSender.
 */
class FlmReminders
{
    private const FROM_HOUR = 7;

    public function __construct(private readonly ReminderSender $sender) {}

    public function run(Carbon $now): int
    {
        $local = $now->copy()->timezone('Asia/Makassar');
        if ($local->hour < self::FROM_HOUR) {
            return 0;
        }

        $overdue = OperasiFlmMonitoring::query()->where('status', 'open')->get()
            ->filter(fn (OperasiFlmMonitoring $r): bool => ($r->openDays($local) ?? 0) >= FlmOperasiController::REMIND_AFTER_DAYS)
            ->groupBy('unit_id');
        if ($overdue->isEmpty()) {
            return 0;
        }

        $users = User::query()->where('is_active', true)->get()
            ->filter(fn (User $user): bool => ! $user->isSuperAdmin() && FlmOperasiController::closes($user));
        $unitNames = Unit::query()->whereIn('id', $overdue->keys())->pluck('name', 'id');
        $sent = 0;

        foreach ($overdue as $unitId => $rows) {
            $oldest = $rows->max(fn (OperasiFlmMonitoring $r): int => (int) $r->openDays($local));
            $list = $rows->take(3)->map(fn (OperasiFlmMonitoring $r): string => trim(($r->mesin ?? '').' — '.($r->masalah ?? ''), ' —'))->implode('; ');
            $reminder = new ReminderNotification(
                NotificationCategory::Pekerjaan,
                'pemeliharaan',
                "{$rows->count()} FLM masih open · ".($unitNames[$unitId] ?? 'Unit'),
                "Terlama {$oldest} hari. {$list}".($rows->count() > 3 ? ' …' : ''),
                route('har.flm-operasi.index', ['unit_id' => $unitId], false),
                "flm-open:{$unitId}:{$local->toDateString()}",
            );

            foreach ($users->filter(fn (User $user): bool => $user->canAccessUnit($unitId)) as $user) {
                $sent += $this->sender->send($user, $reminder) ? 1 : 0;
            }
        }

        return $sent;
    }
}
