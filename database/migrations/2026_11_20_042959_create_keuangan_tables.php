<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Administrasi & Keuangan per UL (user, 2026-11-20): the Master Supplier
     * (nama, email, NPWP, foto KTP) and the monthly Laporan Realisasi Cash
     * Card with its rows (each with its own photo attachments).
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('npwp', 40)->nullable();
            $table->string('ktp_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['service_unit_id', 'name']);
        });

        Schema::create('cash_card_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('saldo_awal', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['service_unit_id', 'month', 'year']);
        });

        Schema::create('cash_card_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_card_report_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->date('tanggal')->nullable();
            $table->unsignedSmallInteger('period')->nullable();
            $table->unsignedSmallInteger('no_entry')->nullable();
            $table->text('uraian')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('penerimaan', 15, 2)->default(0);
            $table->decimal('pengeluaran', 15, 2)->default(0);
            $table->string('kode_perkiraan', 60)->nullable();
            $table->string('kode_sap', 60)->nullable();
            $table->string('cost_centre', 60)->nullable();
            $table->json('photos')->nullable(); // public-disk paths of the nota / barang photos
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_card_entries');
        Schema::dropIfExists('cash_card_reports');
        Schema::dropIfExists('suppliers');
    }
};
