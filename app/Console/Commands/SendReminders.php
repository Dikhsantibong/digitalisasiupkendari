<?php

namespace App\Console\Commands;

use App\Services\Notifications\AttendanceReminders;
use App\Services\Notifications\ScheduleReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('notifications:send-reminders')]
#[Description('Kirim pengingat absen & jadwal yang jatuh tempo (dijalankan scheduler setiap 5 menit)')]
class SendReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AttendanceReminders $attendance, ScheduleReminders $schedules): int
    {
        $now = Carbon::now();
        $absen = $attendance->run($now);
        $jadwal = $schedules->run($now);

        $this->info("Pengingat terkirim — absen: {$absen}, jadwal: {$jadwal}.");

        return self::SUCCESS;
    }
}
