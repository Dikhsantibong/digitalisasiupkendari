<?php

namespace Database\Seeders;

use App\Enums\AttendanceCodeType;
use App\Models\AttendanceCode;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The global attendance / shift codes from the Jadwal Kerja Excel. "S" is Sore
 * (a shift); SAKIT gets the distinct code "SKT" so the two never collide.
 * `hitung_hadir` marks the worked shifts (P/S/M) that count toward the
 * attendance percentage. Idempotent (keyed by code).
 */
class AttendanceCodeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * @var list<array{code: string, label: string, type: AttendanceCodeType, jam_mulai: ?string, jam_selesai: ?string, hitung_hadir: bool}>
     */
    private const CODES = [
        ['code' => 'P', 'label' => 'Pagi', 'type' => AttendanceCodeType::Shift, 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'hitung_hadir' => true],
        ['code' => 'S', 'label' => 'Sore', 'type' => AttendanceCodeType::Shift, 'jam_mulai' => '16:00', 'jam_selesai' => '24:00', 'hitung_hadir' => true],
        ['code' => 'M', 'label' => 'Malam', 'type' => AttendanceCodeType::Shift, 'jam_mulai' => '00:00', 'jam_selesai' => '08:00', 'hitung_hadir' => true],
        ['code' => 'OFF', 'label' => 'Libur Shift', 'type' => AttendanceCodeType::Shift, 'jam_mulai' => null, 'jam_selesai' => null, 'hitung_hadir' => false],
        ['code' => 'C', 'label' => 'Cuti', 'type' => AttendanceCodeType::Absence, 'jam_mulai' => null, 'jam_selesai' => null, 'hitung_hadir' => false],
        ['code' => 'SKT', 'label' => 'Sakit', 'type' => AttendanceCodeType::Absence, 'jam_mulai' => null, 'jam_selesai' => null, 'hitung_hadir' => false],
        ['code' => 'I', 'label' => 'Izin', 'type' => AttendanceCodeType::Absence, 'jam_mulai' => null, 'jam_selesai' => null, 'hitung_hadir' => false],
        ['code' => 'A', 'label' => 'Alpha', 'type' => AttendanceCodeType::Absence, 'jam_mulai' => null, 'jam_selesai' => null, 'hitung_hadir' => false],
    ];

    public function run(): void
    {
        foreach (self::CODES as $index => $code) {
            AttendanceCode::query()->updateOrCreate(
                ['code' => $code['code']],
                [
                    'label' => $code['label'],
                    'type' => $code['type'],
                    'jam_mulai' => $code['jam_mulai'],
                    'jam_selesai' => $code['jam_selesai'],
                    'hitung_hadir' => $code['hitung_hadir'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );
        }
    }
}
