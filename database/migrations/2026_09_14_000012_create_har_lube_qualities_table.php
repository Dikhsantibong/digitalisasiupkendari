<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('har_lube_qualities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->date('test_date');
            $table->string('document_number')->default('FMKD-305-14.3.2.b-A3');
            $table->string('revision')->default('00');
            $table->string('effective_date')->default('31 - 07 - 2024');
            $table->string('page_number')->nullable();

            // Machine & Sample Point Details
            $table->string('unit_sentral')->nullable()->default('ULPLTD WUA-WUA');
            $table->string('machine_name')->nullable();
            $table->string('machine_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('sample_point')->default('Sump Tank');

            // Parameter Measurements (JSON array of rows)
            $table->json('parameters');

            // Status & Standard
            $table->text('status_text')->nullable();
            $table->text('standard_text')->nullable();

            // Photo Sample & Analysis
            $table->string('photo_path')->nullable();
            $table->text('photo_caption')->nullable();
            $table->text('analisa_text')->nullable();

            // CBA & Rekomendasi
            $table->text('cba_text')->nullable();
            $table->text('rekomendasi_text')->nullable();

            // Signatories (3 Kolom: Manager UL, TL Har, Staff Har)
            $table->string('signature_location')->default('Kendari');
            $table->string('signature_date')->nullable();

            $table->foreignId('manager_ul_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('manager_ul_name')->nullable();
            $table->string('manager_ul_title')->nullable();

            $table->foreignId('tl_har_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('tl_har_name')->nullable();
            $table->string('tl_har_title')->nullable();

            $table->foreignId('staff_har_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('staff_har_name')->nullable();
            $table->string('staff_har_title')->nullable();

            // PDF layout settings
            $table->unsignedSmallInteger('page_margin_top')->default(8);
            $table->unsignedSmallInteger('page_margin_bottom')->default(8);
            $table->unsignedSmallInteger('page_margin_left')->default(10);
            $table->unsignedSmallInteger('page_margin_right')->default(10);
            $table->string('line_spacing')->default('1.1');

            // Format & HTML override
            $table->string('format')->default('form');
            $table->longText('content_html')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'machine_id', 'test_date'], 'har_lube_qualities_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_lube_qualities');
    }
};
