<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lembar Mutasi Operator: the shift handover sheet of a unit, one per
     * date + shift (pagi 08-16, sore 16-22, malam 22-08 WITA). JSON sections:
     * mesin [{machine_id, nama, level_bbm, tambah_bbm, pelumas, status}],
     * tangki [{nama, level_cm}], peralatan [{nama, ada, jumlah}],
     * kejadian [{jam, uraian}]. The handing-over regu signs (paraf PNG path),
     * then the receiving regu signs to accept, which locks the sheet.
     */
    public function up(): void
    {
        Schema::create('operator_mutasis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('shift', 10);
            $table->json('mesin');
            $table->json('tangki');
            $table->json('peralatan');
            $table->json('kejadian');
            $table->text('gangguan_mesin')->nullable();
            $table->text('catatan')->nullable();
            $table->string('regu_penyerah', 5)->nullable();
            $table->string('penyerah_nama', 150)->nullable();
            $table->string('paraf_penyerah')->nullable();
            $table->timestamp('diserahkan_at')->nullable();
            $table->string('regu_penerima', 5)->nullable();
            $table->string('penerima_nama', 150)->nullable();
            $table->string('paraf_penerima')->nullable();
            $table->timestamp('diterima_at')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'tanggal', 'shift']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_mutasis');
    }
};
