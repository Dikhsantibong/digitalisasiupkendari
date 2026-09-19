<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('har_vibrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->date('test_date');
            $table->string('document_number')->default('FMKD-314-10.3.3.a-B10');
            $table->string('revision')->default('03');
            $table->string('effective_date')->default('31 Juli 2024');

            // Technical specs
            $table->string('brand')->nullable()->default('MAK');
            $table->string('model_type')->nullable()->default('8M 453 C');
            $table->string('installed_power')->nullable()->default('2800');
            $table->string('capable_power')->nullable()->default('1500 kW');
            $table->string('serial_number')->nullable();
            $table->string('machine_number')->nullable()->default('4');
            $table->string('rpm')->nullable()->default('600');

            // Measurements data (16 points)
            $table->json('measurements');

            // Summary sections
            $table->text('standard_text')->nullable();
            $table->text('max_text')->nullable();
            $table->text('conclusion_text')->nullable();

            // Signatories (3 Kolom: Mengetahui, Diperiksa, Dibuat)
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

            $table->unique(['unit_id', 'machine_id', 'test_date'], 'har_vibrations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_vibrations');
    }
};
