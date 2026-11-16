<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['operasi_data_tekniss', 'operasi_performance_test_mesins', 'operasi_comm_peralatans'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'realisasi')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->json('realisasi')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['operasi_data_tekniss', 'operasi_performance_test_mesins', 'operasi_comm_peralatans'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'realisasi')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('realisasi');
                });
            }
        }
    }
};
