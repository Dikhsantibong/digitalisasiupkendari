<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formulir K3 berbasis lembar (Sarana Prasarana, Kontrol K3 Mingguan,
     * Pemeliharaan TPS LB3, Pemeliharaan Oil Trap): satu dokumen per unit +
     * formulir + periode; isi tabel disimpan di `data` sesuai K3FormulirRegistry.
     */
    public function up(): void
    {
        Schema::create('k3_formulir_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('form', 50);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('week')->default(0);
            $table->json('data');
            $table->text('catatan')->nullable();

            $table->string('document_number', 100)->nullable();
            $table->string('revision', 20)->nullable();
            $table->string('effective_date', 100)->nullable();
            $table->foreignId('manager_ul_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('manager_ul_name', 150)->nullable();
            $table->string('manager_ul_title', 150)->nullable();
            $table->foreignId('tl_k3_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('tl_k3_name', 150)->nullable();
            $table->string('tl_k3_title', 150)->nullable();
            $table->foreignId('staff_k3_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('staff_k3_name', 150)->nullable();
            $table->string('staff_k3_title', 150)->nullable();
            $table->string('sign_place_date', 150)->nullable();
            $table->unsignedTinyInteger('page_margin_top')->nullable();
            $table->unsignedTinyInteger('page_margin_bottom')->nullable();
            $table->unsignedTinyInteger('page_margin_left')->nullable();
            $table->unsignedTinyInteger('page_margin_right')->nullable();
            $table->string('line_spacing', 10)->nullable();
            $table->string('format', 10)->default('form');
            $table->longText('content_html')->nullable();

            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'form', 'year', 'month', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('k3_formulir_records');
    }
};
