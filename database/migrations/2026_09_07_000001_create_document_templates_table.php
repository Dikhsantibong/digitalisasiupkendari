<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document template settings for the Berita Acara engine. A row with a null
 * unit is the global default; a row with a unit overrides it for that unit.
 * The letter number is fixed here (never auto-incremented) and editable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('module_code')->default('operasi');
            $table->string('type');
            $table->foreignId('unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('document_number');
            $table->string('revision')->default('00');
            $table->date('revision_date')->nullable();
            $table->timestamps();

            $table->unique(['type', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
