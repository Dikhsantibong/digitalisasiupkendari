<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One cell of the schedule grid: an employee's attendance code on one day.
 * Recap totals and the attendance percentage are derived from these rows, never
 * stored. A null attendance_code_id means the day is blank (not yet filled).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedule_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignId('attendance_code_id')->nullable()->constrained('attendance_codes')->nullOnDelete();
            $table->string('regu')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['work_schedule_id', 'employee_id', 'work_date'], 'wse_sheet_employee_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedule_entries');
    }
};
