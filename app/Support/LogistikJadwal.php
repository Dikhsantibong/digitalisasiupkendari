<?php

namespace App\Support;

use App\Models\Holiday;
use Illuminate\Support\Carbon;

/**
 * The Logistik & Gudang sheets — grid forms where each row carries a code per
 * column — one definition each: title, layout, menu and the rows a sheet
 * starts with before anything is saved. Rows live in `logistik_jadwal_rows`;
 * `days` maps a column to a code.
 *
 * Layouts (columns · codes):
 * - kegiatan  — days · R (rencana) / D (rencana & realisasi); activities grouped in sections.
 * - pelaksana — days · R / D; one pelaksana (or shift) per row with a RENCANA and a REALISASI line.
 * - shift     — days · {@see self::SHIFT_CODES}; PIC & nama with the rekap absensi.
 * - ik        — months of the year · R / D; instruksi kerja per row, jumlah & total (yearly sheet).
 * - patrol    — days · N (normal) / T (tidak normal); patrol checklist item per row.
 * - aplikasi  — days · D (terinput); aplikasi per row against a target of every day.
 * - checklist — 15 pelaksanaan slots · D; 5S5R items per section, eviden photos, akumulatif.
 * - maturity  — levels 0-5 · L (the chosen level); maturity items per section.
 *
 * `menu` tells whether the sheet belongs to the Jadwal or the Input menu.
 */
class LogistikJadwal
{
    /** @var array<string, array{title: string, kop: string, layout: string, row_label: string, menu: string, description: string}> */
    public const SHEETS = [
        'kegiatan' => [
            'title' => 'Jadwal Kegiatan Logistik & Gudang',
            'kop' => 'JADWAL KEGIATAN LOGISTIK & GUDANG',
            'layout' => 'kegiatan',
            'row_label' => 'Nama Peralatan',
            'menu' => 'jadwal',
            'description' => 'Kegiatan rutin harian, mingguan, bulanan dan non rutin logistik & gudang per tanggal, dengan target, rencana, realisasi dan kinerja.',
        ],
        'shift' => [
            'title' => 'Jadwal Shift Operator Logistik & Gudang',
            'kop' => 'LAPORAN JADWAL SHIFT OPERATOR',
            'layout' => 'shift',
            'row_label' => 'NAMA',
            'menu' => 'jadwal',
            'description' => 'Jadwal shift harian personil logistik (Pagi / Off / Sakit / Izin / Cuti / Mankir) dengan rekap absensi dan persentase kehadiran.',
        ],
        'piket' => [
            'title' => 'Jadwal Piket Patrol Check Logistik & Gudang (On Call)',
            'kop' => 'JADWAL PIKET PATROL CHECK LOGISTIK & GUDANG (ON CALL)',
            'layout' => 'pelaksana',
            'row_label' => 'PELAKSANA',
            'menu' => 'jadwal',
            'description' => 'Rencana & realisasi piket patrol check / on call personil logistik & gudang per tanggal.',
        ],
        '5s5r' => [
            'title' => 'Jadwal Pelaksanaan 5S5R Logistik & Gudang',
            'kop' => 'JADWAL PELAKSANAAN 5S5R LOGISTIK & GUDANG',
            'layout' => 'pelaksana',
            'row_label' => 'PELAKSANA',
            'menu' => 'jadwal',
            'description' => 'Rencana & realisasi pelaksanaan budaya kerja 5S5R di area logistik & gudang per tanggal.',
        ],
        'meeting' => [
            'title' => 'Jadwal Meeting Logistik & Gudang',
            'kop' => 'JADWAL MEETING LOGISTIK & GUDANG',
            'layout' => 'pelaksana',
            'row_label' => 'PIC',
            'menu' => 'jadwal',
            'description' => 'Rencana & realisasi meeting koordinasi logistik & gudang per tanggal.',
        ],
        'inventarisasi' => [
            'title' => 'Jadwal Inventarisasi Tools dan Material Bagian Lainnya',
            'kop' => 'JADWAL INVENTARISASI TOOLS DAN MATERIAL BAGIAN LAINNYA',
            'layout' => 'pelaksana',
            'row_label' => 'SHIFT',
            'menu' => 'jadwal',
            'description' => 'Rencana & realisasi inventarisasi harian tools dan material bagian lainnya per tanggal.',
        ],
        'ik' => [
            'title' => 'Jadwal Pembuatan Instruksi Kerja (IK) Logistik & Gudang',
            'kop' => 'JADWAL PEMBUATAN INSTRUKSI KERJA (IK) LOGISTIK & GUDANG',
            'layout' => 'ik',
            'row_label' => 'INSTRUKSI KERJA',
            'menu' => 'jadwal',
            'description' => 'Rencana & realisasi pembuatan Instruksi Kerja per bulan dalam setahun, dengan jumlah, total IK dan capaian.',
        ],
        'patrol-check' => [
            'title' => 'Laporan Patrol Checklist Logistik & Gudang',
            'kop' => 'LAPORAN PATROL CHECKLIST LOGISTIK & GUDANG',
            'layout' => 'patrol',
            'row_label' => 'Item & Area Pemeriksaan',
            'menu' => 'input',
            'description' => 'Hasil patrol checklist harian tiap item & area gudang (N = normal, siap, baik; T = tidak normal, tidak siap, kotor) dengan hasil temuan dan pelaksanaan.',
        ],
        'inspeksi-5s5r' => [
            'title' => 'Laporan Inspeksi Checklist 5S5R Logistik & Gudang',
            'kop' => 'LAPORAN INSPEKSI CHECKLIST 5S5R LOGISTIK & GUDANG',
            'layout' => 'checklist',
            'row_label' => 'Item Pemeriksaan',
            'menu' => 'input',
            'description' => 'Penilaian 5S5R (Ringkas, Rapi, Resik, Rawat, Rajin) per item pada 15 kali pelaksanaan, dengan eviden foto dan akumulatif.',
        ],
        'input-aplikasi' => [
            'title' => 'Laporan Input Data Aplikasi Pembangkit',
            'kop' => 'LAPORAN INPUT DATA APLIKASI PEMBANGKIT',
            'layout' => 'aplikasi',
            'row_label' => 'APLIKASI',
            'menu' => 'input',
            'description' => 'Realisasi input data harian ke aplikasi pembangkit (ELIPS, dll.) terhadap target setiap hari.',
        ],
        'maturity' => [
            'title' => 'Maturity Level Logistik & Gudang',
            'kop' => 'MATURITY LEVEL LOGISTIK & GUDANG',
            'layout' => 'maturity',
            'row_label' => 'DISKRIPSI',
            'menu' => 'input',
            'description' => 'Penilaian maturity level (0-5) manajemen persediaan dan manajemen gudang.',
        ],
    ];

