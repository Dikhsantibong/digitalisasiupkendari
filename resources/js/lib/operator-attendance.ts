/**
 * Attendance of the Jadwal Shift from the presensi (absen masuk / pulang).
 * Mirrors App\Services\Operator\AttendanceCalculator so the grid, the phone
 * view and the Excel export count exactly like the PDF:
 * - hadir / terlambat: checked in for that work date;
 * - tidak_hadir: a working shift (hitung_hadir code: P/S/M) whose date has
 *   passed without a check-in;
 * - menunggu: today's working shift not checked in yet;
 * - di_luar_jadwal: a check-in on a day without a working shift.
 * % hadir = working days attended ÷ working days due (past, or today once attended).
 */

export type Presence = {
    in: string;
    out: string | null;
    late: number | null;
    shift: string | null;
};

export type AttendanceStatus =
    'hadir' | 'terlambat' | 'tidak_hadir' | 'menunggu' | 'di_luar_jadwal';

export const STATUS_META: Record<
    AttendanceStatus,
    { mark: string; label: string; className: string; fill: string }
> = {
    hadir: {
        mark: '✓',
        label: 'Hadir (absen)',
        className: 'bg-emerald-600 text-white',
        fill: '059669',
    },
    terlambat: {
        mark: '✓',
        label: 'Hadir, terlambat',
        className: 'bg-amber-500 text-white',
        fill: 'F59E0B',
    },
    tidak_hadir: {
        mark: '✗',
        label: 'Tidak hadir (tidak absen)',
        className: 'bg-rose-600 text-white',
        fill: 'E11D48',
    },
    menunggu: {
        mark: '•',
        label: 'Belum absen (hari ini)',
        className: 'bg-slate-300 text-slate-700',
        fill: 'CBD5E1',
    },
    di_luar_jadwal: {
        mark: '●',
        label: 'Absen di luar jadwal',
        className: 'bg-violet-600 text-white',
        fill: '7C3AED',
    },
};

export const dateOf = (year: number, month: number, day: number) =>
    `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

export function attendanceStatus(
    workingShift: boolean,
    presence: Presence | null | undefined,
    date: string,
    today: string,
): AttendanceStatus | null {
    if (presence) {
        if (!workingShift) {
            return 'di_luar_jadwal';
        }

        return (presence.late ?? 0) > 0 ? 'terlambat' : 'hadir';
    }

    if (!workingShift) {
        return null;
    }

    if (date < today) {
        return 'tidak_hadir';
    }

    return date === today ? 'menunggu' : null;
}

export type AttendanceRecap = {
    /** Schedule (plan) code counts. */
    counts: Record<string, number>;
    hadir: number;
    terlambat: number;
    tidakHadir: number;
    /** 0–100, or null when no working day is due yet. */
    percent: number | null;
    /** Status per day. */
    days: Record<number, AttendanceStatus>;
};

/**
 * @param cells  day → schedule code of one employee
 * @param presence  day → presensi of one employee
 * @param isWorking  whether a code is a working shift (hitung_hadir)
 */
export function attendanceRecap(
    dayNumbers: number[],
    cells: Record<number | string, string>,
    presence: Record<number | string, Presence>,
    isWorking: (code: string) => boolean,
    year: number,
    month: number,
    today: string,
): AttendanceRecap {
    const counts: Record<string, number> = {};
    const days: Record<number, AttendanceStatus> = {};
    let hadir = 0;
    let terlambat = 0;
    let tidakHadir = 0;
    let due = 0;
    let attended = 0;

    for (const day of dayNumbers) {
        const code = cells[day] ?? '';
        const working = code !== '' && isWorking(code);
        const date = dateOf(year, month, day);
        const p = presence[day];

        if (code) {
            counts[code] = (counts[code] ?? 0) + 1;
        }

        const status = attendanceStatus(working, p, date, today);

        if (status) {
            days[day] = status;
        }

        if (
            status === 'hadir' ||
            status === 'terlambat' ||
            status === 'di_luar_jadwal'
        ) {
            hadir++;
        }

        terlambat += status === 'terlambat' ? 1 : 0;
        tidakHadir += status === 'tidak_hadir' ? 1 : 0;

        if (working && (p || date < today)) {
            due++;
            attended += p ? 1 : 0;
        }
    }

    return {
        counts,
        hadir,
        terlambat,
        tidakHadir,
        percent: due > 0 ? Math.round((attended / due) * 100) : null,
        days,
    };
}
