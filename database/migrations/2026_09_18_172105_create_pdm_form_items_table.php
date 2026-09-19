<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rows of a generic PdM form document; `section` and the keys of `data`
     * follow the form's definition (App\Support\PdmForms).
     */
    public function up(): void
    {
        Schema::create('pdm_form_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pdm_form_document_id')->constrained('pdm_form_documents')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('section', 40);
            $table->json('data');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['pdm_form_document_id', 'section'], 'pdm_form_items_document_section_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdm_form_items');
    }
};
