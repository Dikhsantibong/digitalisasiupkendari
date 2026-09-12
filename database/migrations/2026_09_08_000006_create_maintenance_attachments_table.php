<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photo attachments for important maintenance work (the report's lampiran). The
 * file lives in Laravel storage; only the path is stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_period_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wo_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('maintenance_activities')->nullOnDelete();
            $table->foreignId('engine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->string('title');
            $table->string('photo_path');
            $table->string('caption')->nullable();
            $table->date('taken_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'report_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_attachments');
    }
};