    /** Shift codes => label (legend of the shift sheet). */
    public const SHIFT_CODES = [
        'P' => 'Pagi (08.00 s/d 16.00 WITA)',
        'OF' => 'Off / Libur',
        'S' => 'Sakit',
        'I' => 'Izin',
        'C' => 'Cuti',
        'M' => 'Mankir',
    ];

    /** Patrol checklist codes => label. */
    public const PATROL_CODES = [
        'N' => 'Normal, siap, baik',
        'T' => 'Tidak normal, tidak siap, kotor',
    ];

    /** @var list<string> */
    public const PATROL_ITEMS = [
        'APAR & Proteksi Kebakaran',
        'Peralatan P3K & APD Gudang',
        'Penyimpanan Bahan B3 (Oli & Chemical)',
        'Integritas Atap & Dinding Gudang',
        'Kebersihan Area (Housekeeping 5S)',
        'Kondisi Struktur Rak & Palet',
        'Penataan Material & Pengelompokan',
        'Kapasitas Beban Rak (Load Limit)',
        'Labeling & Kartu Stok (Bin Card)',
        'Rotasi Stok & Masa Pakai (FIFO/FEFO)',
        'Pemeriksaan Fisik Sparepart Kritis (Sampling)',
        'Peralatan Kerja & Tool Kit Gudang',
        'Sistem Penguncian & Keamanan Pintu',
        'Kamera Pengawas (CCTV Gudang)',
    ];

