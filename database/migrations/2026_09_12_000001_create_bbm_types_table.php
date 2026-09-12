<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global catalogue of fuel (BBM) types — HSD, B30, B40, MFO. A reference master
 * (like status codes): national standards, not per unit. Fuel type on tanks and
 * machines remains a separate enum for now; this list is managed via the operasi
 * master CRUD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bbm_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bbm_types');
    }
};
