<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Revise Program 5S 5R inputs (Operasi & Pemeliharaan/HAR) from weekly (per minggu)
     * to per-shift per-day (unit + year + month + day + shift + program).
     */
    public function up(): void
    {
        $this->upgradeItemsTable('operasi_program_5s5r_items', 'operasi_5s5r_items_day_shift_unique', 'operasi_5s5r_items_period_unique');
        $this->upgradeEvidencesTable('operasi_program_5s5r_evidences', 'operasi_5s5r_evidences_day_shift_index', 'operasi_5s5r_evidences_period_index');

        $this->upgradeItemsTable('har_program_5s5r_items', 'har_5s5r_items_day_shift_unique', 'har_5s5r_items_period_unique');
        $this->upgradeEvidencesTable('har_program_5s5r_evidences', 'har_5s5r_evidences_day_shift_index', 'har_5s5r_evidences_period_index');
    }

    public function down(): void
    {
        $this->downgradeItemsTable('operasi_program_5s5r_items', 'operasi_5s5r_items_day_shift_unique', 'operasi_5s5r_items_period_unique');
        $this->downgradeEvidencesTable('operasi_program_5s5r_evidences', 'operasi_5s5r_evidences_day_shift_index', 'operasi_5s5r_evidences_period_index');

        $this->downgradeItemsTable('har_program_5s5r_items', 'har_5s5r_items_day_shift_unique', 'har_5s5r_items_period_unique');
        $this->downgradeEvidencesTable('har_program_5s5r_evidences', 'har_5s5r_evidences_day_shift_index', 'har_5s5r_evidences_period_index');
    }

    private function upgradeItemsTable(string $tableName, string $newUnique, string $oldUnique): void
    {
        Schema::table($tableName, function (Blueprint $table): void {
            $table->unsignedTinyInteger('day')->default(1)->after('month');
            $table->string('shift', 20)->default('Pagi')->after('day');
            $table->date('tanggal')->nullable()->after('shift');
        });

        DB::table($tableName)->orderBy('id')->each(function (object $row) use ($tableName): void {
            $minggu = max(1, (int) ($row->minggu ?? 1));
            $day = min(28, (($minggu - 1) * 7) + 1);
            $year = max(2000, (int) ($row->year ?? 2026));
            $month = max(1, min(12, (int) ($row->month ?? 1)));

            DB::table($tableName)->where('id', $row->id)->update([
                'day' => $day,
                'shift' => 'Pagi',
                'tanggal' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            ]);
        });

        Schema::table($tableName, function (Blueprint $table) use ($newUnique, $oldUnique): void {
            $table->unique(['unit_id', 'year', 'month', 'day', 'shift', 'program'], $newUnique);
            $table->dropUnique($oldUnique);
        });
    }

    private function upgradeEvidencesTable(string $tableName, string $newIndex, string $oldIndex): void
    {
        Schema::table($tableName, function (Blueprint $table): void {
            $table->unsignedTinyInteger('day')->default(1)->after('month');
            $table->string('shift', 20)->default('Pagi')->after('day');
            $table->date('tanggal')->nullable()->after('shift');
        });

        DB::table($tableName)->orderBy('id')->each(function (object $row) use ($tableName): void {
            $minggu = max(1, (int) ($row->minggu ?? 1));
            $day = min(28, (($minggu - 1) * 7) + 1);
            $year = max(2000, (int) ($row->year ?? 2026));
            $month = max(1, min(12, (int) ($row->month ?? 1)));

            DB::table($tableName)->where('id', $row->id)->update([
                'day' => $day,
                'shift' => 'Pagi',
                'tanggal' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            ]);
        });

        Schema::table($tableName, function (Blueprint $table) use ($newIndex, $oldIndex): void {
            $table->index(['unit_id', 'year', 'month', 'day', 'shift'], $newIndex);
            $table->dropIndex($oldIndex);
        });
    }

    private function downgradeItemsTable(string $tableName, string $newUnique, string $oldUnique): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($newUnique, $oldUnique): void {
            $table->unique(['unit_id', 'year', 'month', 'minggu', 'program'], $oldUnique);
            $table->dropUnique($newUnique);
            $table->dropColumn(['day', 'shift', 'tanggal']);
        });
    }

    private function downgradeEvidencesTable(string $tableName, string $newIndex, string $oldIndex): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($newIndex, $oldIndex): void {
            $table->index(['unit_id', 'year', 'month', 'minggu'], $oldIndex);
            $table->dropIndex($newIndex);
            $table->dropColumn(['day', 'shift', 'tanggal']);
        });
    }
};
