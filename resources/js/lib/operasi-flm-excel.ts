import ExcelJS from 'exceljs';
import { applyStyle, buildDocumentHeader, downloadWorkbook, mergeCells, SPECS } from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/**
 * Excel export of the Operasi input "Monitoring FLM", mirroring
 * resources/views/operasi/input/flm-monitoring-pdf.blade.php.
 */

export type FlmMonitoringRow = {
    no_urut: number;
    mesin: string;
    tanggal: string | null;
    masalah: string;
    kondisi_awal: string[];
    kondisi_akhir: string;
    catatan: string;
    status: 'open' | 'close';
};

const CELL: StyleSpec = { align: 'center', valign: 'center', border: true, wrap: true };
const CELL_LEFT: StyleSpec = { align: 'left', valign: 'center', border: true, wrap: true };
const HEADER: StyleSpec = { ...SPECS.headerGrey, fill: '9DD9F3' };

const put = (sheet: ExcelJS.Worksheet, row: number, col: number, value: string | number, spec?: StyleSpec) => {
    const cell = sheet.getRow(row).getCell(col);
    cell.value = value;

    if (spec) {
        applyStyle(cell, spec);
    }
};

export const isFilled = (row: FlmMonitoringRow) => row.mesin.trim() !== '' || row.masalah.trim() !== '' || !!row.tanggal;

export async function downloadFlmMonitoringWorkbook(unitName: string, periodLabel: string, kondisiAwal: Record<string, string>, rows: FlmMonitoringRow[], formatDate: (date: string) => string, filename: string) {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Monitoring FLM', {
        views: [{ showGridLines: false }],
        pageSetup: { orientation: 'landscape', paperSize: 9, fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
    });

    const kondisi = Object.entries(kondisiAwal);
    const totalCols = 4 + kondisi.length + 3;
    const colWidths = [5, 24, 18, 32, ...kondisi.map(() => 12), 16, 30, 9];
    colWidths.forEach((width, i) => (worksheet.getColumn(i + 1).width = width));

    await buildDocumentHeader({ workbook, worksheet }, {
        totalCols,
        colWidths,
        titleLines: ['JASA PENDUKUNG TEKNIS 6 SITE', unitName.toUpperCase(), 'LAPORAN PROJECT'],
        barTitle: `MONITORING FLM — PRIODE : ${periodLabel.toUpperCase()}`,
    });

    const top = 6;
    ['No', 'Mesin / Peralatan', 'Tanggal', 'Masalah Awal yg ditemukan'].forEach((label, i) => {
        put(worksheet, top, i + 1, label, HEADER);
        put(worksheet, top + 1, i + 1, '', HEADER);
        mergeCells(worksheet, top - 1, i, top, i);
    });
    put(worksheet, top, 5, 'Kondisi Awal', HEADER);
    mergeCells(worksheet, top - 1, 4, top - 1, 4 + kondisi.length - 1);
    kondisi.forEach(([, label], i) => {
        if (i > 0) {
            applyStyle(worksheet.getRow(top).getCell(5 + i), HEADER);
        }

        put(worksheet, top + 1, 5 + i, label, HEADER);
    });
    ['Kondisi Akhir', 'Catatan FLM', 'Status'].forEach((label, i) => {
        const col = 5 + kondisi.length + i;
        put(worksheet, top, col, label, HEADER);
        put(worksheet, top + 1, col, '', HEADER);
        mergeCells(worksheet, top - 1, col - 1, top, col - 1);
    });

    let r = top + 2;

    for (const row of rows) {
        put(worksheet, r, 1, row.no_urut, CELL);
        put(worksheet, r, 2, row.mesin, CELL_LEFT);
        put(worksheet, r, 3, row.tanggal ? formatDate(row.tanggal) : '', CELL);
        put(worksheet, r, 4, row.masalah, CELL_LEFT);
        kondisi.forEach(([key], i) => put(worksheet, r, 5 + i, row.kondisi_awal.includes(key) ? '✓' : '', { ...CELL, bold: true }));
        put(worksheet, r, 5 + kondisi.length, row.kondisi_akhir, CELL);
        put(worksheet, r, 6 + kondisi.length, row.catatan, CELL_LEFT);
        put(worksheet, r, 7 + kondisi.length, isFilled(row) ? row.status.toUpperCase() : '', { ...CELL, bold: true });
        r++;
    }

    await downloadWorkbook(workbook, filename);
}
