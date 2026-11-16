<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Master orchestrator seeder that populates realistic, PRD-aligned dummy data
 * across all modules (Master & User Alignment, Operator, Operasi Akses 1 & 2,
 * Pemeliharaan/HAR Akses 1 & 2, K3 & Keamanan Akses 1 & 2, Logistik & Gudang,
 * PdM & Maturity Level, and Report Documents + Workflows).
 *
 * Usage:
 *   php artisan db:seed --class=DummyDataSeeder
 */
class DummyDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            DummyMasterAlignmentSeeder::class,
            DummyOperatorAndOperasiSeeder::class,
            DummyHarSeeder::class,
            DummyK3Seeder::class,
            DummyLogistikPdmSeeder::class,
            DummyReportWorkflowSeeder::class,
        ]);
    }
}
