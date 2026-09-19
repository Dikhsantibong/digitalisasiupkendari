<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verification & pengesahan workflow of the Laporan Pembangkit (one per
 * module + unit + month), its signing steps and its audit trail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('module', 20);
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['module', 'unit_id', 'month', 'year']);
        });

        Schema::create('report_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_workflow_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 20);
            $table->unsignedTinyInteger('sequence');
            $table->string('caption', 40);
            $table->string('position', 120);
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['report_workflow_id', 'stage', 'sequence']);
        });

        Schema::create('report_workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 160)->nullable();
            $table->string('jabatan', 160)->nullable();
            $table->string('action', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_workflow_logs');
        Schema::dropIfExists('report_workflow_steps');
        Schema::dropIfExists('report_workflows');
    }
};
