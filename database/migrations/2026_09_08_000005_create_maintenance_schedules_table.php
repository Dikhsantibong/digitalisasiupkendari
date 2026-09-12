<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan-vs-realisation schedule matrices per machine (scope: har / pelumas /
 * air). The matrix itself is kept as JSON so the structure stays flexible until
 * the granularity is confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('engine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->string('plan_type');
            $table->string('scope');
            $table->json('schedule_data')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_schedules');
    }
};
