<?php

namespace Database\Seeders;

use App\Enums\EmployeePosition;
use App\Enums\FuelType;
use App\Enums\KwhMeterKategori;
use App\Enums\RoleName;
use App\Enums\TankFuelType;
use App\Enums\TankJenis;
use App\Enums\UnitContract;
use App\Enums\UnitStatus;
use App\Enums\UnitTlStructure;
use App\Models\AuxiliarySource;
use App\Models\BbmType;
use App\Models\Employee;
use App\Models\Feeder;
use App\Models\FuelTank;
use App\Models\KwhMeter;
use App\Models\LubricantType;
use App\Models\Machine;
use App\Models\Role;
use App\Models\ServiceUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Aligns ServiceUnits, Units, Employees, Users, Machines, FuelTanks, KwhMeters,
 * Lubricants, Feeders, Auxiliaries, and K3/Shift masters across all active units
 * while preserving existing custom employee/user names.
 */
class DummyMasterAlignmentSeeder extends Seeder
{
    private const UNIT_COORDS = [
        'PLTD-POASIA' => [-3.9985, 122.5672, 34.40, UnitContract::Kit11],
        'PLTD-POASIA-CONT' => [-3.9990, 122.5680, 10.00, UnitContract::Kit11],
        'PLTD-BAUBAU' => [-5.4633, 122.6012, 16.50, UnitContract::Kit11],
        'PLTD-WANGIWANGI' => [-5.3182, 123.5410, 4.50, UnitContract::Kit11],
        'PLTD-RAHA' => [-4.8380, 122.7230, 8.20, UnitContract::Kit11],
        'PLTD-EREKE' => [-4.8210, 123.1650, 2.50, UnitContract::Kit6],
        'PLTM-RONGI' => [-5.5810, 122.7420, 1.20, UnitContract::Kit6],
        'PLTM-WINNING' => [-5.3920, 122.8910, 1.00, UnitContract::Kit6],
        'PLTD-KOLAKA' => [-4.0620, 121.6080, 14.00, UnitContract::Kit11],
        'PLTG-KOLAKA' => [-4.0630, 121.6090, 20.00, UnitContract::Kit11],
        'PLTM-SABILAMBO' => [-4.0890, 121.6350, 2.40, UnitContract::Kit11],
        'PLTM-MIKUASI' => [-3.9120, 121.5120, 1.60, UnitContract::Kit6],
        'PLTD-LANIPANIPA' => [-3.7850, 121.4210, 3.20, UnitContract::Kit11],
        'PLTD-WUAWUA' => [-3.9870, 122.5120, 25.00, UnitContract::Kit11],
        'PLTD-LANGARA' => [-4.0320, 122.9920, 3.00, UnitContract::Kit6],
        'PLTD-PASARWAJO' => [-5.4410, 122.8420, 4.00, UnitContract::Kit6],
        'PLTU-BARUTA' => [-5.4110, 122.6320, 14.00, UnitContract::Kit11],
        'PLTMG-BAUBAU' => [-5.4650, 122.6040, 30.00, UnitContract::Kit11],
        'PLTU-MORAMO' => [-4.1120, 122.6810, 100.00, UnitContract::Kit11],
        'PLTMG-KENDARI' => [-3.9970, 122.5690, 50.00, UnitContract::Kit11],
        'PLTM-LANGARA' => [-4.0350, 122.9950, 1.50, UnitContract::Kit6],
        'PLTD-LADUMPI' => [-4.7610, 121.9810, 3.50, UnitContract::Kit6],
    ];

    private const INDONESIAN_NAMES = [
        'Andi Pratama', 'Budi Santoso', 'Candra Wijaya', 'Dedi Kurniawan',
        'Eko Prasetyo', 'Fajar Nugroho', 'Gilang Ramadhan', 'Hadi Sucipto',
        'Irfan Hakim', 'Joko Susilo', 'Kurniawan Adi', 'Lukman Hakim',
        'Muhammad Rizki', 'Nanda Putra', 'Oki Setiawan', 'Pandu Wijaya',
        'Qori Sandria', 'Rudi Hartono', 'Surya Darma', 'Taufik Hidayat',
        'Umar Bakri', 'Viktor Lengkong', 'Wahyu Hidayat', 'Yoga Pratama',
        'Zainal Abidin', 'Agus Salim', 'Bambang Pamungkas', 'Chairul Anwar',
        'Darmawan Putra', 'Erwin Gutawa', 'Ferry Irawan', 'Gunawan Wibisono',
        'Hendra Setiawan', 'Indra Lesmana', 'Junaedi Akbar', 'Kaharuddin Syah',
    ];

