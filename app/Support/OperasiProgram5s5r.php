<?php

namespace App\Support;

use App\Enums\AttendanceCode;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkScheduleEntry;
use Illuminate\Support\Carbon;

/**
 * Definisi Program 5S 5R Pengoperasian KIT (Modul OPERASI):
 * lima program per shift per hari (Pagi, Sore, Malam) dengan detail & PIC bawaan dari lembar resmi,
 * pilihan tindakan, progres, kondisi, dan foto eviden per shift per hari.
 */
final class OperasiProgram5s5r
{
    public const DEFAULT_PIC = 'K3L, Operasi Dan Pemeliharaan';

    /** Foto eviden maksimal per shift per hari. */
    public const MAX_EVIDENCE = 3;

    /** @var list<string> */
    public const SHIFTS = ['Pagi', 'Sore', 'Malam'];

    /**
     * @var array<string, array{label: string, istilah: string, detail: string}>
     */
    public const PROGRAMS = [
        'ringkas' => [
            'label' => 'Ringkas',
            'istilah' => 'Seiri',
            'detail' => 'Memilah barang dan memisahkan antara yang diperlukan dan yang tidak diperlukan',
        ],
        'rapi' => [
            'label' => 'Rapi',
            'istilah' => 'Seiton',
            'detail' => 'Menata barang yang diperlukan pada posisi yang tepat dan mudah di akses',
        ],
        'resik' => [
            'label' => 'Resik',
            'istilah' => 'Seiso',
            'detail' => 'Membersihkan area kerja, mesin, dan peralatan secara menyeluruh untuk menghilangkan kotoran dan debu',
        ],
        'rawat' => [
            'label' => 'Rawat',
            'istilah' => 'Seiketsu',
            'detail' => 'Mempertahankan kondisi yang sudah dicapai pada tiga tahap sebelumnya (Ringkas, Rapi, Resik) melalui standarisasi dan evaluasi rutin',
        ],
        'rajin' => [
            'label' => 'Rajin',
            'istilah' => 'Shitsuke',
            'detail' => 'Membangun kedisiplinan dan kebiasaan pada setiap individu untuk mematuhi prosedur 5S 5R',
        ],
    ];

    /**
     * Kolom tindakan (boolean) => judul kolom.
     *
     * @var array<string, string>
     */
    public const TINDAKAN = [
        'membersihkan' => 'Membersihkan',
        'merapikan' => 'Merapikan',
        'membuang_sampah' => 'Membuang sampah',
        'mengecat' => 'Mengecat',
        'lainnya' => 'Lainnya',
    ];

    /** @var list<string> */
    public const PROGRES = ['0-25', '26-50', '51-75', '76-100'];

    /** @var list<string> */
    public const KONDISI = ['Baik', 'Kurang Baik', 'Rusak'];

    public static function daysInMonth(int $month, int $year): int
    {
        return (int) Carbon::create($year, $month, 1)->daysInMonth;
    }

    /**
     * Jumlah minggu dalam bulan (minggu ke-1 = tanggal 1–7, dst.).
     */
    public static function weeks(int $month, int $year): int
    {
        return (int) ceil(self::daysInMonth($month, $year) / 7);
    }

    /**
     * Total slot rencana per program dalam sebulan (jumlah hari × 3 shift).
     */
    public static function plannedSlots(int $month, int $year): int
    {
        return self::daysInMonth($month, $year) * count(self::SHIFTS);
    }

    public static function slotKey(int $day, string $shift): string
    {
        return "{$day}_{$shift}";
    }

    /**
     * @return array{day: int, shift: string}|null
     */
    public static function parseSlotKey(string|int $key, int $maxDay = 31): ?array
    {
        $raw = trim((string) $key);
        if (preg_match('/^(\d+)_(Pagi|Sore|Malam)$/', $raw, $m) === 1) {
            $day = (int) $m[1];
            if ($day >= 1 && $day <= $maxDay) {
                return ['day' => $day, 'shift' => $m[2]];
            }
        }

        if (ctype_digit($raw)) {
            $day = (int) $raw;
            if ($day >= 1 && $day <= $maxDay) {
                return ['day' => $day, 'shift' => 'Pagi'];
            }
        }

        return null;
    }

    /**
     * Baris bawaan satu hari & shift: program, detail & PIC dari lembar resmi; kolom
     * hasil (kondisi, tindakan, progres, jumlah, keterangan) kosong untuk diisi.
     *
     * @return array<string, mixed>
     */
    public static function blankRow(int $day, string $shift, string $program): array
    {
        return [
            'day' => $day,
            'shift' => $shift,
            'minggu' => max(1, (int) ceil($day / 7)),
            'program' => $program,
            'detail' => self::PROGRAMS[$program]['detail'],
            'pic' => self::DEFAULT_PIC,
            'kondisi_awal' => null,
            ...array_fill_keys(array_keys(self::TINDAKAN), false),
            'progres' => null,
            'kondisi_akhir' => null,
            'jumlah' => null,
            'keterangan' => null,
            'saved' => false,
        ];
    }

    /**
     * Apakah baris berisi hasil pelaksanaan (bukan sekadar teks bawaan).
     *
     * @param  array<string, mixed>  $row
     */
    public static function isFilled(array $row): bool
    {
        foreach (['kondisi_awal', 'progres', 'kondisi_akhir', 'jumlah', 'keterangan'] as $key) {
            if (($row[$key] ?? null) !== null && trim((string) $row[$key]) !== '') {
                return true;
            }
        }

        foreach (array_keys(self::TINDAKAN) as $key) {
            if (! empty($row[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve shift aktif & nama petugas/regu dari akun yang sedang login.
     *
     * @return array{shift: string, operator_name: string, regu: string|null}
     */
    public static function resolveUserContext(?User $user, int $unitId, string $tanggal): array
    {
        $hour = (int) now('Asia/Makassar')->format('H');
        $timeBasedShift = match (true) {
            $hour >= 7 && $hour < 15 => 'Pagi',
            $hour >= 15 && $hour < 23 => 'Sore',
            default => 'Malam',
        };

        if ($user === null) {
            return [
                'shift' => $timeBasedShift,
                'operator_name' => '',
                'regu' => null,
            ];
        }

        $employee = Employee::query()
            ->where(function ($q) use ($user): void {
                $q->where('user_id', $user->id);
                if ($user->nip !== null && trim((string) $user->nip) !== '') {
                    $q->orWhere('nip', $user->nip);
                }
            })
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$user->id])
            ->first();

        $operatorName = trim((string) ($employee?->name ?: $user->name));
        $regu = $employee?->regu !== null && trim((string) $employee->regu) !== ''
            ? 'Shift '.trim((string) $employee->regu)
            : null;

        if ($employee !== null) {
            $scheduleCode = WorkScheduleEntry::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $tanggal)
                ->whereHas('schedule', fn ($q) => $q->where('unit_id', $unitId))
                ->value('attendance_code');

            $scheduledShift = match ($scheduleCode) {
                AttendanceCode::Pagi->value => 'Pagi',
                AttendanceCode::Sore->value => 'Sore',
                AttendanceCode::Malam->value => 'Malam',
                default => null,
            };

            if ($scheduledShift !== null) {
                return [
                    'shift' => $scheduledShift,
                    'operator_name' => $operatorName,
                    'regu' => $regu,
                ];
            }
        }

        return [
            'shift' => $timeBasedShift,
            'operator_name' => $operatorName,
            'regu' => $regu,
        ];
    }
}
