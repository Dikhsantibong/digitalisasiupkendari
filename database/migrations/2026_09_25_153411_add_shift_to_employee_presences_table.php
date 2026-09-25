<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scheduled shift at absen masuk (P/S/M/OFF…, null when unscheduled), the
 * minutes late against its start, and the mandatory note when the absen falls
 * outside a working shift (e.g. ganti shift).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_presences', function (Blueprint $table): void {
            $table->string('shift_code', 10)->nullable()->after('work_date');
            $table->unsignedInteger('late_minutes')->nullable()->after('check_in_accuracy_m');
            $table->string('check_in_note', 255)->nullable()->after('late_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('employee_presences', function (Blueprint $table): void {
            $table->dropColumn(['shift_code', 'late_minutes', 'check_in_note']);
        });
    }
};