    public function run(): void
    {
        $this->alignServiceUnitsAndUnits();
        $this->call([
            MachineSeeder::class,
            ShiftPatternSeeder::class,
            OperasiMasterSeeder::class,
            K3MasterDataSeeder::class,
        ]);
        $this->alignMachinesAndTanks();
        $this->alignEmployeesAndUsers();
    }

    private function alignServiceUnitsAndUnits(): void
    {
        $poasiaSu = ServiceUnit::query()->where('code', 'UL-POASIA')->first();
        $baubauSu = ServiceUnit::query()->where('code', 'UL-BAUBAU')->first();
        $kolakaSu = ServiceUnit::query()->where('code', 'UL-KOLAKA')->first();
        $wuawuaSu = ServiceUnit::query()->whereIn('code', ['UL-WUA WUA', 'UL-WUAWUA'])->first();

        if (! $wuawuaSu) {
            $wuawuaSu = ServiceUnit::query()->create([
                'code' => 'UL-WUA WUA',
                'name' => 'UL PLTD Wua Wua',
                'location' => 'Kota Kendari',
                'is_active' => true,
            ]);
        }

        $suMap = [
            'PLTD-POASIA' => $poasiaSu?->id,
            'PLTD-POASIA-CONT' => $poasiaSu?->id,
            'PLTD-BAUBAU' => $baubauSu?->id,
            'PLTD-WANGIWANGI' => $baubauSu?->id,
            'PLTD-RAHA' => $baubauSu?->id,
            'PLTD-EREKE' => $baubauSu?->id,
            'PLTM-RONGI' => $baubauSu?->id,
            'PLTM-WINNING' => $baubauSu?->id,
            'PLTU-BARUTA' => $baubauSu?->id,
            'PLTMG-BAUBAU' => $baubauSu?->id,
            'PLTD-KOLAKA' => $kolakaSu?->id,
            'PLTG-KOLAKA' => $kolakaSu?->id,
            'PLTM-SABILAMBO' => $kolakaSu?->id,
            'PLTM-MIKUASI' => $kolakaSu?->id,
            'PLTD-LANIPANIPA' => $kolakaSu?->id,
            'PLTD-WUAWUA' => $wuawuaSu?->id,
            'PLTD-LANGARA' => $wuawuaSu?->id,
            'PLTD-PASARWAJO' => $wuawuaSu?->id,
            'PLTU-MORAMO' => $wuawuaSu?->id,
            'PLTMG-KENDARI' => $wuawuaSu?->id,
            'PLTM-LANGARA' => $wuawuaSu?->id,
            'PLTD-LADUMPI' => $wuawuaSu?->id,
        ];

        foreach (Unit::query()->where('is_active', true)->get() as $unit) {
            $coords = self::UNIT_COORDS[$unit->code] ?? [-3.9985, 122.5672, 5.00, UnitContract::Kit11];
            $updates = [
                'latitude' => $unit->latitude ?? $coords[0],
                'longitude' => $unit->longitude ?? $coords[1],
                'attendance_radius_m' => $unit->attendance_radius_m ?: 250,
                'installed_capacity_mw' => $unit->installed_capacity_mw ?: $coords[2],
                'contract' => $unit->contract ?? $coords[3],
                'status' => $unit->status ?? UnitStatus::Operasi,
            ];
            if ($unit->service_unit_id === null && isset($suMap[$unit->code])) {
                $updates['service_unit_id'] = $suMap[$unit->code];
            }
            $unit->update($updates);
        }
    }

