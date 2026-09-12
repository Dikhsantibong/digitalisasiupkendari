<?php

use App\Services\Operasi\LogsheetAggregator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks where a daily engine report came from: typed by the TL (manual, the
 * default and only active path) or aggregated from the operator logsheet
 * (logsheet). The logsheet path is prepared but NOT yet activated — see
 * {@see LogsheetAggregator}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_engine_reports', function (Blueprint $table): void {
            $table->string('source')->default('manual')->after('report_date');
        });
    }

    public function down(): void
    {
        Schema::table('daily_engine_reports', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
};
