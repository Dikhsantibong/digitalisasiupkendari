import ExcelJS from 'exceljs';
import {
    applyStyle,
    downloadWorkbook,
    loadImageBase64,
    mergeCells,
    SPECS,
    XLSX_COLORS,
} from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

export type UnsafeConditionExportRow = {
    id: number;
    periode: string;
    kategori: string;
    temuan: string;
    kondisi: string;
    tindak_lanjut: string;
    rekomendasi: string;
    lokasi: string;
    keterangan: string;
    foto_sebelum_url?: string | null;
    foto_sesudah_url?: string | null;
};

const CELL_CENTER: StyleSpec = {
    align: 'center',
    valign: 'center',
    border: true,
    wrap: true,
};

const CELL_LEFT: StyleSpec = {
    align: 'left',
    valign: 'center',
    border: true,
    wrap: true,
};

const HEADER_ORANGE: StyleSpec = {
    fill: XLSX_COLORS.orange,
    bold: true,
    color: XLSX_COLORS.black,
    align: 'center',
    valign: 'center',
    wrap: true,
    border: true,
};

const put = (
    sheet: ExcelJS.Worksheet,
    row: number,
    col: number,
    value: string | number | null | undefined,
    spec?: StyleSpec,
) => {
    const cell = sheet.getRow(row).getCell(col);
    cell.value = value ?? '';
    if (spec) {
        applyStyle(cell, spec);
    }
};

