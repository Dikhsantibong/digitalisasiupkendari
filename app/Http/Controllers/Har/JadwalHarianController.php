<?php

namespace App\Http\Controllers\Har;

use App\Enums\PermissionName;
use App\Http\Controllers\BaseJadwalKegiatanController;
use App\Models\HarJadwalHarian;
use App\Models\User;

/**
 * Jadwal Kegiatan Pemeliharaan — harian, mingguan, bulanan & non rutin (user,
 * 2026-11-25; shared behaviour in {@see BaseJadwalKegiatanController}). Rows
 * saved before the groups existed are daily activities ("harian").
 */
class JadwalHarianController extends BaseJadwalKegiatanController
{
    /**
     * @var array<string, list<string>>
     */
    public const DEFAULT_KEGIATAN = [
        'harian' => [
            'Absensi',
            'Daily Meeting / Safety Breefing',
            'Pengecekan kebocoran air, oli, bbm & udara',
            'Pengecekan terminasi battery & pengukuran tegangan battery',
            'Pengecekan sensor & terminasi socket pada sensor',
            'Pengecekan level oli dan air pendingin',
            'Pengukuran asap cerobong dan breather pada mesin yang operasi',
            'Pengecekan vibrasi pada mesin yang operasi',
            'Pengecekan parameter pada panel PCC 3300',
            'Pengecekan terminasi pada panel GCP',
            'Pembersihan panel GCP & body mesin',
            'Analisis Data dan Pelaporan',
        ],
        'mingguan' => [
            '5S 5R area pemeliharaan',
            'Meeting pemeliharaan',
            'Patrol check pemeliharaan',
            'Laporan material, peralatan & tools pemeliharaan',
        ],
        'bulanan' => [
            'Laporan pemeliharaan pembangkit',
            'Membuat jadwal kegiatan & piket on call pemeliharaan',
            'Pembuatan / review IK pemeliharaan',
            'Inventarisasi tools & material pemeliharaan',
        ],
        'non_rutin' => [
            'Pemeliharaan korektif (CM) / perbaikan gangguan',
            'Pemeliharaan periodik P1 – P5 / overhaul',
            'Commissioning test pasca pemeliharaan',
            'Tindak lanjut Work Order / Service Request',
        ],
    ];

    protected function model(): string
    {
        return HarJadwalHarian::class;
    }

    protected function defaults(): array
    {
        return self::DEFAULT_KEGIATAN;
    }

    protected function canView(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::HarInputView) || $user->hasPermissionTo(PermissionName::HarLaporanView);
    }

    protected function canWrite(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::HarInputWrite);
    }

    protected function page(): string
    {
        return 'har/jadwal/harian/index';
    }

    protected function label(): string
    {
        return 'Jadwal Kegiatan Pemeliharaan';
    }

    protected function kopTitle(): string
    {
        return 'JADWAL KEGIATAN PEMELIHARAAN';
    }

    /**
     * HAR keeps the rencana / realisasi counts read by the Laporan Pemeliharaan.
     */
    protected function rowAttributes(array $attributes): array
    {
        return $attributes + [
            'rencana_count' => $attributes['target'],
            'realisasi_count' => count($attributes['jadwal']),
        ];
    }
}
