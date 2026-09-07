<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Star-Stop log: the single source of machine hours. Operating / HAR /
 * disturbance hours are derived by grouping these durations by the status
 * code's category — never typed in as aggregate hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engine_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('engine_id')->constrained('machines')->cascadeOnDelete();
            $table->date('report_date');
            $table->foreignId('status_code_id')->constrained('unit_status_codes')->cascadeOnDelete();
            $table->string('operator_name')->nullable();
            $table->string('dispatcher_name')->nullable();
            $table->dateTime('start_datetime')->nullable();
            $table->dateTime('stop_datetime')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'engine_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engine_status_logs');
    }
};
