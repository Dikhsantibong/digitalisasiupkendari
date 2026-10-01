<?php

namespace App\Enums;

/**
 * The kinds of notification: reminders the scheduler sends (jadwal, absensi)
 * and events sent when something is saved (kejadian, pekerjaan, laporan).
 * Each is gated twice: the role must hold its permission (Role & Akses, per
 * role) and the user must not have switched it off in their own settings.
 */
enum NotificationCategory: string
{
    case Jadwal = 'jadwal';
    case Absensi = 'absensi';
    case Kejadian = 'kejadian';
    case Pekerjaan = 'pekerjaan';
    case Laporan = 'laporan';

    public function label(): string
    {
        return match ($this) {
            self::Jadwal => 'Pengingat jadwal',
            self::Absensi => 'Pengingat absen & shift',
            self::Kejadian => 'Kejadian di unit',
            self::Pekerjaan => 'Work Order & Service Request',
            self::Laporan => 'Persetujuan laporan',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Jadwal => 'Ringkasan jadwal hari ini per modul setiap pagi, dan jadwal piket / on call / patrol atas nama Anda.',
            self::Absensi => 'Sebelum shift mulai, saat belum absen masuk, saat lupa absen pulang, dan shift Anda besok.',
            self::Kejadian => 'Kondisi abnormal & gangguan mesin, temuan unsafe action/condition, dan kecelakaan kerja yang baru dicatat.',
            self::Pekerjaan => 'Work Order & Service Request pemeliharaan yang baru masuk atau berubah status.',
            self::Laporan => 'Laporan Pembangkit yang menunggu verifikasi / persetujuan / pengesahan Anda, dan hasilnya untuk laporan yang Anda ajukan.',
        };
    }

    public function permission(): PermissionName
    {
        return match ($this) {
            self::Jadwal => PermissionName::NotifikasiJadwal,
            self::Absensi => PermissionName::NotifikasiAbsensi,
            self::Kejadian => PermissionName::NotifikasiKejadian,
            self::Pekerjaan => PermissionName::NotifikasiPekerjaan,
            self::Laporan => PermissionName::NotifikasiLaporan,
        };
    }
}
