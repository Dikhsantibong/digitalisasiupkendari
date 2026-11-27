<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table name => column after which nullable `employee_id` should sit.
     *
     * @var array<string, string>
     */
    private const TABLES = [
        'logistik_jadwal_rows' => 'pic',
        'pdm_jadwal_5s5rs' => 'no_urut',
        'pdm_jadwal_meetings' => 'no_urut',
        'k3_patrol_check_jadwals' => 'no_urut',
        'k3_pekerjaan_rutins' => 'no_urut',
        'k3_activity_plans' => 'k3_activity_type_id',
        'har_jadwal_meeting_pemeliharaans' => 'month',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName => $afterColumn) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'employee_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($afterColumn): void {
                $table->foreignId('employee_id')->nullable()->after($afterColumn)->constrained('employees')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'employee_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('employee_id');
            });
        }
    }
};
