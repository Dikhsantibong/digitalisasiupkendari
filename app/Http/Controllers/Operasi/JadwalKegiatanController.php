<?php

namespace App\Http\Controllers\Operasi;

use App\Enums\PermissionName;
use App\Http\Controllers\BaseJadwalKegiatanController;
use App\Models\OperasiJadwalKegiatan;
use App\Models\User;

/**
 * Jadwal Kegiatan Operasi — harian, mingguan, bulanan & non rutin (user,
 * 2026-11-25; shared behaviour in {@see BaseJadwalKegiatanController}).
 */
class JadwalKegiatanController extends BaseJadwalKegiatanController
{
    /**
     * @var array<string, list<string>>
     */
    public const DEFAULT_KEGIATAN = [
        'harian' => [
            'Absensi',
            'Daily Meeting / Safety Briefing',
            'Serah terima shift (Lembar Mutasi)',
            'Patrol check mesin & alat bantu',
            'Pencatatan logsheet operator',
            'Pengecekan level BBM, pelumas & air pendingin',
            'Pengoperasian start / stop mesin sesuai perintah',
            'Pencatatan kWh, BBM & pelumas harian',
            'Input data rutin aplikasi online pembangkit',
            'Permit to work (PTW)',
            'Laporan harian operasi',
        ],
        'mingguan' => [
            '5S 5R area operasi',
            'First Line Maintenance (FLM)',
            'Meeting shift operator',
            'Laporan material & peralatan operasi',
            'Laporan checklist patrol check operasi',
        ],
        'bulanan' => [
            'Laporan operasi pembangkit',
            'Membuat jadwal shift operator',
            'Inventarisasi tools & material operasi',
            'Pembuatan / review IK operasi',
            'Pembuatan data teknis pembangkit',
        ],
        'non_rutin' => [
            'Commissioning test mesin / peralatan',
            'Performance test mesin',
            'Pemeriksaan instalasi blackstart',
            'Penanganan gangguan & kondisi abnormal',
        ],
    ];

    protected function model(): string
    {
        return OperasiJadwalKegiatan::class;
    }

    protected function defaults(): array
    {
        return self::DEFAULT_KEGIATAN;
    }

    protected function canView(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::OperasiInputView) || $user->hasPermissionTo(PermissionName::OperasiLaporanView);
    }

    protected function canWrite(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::OperasiInputWrite);
    }

    protected function page(): string
    {
        return 'operasi/jadwal/kegiatan/index';
    }

    protected function label(): string
    {
        return 'Jadwal Kegiatan Operasi';
    }

    protected function kopTitle(): string
    {
        return 'JADWAL KEGIATAN OPERASI';
    }
}
