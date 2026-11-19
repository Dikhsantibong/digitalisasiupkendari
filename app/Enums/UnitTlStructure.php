<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * How a unit's Team Leaders are organised (user, 2026-11-18).
 *
 * - Terpisah: TL Operasi and TL Pemeliharaan are two people (only PLTD
 *   Poasia, PLTD Wua-Wua, PLTD Bau-Bau and PLTD Kolaka).
 * - Ophar: TL Operasi and TL Pemeliharaan are one person, the TL Ophar, who
 *   holds (and approves for) both divisi — every other unit.
 * - Containerized: no Team Leader at all; the unit is led by its Project
 *   Leader, who takes every TL step (Operasi, Pemeliharaan, K3).
 *
 * The TL K3 & Keamanan stays its own jabatan in Terpisah and Ophar units.
 */
enum UnitTlStructure: string
{
    case Terpisah = 'terpisah';
    case Ophar = 'ophar';
    case Containerized = 'containerized';

    /**
     * Unit codes that keep TL Operasi and TL Pemeliharaan separate.
     *
     * @var list<string>
     */
    public const SEPARATE_CODES = ['PLTD-POASIA', 'PLTD-WUAWUA', 'PLTD-BAUBAU', 'PLTD-KOLAKA'];

    public function label(): string
    {
        return match ($this) {
            self::Terpisah => 'TL Operasi & TL Pemeliharaan terpisah',
            self::Ophar => 'TL Ophar (Operasi & Pemeliharaan digabung)',
            self::Containerized => 'Containerized (tanpa TL, dipimpin Project Leader)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Terpisah => 'Unit memiliki TL Operasi, TL Pemeliharaan dan TL K3 & Keamanan.',
            self::Ophar => 'TL Ophar memegang Operasi & Pemeliharaan (termasuk Logistik & PdM); TL K3 & Keamanan tetap terpisah.',
            self::Containerized => 'Tidak ada Team Leader: seluruh persetujuan TL dilakukan Project Leader unit.',
        };
    }

    /**
     * The structure of a known unit by its code & name: containerized, the
     * four separate units, otherwise TL Ophar.
     */
    public static function initialFor(?string $code, ?string $name): self
    {
        return match (true) {
            Str::contains(Str::lower((string) $name), 'containerized') || Str::contains(Str::upper((string) $code), '-CONT') => self::Containerized,
            in_array(Str::upper((string) $code), self::SEPARATE_CODES, true) => self::Terpisah,
            default => self::Ophar,
        };
    }

    /**
     * The structure a new unit starts with: containerized when its name says
     * so, otherwise TL Ophar (the four separate units already exist).
     */
    public static function suggestFor(?string $name): self
    {
        return Str::contains(Str::lower((string) $name), 'containerized') ? self::Containerized : self::Ophar;
    }

    /**
     * The jabatan that actually holds a TL jabatan in a unit of this
     * structure: TL Operasi/Pemeliharaan → TL Ophar in an Ophar unit, every
     * TL → Project Leader in a containerized unit, a TL Ophar asked in a
     * Terpisah unit → itself (nobody holds it there). Other jabatan unchanged.
     */
    public function holderPosition(EmployeePosition $position): EmployeePosition
    {
        return match ($this) {
            self::Ophar => in_array($position, [EmployeePosition::TeamLeaderOperasi, EmployeePosition::TeamLeaderPemeliharaan], true)
                ? EmployeePosition::TeamLeaderOphar
                : $position,
            self::Containerized => $position->isTeamLeader() ? EmployeePosition::ProjectLeader : $position,
            self::Terpisah => $position,
        };
    }

    /**
     * The Team Leader jabatan the unit has (accounts & employees).
     *
     * @return list<EmployeePosition>
     */
    public function teamLeaderPositions(): array
    {
        return match ($this) {
            self::Terpisah => [EmployeePosition::TeamLeaderOperasi, EmployeePosition::TeamLeaderPemeliharaan, EmployeePosition::TeamLeaderK3],
            self::Ophar => [EmployeePosition::TeamLeaderOphar, EmployeePosition::TeamLeaderK3],
            self::Containerized => [],
        };
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $structure): array => [
            'value' => $structure->value,
            'label' => $structure->label(),
            'description' => $structure->description(),
        ], self::cases());
    }
}
