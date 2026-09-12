<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * National / commemorative holidays used to shade the schedule grid columns.
 * They do not change shifts (the plant runs 24 hours) — they are a visual aid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->date('date')->unique();
            $table->string('day_name')->nullable();
            $table->string('description');
            $table->boolean('is_national')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
