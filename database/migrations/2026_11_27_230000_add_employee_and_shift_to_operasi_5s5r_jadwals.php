<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_PLACEHOLDERS = [
        'OFFICER FUELFACILITY',
        'OPERATOR SHIFT A',
        'OPERATOR SHIFT B',
        'OPERATOR SHIFT C',
        'OPERATOR SHIFT D',
    ];

    public function up(): void
    {
        Schema::table('operasi_5s5r_jadwals', function (Blueprint $table): void {
            if (! Schema::hasColumn('operasi_5s5r_jadwals', 'employee_id')) {
                $table->foreignId('employee_id')->nullable()->after('no_urut')->constrained('employees')->nullOnDelete();
            }
            if (! Schema::hasColumn('operasi_5s5r_jadwals', 'shift')) {
                $table->string('shift', 20)->nullable()->after('employee_id');
            }
        });

        // Replace purely legacy hardcoded placeholder sheets with real active shift operators
        // of each unit (carrying over rencana/realisasi/target per matching shift), while leaving
        // any sheet that already contains custom pelaksana completely untouched.
        if (! Schema::hasTable('employees')) {
            return;
        }

        $groups = DB::table('operasi_5s5r_jadwals')
            ->select('unit_id', 'year', 'month')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('operasi_5s5r_jadwals')
                ->where('unit_id', $group->unit_id)
                ->where('year', $group->year)
                ->where('month', $group->month)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $allLegacy = $rows->every(fn ($row): bool => in_array(strtoupper(trim((string) $row->pelaksana)), self::LEGACY_PLACEHOLDERS, true));
            if (! $allLegacy) {
                continue;
            }

            $operators = DB::table('employees')
                ->where('unit_id', $group->unit_id)
                ->where('is_active', true)
                ->whereNotNull('regu')
                ->where('regu', '!=', '')
                ->orderBy('regu')
                ->orderByDesc('is_shift_leader')
                ->orderBy('name')
                ->get(['id', 'name', 'regu']);

            if ($operators->isEmpty()) {
                foreach ($rows as $row) {
                    if (preg_match('/SHIFT\s+([A-D])$/i', trim((string) $row->pelaksana), $m)) {
                        DB::table('operasi_5s5r_jadwals')
                            ->where('id', $row->id)
                            ->update(['shift' => strtoupper($m[1])]);
                    }
                }

                continue;
            }

            $byShift = [];
            $fallback = $rows->first();
            foreach ($rows as $row) {
                if (preg_match('/SHIFT\s+([A-D])$/i', trim((string) $row->pelaksana), $m)) {
                    $byShift[strtoupper($m[1])] = $row;
                }
            }

            $now = now();
            DB::transaction(function () use ($group, $operators, $byShift, $fallback, $now): void {
                DB::table('operasi_5s5r_jadwals')
                    ->where('unit_id', $group->unit_id)
                    ->where('year', $group->year)
                    ->where('month', $group->month)
                    ->delete();

                foreach ($operators->values() as $idx => $op) {
                    $regu = strtoupper(trim((string) $op->regu));
                    $source = $byShift[$regu] ?? $fallback;

                    DB::table('operasi_5s5r_jadwals')->insert([
                        'unit_id' => $group->unit_id,
                        'year' => $group->year,
                        'month' => $group->month,
                        'no_urut' => $idx + 1,
                        'employee_id' => $op->id,
                        'shift' => $regu,
                        'pelaksana' => $op->name,
                        'rencana' => $source?->rencana ?? '[]',
                        'realisasi' => $source?->realisasi ?? '[]',
                        'target' => (int) ($source?->target ?? 4),
                        'sort_order' => $idx,
                        'input_by' => $source?->input_by,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('operasi_5s5r_jadwals', function (Blueprint $table): void {
            if (Schema::hasColumn('operasi_5s5r_jadwals', 'employee_id')) {
                $table->dropConstrainedForeignId('employee_id');
            }
            if (Schema::hasColumn('operasi_5s5r_jadwals', 'shift')) {
                $table->dropColumn('shift');
            }
        });
    }
};
