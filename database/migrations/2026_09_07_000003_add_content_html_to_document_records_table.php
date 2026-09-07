<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the edited document body so a saved Berita Acara can be reopened,
 * edited again, and exported to PDF exactly as the user left it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_records', function (Blueprint $table): void {
            $table->longText('content_html')->nullable()->after('document_number');
        });
    }

    public function down(): void
    {
        Schema::table('document_records', function (Blueprint $table): void {
            $table->dropColumn('content_html');
        });
    }
};
