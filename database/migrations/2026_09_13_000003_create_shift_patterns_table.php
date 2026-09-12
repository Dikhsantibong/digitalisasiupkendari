<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rotation pattern per regu, per unit — the optional starting point for the
 * "Generate pola" button. `sequence` is a comma-separated list of attendance
 * codes (e.g. "OFF,OFF,S,S,P,P,M,M"); generation repeats it across the month
 * from a chosen start date, after which every cell stays editable by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_patterns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('regu');
            $table->string('sequence');
            $table->unsignedInteger('cycle_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['unit_id', 'regu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_patterns');
    }
};
