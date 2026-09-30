import ExcelJS from 'exceljs';
import {
    applyStyle,
    downloadWorkbook,
    loadImageBase64,
    mergeCells,
} from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';
import { attendanceRecap, STATUS_META } from '@/lib/operator-attendance';
import type { Presence } from '@/lib/operator-attendance';

/**
 * Excel export of the Jadwal Shift & Absensi sheet (Kerja Shift then Non
 * Shift), mirroring resources/views/operator/absensi-pdf.blade.php. Each cell
 * shows the schedule code plus the attendance mark from the presensi, and the
 * Hadir / Tdk Hadir / % columns follow App\Services\Operator\AttendanceCalculator
 * (via lib/operator-attendance.ts).
 */

export type AbsensiExcel = {
    unit: string;
    periodLabel: string;
    year: number;
    month: number;
    /** Today (WITA), Y-m-d. */
    today: string;
    days: {
        day: number;
        name: string;
        is_weekend: boolean;
        is_holiday: boolean;
    }[];
    sections: {
        label: string;
        employees: {
            name: string;
            regu: string | null;
            cells: Record<string, string>;
            presence: Record<string, Presence>;
        }[];
    }[];
    codes: { code: string; label: string; hitung_hadir: boolean }[];
};

/** [fill, font] per code — the colours of the page and the unit's Excel sheet. */
const COLORS: Record<string, [string, string]> = {
    P: ['FFFFFF', '0F172A'],
    S: ['0EA5E9', 'FFFFFF'],
    M: ['94A3B8', 'FFFFFF'],
    OFF: ['DC2626', 'FFFFFF'],
    C: ['000000', 'FFFFFF'],
    SKT: ['67E8F9', '083344'],
    I: ['BAE6FD', '082F49'],
    A: ['404040', 'FFFFFF'],
};

const RECAP: [string, string][] = [
    ['P', 'Pagi'],
    ['S', 'Sore'],
    ['M', 'Malam'],
    ['OFF', 'Off'],
    ['SKT', 'Sakit'],
    ['I', 'Izin'],
    ['A', 'Alpha'],
    ['C', 'Cuti'],
];

const CELL: StyleSpec = {
    align: 'center',
    valign: 'center',
    border: true,
    size: 8,
};
const HEAD: StyleSpec = {
    ...CELL,
    bold: true,
    fill: '5B2C8F',
    color: 'FFFFFF',
    wrap: true,
};
const RED_HEAD: StyleSpec = { ...HEAD, fill: 'FEE2E2', color: 'B91C1C' };
const RECAP_HEAD: StyleSpec = { ...HEAD, fill: 'D9D9D9', color: '000000' };

const put = (
    sheet: ExcelJS.Worksheet,
    row: number,
    col: number,
    value: string | number | null,
    spec: StyleSpec,
) => {
    const cell = sheet.getRow(row).getCell(col);
    cell.value = value ?? '';
    applyStyle(cell, spec);
};

