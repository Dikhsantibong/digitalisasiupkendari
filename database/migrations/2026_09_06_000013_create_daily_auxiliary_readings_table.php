<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily closing readings per auxiliary source. Opening stands carry over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_auxiliary_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auxiliary_source_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->decimal('stand_kwh_akhir', 18, 4)->nullable();
            $table->decimal('stand_bbm_akhir', 18, 4)->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['auxiliary_source_id', 'report_date'], 'daily_aux_readings_source_date_unique');
            $table->index(['unit_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_auxiliary_readings');
    }
};
