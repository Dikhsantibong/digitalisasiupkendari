<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core K3 input tables: the Time Frame plan-vs-realisation matrix, accident
 * reports (PAK/PAHK, mostly NIHIL), and the reusable inspection engine
 * (inspections + inspection_results) that backs every uniform checklist form.
 * All scoped per unit + year/month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('k3_activity_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->foreignId('k3_activity_type_id')->constrained('k3_activity_types')->cascadeOnDelete();
            $table->string('pic')->nullable();
            $table->json('plan_days')->nullable();
            $table->json('real_days')->nullable();
            $table->string('status')->nullable();
            $table->string('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_id', 'year', 'month', 'k3_activity_type_id'], 'k3_activity_plans_period_type_unique');
        });

        Schema::create('accident_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->date('incident_date')->nullable();
            $table->string('fungsi')->nullable();
            $table->string('lokasi')->nullable();
            $table->string('category');
            $table->unsignedInteger('luka_ringan')->default(0);
            $table->unsignedInteger('luka_berat')->default(0);
            $table->unsignedInteger('meninggal')->default(0);
            $table->decimal('kerugian_material', 15, 2)->nullable();
            $table->boolean('is_nihil')->default(false);
            $table->string('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month']);
        });

        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('form_code');
            $table->date('inspection_date')->nullable();
            $table->string('inspector_team')->nullable();
            $table->string('ketua_tim')->nullable();
            $table->string('keterangan')->nullable();
            $table->foreignId('input_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_id', 'year', 'month', 'form_code']);
        });

        Schema::create('inspection_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->string('item_ref');
            $table->string('kondisi')->nullable();
            $table->string('tindak_lanjut')->nullable();
            $table->string('nilai')->nullable();
            $table->string('catatan')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_results');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('accident_reports');
        Schema::dropIfExists('k3_activity_plans');
    }
};
