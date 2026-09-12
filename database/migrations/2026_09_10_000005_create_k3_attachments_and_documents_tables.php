<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly K3 attachments (documents/photos on disk, path only) and the saved,
 * editable K3 report documents (rich text or spreadsheet grid), mirroring the
 * HAR document engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('k3_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('title');
            $table->string('file_path');
            $table->string('category')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('k3_document_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('k3_document_records');
        Schema::dropIfExists('k3_attachments');
    }
};
