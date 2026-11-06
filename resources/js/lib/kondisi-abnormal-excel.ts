import ExcelJS from 'exceljs';
import { applyStyle, buildDocumentHeader, downloadWorkbook, loadImageBase64, mergeCells, SPECS } from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/**
 * Excel export of the Operasi input "Laporan Kondisi Abnormal dan Gangguan
 * Pembangkit", mirroring resources/views/operasi/input/kondisi-abnormal-pdf.blade.php
 * (kronologi of a gangguan, the eviden photo, the TOTAL row and the notes).
 */

export type KondisiAbnormalExcelRow = {
    no: number;
    tanggal: string;
    uraian_kondisi: string;
    mesin: string;
    peralatan: string;
    is_abnormal: number;
    durasi_abnormal: number;
    is_gangguan: number;
    durasi_gangguan: number;
    jam_mulai: string;
    jam_selesai: string;
    parameter_terkait: string;
    kronologi: string;
    tl_pendek_keterangan: string;
    tl_panjang: string;
    dampak_sistem: string;
    dampak_pembangkit: string;
    eviden_url: string | null;
} & Record<string, unknown>;

const CELL: StyleSpec = { align: 'center', valign: 'center', border: true, wrap: true };
const CELL_LEFT: StyleSpec = { align: 'left', valign: 'top', border: true, wrap: true };
const HEADER: StyleSpec = { ...SPECS.headerOrange, size: 9 };

const put = (sheet: ExcelJS.Worksheet, row: number, col: number, value: string | number, spec?: StyleSpec) => {
    const cell = sheet.getRow(row).getCell(col);
    cell.value = value;

    if (spec) {
        applyStyle(cell, spec);
    }
};

const imageExtension = (url: string): 'jpeg' | 'png' | 'gif' => (/png/i.test(url) ? 'png' : /gif/i.test(url) ? 'gif' : 'jpeg');

/** "2026-08-12" → "12/08/2026". */
const dmy = (date: string) => (date ? date.split('-').reverse().join('/') : '');

