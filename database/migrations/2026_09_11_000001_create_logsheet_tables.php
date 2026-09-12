<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operator logsheet (layer input dalam modul OPERASI). Parameter columns are a
 * global master (per plant_type, all = shared set now); each logsheet is one
 * machine + date, and readings are stored long (row per time-slot per parameter)
 * so 30-minute peak slots and future PLTM/PLTG parameter sets fit without a
 * schema change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logsheet_parameters', function (Blueprint $table): void {
            $table->id();
            $table->string('plant_type')->default('all');
            $table->string('code');
            $table->string('name');
            $table->string('unit_of_measure')->nullable();
            $table->string('sub_channel')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['plant_type', 'is_active']);
        });

        Schema::create('operator_logsheets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('engine_id')->constrained('machines')->cascadeOnDelete();
            $table->date('log_date');
            $table->foreignId('operator_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('operator_name')->nullable();
            $table->string('shift')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['engine_id', 'log_date']);
            $table->index(['unit_id', 'log_date']);
        });

        Schema::create('operator_logsheet_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logsheet_id')->constrained('operator_logsheets')->cascadeOnDelete();
            $table->time('time_slot');
            $table->foreignId('parameter_id')->constrained('logsheet_parameters')->cascadeOnDelete();
            $table->decimal('value', 18, 4)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['logsheet_id', 'time_slot']);
            $table->unique(['logsheet_id', 'time_slot', 'parameter_id'], 'logsheet_reading_slot_param_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_logsheet_readings');
        Schema::dropIfExists('operator_logsheets');
        Schema::dropIfExists('logsheet_parameters');
    }
};
