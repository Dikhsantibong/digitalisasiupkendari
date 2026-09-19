import ExcelJS from 'exceljs';
import { applyStyle, buildDocumentHeader, downloadWorkbook, mergeCells, SPECS } from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/**
 * The Logistik & Gudang jadwal sheets (App\Support\LogistikJadwal): shared
 * types, the live rencana/realisasi and rekap absensi calculations, and the
 * Excel export mirroring resources/views/logistik/jadwal/{layout}-pdf.
 */

export type JadwalLayout = 'kegiatan' | 'pelaksana' | 'shift' | 'ik' | 'patrol' | 'aplikasi' | 'checklist' | 'maturity';
export type JadwalSheetDef = { key: string; title: string; kop: string; layout: JadwalLayout; row_label: string; menu: 'jadwal' | 'input'; description: string; yearly: boolean; evidence: number };
export type JadwalColumn = { col: number; label: string; dow: string; dow_en: string; is_weekend: boolean; is_holiday: boolean; is_red: boolean };
export type JadwalSection = { key: string; number: string; title: string; note: string };
export type JadwalRow = { section: string | null; nama: string; pic: string; days: Record<string, string>; target: number | null; keterangan: string; evidence: string[]; evidence_urls: string[] };

/** Rencana (R or D), realisasi (D), target (default rencana) and kinerja of a R/D row. */
export const jadwalProgress = (row: JadwalRow) => {
    const codes = Object.values(row.days);
    const rencana = codes.filter((code) => code === 'R' || code === 'D').length;
    const realisasi = codes.filter((code) => code === 'D').length;
    const target = row.target ?? rencana;

    return { rencana, realisasi, target, kinerja: target > 0 ? `${Math.round((realisasi / target) * 100)}%` : '-' };
};

/** Count per shift code and the kehadiran percentage (Pagi over all attendance codes). */
export const rekapAbsensi = (row: JadwalRow) => {
    const codes = Object.values(row.days);
    const count = (code: string) => codes.filter((c) => c === code).length;
    const rekap = { P: count('P'), S: count('S'), I: count('I'), C: count('C'), M: count('M') };
    const total = rekap.P + rekap.S + rekap.I + rekap.C + rekap.M;

    return { ...rekap, kehadiran: total > 0 ? `${Math.round((rekap.P / total) * 100)}%` : '-' };
};

/** Target (default every column), realisasi (D) and kinerja of an aplikasi / checklist row. */
export const dailyProgress = (row: JadwalRow, columnCount: number) => {
    const target = row.target ?? columnCount;
    const realisasi = Object.values(row.days).filter((code) => code === 'D').length;

    return { target, realisasi, kinerja: target > 0 ? `${Math.round((realisasi / target) * 100)}%` : '-' };
};

/** The chosen maturity level of a row, or null. */
export const maturityLevel = (row: JadwalRow): number | null => {
    const level = Object.entries(row.days).find(([, code]) => code === 'L');

    return level ? Number(level[0]) : null;
};

/** Hasil temuan (N / T counts) and pelaksanaan (rencana = target or every day, realisasi = checked days) of a patrol row. */
export const patrolSummary = (row: JadwalRow, dayCount: number) => {
    const codes = Object.values(row.days);
    const normal = codes.filter((code) => code === 'N').length;
    const tidakNormal = codes.filter((code) => code === 'T').length;
    const rencana = row.target ?? dayCount;
    const realisasi = normal + tidakNormal;

    return { normal, tidak_normal: tidakNormal, rencana, realisasi, hasil: rencana > 0 ? `${Math.round((realisasi / rencana) * 100)}%` : '-' };
};

const CELL: StyleSpec = { align: 'center', valign: 'center', border: true, wrap: true };
const CELL_LEFT: StyleSpec = { align: 'left', valign: 'center', border: true, wrap: true };
const BOLD: StyleSpec = { ...CELL, bold: true };
const RED: StyleSpec = { ...CELL, fill: 'FF0000', color: 'FFFFFF' };
const OFF: StyleSpec = { ...CELL, fill: 'FFC000', color: 'C00000', bold: true };
const DONE: StyleSpec = { ...CELL, fill: 'C6EFCE' };
const FINDING: StyleSpec = { ...CELL, fill: 'FFC7CE', bold: true };
const SECTION: StyleSpec = { ...CELL_LEFT, fill: '70AD47', bold: true, italic: true };
const HEADER: StyleSpec = { ...SPECS.headerGrey, fill: '5BC8F5' };
const HEADER_RED: StyleSpec = { ...HEADER, color: 'FF0000' };

