<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master kWh Meter (per unit): the meters of the Opname kWh — kWh produksi,
     * kWh PS and susut trafo — optionally tied to a mesin.
     */
    public function up(): void
    {
        Schema::create('kwh_meters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kategori', 20);
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['unit_id', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kwh_meters');
    }
};
