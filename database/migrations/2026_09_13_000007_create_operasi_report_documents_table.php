<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved, editable Operasi report documents. Mirrors document_records (Berita
 * Acara) and har_document_records: one document per unit + report + engine +
 * period, editable as rich text (PDF) or as a spreadsheet grid (Excel), and
 * exported to PDF from the edited content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasi_report_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('report_code');
            $table->foreignId('engine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('document_number')->nullable();
            $table->string('format')->default('html');
            $table->longText('content_html')->nullable();
            $table->json('content_grid')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'report_code', 'engine_id', 'month', 'year'], 'ord_unit_report_engine_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_report_documents');
    }
};