    private function alignMachinesAndTanks(): void
    {
        $hsdBbm = BbmType::query()->firstOrCreate(
            ['code' => 'HSD'],
            ['name' => 'HSD (High Speed Diesel)', 'sort_order' => 1, 'is_active' => true],
        );
        $mfoBbm = BbmType::query()->firstOrCreate(
            ['code' => 'MFO'],
            ['name' => 'MFO (Marine Fuel Oil)', 'sort_order' => 2, 'is_active' => true],
        );

        foreach (Unit::query()->where('is_active', true)->get() as $unit) {
            $machines = Machine::query()->where('unit_id', $unit->id)->orderBy('id')->get();
            if ($machines->isEmpty()) {
                for ($i = 1; $i <= 2; $i++) {
                    $machines->push(Machine::query()->create([
                        'unit_id' => $unit->id,
                        'name' => "Unit #{$i} — {$unit->name}",
                        'merk' => 'Caterpillar',
                        'type' => '3512B',
                        'fuel_type' => FuelType::HsdOnly,
                        'serial_number' => "{$unit->code}-SN-00{$i}",
                        'engine_hp' => '1500',
                        'engine_rpm' => 1500,
                        'tahun_pembuatan' => 2018,
                        'generator_merk' => 'Stamford',
                        'generator_type' => 'HCI634J',
                        'generator_serial_number' => "{$unit->code}-GEN-00{$i}",
                        'generator_volt' => 400,
                        'generator_kva' => '1250.00',
                        'generator_cos_phi' => '0.80',
                        'capacity_kw' => '1000.00',
                        'kwh_faktor_kali_produksi' => '1.0000',
                        'kwh_faktor_kali_ps' => '1.0000',
                        'is_active' => true,
                    ]));
                }
            }

            $lubricants = LubricantType::query()->where('unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->get();
            $defaultLubeIds = $lubricants->take(2)->pluck('id')->all();

            foreach ($machines as $idx => $machine) {
                $defaultCap = match (true) {
                    str_contains($unit->code, 'PLTU-MORAMO') => '50000.00',
                    str_contains($unit->code, 'PLTMG-KENDARI') => '10000.00',
                    str_contains($unit->code, 'PLTD-POASIA-CONT') => '1000.00',
                    str_contains($unit->code, 'PLTD-POASIA') => '5000.00',
                    str_contains($unit->code, 'PLTG') => '10000.00',
                    default => '1000.00',
                };

                $machine->update([
                    'merk' => $machine->merk ?: 'Wärtsilä / Caterpillar',
                    'type' => $machine->type ?: 'V32 / 3516B',
                    'fuel_type' => $machine->fuel_type ?? FuelType::HsdOnly,
                    'serial_number' => $machine->serial_number ?: sprintf('%s-M%02d', $unit->code, $idx + 1),
                    'engine_hp' => $machine->engine_hp ?: '1800',
                    'engine_rpm' => $machine->engine_rpm ?: 750,
                    'tahun_pembuatan' => $machine->tahun_pembuatan ?: 2016,
                    'generator_merk' => $machine->generator_merk ?: 'ABB / Leroy Somer',
                    'generator_type' => $machine->generator_type ?: 'AMG 0900',
                    'generator_volt' => $machine->generator_volt ?: 6300,
                    'generator_kva' => $machine->generator_kva ?: '1500.00',
                    'generator_cos_phi' => $machine->generator_cos_phi ?: '0.80',
                    'capacity_kw' => (float) $machine->capacity_kw > 0 ? $machine->capacity_kw : $defaultCap,
                    'kwh_faktor_kali_produksi' => (float) $machine->kwh_faktor_kali_produksi > 0 ? $machine->kwh_faktor_kali_produksi : '1.0000',
                    'kwh_faktor_kali_ps' => (float) $machine->kwh_faktor_kali_ps > 0 ? $machine->kwh_faktor_kali_ps : '1.0000',
                    'is_active' => true,
                ]);

                if ($defaultLubeIds !== []) {
                    $machine->lubricantTypes()->syncWithoutDetaching($defaultLubeIds);
                }
            }

            // Ensure FuelTanks exist (Storage + Service) with capacity_liter & jenis
            $tanks = FuelTank::query()->where('unit_id', $unit->id)->orderBy('sort_order')->get();
            if ($tanks->isEmpty()) {
                $tanks->push(FuelTank::query()->create([
                    'unit_id' => $unit->id,
                    'code' => 'T1',
                    'name' => 'Tangki Timbun #1 (HSD)',
                    'jenis' => TankJenis::Storage,
                    'bbm_type_id' => $hsdBbm->id,
                    'fuel_type' => TankFuelType::Hsd,
                    'capacity_liter' => '250000.00',
                    'is_daily_tank' => false,
                    'sort_order' => 1,
                    'is_active' => true,
                ]));
                $tanks->push(FuelTank::query()->create([
                    'unit_id' => $unit->id,
                    'code' => 'T2',
                    'name' => 'Tangki Harian #1 (HSD)',
                    'jenis' => TankJenis::Service,
                    'bbm_type_id' => $hsdBbm->id,
                    'fuel_type' => TankFuelType::Hsd,
                    'capacity_liter' => '15000.00',
                    'is_daily_tank' => true,
                    'sort_order' => 2,
                    'is_active' => true,
                ]));
            } else {
                foreach ($tanks as $tank) {
                    $isMfo = $tank->fuel_type === TankFuelType::Mfo;
                    $jenis = $tank->jenis ?? ($tank->is_daily_tank ? TankJenis::Service : TankJenis::Storage);
                    $cap = (float) $tank->capacity_liter > 0
                        ? $tank->capacity_liter
                        : ($jenis === TankJenis::Service ? '15000.00' : '500000.00');
                    $tank->update([
                        'jenis' => $jenis,
                        'bbm_type_id' => $tank->bbm_type_id ?? ($isMfo ? $mfoBbm->id : $hsdBbm->id),
                        'capacity_liter' => $cap,
                        'is_active' => true,
                    ]);
                }
            }

            // Ensure KwhMeters exist for produksi, ps, susut_trafo
            if (KwhMeter::query()->where('unit_id', $unit->id)->count() === 0) {
                $sort = 1;
                foreach ($machines->take(3) as $machine) {
                    KwhMeter::query()->create([
                        'unit_id' => $unit->id,
                        'name' => "kWh Produksi {$machine->name}",
                        'kategori' => KwhMeterKategori::Produksi,
                        'machine_id' => $machine->id,
                        'sort_order' => $sort++,
                        'is_active' => true,
                    ]);
                    KwhMeter::query()->create([
                        'unit_id' => $unit->id,
                        'name' => "kWh PS {$machine->name}",
                        'kategori' => KwhMeterKategori::Ps,
                        'machine_id' => $machine->id,
                        'sort_order' => $sort++,
                        'is_active' => true,
                    ]);
                }
                KwhMeter::query()->create([
                    'unit_id' => $unit->id,
                    'name' => 'kWh Trafo PS Utama',
                    'kategori' => KwhMeterKategori::SusutTrafo,
                    'machine_id' => null,
                    'sort_order' => $sort++,
                    'is_active' => true,
                ]);
            }

            // Ensure Feeders & AuxiliarySources
            if (Feeder::query()->where('unit_id', $unit->id)->count() === 0) {
                for ($f = 1; $f <= 2; $f++) {
                    Feeder::query()->create([
                        'unit_id' => $unit->id,
                        'name' => "Penyulang #{$f} {$unit->name}",
                        'feeder_type' => 'outgoing',
                        'sort_order' => $f,
                        'is_active' => true,
                    ]);
                }
            }
            if (AuxiliarySource::query()->where('unit_id', $unit->id)->count() === 0) {
                AuxiliarySource::query()->create([
                    'unit_id' => $unit->id,
                    'name' => 'Trafo PS #1',
                    'description' => 'Trafo Pemakaian Sendiri Utama',
                    'sort_order' => 1,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function alignEmployeesAndUsers(): void
    {
        $password = Hash::make('password');
        $roles = Role::query()->pluck('id', 'name');

        // 1. Run Poasia & Poasia Containerized real staff seeders first
        $this->call([
            PoasiaStaffSeeder::class,
            PoasiaContainerizedStaffSeeder::class,
        ]);

        // 2. Preserve/enforce custom named Poasia TLs & Manager ULs
        $poasia = Unit::query()->where('code', 'PLTD-POASIA')->first();
        if ($poasia) {
            $customPoasiaTls = [
                ['nip' => 'PLTD-POASIA-PEG-007', 'name' => 'Muh Isyak', 'pos' => EmployeePosition::TeamLeaderPemeliharaan, 'role' => RoleName::TeamLeaderPemeliharaan, 'email' => 'tl-har.pltd-poasia@upkendari.co.id'],
                ['nip' => 'PLTD-POASIA-PEG-008', 'name' => 'Adi Yusuf Sanjani', 'pos' => EmployeePosition::TeamLeaderOperasi, 'role' => RoleName::TeamLeaderOperasi, 'email' => 'tl-operasi.pltd-poasia@upkendari.co.id'],
                ['nip' => 'PLTD-POASIA-PEG-009', 'name' => 'Muh Amin', 'pos' => EmployeePosition::TeamLeaderK3, 'role' => RoleName::TeamLeaderK3, 'email' => 'tl-k3.pltd-poasia@upkendari.co.id'],
            ];
            foreach ($customPoasiaTls as $tl) {
                $user = User::query()->updateOrCreate(
                    ['email' => $tl['email']],
                    [
                        'name' => $tl['name'],
                        'password' => $password,
                        'email_verified_at' => now(),
                        'is_active' => true,
                    ],
                );
                $user->roleAssignments()->updateOrCreate(
                    ['role_id' => $roles[$tl['role']->value]],
                    ['service_unit_id' => null, 'unit_id' => $poasia->id],
                );

                // Deactivate any other holder of this singleton position in Poasia
                Employee::query()
                    ->where('unit_id', $poasia->id)
                    ->where('position', $tl['pos']->value)
                    ->where('nip', '!=', $tl['nip'])
                    ->update(['is_active' => false, 'singleton_key' => null]);

                Employee::query()->updateOrCreate(
                    ['nip' => $tl['nip']],
                    [
                        'unit_id' => $poasia->id,
                        'service_unit_id' => null,
                        'user_id' => $user->id,
                        'name' => $tl['name'],
                        'position' => $tl['pos']->value,
                        'is_active' => true,
                    ],
                );
            }
        }

        // 3. Ensure all 4 ServiceUnits have active Manager UL with real names
        $managerNames = [
            'UL-POASIA' => ['name' => 'Zulkifli', 'email' => 'manager.ul-poasia@upkendari.co.id'],
            'UL-BAUBAU' => ['name' => 'Hendra Kusuma', 'email' => 'manager.ul-baubau@upkendari.co.id'],
            'UL-KOLAKA' => ['name' => 'Bambang Suryono', 'email' => 'manager.ul-kolaka@upkendari.co.id'],
            'UL-WUA WUA' => ['name' => 'Abdul Rahman Kadir', 'email' => 'manager.ul-wua-wua@upkendari.co.id'],
            'UL-WUAWUA' => ['name' => 'Abdul Rahman Kadir', 'email' => 'manager.ul-wuawua@upkendari.co.id'],
        ];

        foreach (ServiceUnit::query()->where('is_active', true)->get() as $su) {
            $cfg = $managerNames[$su->code] ?? [
                'name' => 'Hendra Kusuma',
                'email' => 'manager.'.strtolower(str_replace(' ', '-', $su->code)).'@upkendari.co.id',
            ];
            $user = User::query()->updateOrCreate(
                ['email' => $cfg['email']],
                [
                    'name' => $cfg['name'],
                    'password' => $password,
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );
            $user->roleAssignments()->updateOrCreate(
                ['role_id' => $roles[RoleName::ManagerUl->value]],
                ['service_unit_id' => $su->id, 'unit_id' => null],
            );

            $mgrNip = "{$su->code}-MGR";
            Employee::query()
                ->where('service_unit_id', $su->id)
                ->where('position', EmployeePosition::ManagerUl->value)
                ->where('nip', '!=', $mgrNip)
                ->update(['is_active' => false, 'singleton_key' => null]);

            Employee::query()->updateOrCreate(
                ['nip' => $mgrNip],
                [
                    'service_unit_id' => $su->id,
                    'unit_id' => null,
                    'user_id' => $user->id,
                    'name' => $cfg['name'],
                    'position' => EmployeePosition::ManagerUl->value,
                    'is_active' => true,
                ],
            );
        }

        // 4. Ensure every active unit has complete staff, Staf 10..12, Operators 1..6 (plus Regu D), and realistic names
        $nameIdx = 0;
        $nextName = function () use (&$nameIdx): string {
            $name = self::INDONESIAN_NAMES[$nameIdx % count(self::INDONESIAN_NAMES)];
            $nameIdx++;

            return $name;
        };

        foreach (Unit::query()->where('is_active', true)->orderBy('id')->get() as $unit) {
            $slug = strtolower($unit->code);

            // Ensure TLs (007..009), Staf (010..012) & Signers (013..024) exist and have users
            $requiredPositions = [
                [7, EmployeePosition::TeamLeaderPemeliharaan->value, RoleName::TeamLeaderPemeliharaan, "tl-har.{$slug}@upkendari.co.id", 'pemeliharaan'],
                [8, EmployeePosition::TeamLeaderOperasi->value, RoleName::TeamLeaderOperasi, "tl-operasi.{$slug}@upkendari.co.id", 'operasi'],
                [9, EmployeePosition::TeamLeaderK3->value, RoleName::TeamLeaderK3, "tl-k3.{$slug}@upkendari.co.id", 'k3'],
                [10, 'Staf Operasi', RoleName::StafOperasi, "staf-operasi.{$slug}@upkendari.co.id", 'operasi'],
                [11, 'Staf Pemeliharaan', RoleName::StafPemeliharaan, "staf-har.{$slug}@upkendari.co.id", 'pemeliharaan'],
                [12, 'Staf K3', RoleName::StafK3, "staf-k3.{$slug}@upkendari.co.id", 'k3'],
                [13, EmployeePosition::KoordinatorOperasi->value, RoleName::KoordinatorOperasi, "koord-operasi.{$slug}@upkendari.co.id", 'operasi'],
                [14, EmployeePosition::KoordinatorPemeliharaan->value, RoleName::KoordinatorPemeliharaan, "koord-har.{$slug}@upkendari.co.id", 'pemeliharaan'],
                [15, EmployeePosition::KoordinatorK3->value, RoleName::KoordinatorK3, "koord-k3.{$slug}@upkendari.co.id", 'k3'],
                [16, EmployeePosition::KoordinatorLogistik->value, RoleName::KoordinatorLogistik, "koord-logistik.{$slug}@upkendari.co.id", 'logistik'],
                [17, EmployeePosition::KoordinatorPdm->value, RoleName::KoordinatorPdm, "koord-pdm.{$slug}@upkendari.co.id", 'pdm'],
                [18, EmployeePosition::ProjectLeader->value, RoleName::ProjectLeaderOperasi, "project-leader.{$slug}@upkendari.co.id", null],
                [19, EmployeePosition::OfficeOperasi->value, RoleName::KoordinatorOperasi, "office-operasi.{$slug}@upkendari.co.id", 'operasi'],
                [20, EmployeePosition::OfficePemeliharaan->value, RoleName::KoordinatorPemeliharaan, "office-har.{$slug}@upkendari.co.id", 'pemeliharaan'],
                [21, EmployeePosition::OfficeK3->value, RoleName::KoordinatorK3, "office-k3.{$slug}@upkendari.co.id", 'k3'],
                [22, EmployeePosition::OfficeLogistik->value, RoleName::KoordinatorLogistik, "office-logistik.{$slug}@upkendari.co.id", 'logistik'],
                [23, EmployeePosition::OfficePdm->value, RoleName::KoordinatorPdm, "office-pdm.{$slug}@upkendari.co.id", 'pdm'],
                [24, EmployeePosition::PicPdm->value, RoleName::KoordinatorPdm, "pic-pdm.{$slug}@upkendari.co.id", 'pdm'],
            ];

            // Team Leaders by the unit's TL structure: the TL Operasi row is the TL Ophar (same e-mail) and
            // there is no TL Pemeliharaan in an Ophar unit; a containerized unit has no Team Leader.
            $structure = $unit->tlStructure();
            $requiredPositions = array_values(array_filter(array_map(fn (array $row): ?array => match (true) {
                ! in_array($row[0], [7, 8, 9], true) || $structure === UnitTlStructure::Terpisah => $row,
                $structure === UnitTlStructure::Containerized, $row[0] === 7 => null,
                $row[0] === 8 => [8, EmployeePosition::TeamLeaderOphar->value, RoleName::TeamLeaderOphar, $row[3], 'pemeliharaan'],
                default => $row,
            }, $requiredPositions)));

            foreach ($requiredPositions as [$suffix, $posValue, $role, $email, $div]) {
                $existingByPos = Employee::query()
                    ->where('unit_id', $unit->id)
                    ->where('position', $posValue)
                    ->where('is_active', true)
                    ->first();

                $nip = sprintf('%s-PEG-%03d', $unit->code, $suffix);
                $emp = $existingByPos ?? Employee::query()->where('nip', $nip)->first();

                $personName = ($emp && $emp->name !== '' && ! str_contains($emp->name, '—')) ? $emp->name : $nextName();
                $userEmail = $emp?->user?->email ?: $email;

                $user = User::query()->updateOrCreate(
                    ['email' => $userEmail],
                    [
                        'name' => $personName,
                        'password' => $password,
                        'email_verified_at' => now(),
                        'is_active' => true,
                    ],
                );
                $user->roleAssignments()->updateOrCreate(
                    ['role_id' => $roles[$role->value]],
                    ['service_unit_id' => null, 'unit_id' => $unit->id],
                );

                $targetNip = $emp?->nip ?? $nip;
                if (EmployeePosition::tryFrom($posValue) !== null) {
                    Employee::query()
                        ->where('unit_id', $unit->id)
                        ->where('position', $posValue)
                        ->where('nip', '!=', $targetNip)
                        ->update(['is_active' => false, 'singleton_key' => null]);
                }

                Employee::query()->updateOrCreate(
                    ['nip' => $targetNip],
                    [
                        'unit_id' => $unit->id,
                        'service_unit_id' => null,
                        'user_id' => $user->id,
                        'name' => $personName,
                        'position' => $posValue,
                        'division' => $div,
                        'is_active' => true,
                    ],
                );
            }

            // Ensure Operators across Regu A, B, C, D with 1 shift leader per regu (for non-Poasia units)
            if (! in_array($unit->code, ['PLTD-POASIA', 'PLTD-POASIA-CONT'], true)) {
                $operatorSlots = [
                    [1, 'A', true],
                    [2, 'A', false],
                    [3, 'B', true],
                    [4, 'B', false],
                    [5, 'C', true],
                    [6, 'C', false],
                    [30, 'D', true],
                    [31, 'D', false],
                ];
                foreach ($operatorSlots as [$suffix, $regu, $isLeader]) {
                    $nip = sprintf('%s-PEG-%03d', $unit->code, $suffix);
                    $emp = Employee::query()->where('nip', $nip)->first();
                    $personName = ($emp && $emp->name !== '' && ! str_contains($emp->name, '—')) ? $emp->name : $nextName();
                    $email = "operator{$suffix}.{$slug}@upkendari.co.id";

                    $user = User::query()->updateOrCreate(
                        ['email' => $email],
                        [
                            'name' => $personName,
                            'password' => $password,
                            'email_verified_at' => now(),
                            'is_active' => true,
                        ],
                    );
                    $user->roleAssignments()->updateOrCreate(
                        ['role_id' => $roles[RoleName::Operator->value]],
                        ['service_unit_id' => null, 'unit_id' => $unit->id],
                    );

                    Employee::query()->updateOrCreate(
                        ['nip' => $nip],
                        [
                            'unit_id' => $unit->id,
                            'service_unit_id' => null,
                            'user_id' => $user->id,
                            'name' => $personName,
                            'position' => 'Operator',
                            'division' => 'operasi',
                            'regu' => $regu,
                            'is_shift_leader' => $isLeader,
                            'is_active' => true,
                        ],
                    );
                }

                // Also give realistic names to Harmes/Harlist (25..29)
                foreach (range(25, 29) as $suffix) {
                    $nip = sprintf('%s-PEG-%03d', $unit->code, $suffix);
                    $emp = Employee::query()->where('nip', $nip)->first();
                    if ($emp && str_contains($emp->name, '—')) {
                        $newName = $nextName();
                        $emp->update(['name' => $newName]);
                        if ($emp->user) {
                            $emp->user->update(['name' => $newName]);
                        }
                    }
                }
            }
        }
    }
}
