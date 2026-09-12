<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved, editable HAR report documents. Mirrors the Operasi document_records
 * table: one document per unit + period, editable as rich text (PDF) or as a
 * spreadsheet grid (Excel), exported to PDF from the edited content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('har_document_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('bulanan');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('document_number')->nullable();
            $table->string('format')->default('html');
            $table->longText('content_html')->nullable();
            $table->json('content_grid')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'type', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_document_records');
    }
};
