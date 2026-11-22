<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logsheet per jenis mesin (user, 2026-11-21): PLTD Poasia (Mirrlees) and PLTD
 * Poasia Containerized (Cummins) read different parameters. A template holds
 * the logsheet parameters (multi-level headers, Normal / Min-Alarm / Max-Trip,
 * input type), its time slots and, optionally, the Patrol Check Mesin items;
 * each machine points at one template, otherwise the default one is used.
 *
 * The existing parameter set becomes the default "Cummins (Containerized)"
 * template, so every stored reading keeps its parameter. The Mirrlees template
 * follows the PLTD Poasia "Logsheet Lokal02 — Mesin Mirrlees" form and is
 * given to the PLTD Poasia machines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logsheet_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('time_slots')->nullable();
            $table->json('patrol_items')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('logsheet_parameters', function (Blueprint $table): void {
            $table->foreignId('template_id')->nullable()->after('id')->constrained('logsheet_templates')->cascadeOnDelete();
            $table->string('group_label')->nullable()->after('code');
            $table->string('input_type')->default('number')->after('sub_channel');
            $table->json('options')->nullable()->after('input_type');
            $table->string('normal_value')->nullable()->after('options');
            $table->string('alarm_value')->nullable()->after('normal_value');
            $table->string('trip_value')->nullable()->after('alarm_value');
        });

        Schema::table('machines', function (Blueprint $table): void {
            $table->foreignId('logsheet_template_id')->nullable()->after('unit_id')->constrained('logsheet_templates')->nullOnDelete();
        });

        Schema::table('operator_logsheet_readings', function (Blueprint $table): void {
            $table->string('value_text', 60)->nullable()->after('value');
        });

        $now = now();
        $cummins = DB::table('logsheet_templates')->insertGetId([
            'code' => 'cummins-containerized',
            'name' => 'Cummins (Containerized)',
            'description' => 'Logsheet mesin Cummins — PLTD Poasia Containerized; bawaan untuk mesin yang belum diberi template.',
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('logsheet_parameters')->whereNull('template_id')->update(['template_id' => $cummins]);

        $mirrlees = DB::table('logsheet_templates')->insertGetId([
            'code' => 'mirrlees-pltd-poasia',
            'name' => 'Mirrlees (PLTD Poasia)',
            'description' => 'Laporan Isian Data Operator Lokal — Logsheet Lokal02, Mesin Mirrlees PLTD Poasia (setiap jam).',
            'time_slots' => json_encode(array_map(fn (int $h): string => sprintf('%02d:00', $h), range(1, 24))),
            'is_default' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rows = [];
        $add = function (string $code, ?string $group, string $name, ?string $sub, ?string $uom, ?string $normal, ?string $alarm, ?string $trip, string $type = 'number', ?array $options = null) use (&$rows, $mirrlees, $now): void {
            $rows[] = [
                'template_id' => $mirrlees, 'plant_type' => 'all', 'code' => $code, 'group_label' => $group, 'name' => $name,
                'sub_channel' => $sub, 'unit_of_measure' => $uom, 'input_type' => $type, 'options' => $options === null ? null : json_encode($options),
                'normal_value' => $normal, 'alarm_value' => $alarm, 'trip_value' => $trip,
                'sort_order' => count($rows), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ];
        };

        $add('LOAD', null, 'Beban', null, 'kW', '1500', '1300', '2860');
        foreach (['A', 'B'] as $bank) {
            foreach (range(1, 9) as $i) {
                $add("STUD_{$bank}{$i}", 'Temperature Stud Main Bearing', "Bank {$bank}", (string) $i, '°C', '60', '56', '65');
            }
        }
        $add('TEMP_RUANGAN', null, 'Temp. Ruangan', null, '°C', '39', '36', '45');
        $add('TEMP_CRANKSHAFT_FLYWHEEL', null, 'Temp Crankshaft Flywheel', null, '°C', '50', '46', '54');
        $add('OIL_IN_ENGINE', null, 'Oil In To Engine', null, '°C', '55-62', '68', '73');
        $add('OIL_OUT_ENGINE', null, 'Oil Out Engine', null, '°C', '55-62', '68', '73');
        $add('LEVEL_OLI_RUN', null, 'Level Oli Mesin/Carter', 'Run', 'Liter', 'MAX', 'MAX -2', 'MAX +3', 'text');
        $add('LEVEL_OLI_STOP', null, 'Level Oli Mesin/Carter', 'Stop', 'Liter', 'MAX +2', 'MAX -1', 'MAX +4', 'text');
        $add('BACKWASH_IN', null, 'Filter Auto Back Wash', 'IN', 'Liter', 'Pembacaan langsung', null, null);
        $add('BACKWASH_OUT', null, 'Filter Auto Back Wash', 'OUT', 'Liter', 'Pembacaan langsung', null, null);
        $add('LEVEL_EXPANSION_TANK', null, 'Level Expansion Tank', null, 'L/N/H', 'N', 'L', 'H', 'choice', ['L', 'N', 'H']);
        $add('TEMP_TRAFO', null, 'Trafo', null, '°C', '85', '80', '90');
        $add('LEVEL_OIL_TRAFO', null, 'Level Oil', null, 'L/N/H', 'N', 'L', 'H', 'choice', ['L', 'N', 'H']);
        $add('TEMP_OIL_TRAFO', null, 'Temp Oil', null, '°C', '90', '85', '95');
        DB::table('logsheet_parameters')->insert($rows);

        // The PLTD Poasia machines (not the Containerized unit) read the Mirrlees form.
        $poasia = DB::table('units')->where('code', 'PLTD-POASIA')->value('id');
        if ($poasia !== null) {
            DB::table('machines')->where('unit_id', $poasia)->update(['logsheet_template_id' => $mirrlees]);
        }
    }

    public function down(): void
    {
        $mirrlees = DB::table('logsheet_templates')->where('code', 'mirrlees-pltd-poasia')->value('id');
        if ($mirrlees !== null) {
            DB::table('logsheet_parameters')->where('template_id', $mirrlees)->delete();
        }

        Schema::table('operator_logsheet_readings', function (Blueprint $table): void {
            $table->dropColumn('value_text');
        });
        Schema::table('machines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('logsheet_template_id');
        });
        Schema::table('logsheet_parameters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn(['group_label', 'input_type', 'options', 'normal_value', 'alarm_value', 'trip_value']);
        });
        Schema::dropIfExists('logsheet_templates');
    }
};
