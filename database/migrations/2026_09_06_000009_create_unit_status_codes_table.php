<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Star-Stop status codes (RSH, FO, MOH, ...). Global by default (unit_id null),
 * with an optional per-unit override row. The full catalogue is not final, so
 * this master is user-maintainable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_status_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('label');
            $table->string('category');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['unit_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_status_codes');
    }
};
