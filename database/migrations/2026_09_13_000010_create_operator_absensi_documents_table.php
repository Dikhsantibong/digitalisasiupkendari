<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved, editable Operator attendance/schedule report documents — one per unit,
 * month, and employee group. Mirrors the other report-document tables (editable
 * as rich text / spreadsheet, exported to PDF, auto-refreshed on version bump).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_absensi_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('group_type')->default('shift');
            $table->string('document_number')->nullable();
            $table->string('format')->default('html');
            $table->unsignedSmallInteger('content_version')->nullable();
            $table->longText('content_html')->nullable();
            $table->json('content_grid')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month', 'group_type'], 'oad_unit_period_group_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_absensi_documents');
    }
};
