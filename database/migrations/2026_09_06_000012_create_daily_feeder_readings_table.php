<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily closing meter reading per feeder. Opening stand carries over.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_feeder_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feeder_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->decimal('stand_akhir', 18, 4)->nullable();
            $table->boolean('is_active_today')->default(true);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['feeder_id', 'report_date']);
            $table->index(['unit_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_feeder_readings');
    }
};
