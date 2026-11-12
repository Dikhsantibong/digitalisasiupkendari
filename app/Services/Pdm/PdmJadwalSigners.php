<?php

namespace App\Services\Pdm;

use App\Enums\EmployeePosition;
use App\Models\Employee;
use App\Models\PdmJadwalMeta;
use App\Models\Unit;
use App\Services\Reports\ReportSignatories;

/**
 * The signers (Mengetahui / Menyetujui / Dibuat) of the PdM jadwal sheets,
 * printed by name: the chosen employee's current name, else the name typed on
 * the sheet, else the holder of the jabatan in the unit (Data Pegawai). A
 * jabatan text saved as a name by earlier versions ("Project Leader", "TL PdM
 * & MATLEV", …) counts as not filled. Without a holder the name stays empty
 * (a dotted line on the sheet), never the jabatan.
 */
class PdmJadwalSigners
{
    /** @var array<string, array<string, array{0: EmployeePosition, 1: string}>> type => role => [jabatan holder, default jabatan text] */
    private const ROLES = [
        // Jadwal Harian PdM: disetujui TL Pemeliharaan, dibuat Koordinator PDM (user, 2026-11-09).
        'harian' => [
            'disetujui' => [EmployeePosition::TeamLeaderPemeliharaan, 'Team Leader Pemeliharaan'],
            'dibuat' => [EmployeePosition::KoordinatorPdm, 'Koordinator PDM'],
        ],
        'default' => [
            'mengetahui' => [EmployeePosition::TeamLeaderPemeliharaan, 'Team Leader Pemeliharaan'],
            'disetujui' => [EmployeePosition::ProjectLeader, 'Project Leader'],
            'dibuat' => [EmployeePosition::KoordinatorPemeliharaan, 'Koordinator Pemeliharaan'],
        ],
    ];

    /** Jabatan texts earlier versions saved in the name fields. */
    private const NOT_A_NAME = ['TL PdM & MATLEV', 'Pelaksana PdM', 'Team Leader Pemeliharaan', 'Project Leader', 'Koordinator Pemeliharaan'];

    /** Jabatan texts of the dropped TL PdM era: replaced by the holder's jabatan. */
    private const OLD_JABATAN = ['Team Leader PdM & Matlev', 'Junior Engineer PdM'];

    public function __construct(private readonly ReportSignatories $signatories) {}

    /**
     * Fill the signer names (and empty jabatan / employee ids) of a sheet's
     * meta for display; nothing is saved.
     */
    public function apply(?PdmJadwalMeta $meta, Unit $unit, string $type): PdmJadwalMeta
    {
        $meta ??= new PdmJadwalMeta(['unit_id' => $unit->id, 'type' => $type]);

        foreach (self::ROLES[$type] ?? self::ROLES['default'] as $role => [$position, $jabatan]) {
            $chosen = $meta->{"{$role}_employee_id"} ? Employee::query()->find($meta->{"{$role}_employee_id"}) : null;
            $typed = trim((string) $meta->{"{$role}_nama"});
            // A sheet saved under the old TL PdM jabatan gets the new signer, not the old pick.
            if ($chosen !== null && in_array(trim((string) $meta->{"{$role}_jabatan"}), self::OLD_JABATAN, true) && $chosen->position !== $position->value) {
                $chosen = null;
                $typed = '';
            }
            $holder = $chosen === null && ($typed === '' || in_array($typed, self::NOT_A_NAME, true))
                ? $this->signatories->holder($unit, $position)
                : null;

            $meta->{"{$role}_nama"} = match (true) {
                $chosen !== null => $chosen->name,
                $holder !== null => $holder->name,
                in_array($typed, self::NOT_A_NAME, true) => '',
                default => $typed,
            };
            if ($chosen === null) {
                $meta->{"{$role}_employee_id"} = $holder?->id;
            }
            // A default jabatan text follows the holder whose name is printed.
            $savedJabatan = trim((string) $meta->{"{$role}_jabatan"});
            $meta->{"{$role}_jabatan"} = match (true) {
                $holder !== null && ($savedJabatan === '' || $savedJabatan === $jabatan || in_array($savedJabatan, self::OLD_JABATAN, true)) => $holder->position,
                $savedJabatan === '' || in_array($savedJabatan, self::OLD_JABATAN, true) => $jabatan,
                default => $savedJabatan,
            };
        }

        return $meta;
    }
}