export async function downloadUnsafeConditionWorkbook(
    unitName: string,
    periodLabel: string,
    rows: UnsafeConditionExportRow[],
    summary: {
        total: number;
        unsafe_action_count: number;
        unsafe_condition_count: number;
    },
    filename: string,
) {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Unsafe Action & Condition', {
        views: [{ showGridLines: true }],
        pageSetup: {
            orientation: 'landscape',
            paperSize: 9, // A4
            fitToPage: true,
            fitToWidth: 1,
            fitToHeight: 0,
        },
    });

    const colWidths = [6, 16, 20, 30, 15, 26, 26, 18, 14, 18, 18];
    colWidths.forEach((width, i) => (worksheet.getColumn(i + 1).width = width));

    const totalCols = 11;

    // Borders on top Kop (rows 1 to 3)
    for (let r = 1; r <= 3; r++) {
        for (let c = 1; c <= totalCols; c++) {
            worksheet.getRow(r).getCell(c).border = {
                top: { style: 'thin', color: { argb: 'FF000000' } },
                bottom: { style: 'thin', color: { argb: 'FF000000' } },
                left: { style: 'thin', color: { argb: 'FF000000' } },
                right: { style: 'thin', color: { argb: 'FF000000' } },
            };
        }
    }

    // Left logo cell: merge col 1 to 2, row 1 to 3
    mergeCells(worksheet, 0, 0, 2, 1);
    // Right logo cell: merge col 10 to 11, row 1 to 3
    mergeCells(worksheet, 0, 9, 2, 10);

    // Title lines in cols 3 to 9 (0-indexed: 2 to 8)
    const titleLines = [
        'JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE -KIT',
        `LAPORAN PROJECT ${unitName.toUpperCase()}`,
        'LAPORAN UNSAFE ACTION DAN UNSAFE CONDITION',
    ];

    titleLines.forEach((line, i) => {
        const cell = worksheet.getRow(i + 1).getCell(3);
        cell.value = line;
        applyStyle(cell, {
            bold: true,
            size: i === 2 ? 11 : 10,
            align: 'center',
            valign: 'center',
        });
        mergeCells(worksheet, i, 2, i, 8);
    });

    worksheet.getRow(1).height = 20;
    worksheet.getRow(2).height = 20;
    worksheet.getRow(3).height = 22;

    // Load logos
    const pln = await loadImageBase64('/logo/sidebar-logo.png');
    if (pln) {
        const id = workbook.addImage({ base64: pln, extension: 'png' });
        worksheet.addImage(id, {
            tl: { col: 0.15, row: 0.18 },
            ext: { width: 145, height: 42 },
        });
    }

    const mkp = await loadImageBase64('/logo/mkp.jpg');
    if (mkp) {
        const id = workbook.addImage({ base64: mkp, extension: 'jpeg' });
        worksheet.addImage(id, {
            tl: { col: 9.15, row: 0.18 },
            ext: { width: 145, height: 42 },
        });
    }

    // Table Headers (Row 4 and 5)
    // Row 4: NO, PERIODE, KATEGORI, TEMUAN, KONDISI, TINDAK LANJUT, REKOMENDASI, LOKASI, KETERANGAN, EVIDEN (colSpan 2)
    const singleHeaders = [
        { col: 1, label: 'NO' },
        { col: 2, label: 'PERIODE' },
        { col: 3, label: 'KATEGORI' },
        { col: 4, label: 'TEMUAN' },
        { col: 5, label: 'KONDISI' },
        { col: 6, label: 'TINDAK LANJUT' },
        { col: 7, label: 'REKOMENDASI' },
        { col: 8, label: 'LOKASI' },
        { col: 9, label: 'KETERANGAN' },
    ];

    singleHeaders.forEach(({ col, label }) => {
        put(worksheet, 4, col, label, HEADER_ORANGE);
        put(worksheet, 5, col, '', HEADER_ORANGE);
        mergeCells(worksheet, 3, col - 1, 4, col - 1);
    });

    // EVIDEN Header
    put(worksheet, 4, 10, 'EVIDEN', HEADER_ORANGE);
    put(worksheet, 4, 11, '', HEADER_ORANGE);
    mergeCells(worksheet, 3, 9, 3, 10);

    put(worksheet, 5, 10, 'SEBELUM', HEADER_ORANGE);
    put(worksheet, 5, 11, 'SESUDAH', HEADER_ORANGE);

    worksheet.getRow(4).height = 24;
    worksheet.getRow(5).height = 22;

    let r = 6;
    if (rows.length === 0) {
        put(worksheet, r, 1, 'Tidak ada data temuan unsafe action / condition pada periode ini.', CELL_CENTER);
        mergeCells(worksheet, r - 1, 0, r - 1, 10);
        worksheet.getRow(r).height = 28;
        r++;
    } else {
        rows.forEach((row, idx) => {
            put(worksheet, r, 1, idx + 1, CELL_CENTER);
            put(worksheet, r, 2, row.periode, CELL_CENTER);
            put(worksheet, r, 3, row.kategori, { ...CELL_CENTER, bold: true });
            put(worksheet, r, 4, row.temuan, CELL_LEFT);
            put(worksheet, r, 5, row.kondisi, CELL_CENTER);
            put(worksheet, r, 6, row.tindak_lanjut, CELL_LEFT);
            put(worksheet, r, 7, row.rekomendasi, CELL_LEFT);
            put(worksheet, r, 8, row.lokasi, CELL_CENTER);
            put(worksheet, r, 9, row.keterangan?.toUpperCase() || '', { ...CELL_CENTER, bold: true });
            put(worksheet, r, 10, row.foto_sebelum_url ? 'Ada Foto' : '-', CELL_CENTER);
            put(worksheet, r, 11, row.foto_sesudah_url ? 'Ada Foto' : '-', CELL_CENTER);
            worksheet.getRow(r).height = 36;
            r++;
        });
    }

    // Spacing
    r++;

    // Summary Table (Rekapitulasi Temuan Kondisi)
    const summaryStartRow = r;
    put(worksheet, summaryStartRow, 1, 'NO', HEADER_ORANGE);
    put(worksheet, summaryStartRow, 2, 'TEMUAN KONDISI', HEADER_ORANGE);
    put(worksheet, summaryStartRow, 3, '', HEADER_ORANGE);
    mergeCells(worksheet, summaryStartRow - 1, 1, summaryStartRow - 1, 2);

    put(worksheet, summaryStartRow, 4, 'JUMLAH', HEADER_ORANGE);

    // Row 1: Unsafe Action
    put(worksheet, summaryStartRow + 1, 1, 1, CELL_CENTER);
    put(worksheet, summaryStartRow + 1, 2, 'UNSAFE ACTION', CELL_LEFT);
    put(worksheet, summaryStartRow + 1, 3, '', CELL_LEFT);
    mergeCells(worksheet, summaryStartRow, 1, summaryStartRow, 2);
    put(worksheet, summaryStartRow + 1, 4, summary.unsafe_action_count, { ...CELL_CENTER, bold: true });

    // Row 2: Unsafe Condition
    put(worksheet, summaryStartRow + 2, 1, 2, CELL_CENTER);
    put(worksheet, summaryStartRow + 2, 2, 'UNSAFE CONDITION', CELL_LEFT);
    put(worksheet, summaryStartRow + 2, 3, '', CELL_LEFT);
    mergeCells(worksheet, summaryStartRow + 1, 1, summaryStartRow + 1, 2);
    put(worksheet, summaryStartRow + 2, 4, summary.unsafe_condition_count, { ...CELL_CENTER, bold: true });

    // Row 3: Total
    put(worksheet, summaryStartRow + 3, 1, '', { ...CELL_CENTER, fill: 'F5F5F5' });
    put(worksheet, summaryStartRow + 3, 2, 'TOTAL TEMUAN:', { ...CELL_CENTER, bold: true, align: 'right', fill: 'F5F5F5' });
    put(worksheet, summaryStartRow + 3, 3, '', { ...CELL_CENTER, fill: 'F5F5F5' });
    mergeCells(worksheet, summaryStartRow + 2, 0, summaryStartRow + 2, 2);
    put(worksheet, summaryStartRow + 3, 4, summary.total, { ...CELL_CENTER, bold: true, fill: 'F5F5F5' });

    await downloadWorkbook(workbook, filename);
}
