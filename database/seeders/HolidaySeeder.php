<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Indicative 2026 Indonesian national holidays, used only to shade the schedule
 * grid columns (they do not change shifts — the plant runs 24 hours). Religious
 * dates are approximate until the government's SKB is issued; they are editable
 * later. Idempotent (keyed by date).
 */
class HolidaySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{date: string, description: string}>
     */
    private const HOLIDAYS = [
        ['date' => '2026-01-01', 'description' => 'Tahun Baru Masehi'],
        ['date' => '2026-01-16', 'description' => 'Isra Mikraj Nabi Muhammad SAW'],
        ['date' => '2026-02-17', 'description' => 'Tahun Baru Imlek'],
        ['date' => '2026-03-19', 'description' => 'Hari Suci Nyepi'],
        ['date' => '2026-03-20', 'description' => 'Hari Raya Idul Fitri'],
        ['date' => '2026-03-21', 'description' => 'Hari Raya Idul Fitri'],
        ['date' => '2026-04-03', 'description' => 'Wafat Isa Almasih'],
        ['date' => '2026-05-01', 'description' => 'Hari Buruh Internasional'],
        ['date' => '2026-05-14', 'description' => 'Kenaikan Isa Almasih'],
        ['date' => '2026-05-27', 'description' => 'Hari Raya Idul Adha'],
        ['date' => '2026-05-31', 'description' => 'Hari Raya Waisak'],
        ['date' => '2026-06-01', 'description' => 'Hari Lahir Pancasila'],
        ['date' => '2026-06-16', 'description' => 'Tahun Baru Islam 1448 H'],
        ['date' => '2026-08-17', 'description' => 'Hari Kemerdekaan RI'],
        ['date' => '2026-08-25', 'description' => 'Maulid Nabi Muhammad SAW'],
        ['date' => '2026-12-25', 'description' => 'Hari Raya Natal'],
    ];

    public function run(): void
    {
        foreach (self::HOLIDAYS as $holiday) {
            $date = Carbon::parse($holiday['date']);

            Holiday::query()->updateOrCreate(
                ['date' => $date->toDateString()],
                [
                    'year' => $date->year,
                    'day_name' => $date->locale('id')->dayName,
                    'description' => $holiday['description'],
                    'is_national' => true,
                ],
            );
        }
    }
}
