<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order matters: roles must exist before the Super Admin can be assigned one.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            WorkModuleSeeder::class,
            OrganizationSeeder::class,
            MachineSeeder::class,
            OperasiPoasiaSeeder::class,
            OperasiMasterSeeder::class,
            HarMasterSeeder::class,
            K3MasterDataSeeder::class,
            LogsheetParameterSeeder::class,
            DocumentTemplateSeeder::class,
            SuperAdminSeeder::class,
            DemoAccountSeeder::class,
        ]);
    }
}
