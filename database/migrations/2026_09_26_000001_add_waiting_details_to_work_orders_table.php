<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom WO untuk tabel WO Waiting Shutdown (FMKD-314-10.3.3-A16) & WO
     * Waiting Material dan Jasa (A17): ASSETNUM, OWNER GROUP, WOPRIOR TEXT dan
     * rincian material/jasa per WO (deskripsi, no stockcode/part, jumlah).
     * Juga melengkapi master status WO (WMATL/WENG/WSCH) dan work group
     * (Kontrol & Instrumen, Sipil) yang dipakai lembar tersebut.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->string('assetnum', 100)->nullable()->after('engine_id');
            $table->string('owner_group', 50)->nullable()->after('work_group_id');
            $table->string('priority_text', 100)->nullable()->after('waiting_reason');
            $table->json('materials')->nullable()->after('priority_text');
        });

        $now = now();
        $statuses = [
            ['code' => 'WMATL', 'name' => 'Waiting Material', 'is_closed' => false],
            ['code' => 'WENG', 'name' => 'Waiting Engineering', 'is_closed' => false],
            ['code' => 'WSCH', 'name' => 'Waiting Schedule / Shutdown', 'is_closed' => false],
        ];
        $nextStatus = (int) DB::table('wo_statuses')->max('sort_order') + 1;
        foreach ($statuses as $i => $status) {
            if (DB::table('wo_statuses')->where('code', $status['code'])->doesntExist()) {
                DB::table('wo_statuses')->insert([...$status, 'sort_order' => $nextStatus + $i, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $groups = [
            ['code' => 'INSTD', 'name' => 'Kontrol & Instrumen'],
            ['code' => 'CIVD', 'name' => 'Sipil'],
        ];
        $nextGroup = (int) DB::table('work_groups')->max('sort_order') + 1;
        foreach ($groups as $i => $group) {
            if (DB::table('work_groups')->where('code', $group['code'])->doesntExist()) {
                DB::table('work_groups')->insert([...$group, 'sort_order' => $nextGroup + $i, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn(['assetnum', 'owner_group', 'priority_text', 'materials']);
        });
    }
};
