<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Laporan Administrasi Keuangan of a UL as an edited document (user,
     * 2026-11-20): the Laporan Realisasi Cash Card table, then per row the
     * Bukti Penerimaan Kas Kecil and its Lampiran — saved from the document
     * editor (Teks / Excel), what the PDF then prints.
     */
    public function up(): void
    {
        Schema::create('keuangan_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('format', 10)->default('html');
            $table->longText('content_html')->nullable();
            $table->json('content_grid')->nullable();
            $table->unsignedSmallInteger('content_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['service_unit_id', 'month', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_documents');
    }
};
