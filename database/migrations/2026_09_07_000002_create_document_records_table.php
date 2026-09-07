<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A saved Berita Acara: the numbers snapshot at the moment it was generated,
 * plus who made and approved it. Kept for audit and reprinting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->foreignId('report_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_number');
            $table->json('snapshot');
            $table->string('pdf_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'type', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_records');
    }
};
