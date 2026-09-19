import ExcelJS from 'exceljs';
import { applyStyle, buildDocumentHeader, downloadWorkbook, SPECS } from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/**
 * Excel exports of the Logistik & Gudang input forms, built with ExcelJS and
 * the same PLN/MKP kop as the jadwal exports. Each mirrors its PDF in
 * resources/views/logistik/input.
 */

const CELL: StyleSpec = { align: 'center', valign: 'center', border: true, wrap: true };
const CELL_LEFT: StyleSpec = { align: 'left', valign: 'center', border: true, wrap: true };
const HEADER: StyleSpec = { ...SPECS.headerGrey, fill: '9DD9F3' };

const put = (sheet: ExcelJS.Worksheet, row: number, col: number, value: string | number | null | undefined, spec?: StyleSpec) => {
    const cell = sheet.getRow(row).getCell(col);
    cell.value = value ?? '';

    if (spec) {
        applyStyle(cell, spec);
    }
};

// ------------------------------------------------ Rekomendasi Logistik & Gudang

export type RekomendasiExportRow = {
    no_urut: number;
    uraian: string;
    kondisi_existing: string;
    tindak_lanjut: string;
    keterangan: string;
};

export async function downloadRekomendasiWorkbook(unitName: string, periodLabel: string, rows: RekomendasiExportRow[], filename: string) {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Rekomendasi', {
        views: [{ showGridLines: false }],
        pageSetup: { orientation: 'portrait', paperSize: 9, fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
    });
    const colWidths = [7, 24, 34, 34, 26];
    colWidths.forEach((width, i) => (worksheet.getColumn(i + 1).width = width));

    await buildDocumentHeader({ workbook, worksheet }, {
        totalCols: 5,
        colWidths,
        titleLines: ['JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE -KIT', `PLN NP UP KENDARI - ${unitName.toUpperCase()}`, 'REKOMENDASI LOGISTIK & GUDANG'],
        barTitle: `REKOMENDASI LOGISTIK & GUDANG — ${periodLabel.toUpperCase()}`,
    });

    put(worksheet, 6, 2, 'UNIT', { bold: true, align: 'left' });
    put(worksheet, 6, 3, `: ${unitName.toUpperCase()}`, { bold: true, align: 'left' });
    put(worksheet, 7, 2, 'PERIODE/BULAN', { bold: true, align: 'left' });
    put(worksheet, 7, 3, `: ${periodLabel.toUpperCase()}`, { bold: true, align: 'left' });

    ['NO.', 'Uraian', 'Kondisi Existing', 'Tindak Lanjut', 'Keterangan'].forEach((label, i) => put(worksheet, 9, i + 1, label, HEADER));
    worksheet.getRow(9).height = 28;

    let r = 10;

    for (const row of rows) {
        put(worksheet, r, 1, row.no_urut, CELL);
        put(worksheet, r, 2, row.uraian, CELL_LEFT);
        put(worksheet, r, 3, row.kondisi_existing, CELL_LEFT);
        put(worksheet, r, 4, row.tindak_lanjut, CELL_LEFT);
        put(worksheet, r, 5, row.keterangan, CELL_LEFT);
        worksheet.getRow(r).height = 40;
        r++;
    }

    await downloadWorkbook(workbook, filename);
}
