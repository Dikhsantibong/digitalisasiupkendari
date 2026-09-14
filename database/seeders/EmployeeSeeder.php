<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * A small roster of employees per unit so the Absensi schedule grid has rows to
 * work with: six shift operators (regu A/B/C, two each) and two non-shift staff.
 * Idempotent (keyed by NIP). Real rosters are maintained through the Pegawai
 * master; this only bootstraps a usable demo.
 */
class EmployeeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        (new OrganizationSeeder)->seedEmployees();
    }
}
