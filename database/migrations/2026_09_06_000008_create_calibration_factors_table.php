<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-unit (optionally per-machine) calibration factors applied when converting
 * a meter delta into kWh or litres. Never hardcoded in calculation code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calibration_factors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('engine_id')->nullable()->constrained('machines')->cascadeOnDelete();
            $table->string('factor_type');
            $table->decimal('value', 20, 10);
            $table->date('effective_date');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'engine_id', 'factor_type', 'effective_date'], 'calibration_factors_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calibration_factors');
    }
};
