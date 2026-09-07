<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the spreadsheet (Excel-mode) edit of a document and remembers which
 * editor produced the saved version, so re-opening and PDF export use the right
 * source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_records', function (Blueprint $table): void {
            $table->json('content_grid')->nullable()->after('content_html');
            $table->string('format')->default('html')->after('content_grid');
        });
    }

    public function down(): void
    {
        Schema::table('document_records', function (Blueprint $table): void {
            $table->dropColumn(['content_grid', 'format']);
        });
    }
};
