<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The edited SLA documents (user, 2026-11-19): the SLA of one divisi or the
     * Laporan SLA gabungan of one unit (or every unit: unit_id null), per
     * month, saved from the document editor (Teks / Excel) — the PDF then
     * prints the edited values.
     */
    public function up(): void
    {
        Schema::create('sla_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 30); // divisi key (operasi, pemeliharaan, …) or "gabungan"
            $table->foreignId('unit_id')->nullable()->constrained()->cascadeOnDelete(); // null = semua unit (gabungan)
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('format', 10)->default('html');
            $table->longText('content_html')->nullable();
            $table->json('content_grid')->nullable();
            $table->unsignedSmallInteger('content_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'unit_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_documents');
    }
};