    /**
     * Sections per sheet: key => [number, title, note, default items].
     *
     * @var array<string, array<string, array{0: string, 1: string, 2: string, 3: list<string>}>>
     */
    private const SECTIONS = [
        'kegiatan' => [
            'mesin' => ['I', 'MESIN', '', []],
            'mingguan' => ['II', 'Mingguan', '', []],
            'bulanan' => ['III', 'Bulanan', '', []],
            'non-rutin' => ['IV', 'NON RUTIN', '', []],
        ],
        'inspeksi-5s5r' => [
            'ringkas' => ['1', 'RINGKAS (SEIRI)', 'Memastikan hanya peralatan dan material yang diperlukan berada di area kerja.', [
                'Peralatan ukur hanya yang digunakan tersedia', 'Alat rusak dipisahkan', 'Material bekas dibuang', 'Dokumen lama diarsipkan', 'Area bebas barang tidak diperlukan',
            ]],
            'rapi' => ['2', 'RAPI (SEITON)', 'Menata seluruh peralatan agar mudah dicari.', [
                'Radio Komunikasi (HT) pada tempatnya', 'Handphone (HP) Komunikasi pada tempatnya', 'Computer Personal (PC) pada tempatnya', 'Termo Gun pada tempatnya', 'Toolkit lengkap', 'Dokumen Logsheet Manual lama tertata rapi', 'Label aset lengkap',
            ]],
            'resik' => ['3', 'RESIK (SEISO)', 'Menjaga kebersihan area gudang & kerja.', [
                'Panel bersih dari debu', 'Generator bersih', 'Turbin bersih', 'Oil Cooler bersih', 'Bearing bersih', 'Tidak ada kebocoran oli', 'Area inspeksi bersih',
            ]],
            'rawat' => ['4', 'RAWAT (SEIKETSU)', 'Menjaga standar pelaksanaan kerja.', [
                'Checklist tersedia', 'SOP tersedia', 'Instruksi Kerja tersedia', 'Toolkit & Peralatan dalam kondisi Baik', 'Jadwal berjalan', 'History Equipment diperbarui', 'Data Input Online Laporan lengkap',
            ]],
            'rajin' => ['5', 'RAJIN (SHITSUKE)', 'Membentuk disiplin pelaksanaan kerja.', [
                'Inspeksi tepat waktu', 'Menggunakan APD lengkap', 'Data diinput tepat waktu', 'Temuan abnormal segera dilaporkan', 'Tindak lanjut selesai sesuai target', 'Briefing dilaksanakan', 'Housekeeping setelah pekerjaan',
            ]],
        ],
        'maturity' => [
            'd1' => ['D.1', 'MANAJEMEN PERSEDIAAN (INVENTORY MANAGEMENT)', '', [
                'Terdapat data base cataloque material', 'Membuat usulan pengadaan/Purchase Requisition (PR)', 'Membuat Laporan Manajemen Material',
            ]],
            'd2' => ['D.2', 'MANAJEMEN GUDANG (WAREHOUSE MANAGEMENT)', '', [
                'Melakukan Stock Opname tiap bulan', 'Memiliki dan melaksanakan SOP penanganan/penyimpanan material', 'Memiliki perencanaan dan proses pelaksanaan transaksi pergudangan (in dan out)',
                'Melakukan identifikasi dan penanganan material dead stock, obsolete stock dan material return (pengembalian bekas pakai)',
            ]],
        ],
    ];

    public const IK_ROWS = 30;

    public const CHECKLIST_SLOTS = 15;

    /** Eviden photos per row of the sheets that carry them. */
    public const EVIDENCE_PER_ROW = 3;

