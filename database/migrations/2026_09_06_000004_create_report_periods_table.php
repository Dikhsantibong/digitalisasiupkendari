<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One reporting period (a month) per unit. Drives the number of date rows and
 * the report titles. Unit identity for the letterhead comes from the master
 * unit, never duplicated here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('total_days');
            $table->unsignedSmallInteger('total_hours');
            $table->foreignId('pic_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(['unit_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_periods');
    }
};
