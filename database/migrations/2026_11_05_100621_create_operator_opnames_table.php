<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opname BBM, Opname Pelumas and Opname kWh (modul Operator): one sheet per
     * unit, tanggal and jenis. `data` = {section: {item: {field: number}}};
     * subtotals and the kWh summary are computed, never stored.
     */
    public function up(): void
    {
        Schema::create('operator_opnames', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('jenis', 20);
            $table->json('data');
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'tanggal', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_opnames');
    }
};
