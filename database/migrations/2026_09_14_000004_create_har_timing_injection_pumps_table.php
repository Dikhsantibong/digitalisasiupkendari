<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('har_timing_injection_pumps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->date('test_date');
            $table->string('document_number')->default('FMKD-314-10.3.3.a-B5');
            $table->string('revision')->default('02');
            $table->string('effective_date')->default('31 Juli 2024');

            // Technical specs snapshot / input
            $table->string('brand')->nullable();
            $table->string('model_type')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('machine_number')->nullable();
            $table->string('installed_power')->nullable();
            $table->string('capable_power')->nullable();
            $table->string('rpm')->nullable();

            // Cylinder timing injection checklist data
            $table->unsignedTinyInteger('cylinders_count')->default(8);
            $table->string('standard_allowed')->nullable()->default('Sesuai petunjuk pabrik / buku manual');
            $table->json('checklist_items');
            $table->text('notes')->nullable();

            // Signatories
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
            $table->unsignedSmallInteger('page_margin_top')->default(12);
            $table->unsignedSmallInteger('page_margin_bottom')->default(12);
            $table->unsignedSmallInteger('page_margin_left')->default(15);
            $table->unsignedSmallInteger('page_margin_right')->default(15);
            $table->string('line_spacing')->default('1.15');

            // Format & HTML override
            $table->string('format')->default('form');
            $table->longText('content_html')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_timing_injection_pumps');
    }
};
