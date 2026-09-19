<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rows of the sample monitoring form: section "pengiriman" (A), "hasil" (B)
     * or "temuan" (D). The columns of each section are defined in
     * App\Support\PdmSampleMonitoringForm and stored in `data`.
     */
    public function up(): void
    {
        Schema::create('pdm_sample_monitoring_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pdm_sample_monitoring_id')->constrained('pdm_sample_monitorings')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('section', 20);
            $table->json('data');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['pdm_sample_monitoring_id', 'section'], 'pdm_sample_items_monitoring_section_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdm_sample_monitoring_items');
    }
};
