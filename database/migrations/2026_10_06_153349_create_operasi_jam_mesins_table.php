<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('operasi_jam_mesin', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('jenis', 20);
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->json('readings')->nullable();
            $table->json('totals_by_machine')->nullable();
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'jenis', 'month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operasi_jam_mesin');
    }
};