export async function downloadAbsensiWorkbook(
    data: AbsensiExcel,
    filename: string,
): Promise<void> {
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Jadwal Shift', {
        views: [{ showGridLines: false, state: 'frozen', xSplit: 3 }],
        pageSetup: {
            orientation: 'landscape',
            paperSize: 9,
            fitToPage: true,
            fitToWidth: 1,
            fitToHeight: 0,
        },
    });

    const dayCount = data.days.length;
    const firstRecap = 4 + dayCount;
    // Plan recap, then Hadir, Tdk Hadir and % (from the presensi).
    const totalCols = firstRecap + RECAP.length + 2;
    const working = new Set(
        data.codes.filter((c) => c.hitung_hadir).map((c) => c.code),
    );

    sheet.getColumn(1).width = 4;
    sheet.getColumn(2).width = 26;
    sheet.getColumn(3).width = 5;
    data.days.forEach((_, i) => (sheet.getColumn(4 + i).width = 4.2));
    RECAP.forEach((_, i) => (sheet.getColumn(firstRecap + i).width = 6));
    sheet.getColumn(totalCols - 2).width = 7;
    sheet.getColumn(totalCols - 1).width = 7;
    sheet.getColumn(totalCols).width = 7;

    // Title block.
    sheet.getRow(1).height = 34;
    const pln = await loadImageBase64('/logo/sidebar-logo.png');

    if (pln) {
        sheet.addImage(workbook.addImage({ base64: pln, extension: 'png' }), {
            tl: { col: 0.1, row: 0.1 },
            ext: { width: 140, height: 38 },
        });
    }

    const mkp = await loadImageBase64('/logo/mkp.jpg');

    if (mkp) {
        sheet.addImage(workbook.addImage({ base64: mkp, extension: 'jpeg' }), {
            tl: { col: totalCols - 1.4, row: 0.1 },
            ext: { width: 54, height: 40 },
        });
    }

    put(sheet, 2, 1, `JADWAL SHIFT & ABSENSI ${data.unit.toUpperCase()}`, {
        bold: true,
        size: 13,
        align: 'center',
    });
    mergeCells(sheet, 1, 0, 1, totalCols - 1);
    put(sheet, 3, 1, `Periode ${data.periodLabel}`, {
        size: 9,
        align: 'center',
        italic: true,
    });
    mergeCells(sheet, 2, 0, 2, totalCols - 1);

    let r = 5;

    for (const section of data.sections) {
        put(sheet, r, 1, section.label.toUpperCase(), { bold: true, size: 10 });
        mergeCells(sheet, r - 1, 0, r - 1, 5);
        r++;

        // Two header rows: date + day name; No / Nama / Regu / recap span both.
        ['No', 'Nama', 'Regu'].forEach((label, i) => {
            put(sheet, r, i + 1, label, HEAD);
            put(sheet, r + 1, i + 1, '', HEAD);
            mergeCells(sheet, r - 1, i, r, i);
        });
        data.days.forEach((d, i) => {
            const spec = d.is_weekend || d.is_holiday ? RED_HEAD : HEAD;
            put(sheet, r, 4 + i, d.day, spec);
            put(sheet, r + 1, 4 + i, d.name.slice(0, 3), {
                ...spec,
                bold: false,
                size: 7,
            });
        });
        [
            ...RECAP.map(([, label]) => label),
            'Hadir',
            'Tdk Hadir',
            '% Hadir',
        ].forEach((label, i) => {
            const spec =
                label === 'Hadir'
                    ? { ...RECAP_HEAD, fill: '047857', color: 'FFFFFF' }
                    : label === 'Tdk Hadir'
                      ? { ...RECAP_HEAD, fill: 'BE123C', color: 'FFFFFF' }
                      : RECAP_HEAD;
            put(sheet, r, firstRecap + i, label, spec);
            put(sheet, r + 1, firstRecap + i, '', RECAP_HEAD);
            mergeCells(sheet, r - 1, firstRecap + i - 1, r, firstRecap + i - 1);
        });
        r += 2;

        if (section.employees.length === 0) {
            put(sheet, r, 1, 'Belum ada pegawai pada roster ini.', {
                ...CELL,
                align: 'left',
                italic: true,
            });
            mergeCells(sheet, r - 1, 0, r - 1, totalCols - 1);
            r += 2;

            continue;
        }

        section.employees.forEach((employee, index) => {
            put(sheet, r, 1, index + 1, CELL);
            put(sheet, r, 2, employee.name, { ...CELL, align: 'left' });
            put(sheet, r, 3, employee.regu ?? '-', CELL);

            const recap = attendanceRecap(
                data.days.map((d) => d.day),
                employee.cells,
                employee.presence ?? {},
                (code) => working.has(code),
                data.year,
                data.month,
                data.today,
            );

            data.days.forEach((d, i) => {
                const code = employee.cells[String(d.day)] ?? '';
                const [fill, color] = COLORS[code] ?? [
                    d.is_weekend || d.is_holiday ? 'FEE2E2' : 'FFFFFF',
                    '000000',
                ];
                const status = recap.days[d.day];
                const cell = sheet.getRow(r).getCell(4 + i);
                cell.value = status
                    ? `${code}${code ? ' ' : ''}${STATUS_META[status].mark}`
                    : code;
                applyStyle(cell, { ...CELL, bold: code !== '', fill, color });

                if (status) {
                    cell.note = STATUS_META[status].label;
                }

                if (status === 'tidak_hadir') {
                    const red = {
                        style: 'medium' as const,
                        color: { argb: 'FFE11D48' },
                    };
                    cell.border = {
                        top: red,
                        bottom: red,
                        left: red,
                        right: red,
                    };
                }
            });
            RECAP.forEach(([code], i) =>
                put(sheet, r, firstRecap + i, recap.counts[code] ?? 0, CELL),
            );
            put(sheet, r, totalCols - 2, recap.hadir, {
                ...CELL,
                bold: true,
                color: '047857',
            });
            put(sheet, r, totalCols - 1, recap.tidakHadir, {
                ...CELL,
                bold: true,
                color: recap.tidakHadir > 0 ? 'BE123C' : '555555',
            });
            const percent = sheet.getRow(r).getCell(totalCols);
            percent.value = recap.percent === null ? '-' : recap.percent / 100;
            applyStyle(percent, CELL);
            percent.numFmt = '0.0%';
            r++;
        });

        r++;
    }

    // Legend.
    let c = 1;

    for (const code of data.codes) {
        const [fill, color] = COLORS[code.code] ?? ['FFFFFF', '000000'];
        put(sheet, r, c, code.code, { ...CELL, bold: true, fill, color });
        put(sheet, r, c + 1, code.label, { size: 8 });
        c += c === 1 ? 2 : 3;
    }

    // Attendance marks (from the presensi).
    r++;
    c = 1;

    for (const meta of Object.values(STATUS_META)) {
        put(sheet, r, c, meta.mark, {
            ...CELL,
            bold: true,
            fill: meta.fill,
            color: 'FFFFFF',
        });
        put(sheet, r, c + 1, meta.label, { size: 8 });
        c += c === 1 ? 2 : 4;
    }

    put(
        sheet,
        r + 1,
        1,
        '% Hadir = jadwal kerja (P/S/M) yang diabsen ÷ jadwal kerja yang sudah lewat. Kehadiran dari absen masuk akun pegawai.',
        { size: 8, italic: true },
    );

    await downloadWorkbook(workbook, filename);
}