const put = (sheet: ExcelJS.Worksheet, row: number, col: number, value: string | number | null | undefined, spec?: StyleSpec) => {
    const cell = sheet.getRow(row).getCell(col);
    cell.value = value ?? '';

    if (spec) {
        applyStyle(cell, spec);
    }
};

/** Fixed leading columns, trailing summary columns and their widths per layout. */
const LAYOUT: Record<JadwalLayout, { lead: (sheet: JadwalSheetDef) => string[]; leadWidths: number[]; tail: string[] }> = {
    kegiatan: { lead: (sheet) => ['No', sheet.row_label], leadWidths: [5, 34], tail: ['TARGET', 'RENCANA', 'REALISASI', 'A. KINERJA', 'Keterangan'] },
    pelaksana: { lead: (sheet) => [sheet.row_label, 'RENCANA / REALISASI'], leadWidths: [26, 12], tail: ['RENCANA', 'TARGET', 'REALISASI', 'A. KINERJA'] },
    shift: { lead: () => ['NO', 'PIC', 'NAMA'], leadWidths: [5, 20, 22], tail: ['PAGI', 'SAKIT', 'IZIN', 'CUTI', 'MANKIR', 'KEHADIRAN'] },
    ik: { lead: () => ['NO', 'INSTRUKSI KERJA', 'PIC PEMBUAT'], leadWidths: [5, 38, 20], tail: ['JUMLAH'] },
    patrol: { lead: (sheet) => ['NO', sheet.row_label], leadWidths: [5, 40], tail: ['NORMAL', 'T. NORMAL', 'RNC', 'REAL', 'HASIL'] },
    aplikasi: { lead: (sheet) => ['No', sheet.row_label], leadWidths: [5, 30], tail: ['TARGET', 'REALISASI', 'A. KINERJA'] },
    checklist: { lead: (sheet) => ['No', sheet.row_label], leadWidths: [5, 40], tail: ['RENCANA', 'REALISASI', 'A. DATA'] },
    maturity: { lead: (sheet) => ['NO', sheet.row_label], leadWidths: [8, 70], tail: [] },
};

