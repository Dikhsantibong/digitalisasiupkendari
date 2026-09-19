<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The saved, editable Laporan Logistik & Gudang documents (rich
 * text or spreadsheet grid), mirroring k3_document_records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistik_document_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('bulanan');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('document_number')->nullable();
            $table->string('format')->default('html');
            $table->longText('content_html')->nullable();
            $table->json('content_grid')->nullable();
            $table->unsignedSmallInteger('content_version')->default(1);
            $table->json('snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'type', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistik_document_records');
    }
};
