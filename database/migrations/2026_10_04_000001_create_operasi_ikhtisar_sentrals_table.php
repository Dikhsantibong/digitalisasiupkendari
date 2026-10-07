<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operasi_ikhtisar_sentrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->smallInteger('year');

            // Ringkasan Sentral
            $table->decimal('kwh_dibangkit', 18, 2)->default(0);
            $table->decimal('kwh_pemakaian_sendiri', 18, 2)->default(0);
            $table->decimal('kwh_disalurkan', 18, 2)->default(0);
            $table->decimal('beban_puncak_pagi_kw', 14, 2)->default(0);
            $table->decimal('beban_puncak_malam_kw', 14, 2)->default(0);
            $table->decimal('jam_jalan_perhari', 8, 2)->default(24);

            // Ikhtisar Persediaan Bahan Bakar & Pelumas
            $table->json('persediaan_awal')->nullable();
            $table->json('penerimaan')->nullable();
            $table->json('penerimaan_sewa_smp')->nullable();
            $table->json('pemakaian_non_operasi')->nullable();
            $table->json('pengiriman')->nullable();

            $table->text('catatan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'month', 'year']);
            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('operasi_ikhtisar_sentral_mesins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ikhtisar_sentral_id')->constrained('operasi_ikhtisar_sentrals', indexName: 'ops_ikhtisar_m_parent_fk')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units', indexName: 'ops_ikhtisar_m_unit_fk')->cascadeOnDelete();
            $table->foreignId('engine_id')->constrained('machines', indexName: 'ops_ikhtisar_m_engine_fk')->cascadeOnDelete();

            $table->decimal('kwh_dibangkit', 18, 2)->default(0);
            $table->decimal('jam_jalan', 10, 2)->default(0);
            $table->decimal('pemakaian_hsd', 14, 2)->default(0);
            $table->decimal('pemakaian_mfo', 14, 2)->default(0);
            $table->decimal('sfc', 8, 4)->default(0);
            $table->decimal('t_kalor', 10, 2)->default(0);
            $table->decimal('slc', 8, 4)->default(0);
            $table->json('pemakaian_pelumas')->nullable();

            $table->timestamps();

            $table->unique(['ikhtisar_sentral_id', 'engine_id'], 'ops_ikhtisar_m_engine_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operasi_ikhtisar_sentral_mesins');
        Schema::dropIfExists('operasi_ikhtisar_sentrals');
    }
};
