<?php

namespace App\Enums;

/**
 * The canonical jabatan (employees.position) that sign the Laporan Pembangkit.
 * Other positions (Operator, Staf …) stay free text; these must match exactly
 * so a report signer is resolved by unit + jabatan, never by name or LIKE.
 */
enum EmployeePosition: string
{
    case ManagerUl = 'Manager UL';
    case TeamLeaderPemeliharaan = 'Team Leader Pemeliharaan';
    case TeamLeaderOperasi = 'Team Leader Operasi';
    case TeamLeaderK3 = 'Team Leader K3 & Keamanan';
    case KoordinatorPemeliharaan = 'Koordinator Pemeliharaan';
    case KoordinatorOperasi = 'Koordinator Operasi';
    case KoordinatorK3 = 'Koordinator K3';
    case KoordinatorPdm = 'Koordinator PDM';
    case KoordinatorLogistik = 'Koordinator Logistik';
    case ProjectLeader = 'Project Leader';
    case OfficePemeliharaan = 'Office Pemeliharaan';
    case OfficeOperasi = 'Office Operasi';
    case OfficeK3 = 'Office K3';
    case OfficePdm = 'Office PDM';
    case OfficeLogistik = 'Office Logistik';
    case PicPdm = 'PIC PDM';

    /**
     * The divisi (work_modules.code) the jabatan belongs to; null for the
     * unit-wide jabatan (Manager UL, Project Leader).
     */
    public function division(): ?string
    {
        return match ($this) {
            self::ManagerUl, self::ProjectLeader => null,
            self::TeamLeaderPemeliharaan, self::KoordinatorPemeliharaan, self::OfficePemeliharaan => 'pemeliharaan',
            self::TeamLeaderOperasi, self::KoordinatorOperasi, self::OfficeOperasi => 'operasi',
            self::TeamLeaderK3, self::KoordinatorK3, self::OfficeK3 => 'k3',
            self::KoordinatorPdm, self::OfficePdm, self::PicPdm => 'pdm',
            self::KoordinatorLogistik, self::OfficeLogistik => 'logistik',
        };
    }

    /**
     * Manager UL heads a service unit (UL); every other jabatan here belongs
     * to one generating unit.
     */
    public function isServiceUnitLevel(): bool
    {
        return $this === self::ManagerUl;
    }

    /**
     * The key that keeps at most one active holder of this jabatan per unit
     * (per service unit for Manager UL), enforced by a unique index.
     */
    public function singletonKey(?int $unitId, ?int $serviceUnitId): ?string
    {
        if ($this->isServiceUnitLevel()) {
            return $serviceUnitId !== null ? "su:{$serviceUnitId}:{$this->value}" : null;
        }

        return $unitId !== null ? "unit:{$unitId}:{$this->value}" : null;
    }

    /**
     * The Office jabatan of a divisi (work_modules.code).
     */
    public static function officeFor(string $division): self
    {
        return match ($division) {
            'pemeliharaan' => self::OfficePemeliharaan,
            'operasi' => self::OfficeOperasi,
            'k3' => self::OfficeK3,
            'pdm' => self::OfficePdm,
            'logistik' => self::OfficeLogistik,
        };
    }
}
