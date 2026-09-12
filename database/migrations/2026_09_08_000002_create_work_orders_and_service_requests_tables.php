<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Work Orders and Service Requests. Manual entry now; the `source` column keeps
 * WPC-pulled rows distinguishable once that integration is added. Every row is
 * scoped to a unit and a reporting period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sr_number');
            $table->text('description')->nullable();
            $table->foreignId('sr_category_id')->nullable()->constrained('sr_categories')->nullOnDelete();
            $table->string('status')->default('open');
            $table->foreignId('engine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->string('source')->default('manual');
            $table->string('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'report_period_id']);
        });

        Schema::create('work_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('wonum');
            $table->text('description')->nullable();
            $table->foreignId('maintenance_type_id')->nullable()->constrained('maintenance_types')->nullOnDelete();
            $table->foreignId('engine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->foreignId('work_group_id')->nullable()->constrained('work_groups')->nullOnDelete();
            $table->foreignId('wo_status_id')->nullable()->constrained('wo_statuses')->nullOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('maintenance_cycles')->nullOnDelete();
            $table->dateTime('report_date')->nullable();
            $table->dateTime('sched_start')->nullable();
            $table->dateTime('sched_finish')->nullable();
            $table->dateTime('actual_finish')->nullable();
            $table->string('waiting_reason')->nullable();
            $table->decimal('service_cost', 15, 2)->nullable();
            $table->decimal('material_cost', 15, 2)->nullable();
            $table->string('source')->default('manual');
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'report_period_id', 'maintenance_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('service_requests');
    }
};
