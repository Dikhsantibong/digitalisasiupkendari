<?php

namespace App\Console\Commands;

use App\Models\ReminderLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

#[Signature('notifications:prune {--days=90 : Umur notifikasi (hari) yang disimpan}')]
#[Description('Hapus notifikasi dan catatan pengingat yang lebih tua dari batas simpan')]
class PruneNotifications extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $before = Carbon::now()->subDays(max(1, (int) $this->option('days')));

        $notifications = DatabaseNotification::query()->where('created_at', '<', $before)->delete();
        $logs = ReminderLog::query()->where('created_at', '<', $before)->delete();

        $this->info("Dihapus: {$notifications} notifikasi, {$logs} catatan pengingat.");

        return self::SUCCESS;
    }
}
