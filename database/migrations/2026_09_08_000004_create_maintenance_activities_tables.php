<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The HARMES activity log — the heart of the field work — with its task lines
 * and materials used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_period_id')->nullable()->constrained()->nullOnDelete();
            $table->date('activity_date');
            $table->foreignId('engine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->foreignId('maintenance_type_id')->nullable()->constrained('maintenance_types')->nullOnDelete();
            $table->foreignId('wo_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->string('work_result')->nullable();
            $table->string('no_lh05')->nullable();
            $table->string('no_sr')->nullable();
            $table->string('no_tug9')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'report_period_id']);
        });

        Schema::create('maintenance_activity_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained('maintenance_activities')->cascadeOnDelete();
            $table->text('task_description');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('maintenance_activity_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained('maintenance_activities')->cascadeOnDelete();
            $table->string('material_name');
            $table->string('part_number')->nullable();
            $table->decimal('quantity', 12, 2)->nullable();
            $table->string('unit_of_measure')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_activity_materials');
        Schema::dropIfExists('maintenance_activity_tasks');
        Schema::dropIfExists('maintenance_activities');
    }
};
