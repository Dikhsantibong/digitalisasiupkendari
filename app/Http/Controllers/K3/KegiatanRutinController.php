<?php

namespace App\Http\Controllers\K3;

use App\Enums\PermissionName;
use App\Http\Controllers\BaseJadwalKegiatanController;
use App\Models\K3KegiatanRutin;
use App\Models\User;

/**
 * Jadwal Kegiatan Rutin K3L KIT — harian, mingguan, bulanan & non rutin
 * (shared behaviour in {@see BaseJadwalKegiatanController}).
 */
class KegiatanRutinController extends BaseJadwalKegiatanController
{
    /**
     * Default activities per group, in display order. Used to seed the grid the
     * first time a unit/period is opened; the user can then edit/add/remove.
     *
     * @var array<string, list<string>>
     */
    public const DEFAULT_KEGIATAN = [
        'harian' => [
            'Absensi',
            'Daily Meeting / Safety Briefing',
            'Pemeriksaan APD Personil',
            'Inspeksi Area Pembangkit',
            'Pemeriksaan Material & Peralatan K3',
            'Housekeeping Area Kerja',
            'Pemeriksaan Penyimpanan B3',
            'Monitoring Lingkungan',
            'Inspeksi Pekerjaan Berisiko Tinggi',
            'Pemeriksaan Limbah dan Drainase',
            'Cheklist Harian K3L',
            'Input data rutin aplikasi online pembangkit',
            'Permit to work (PTW)',
            'Evaluasi Temuan dan Pelaporan',
        ],
        'mingguan' => [
            '5S 5R',
            'Laporan peralatan, material dan tools K3L KIT',
            'Laporan checklist patrol chek K3L KIT',
            'Laporan peralatan dan APD K3L KIT',
            'Laporan peralatan fire fighting KIT',
        ],
        'bulanan' => [
            'Membuat jadwal kegiatan & piket K3L KIT',
            'Membuat dokumen rambu-rambu K3L KIT',
            'Laporan peralatan dan APD K3L KIT',
            'Laporan pengawasan APD semua bagian KIT',
            'Laporan K3L pembangkit',
            'Pembuatan IK / review IK K3L KIT',
        ],
        'non_rutin' => [
            'Investigasi kecelakaan / insiden kerja',
            'Simulasi tanggap darurat (kebakaran / evakuasi)',
            'Pendampingan pekerjaan berisiko tinggi (PTW khusus)',
            'Penanganan temuan unsafe action & unsafe condition',
        ],
    ];

    protected function model(): string
    {
        return K3KegiatanRutin::class;
    }

    protected function defaults(): array
    {
        return self::DEFAULT_KEGIATAN;
    }

    protected function canView(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::K3InputView) || $user->hasPermissionTo(PermissionName::K3LaporanView);
    }

    protected function canWrite(User $user): bool
    {
        return $user->hasPermissionTo(PermissionName::K3InputWrite);
    }

    protected function page(): string
    {
        return 'k3/jadwal/kegiatan-rutin/index';
    }

    protected function label(): string
    {
        return 'Jadwal Kegiatan Rutin K3L KIT';
    }

    protected function kopTitle(): string
    {
        return 'JADWAL KEGIATAN K3L';
    }

    protected function pdfTemplate(): string
    {
        return 'k3.jadwal.kegiatan-rutin-pdf';
    }
}
