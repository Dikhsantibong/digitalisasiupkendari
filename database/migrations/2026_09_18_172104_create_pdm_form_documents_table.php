<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generic PdM input forms defined in App\Support\PdmForms (checklist 5S5R,
     * kualitas air pendingin, vibrasi, pelumas, kontrol material): one document
     * per unit, form, period and — for per-machine forms — subject (machine).
     */
    public function up(): void
    {
        Schema::create('pdm_form_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('form', 50);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('subject', 100)->default('');
            $table->json('header')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'form', 'year', 'month', 'subject'], 'pdm_form_documents_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdm_form_documents');
    }
};
