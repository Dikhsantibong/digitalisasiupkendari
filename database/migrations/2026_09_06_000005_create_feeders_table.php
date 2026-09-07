<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-unit master of outgoing feeders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feeders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('feeder_type')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['unit_id', 'name']);
            $table->index(['unit_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feeders');
    }
};
