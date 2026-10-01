<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pengingat absen & jadwal (lihat App\Services\Notifications). Server: cron `php artisan schedule:run` setiap menit.
Schedule::command('notifications:send-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('notifications:prune')->dailyAt('02:00')->timezone('Asia/Makassar');
