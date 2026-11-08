<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Employee;

/**
 * The real staff of PLTD Poasia Containerized ({@see UnitStaffSeeder}).
 * Officer Logistik & Gudang and Officer PdM have no name yet, so those
 * jabatan keep their placeholder.
 */
class PoasiaContainerizedStaffSeeder extends UnitStaffSeeder
{
    public const UNIT_CODE = 'PLTD-POASIA-CONT';

    public const STAFF = [
        18 => ['name' => 'Herwin Syahputra', 'position' => 'Project Leader', 'account' => 'project-leader'],
        21 => ['name' => 'Sudoyo Age', 'position' => 'Office K3', 'account' => 'office-k3'],
        14 => ['name' => 'Eric Agus Hasti P', 'position' => 'Koordinator Operasi', 'account' => 'koordinator-operasi'],
        13 => ['name' => 'Amirullah', 'position' => 'Koordinator Pemeliharaan', 'account' => 'koordinator-har'],

        1 => ['name' => 'Sarono', 'position' => 'Operator', 'regu' => 'A', 'leader' => true, 'role' => RoleName::Operator],
        2 => ['name' => 'Rian Pramana P', 'position' => 'Operator', 'regu' => 'A', 'role' => RoleName::Operator],
        3 => ['name' => 'Aswadi', 'position' => 'Operator', 'regu' => 'B', 'leader' => true, 'role' => RoleName::Operator],
        4 => ['name' => 'Masmudin', 'position' => 'Operator', 'regu' => 'B', 'role' => RoleName::Operator],
        5 => ['name' => 'R. Yusuf', 'position' => 'Operator', 'regu' => 'C', 'leader' => true, 'role' => RoleName::Operator],
        6 => ['name' => 'Ekar Dinsyah', 'position' => 'Operator', 'regu' => 'C', 'role' => RoleName::Operator],
        30 => ['name' => 'Muhlis', 'position' => 'Operator', 'regu' => 'D', 'leader' => true, 'role' => RoleName::Operator],
        31 => ['name' => 'Muh. Abmar Siddiq P', 'position' => 'Operator', 'regu' => 'D', 'role' => RoleName::Operator],

        25 => ['name' => 'Dominikus Kofi', 'position' => Employee::POSITION_HARMES, 'role' => RoleName::Harmes],
        26 => ['name' => 'Ridwan Bahudi', 'position' => Employee::POSITION_HARMES, 'role' => RoleName::Harmes],
        27 => ['name' => 'Yonatan Linggi Allo', 'position' => Employee::POSITION_HARMES, 'role' => RoleName::Harmes],
    ];

    /** Placeholder employees without a person in the roster (Harlist 1 and 2). */
    public const INACTIVE_EMPLOYEES = [28, 29];

    /** Placeholder accounts replaced by a personal account. */
    public const INACTIVE_ACCOUNTS = ['operator1', 'operator2', 'operator3', 'operator4', 'harmes1', 'harmes2', 'harmes3', 'harlist1', 'harlist2'];
}