export async function downloadLogistikJadwalWorkbook(
    sheet: JadwalSheetDef,
    unitName: string,
    periodLabel: string,
    columns: JadwalColumn[],
    rows: JadwalRow[],
    sections: JadwalSection[],
    filename: string,
) {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet(sheet.title.slice(0, 31), {
        views: [{ showGridLines: false }],
        pageSetup: { orientation: 'landscape', paperSize: 9, fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
    });

    const layout = LAYOUT[sheet.layout];
    const leadLabels = layout.lead(sheet);
    const lead = leadLabels.length;
    const firstTail = lead + columns.length + 1;
    const totalCols = lead + columns.length + layout.tail.length;
    const colWidths = [...layout.leadWidths, ...columns.map(() => (sheet.yearly ? 6 : 4)), ...layout.tail.map((label) => (label === 'Keterangan' ? 24 : 10))];
    colWidths.forEach((width, i) => (worksheet.getColumn(i + 1).width = width));

    await buildDocumentHeader({ workbook, worksheet }, {
        totalCols,
        colWidths,
        titleLines: ['JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE -KIT', `LAPORAN PROJECT SENTRAL ${unitName.toUpperCase()}`, sheet.kop],
        barTitle: `${sheet.title.toUpperCase()} — ${periodLabel.toUpperCase()}`,
    });

    // Header: labels on row 6, day numbers on row 7 (monthly sheets).
    leadLabels.forEach((label, i) => put(worksheet, 6, i + 1, label, HEADER));
    columns.forEach((column, i) => {
        const label = sheet.yearly ? column.label : sheet.layout === 'shift' ? column.dow_en.slice(0, 3) : column.dow;
        put(worksheet, 6, lead + i + 1, label, column.is_red ? HEADER_RED : HEADER);
    });
    layout.tail.forEach((label, i) => put(worksheet, 6, firstTail + i, label, HEADER));

    if (!sheet.yearly) {
        for (let c = 1; c <= totalCols; c++) {
            applyStyle(worksheet.getRow(7).getCell(c), HEADER);
        }

        columns.forEach((column, i) => put(worksheet, 7, lead + i + 1, column.col, column.is_red ? HEADER_RED : HEADER));
    }

    let r = sheet.yearly ? 7 : 8;

    const writeDays = (row: JadwalRow, line: 'single' | 'rencana' | 'realisasi') => {
        columns.forEach((column, i) => {
            const code = row.days[String(column.col)] ?? '';
            let value: string | number = '';
            let spec: StyleSpec = CELL;

            if (sheet.layout === 'shift') {
                value = code;
                spec = column.is_holiday ? RED : code === 'OF' ? OFF : BOLD;
            } else if (sheet.layout === 'patrol') {
                value = code === 'N' ? 0 : code === 'T' ? 1 : '';
                spec = code === 'T' ? FINDING : CELL;
            } else if (sheet.layout === 'maturity') {
                value = code === 'L' ? column.col : '';
                spec = code === 'L' ? { ...BOLD, fill: 'FFD966' } : CELL;
            } else if (sheet.layout === 'aplikasi' || sheet.layout === 'checklist') {
                value = code === 'D' ? 1 : '';
                spec = code === 'D' ? DONE : CELL;
            } else if (line === 'rencana') {
                value = code === 'R' || code === 'D' ? 1 : '';
            } else if (line === 'realisasi') {
                value = code === 'D' ? 1 : '';
                spec = code === 'D' ? DONE : CELL;
            } else {
                value = code === 'D' ? '✓' : code === 'R' ? 1 : '';
                spec = column.is_red ? RED : code === 'D' ? DONE : CELL;
            }

            put(worksheet, r, lead + i + 1, value, spec);
        });
    };

    const writeTail = (values: (string | number)[]) =>
        values.forEach((value, i) => put(worksheet, r, firstTail + i, value, layout.tail[i] === 'Keterangan' ? CELL_LEFT : BOLD));

    if (sheet.layout === 'aplikasi') {
        rows.forEach((row, index) => {
            const p = dailyProgress(row, columns.length);
            put(worksheet, r, 1, index + 1, CELL);
            put(worksheet, r, 2, row.nama, CELL_LEFT);
            writeDays(row, 'single');
            writeTail([p.target, p.realisasi, p.kinerja]);
            r++;
        });
    } else if (sheet.layout === 'checklist' || sheet.layout === 'maturity') {
        for (const section of sections) {
            put(worksheet, r, 1, section.number, { ...SECTION, align: 'center' });
            put(worksheet, r, 2, section.note ? `${section.title} — Tujuan: ${section.note}` : section.title, SECTION);

            for (let c = 3; c <= totalCols; c++) {
                applyStyle(worksheet.getRow(r).getCell(c), SECTION);
            }

            r++;
            let rencana = 0;
            let realisasi = 0;

            rows.filter((row) => row.section === section.key).forEach((row, index) => {
                put(worksheet, r, 1, index + 1, CELL);
                put(worksheet, r, 2, row.nama, CELL_LEFT);
                writeDays(row, 'single');

                if (sheet.layout === 'checklist') {
                    const p = dailyProgress(row, columns.length);
                    rencana = Math.max(rencana, p.target);
                    realisasi += p.realisasi;
                    writeTail([p.target, p.realisasi, p.kinerja]);
                }

                r++;
            });

            if (sheet.layout === 'checklist') {
                put(worksheet, r, 1, 'TOTAL', BOLD);
                mergeCells(worksheet, r - 1, 0, r - 1, firstTail - 2);
                writeTail([rencana, realisasi, rencana > 0 ? `${Math.round((realisasi / rencana) * 100)}%` : '0%']);
                r++;
            }
        }
    } else if (sheet.layout === 'kegiatan') {
        for (const section of sections) {
            put(worksheet, r, 1, section.number, { ...SECTION, align: 'center' });
            put(worksheet, r, 2, section.title, SECTION);

            for (let c = 3; c <= totalCols; c++) {
                applyStyle(worksheet.getRow(r).getCell(c), SECTION);
            }

            r++;

            rows.filter((row) => (row.section ?? 'non-rutin') === section.key).forEach((row, index) => {
                const p = jadwalProgress(row);
                put(worksheet, r, 1, index + 1, CELL);
                put(worksheet, r, 2, row.nama, CELL_LEFT);
                writeDays(row, 'single');
                writeTail([p.target, p.rencana, p.realisasi, p.kinerja, row.keterangan]);
                r++;
            });
        }
    } else if (sheet.layout === 'pelaksana') {
        for (const row of rows) {
            const p = jadwalProgress(row);
            put(worksheet, r, 1, row.nama, { ...CELL_LEFT, bold: true });
            put(worksheet, r + 1, 1, '', CELL_LEFT);
            mergeCells(worksheet, r - 1, 0, r, 0);
            put(worksheet, r, 2, 'RENCANA', CELL_LEFT);
            put(worksheet, r + 1, 2, 'REALISASI', CELL_LEFT);
            writeDays(row, 'rencana');
            [p.rencana, p.target, p.realisasi, p.kinerja].forEach((value, i) => {
                put(worksheet, r, firstTail + i, value, BOLD);
                put(worksheet, r + 1, firstTail + i, '', CELL);
                mergeCells(worksheet, r - 1, firstTail + i - 1, r, firstTail + i - 1);
            });
            r++;
            writeDays(row, 'realisasi');
            r++;
        }
    } else if (sheet.layout === 'patrol') {
        const totals = { normal: 0, tidak_normal: 0, rencana: 0, realisasi: 0 };
        rows.forEach((row, index) => {
            const p = patrolSummary(row, columns.length);
            totals.normal += p.normal;
            totals.tidak_normal += p.tidak_normal;
            totals.rencana += p.rencana;
            totals.realisasi += p.realisasi;
            put(worksheet, r, 1, index + 1, CELL);
            put(worksheet, r, 2, row.nama, CELL_LEFT);
            writeDays(row, 'single');
            writeTail([p.normal, p.tidak_normal, p.rencana, p.realisasi, p.hasil]);
            r++;
        });
        put(worksheet, r, 1, 'TOTAL', BOLD);
        mergeCells(worksheet, r - 1, 0, r - 1, firstTail - 2);
        writeTail([totals.normal, totals.tidak_normal, totals.rencana, totals.realisasi, totals.rencana > 0 ? `${Math.round((totals.realisasi / totals.rencana) * 100)}%` : '-']);
        r += 2;
        put(worksheet, r, 1, 'CATATAN: 1. Isi 0 (N) jika temuan pemeriksaan normal; 2. Isi 1 (T) jika temuan pemeriksaan tidak normal.', { align: 'left' });
    } else if (sheet.layout === 'shift') {
        rows.forEach((row, index) => {
            const rekap = rekapAbsensi(row);
            put(worksheet, r, 1, index + 1, BOLD);
            put(worksheet, r, 2, row.pic, BOLD);
            put(worksheet, r, 3, row.nama, BOLD);
            writeDays(row, 'single');
            writeTail([rekap.P, rekap.S, rekap.I, rekap.C, rekap.M, rekap.kehadiran]);
            r++;
        });
    } else {
        rows.forEach((row, index) => {
            put(worksheet, r, 1, index + 1, CELL);
            put(worksheet, r, 2, row.nama || 'IK........................................', CELL_LEFT);
            put(worksheet, r, 3, row.pic, CELL_LEFT);
            writeDays(row, 'single');
            writeTail([jadwalProgress(row).rencana]);
            r++;
        });

        const planned = rows.filter((row) => jadwalProgress(row).rencana > 0).length;
        const done = rows.filter((row) => jadwalProgress(row).realisasi > 0).length;
        put(worksheet, r, 1, 'TOTAL IK', BOLD);
        mergeCells(worksheet, r - 1, 0, r - 1, totalCols - 2);
        put(worksheet, r, totalCols, rows.reduce((sum, row) => sum + jadwalProgress(row).rencana, 0), BOLD);
        r += 2;
        put(worksheet, r, 2, 'RENCANA', CELL_LEFT);
        put(worksheet, r, 3, planned, CELL);
        put(worksheet, r + 1, 2, 'REALISASI', CELL_LEFT);
        put(worksheet, r + 1, 3, done, CELL);
        put(worksheet, r, 4, planned > 0 ? `${Math.round((done / planned) * 100)}%` : '0%', BOLD);
    }

    await downloadWorkbook(workbook, filename);
}
