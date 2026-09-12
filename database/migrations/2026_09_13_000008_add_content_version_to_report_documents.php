<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stamps each saved report document with the template version it was built from.
 * When the report layout changes, the controller bumps its version constant;
 * documents saved against an older layout are then treated as stale and
 * re-rendered from the current template automatically (instead of showing the
 * outdated saved snapshot). Nullable: existing rows are stale until re-saved.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'har_document_records',
        'k3_document_records',
        'operasi_report_documents',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedSmallInteger('content_version')->nullable()->after('format');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('content_version');
            });
        }
    }
};
