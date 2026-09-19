<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional K3 monthly input registers: hydrant inspection, CCTV list, fire
 * alarm inspection, K3/B3 signage (rambu) inspection, and the emergency
 * facility readiness matrix. All scoped per unit + year + month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('k3_hydrant_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('lokasi')->nullable();
            $table->string('jenis')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('hose')->nullable();
            $table->string('nozzle')->nullable();
            $table->string('box')->nullable();
            $table->string('tekanan')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('foto')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('k3_cctv_lists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('no_cctv')->nullable();
            $table->string('titik_lokasi')->nullable();
            $table->string('status')->default('on'); // on | off | rusak
            $table->string('foto_terpasang')->nullable();
            $table->string('foto_tampilan')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('k3_fire_alarm_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('lokasi')->nullable();
            $table->date('tanggal_periksa')->nullable();
            $table->string('kondisi')->nullable();
            $table->string('panel_indikator')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('k3_rambu_inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('rambu')->nullable();
            $table->string('lokasi')->nullable();
            $table->string('kondisi')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('k3_emergency_facility_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('grup')->default('EMERGENCY FACILITY');
            $table->unsignedSmallInteger('no_urut')->nullable();
            $table->string('nama_peralatan');
            $table->unsignedInteger('jml_total')->default(0);
            $table->unsignedInteger('jml_ready')->default(0);
            $table->unsignedInteger('jml_not_ready')->default(0);
            $table->string('lokasi')->nullable();
            $table->string('kendala')->nullable();
            $table->string('tindak_lanjut')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unit_id', 'year', 'month', 'grup']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('k3_emergency_facility_checks');
        Schema::dropIfExists('k3_rambu_inspections');
        Schema::dropIfExists('k3_fire_alarm_inspections');
        Schema::dropIfExists('k3_cctv_lists');
        Schema::dropIfExists('k3_hydrant_inspections');
    }
};
