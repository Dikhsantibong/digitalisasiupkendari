<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily security patrol logs (POA checkpoints, cumulative per month) and the two
 * periodic-check tables that read their subjects from the K3 masters: APAR/APAB
 * condition checks (per extinguisher) and emergency-facility readiness (per
 * equipment, weekly or monthly).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_patrols', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->date('patrol_date');
            $table->foreignId('patrol_location_id')->constrained('patrol_locations')->cascadeOnDelete();
            $table->json('scan_times')->nullable();
            $table->unsignedInteger('total_scan')->default(0);
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'patrol_location_id', 'patrol_date'], 'security_patrols_loc_date_unique');
        });

        Schema::create('fire_extinguisher_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('fire_extinguisher_id')->constrained('fire_extinguishers')->cascadeOnDelete();
            $table->date('tgl_periksa')->nullable();
            $table->string('kondisi_tabung')->nullable();
            $table->string('kondisi_nozzle')->nullable();
            $table->string('indikator_tekanan')->nullable();
            $table->string('kondisi_pin_segel')->nullable();
            $table->date('exp_date')->nullable();
            $table->string('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'fire_extinguisher_id', 'year', 'month'], 'apar_checks_period_unique');
        });

        Schema::create('emergency_facility_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('week')->nullable(); // null = monthly, 1-4 = weekly
            $table->foreignId('emergency_equipment_id')->constrained('emergency_equipments')->cascadeOnDelete();
            $table->unsignedInteger('jml_total')->default(0);
            $table->unsignedInteger('jml_ready')->default(0);
            $table->unsignedInteger('jml_not_ready')->default(0);
            $table->string('kendala')->nullable();
            $table->string('tindak_lanjut')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'emergency_equipment_id', 'year', 'month', 'week'], 'emergency_checks_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_facility_checks');
        Schema::dropIfExists('fire_extinguisher_checks');
        Schema::dropIfExists('security_patrols');
    }
};
