<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved, editable Operator logsheet report documents — one per machine per day.
 * Mirrors the other report-document tables (HAR/K3/Operasi): editable as rich
 * text (PDF) or spreadsheet grid (Excel), exported to PDF from the edited
 * content, and auto-refreshed from the template when the layout version bumps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operator_logsheet_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('engine_id')->constrained('machines')->cascadeOnDelete();
            $table->date('log_date');
            $table->string('document_number')->nullable();
            $table->string('format')->default('html');
            $table->unsignedSmallInteger('content_version')->nullable();
            $table->longText('content_html')->nullable();
            $table->json('content_grid')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'engine_id', 'log_date'], 'old_unit_engine_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_logsheet_documents');
    }
};
