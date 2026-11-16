<?php

use App\Casts\DayList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'pdm_jadwal_meetings' => ['rencana', 'realisasi'],
            'pdm_jadwal_5s5rs' => ['rencana', 'realisasi'],
            'pdm_jadwal_patrol_checks' => ['jadwal'],
            'pdm_jadwal_harians' => ['jadwal'],
        ];

        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->orderBy('id')
                ->select(['id', ...$columns])
                ->chunkById(200, function ($rows) use ($table, $columns): void {
                    foreach ($rows as $row) {
                        $updates = [];
                        foreach ($columns as $col) {
                            $normalized = DayList::normalize($row->{$col});
                            $updates[$col] = json_encode($normalized);
                        }
                        if ($table === 'pdm_jadwal_harians' && isset($updates['jadwal'])) {
                            $updates['realisasi_count'] = count(json_decode($updates['jadwal'], true) ?: []);
                        }
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                });
        }
    }

    public function down(): void
    {
        // Data normalization is forward-only and preserves all active day numbers.
    }
};
