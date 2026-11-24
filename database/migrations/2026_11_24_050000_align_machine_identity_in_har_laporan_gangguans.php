<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align machine identity (merek = machine name, type, serial_number, capacity_kw)
     * on existing `har_laporan_gangguans` records and clean up placeholder `merk`
     * values on `machines`.
     */
    public function up(): void
    {
        if (Schema::hasTable('machines')) {
            DB::table('machines')
                ->where('merk', 'Wärtsilä / Caterpillar')
                ->update(['merk' => DB::raw('name')]);
        }

        if (! Schema::hasTable('har_laporan_gangguans') || ! Schema::hasTable('machines')) {
            return;
        }

        $machines = DB::table('machines')->get()->keyBy('id');

        DB::table('har_laporan_gangguans')
            ->whereNotNull('machine_id')
            ->orderBy('id')
            ->each(function (object $report) use ($machines): void {
                $machine = $machines->get($report->machine_id);
                if ($machine === null) {
                    return;
                }

                $cap = (float) ($machine->capacity_kw ?? 0);
                DB::table('har_laporan_gangguans')
                    ->where('id', $report->id)
                    ->update([
                        'merek' => $machine->name,
                        'type' => $machine->type ?: $report->type,
                        'no_seri' => $machine->serial_number ?: $report->no_seri,
                        'daya_terpasang' => $cap > 0 ? (string) $machine->capacity_kw : $report->daya_terpasang,
                        'daya_mampu' => ($report->daya_mampu !== null && trim((string) $report->daya_mampu) !== '')
                            ? $report->daya_mampu
                            : ($cap > 0 ? (string) round($cap * 0.9, 2) : null),
                    ]);
            });
    }

    public function down(): void
    {
        // Data alignment only.
    }
};