    private const PELAKSANA = 'OFFICER LOGISTIK & GUDANG';

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::SHEETS);
    }

    /**
     * @return array{title: string, kop: string, layout: string, row_label: string, menu: string, description: string}
     */
    public static function sheet(string $key): array
    {
        return self::SHEETS[$key];
    }

    public static function layout(string $key): string
    {
        return self::SHEETS[$key]['layout'];
    }

    /**
     * The sheet keys of one menu (jadwal|input).
     *
     * @return list<string>
     */
    public static function keysFor(string $menu): array
    {
        return array_keys(array_filter(self::SHEETS, fn (array $sheet): bool => $sheet['menu'] === $menu));
    }

    /**
     * The sections of a sheet (empty when it has none).
     *
     * @return list<array{key: string, number: string, title: string, note: string}>
     */
    public static function sections(string $key): array
    {
        return collect(self::SECTIONS[$key] ?? [])
            ->map(fn (array $s, string $section): array => ['key' => $section, 'number' => $s[0], 'title' => $s[1], 'note' => $s[2]])
            ->values()->all();
    }

    /**
     * The codes a sheet accepts, with their labels.
     *
     * @return array<string, string>
     */
    public static function codes(string $key): array
    {
        return match (self::layout($key)) {
            'shift' => self::SHIFT_CODES,
            'patrol' => self::PATROL_CODES,
            'aplikasi', 'checklist' => ['D' => 'Dilaksanakan'],
            'maturity' => ['L' => 'Level'],
            default => ['R' => 'Rencana', 'D' => 'Realisasi'],
        };
    }

    public static function yearly(string $key): bool
    {
        return self::layout($key) === 'ik';
    }

    /** Maturity prints portrait like the paper form; every other sheet landscape. */
    public static function orientation(string $key): string
    {
        return self::layout($key) === 'maturity' ? 'portrait' : 'landscape';
    }

    public static function hasEvidence(string $key): bool
    {
        return self::layout($key) === 'checklist';
    }

    /**
     * The columns of a sheet: the days of the month (red = weekend or
     * holiday), the twelve months (IK), the pelaksanaan slots (checklist)
     * or the levels 0-5 (maturity).
     *
     * @return list<array{col: int, label: string, dow: string, dow_en: string, is_weekend: bool, is_holiday: bool, is_red: bool}>
     */
    public static function columns(string $key, int $month, int $year): array
    {
        $plain = fn (int $col, string $label): array => [
            'col' => $col, 'label' => $label, 'dow' => '', 'dow_en' => '', 'is_weekend' => false, 'is_holiday' => false, 'is_red' => false,
        ];

        return match (self::layout($key)) {
            'ik' => array_map(fn (int $m): array => $plain($m, strtoupper(Carbon::create($year, $m, 1)->format('M'))), range(1, 12)),
            'checklist' => array_map(fn (int $slot): array => $plain($slot, (string) $slot), range(1, self::CHECKLIST_SLOTS)),
            'maturity' => array_map(fn (int $level): array => $plain($level, (string) $level), range(0, 5)),
            default => self::days($month, $year),
        };
    }

    /**
     * The rows a sheet starts with before anything is saved.
     *
     * @param  list<array{col: int, dow: string, is_red: bool}>  $columns
     * @return list<array{section: string|null, nama: string, pic: string, days: array<string, string>, target: int|null, keterangan: string, evidence: list<string>}>
     */
    public static function defaultRows(string $key, array $columns, string $officerName = ''): array
    {
        $work = array_values(array_filter($columns, fn (array $c): bool => ! $c['is_red']));
        $mark = fn (array $cols): array => collect($cols)->mapWithKeys(fn (array $c): array => [(string) $c['col'] => 'R'])->all();
        $on = fn (string ...$dows): array => array_values(array_filter($work, fn (array $c): bool => in_array($c['dow'], $dows, true)));
        $row = fn (?string $section, string $nama, array $days = [], string $pic = ''): array => [
            'section' => $section, 'nama' => $nama, 'pic' => $pic, 'days' => $days, 'target' => null, 'keterangan' => '', 'evidence' => [],
        ];
        $sectionItems = fn (): array => collect(self::SECTIONS[$key] ?? [])
            ->flatMap(fn (array $s, string $section): array => array_map(fn (string $item): array => $row($section, $item), $s[3]))
            ->values()->all();

        return match ($key) {
            'kegiatan' => [
                ...array_map(fn (string $nama): array => $row('mesin', $nama, $mark($work)), [
                    'Absensi',
                    'Daily Meeting / Safety Briefing',
                    'Inventarisasi Tools dan Material Bagian Lainnya',
                    'Laporan Patrol Checklist Logistik & Gudang',
                    'Laporan Data Aplikasi Online',
                    'Laporan Inventaris Lainnya',
                ]),
                $row('mingguan', '5S 5R', $mark($on('SN', 'RB', 'JM'))),
                $row('mingguan', 'Laporan Unsafe Action & Unsafe Condition', $mark($on('JM'))),
                $row('mingguan', 'Laporan Peralatan, Material, Tools', $mark($on('JM'))),
                $row('bulanan', 'Laporan Logistik & Gudang', $mark(array_slice($work, -1))),
                $row('bulanan', 'Laporan Pembuatan IK', $mark(array_slice($work, -1))),
                ...array_fill(0, 4, $row('non-rutin', '')),
            ],
            'shift' => [$row(null, $officerName, collect($columns)->mapWithKeys(fn (array $c): array => [(string) $c['col'] => $c['is_red'] ? 'OF' : 'P'])->all(), 'OFFICER LOGISTIK')],
            'piket' => [$row(null, self::PELAKSANA, $mark(array_filter($columns, fn (array $c): bool => $c['is_red'])))],
            '5s5r' => [$row(null, self::PELAKSANA, $mark($on('SN', 'RB', 'JM')))],
            'meeting' => [$row(null, self::PELAKSANA, $mark($work))],
            'inventarisasi' => [$row(null, 'JADWAL', $mark($columns))],
            'ik' => array_map(fn (): array => $row(null, ''), range(1, self::IK_ROWS)),
            'patrol-check' => array_map(fn (string $item): array => $row(null, $item), self::PATROL_ITEMS),
            'input-aplikasi' => [$row(null, 'ELIPS'), ...array_map(fn (): array => $row(null, ''), range(1, 5))],
            'inspeksi-5s5r', 'maturity' => $sectionItems(),
            default => [],
        };
    }

    /**
     * The computed columns of one row, per layout.
     *
     * @param  array<string, string>  $days
     * @return array<string, int|string|null>
     */
    public static function summary(string $key, array $days, ?int $target, int $columnCount): array
    {
        $count = fn (string ...$codes): int => count(array_filter($days, fn (string $code): bool => in_array($code, $codes, true)));
        $percent = fn (int $done, int $of): string => $of > 0 ? round($done / $of * 100).'%' : '-';

        switch (self::layout($key)) {
            case 'shift':
                $counts = ['P' => $count('P'), 'S' => $count('S'), 'I' => $count('I'), 'C' => $count('C'), 'M' => $count('M')];

                return $counts + ['kehadiran' => $percent($counts['P'], array_sum($counts))];

            case 'patrol':
                $rencana = $target ?? $columnCount;
                $realisasi = $count('N', 'T');

                return ['normal' => $count('N'), 'tidak_normal' => $count('T'), 'rencana' => $rencana, 'realisasi' => $realisasi, 'hasil' => $percent($realisasi, $rencana)];

            case 'aplikasi':
            case 'checklist':
                $rencana = $target ?? $columnCount;
                $realisasi = $count('D');

                return ['rencana' => $rencana, 'target' => $rencana, 'realisasi' => $realisasi, 'kinerja' => $percent($realisasi, $rencana)];

            case 'maturity':
                $level = array_search('L', $days, true);

                return ['level' => $level === false ? null : (int) $level];

            default:
                $rencana = $count('R', 'D');
                $realisasi = $count('D');
                $target ??= $rencana;

                return ['rencana' => $rencana, 'realisasi' => $realisasi, 'target' => $target, 'kinerja' => $percent($realisasi, $target)];
        }
    }

    /**
     * The days of a month; weekends and holidays are red.
     *
     * @return list<array{col: int, label: string, dow: string, dow_en: string, is_weekend: bool, is_holiday: bool, is_red: bool}>
     */
    private static function days(int $month, int $year): array
    {
        $holidays = Holiday::query()->whereYear('date', $year)->whereMonth('date', $month)->get(['date'])
            ->map(fn (Holiday $h): int => Carbon::parse($h->date)->day)->all();

        return array_map(function (int $day) use ($year, $month, $holidays): array {
            $date = Carbon::create($year, $month, $day);
            $weekend = $date->isWeekend();
            $holiday = in_array($day, $holidays, true);

            return [
                'col' => $day,
                'label' => sprintf('%02d', $day),
                'dow' => ['MG', 'SN', 'SL', 'RB', 'KM', 'JM', 'SB'][$date->dayOfWeek],
                'dow_en' => $date->locale('en')->dayName,
                'is_weekend' => $weekend,
                'is_holiday' => $holiday,
                'is_red' => $weekend || $holiday,
            ];
        }, range(1, (int) Carbon::create($year, $month, 1)->daysInMonth));
    }
}