export async function downloadKondisiAbnormalWorkbook(
    unitName: string,
    periodLabel: string,
    rows: KondisiAbnormalExcelRow[],
    tindakPendek: Record<string, string>,
    filename: string,
) {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Abnormal & Gangguan', {
        views: [{ showGridLines: false }],
        pageSetup: { orientation: 'landscape', paperSize: 9, fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
    });

    const tindak = Object.entries(tindakPendek);
    // NO, URAIAN, TANGGAL, 4 status, PARAMETER, KRONOLOGI, tindak…, TL PANJANG, DAMPAK ×2, EVIDEN
    const colWidths = [5, 34, 12, 10, 8, 10, 8, 22, 30, ...tindak.map((_, i) => (i === tindak.length - 1 ? 22 : 6)), 24, 22, 22, 16];
    const totalCols = colWidths.length;
    colWidths.forEach((width, i) => (worksheet.getColumn(i + 1).width = width));

    await buildDocumentHeader({ workbook, worksheet }, {
        totalCols,
        colWidths,
        titleLines: ['JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE -KIT', `LAPORAN PROJECT ${unitName.toUpperCase()}`, 'LAPORAN KONDISI ABNORMAL DAN GANGGUAN PEMBANGKIT'],
        barTitle: `LAPORAN KONDISI ABNORMAL DAN GANGGUAN PEMBANGKIT — PERIODE : ${periodLabel.toUpperCase()}`,
    });

    const top = 6;
    const statusCol = 4;
    const kronologiCol = 9;
    const tindakCol = 10;
    const afterTindak = tindakCol + tindak.length;
    const single = (col: number, label: string) => {
        put(worksheet, top, col, label, HEADER);
        put(worksheet, top + 1, col, '', HEADER);
        mergeCells(worksheet, top - 1, col - 1, top, col - 1);
    };
    const group = (col: number, label: string, subs: string[]) => {
        put(worksheet, top, col, label, HEADER);
        subs.forEach((sub, i) => {
            if (i > 0) {
                applyStyle(worksheet.getRow(top).getCell(col + i), HEADER);
            }

            put(worksheet, top + 1, col + i, sub, HEADER);
        });
        mergeCells(worksheet, top - 1, col - 1, top - 1, col - 1 + subs.length - 1);
    };

    single(1, 'NO');
    single(2, 'INDIKASI / URAIAN KONDISI');
    single(3, 'TANGGAL');
    group(statusCol, 'STATUS & DURASI (JAM)', ['ABNORMAL', 'DURASI', 'GANGGUAN', 'DURASI']);
    single(8, 'PARAMETER TERKAIT');
    single(kronologiCol, 'KRONOLOGI (GANGGUAN)');
    group(tindakCol, 'TINDAK LANJUT JANGKA PENDEK', tindak.map(([, label]) => label.toUpperCase()));
    single(afterTindak, 'TINDAK LANJUT JANGKA PANJANG');
    single(afterTindak + 1, 'DAMPAK TERHADAP SISTEM');
    single(afterTindak + 2, 'DAMPAK TERHADAP PEMBANGKIT');
    single(afterTindak + 3, 'EVIDEN');
    worksheet.getRow(top).height = 22;
    worksheet.getRow(top + 1).height = 22;

    let r = top + 2;

    if (rows.length === 0) {
        put(worksheet, r, 1, 'Belum ada laporan kondisi abnormal / gangguan pada periode ini.', CELL);

        for (let c = 2; c <= totalCols; c++) {
            applyStyle(worksheet.getRow(r).getCell(c), CELL);
        }

        mergeCells(worksheet, r - 1, 0, r - 1, totalCols - 1);
        r++;
    }

    for (const row of rows) {
        const where = [row.mesin, row.peralatan].filter(Boolean).join(' · ');
        const jam = row.jam_mulai && row.jam_selesai ? `\n${row.jam_mulai}–${row.jam_selesai}` : '';

        put(worksheet, r, 1, row.no, { ...CELL, bold: true });
        put(worksheet, r, 2, where ? `${row.uraian_kondisi}\n${where}` : row.uraian_kondisi, CELL_LEFT);
        put(worksheet, r, 3, dmy(row.tanggal), CELL);
        put(worksheet, r, statusCol, row.is_abnormal === 1 ? 1 : '', { ...CELL, bold: true });
        put(worksheet, r, statusCol + 1, row.is_abnormal === 1 && row.durasi_abnormal > 0 ? row.durasi_abnormal : '', CELL);
        put(worksheet, r, statusCol + 2, row.is_gangguan === 1 ? 1 : '', { ...CELL, bold: true });
        put(worksheet, r, statusCol + 3, row.is_gangguan === 1 && row.durasi_gangguan > 0 ? `${row.durasi_gangguan}${jam}` : jam.trim(), CELL);
        put(worksheet, r, 8, row.parameter_terkait, CELL_LEFT);
        put(worksheet, r, kronologiCol, row.kronologi, CELL_LEFT);
        tindak.forEach(([key], i) => {
            const ticked = Number(row[key]) === 1;

            // Lainnya: the tick plus the keterangan of the short-term follow-up.
            if (i === tindak.length - 1) {
                put(worksheet, r, tindakCol + i, [ticked ? '✓' : '', row.tl_pendek_keterangan].filter(Boolean).join(' '), CELL_LEFT);
            } else {
                put(worksheet, r, tindakCol + i, ticked ? '✓' : '', { ...CELL, bold: true });
            }
        });
        put(worksheet, r, afterTindak, row.tl_panjang, CELL_LEFT);
        put(worksheet, r, afterTindak + 1, row.dampak_sistem, CELL_LEFT);
        put(worksheet, r, afterTindak + 2, row.dampak_pembangkit, CELL_LEFT);
        put(worksheet, r, afterTindak + 3, '', CELL);

        const base64 = row.eviden_url ? await loadImageBase64(row.eviden_url) : null;

        if (row.eviden_url && base64) {
            const id = workbook.addImage({ base64, extension: imageExtension(row.eviden_url) });
            worksheet.addImage(id, { tl: { col: afterTindak + 2 + 0.1, row: r - 1 + 0.1 }, ext: { width: 96, height: 64 } });
            worksheet.getRow(r).height = 54;
        }

        r++;
    }

    // TOTAL like the PDF: abnormal / gangguan count & hours, and each short-term follow-up.
    const sum = (pick: (row: KondisiAbnormalExcelRow) => number) => rows.reduce((total, row) => total + pick(row), 0);
    const TOTAL: StyleSpec = { ...SPECS.total, wrap: true };

    for (let c = 1; c <= totalCols; c++) {
        put(worksheet, r, c, '', TOTAL);
    }

    put(worksheet, r, 1, 'TOTAL', TOTAL);
    mergeCells(worksheet, r - 1, 0, r - 1, 2);
    put(worksheet, r, statusCol, sum((row) => (row.is_abnormal === 1 ? 1 : 0)), TOTAL);
    put(worksheet, r, statusCol + 1, Math.round(sum((row) => Number(row.durasi_abnormal) || 0) * 100) / 100, TOTAL);
    put(worksheet, r, statusCol + 2, sum((row) => (row.is_gangguan === 1 ? 1 : 0)), TOTAL);
    put(worksheet, r, statusCol + 3, Math.round(sum((row) => Number(row.durasi_gangguan) || 0) * 100) / 100, TOTAL);
    tindak.forEach(([key], i) => put(worksheet, r, tindakCol + i, sum((row) => (Number(row[key]) === 1 ? 1 : 0)), TOTAL));

    const notes = [
        'NOTE : 1. KOLOM ABNORMAL DAN GANGGUAN DI ISI ANGKA 1',
        '2. KOLOM DURASI DI ISI JAM',
        '3. TINDAK LANJUT JANGKA PENDEK: SR = SERVICE REQUEST, FLM = FIRST LINE MAINTENANCE, LAINNYA = TULISKAN KETERANGANNYA',
        '4. KRONOLOGI DIISI UNTUK KEJADIAN GANGGUAN',
    ];
    notes.forEach((note, i) => put(worksheet, r + 2 + i, 1, note, { bold: true, size: 9, align: 'left' }));

    await downloadWorkbook(workbook, filename);
}
