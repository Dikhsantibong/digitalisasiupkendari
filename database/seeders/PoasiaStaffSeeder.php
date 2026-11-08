<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Employee;

/**
 * The real staff of PLTD Poasia ({@see UnitStaffSeeder}).
 */
class PoasiaStaffSeeder extends UnitStaffSeeder
{
    public const UNIT_CODE = 'PLTD-POASIA';

    public const STAFF = [
        18 => ['name' => 'Firman', 'position' => 'Project Leader', 'account' => 'project-leader'],
        14 => ['name' => 'Avan Libransio Silambi', 'position' => 'Koordinator Operasi', 'account' => 'koordinator-operasi'],
        13 => ['name' => 'La Hamza', 'position' => 'Koordinator Pemeliharaan', 'account' => 'koordinator-har'],
        21 => ['name' => 'Mahnan', 'position' => 'Office K3', 'account' => 'office-k3'],
        23 => ['name' => 'Sartono', 'position' => 'Office Logistik', 'account' => 'office-logistik'],
        24 => ['name' => 'Laode Irsad', 'position' => 'PIC PDM', 'account' => 'pic-pdm'],

        1 => ['name' => 'Muh. Zainuddin', 'position' => 'Operator', 'regu' => 'A', 'leader' => true, 'role' => RoleName::Operator],
        2 => ['name' => 'Syamsul', 'position' => 'Operator', 'regu' => 'A', 'role' => RoleName::Operator],
        30 => ['name' => 'Krisna Bayu', 'position' => 'Operator', 'regu' => 'A', 'role' => RoleName::Operator],
        3 => ['name' => 'Yan Kristo Feliks', 'position' => 'Operator', 'regu' => 'B', 'leader' => true, 'role' => RoleName::Operator],
        4 => ['name' => 'Agung Prawoto', 'position' => 'Operator', 'regu' => 'B', 'role' => RoleName::Operator],
        31 => ['name' => 'Addrian Felix', 'position' => 'Operator', 'regu' => 'B', 'role' => RoleName::Operator],
        5 => ['name' => 'M. Julbar', 'position' => 'Operator', 'regu' => 'C', 'leader' => true, 'role' => RoleName::Operator],
        6 => ['name' => 'Adwin Mudale', 'position' => 'Operator', 'regu' => 'C', 'role' => RoleName::Operator],
        32 => ['name' => 'I Made Budiasa', 'position' => 'Operator', 'regu' => 'C', 'role' => RoleName::Operator],
        33 => ['name' => 'Jemmy', 'position' => 'Operator', 'regu' => 'D', 'leader' => true, 'role' => RoleName::Operator],
        34 => ['name' => 'Rahmad Sukarno Mardhan', 'position' => 'Operator', 'regu' => 'D', 'role' => RoleName::Operator],
        35 => ['name' => 'Erikson M. Menge', 'position' => 'Operator', 'regu' => 'D', 'role' => RoleName::Operator],

        36 => ['name' => 'La Aka', 'position' => 'Teknisi Administrasi Fuel Facility', 'division' => 'operasi', 'role' => RoleName::StafOperasi],
        37 => ['name' => 'Riski Ardiansyah', 'position' => 'Teknisi Administrasi Fuel Facility', 'division' => 'operasi', 'role' => RoleName::StafOperasi],

        25 => ['name' => 'Amiruddin', 'position' => Employee::POSITION_HARMES, 'role' => RoleName::Harmes],
        26 => ['name' => 'Yacub Martin S.', 'position' => Employee::POSITION_ASME, 'role' => RoleName::Harmes],
        27 => ['name' => 'Zaenal', 'position' => Employee::POSITION_ASME, 'role' => RoleName::Harmes],
        28 => ['name' => 'Ismail', 'position' => Employee::POSITION_ASME, 'role' => RoleName::Harmes],
    ];

    /** Placeholder employees without a person in the roster (Harlist 2). */
    public const INACTIVE_EMPLOYEES = [29];

    /** Placeholder accounts replaced by a personal account. */
    public const INACTIVE_ACCOUNTS = ['operator1', 'operator2', 'operator3', 'operator4', 'harmes1', 'harmes2', 'harmes3', 'harlist1', 'harlist2'];
}
