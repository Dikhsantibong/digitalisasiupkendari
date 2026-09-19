<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rows of the Logistik & Gudang table forms (App\Support\LogistikForms):
 * Laporan Pendukung, Peralatan/Material/Tools, Kondisi Stok, Unsafe Action &
 * Condition. `data` holds the row's column values (photo paths included).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistik_form_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('form', 40);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('section', 40)->nullable();
            $table->json('data');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'form', 'year', 'month'], 'logistik_form_rows_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistik_form_rows');
    }
};
