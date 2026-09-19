<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('har_battery_voltages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->cascadeOnDelete();
            $table->date('test_date');
            $table->string('document_number')->default('SMT-FM-KIT-02.07');
            $table->string('revision')->default('01');
            $table->string('effective_date')->default('13 Oktober 2021');

            // Technical specs snapshot / input
            $table->string('brand')->nullable()->default('MAK');
            $table->string('model_type')->nullable()->default('8M 453 AK');
            $table->string('serial_number')->nullable();
            $table->string('machine_number')->nullable()->default('1,2,3');
            $table->string('installed_power')->nullable()->default('2544');
            $table->string('capable_power')->nullable();
            $table->string('rpm')->nullable()->default('600');

            // 24V measurements (12 cells) & summary
            $table->json('cells_24v');
            $table->json('summary_24v')->nullable();

            // 110V measurements (55 cells) & summary
            $table->json('cells_110v');
            $table->json('summary_110v')->nullable();

            // Charging & Rectifier conditions (9 rows)
            $table->json('charging_conditions');
            $table->text('notes')->nullable();

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

            $table->unique(['unit_id', 'machine_id', 'test_date'], 'har_battery_voltages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('har_battery_voltages');
    }
};
