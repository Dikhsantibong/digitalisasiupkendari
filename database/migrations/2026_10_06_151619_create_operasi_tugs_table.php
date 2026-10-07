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
        Schema::create('operasi_tugs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->string('jenis', 20);
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('nomor')->nullable();
            $table->string('pekerjaan')->default('RUTIN');
            $table->string('no_spk')->nullable();
            $table->string('cost_center')->nullable();
            $table->string('kode_perkiraan')->nullable();
            $table->date('tanggal_dokumen')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'machine_id', 'jenis', 'month', 'year'], 'uq_operasi_tugs_period');
        });

        Schema::table('bbm_types', function (Blueprint $table): void {
            $table->string('material_code', 30)->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bbm_types', function (Blueprint $table): void {
            $table->dropColumn('material_code');
        });

        Schema::dropIfExists('operasi_tugs');
    }
};
