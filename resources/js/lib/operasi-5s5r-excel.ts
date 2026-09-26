import ExcelJS from 'exceljs';
import {
    applyStyle,
    downloadWorkbook,
    loadImageBase64,
    mergeCells,
    XLSX_COLORS,
} from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

export type Operasi5s5rExportRow = {
    minggu: number;
    programKey: string;
    programLabel: string;
    programIstilah: string;
    detail: string | null;
    pic: string | null;
    kondisi_awal: string | null;
    membersihkan: boolean;
    merapikan: boolean;
    membuang_sampah: boolean;
    mengecat: boolean;
    lainnya: boolean;
    progres: string | null;
    kondisi_akhir: string | null;
    jumlah: number | null;
    keterangan: string | null;
};

export type Operasi5s5rExportWeek = {
    minggu: number;
    rows: Operasi5s5rExportRow[];
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

const HEADER_GREY: StyleSpec = {
    fill: XLSX_COLORS.headerGrey,
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

export async function downloadOperasi5s5rWorkbook(
    unitName: string,
    periodLabel: string,
    weeks: Operasi5s5rExportWeek[],
    filename: string,
) {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('Program 5S 5R', {
        views: [{ showGridLines: true }],
        pageSetup: {
            orientation: 'landscape',
            paperSize: 9, // A4
            fitToPage: true,
            fitToWidth: 1,
            fitToHeight: 0,
        },
    });

    const TOTAL_COLS = 17;

    // Kop lines
    // Row 1 to 4: Left logo (col 1-3), Right logo (col 15-17), Center titles (col 4-14)
    for (let r = 1; r <= 4; r++) {
        for (let c = 1; c <= TOTAL_COLS; c++) {
            worksheet.getRow(r).getCell(c).border = {
                top: { style: 'thin', color: { argb: 'FF000000' } },
                bottom: { style: 'thin', color: { argb: 'FF000000' } },
                left: { style: 'thin', color: { argb: 'FF000000' } },
                right: { style: 'thin', color: { argb: 'FF000000' } },
            };
        }
    }

    mergeCells(worksheet, 0, 0, 3, 2); // Left logo
    mergeCells(worksheet, 0, 14, 3, 16); // Right logo

    const titleLines = [
        'JASA PENDUKUNG TEKNIK 6 SITE',
        unitName.toUpperCase(),
        'LAPORAN PROJECT',
        'JADWAL PROGRAM 5S 5R PENGOPERASIAN KIT',
    ];

    titleLines.forEach((text, i) => {
        const row = i + 1;
        put(worksheet, row, 4, text, {
            bold: true,
            size: i === 3 ? 11 : 10,
            align: 'center',
            valign: 'center',
            border: true,
        });
        mergeCells(worksheet, i, 3, i, 13);
        worksheet.getRow(row).height = 18;
    });

    // Images
    const pln = await loadImageBase64('/logo/sidebar-logo.png');
    if (pln) {
        const id = workbook.addImage({ base64: pln, extension: 'png' });
        worksheet.addImage(id, {
            tl: { col: 0.2, row: 0.3 },
            ext: { width: 140, height: 42 },
        });
    }

    const mkp = await loadImageBase64('/logo/mkp.jpg');
    if (mkp) {
        const id = workbook.addImage({ base64: mkp, extension: 'jpeg' });
        worksheet.addImage(id, {
            tl: { col: 14.3, row: 0.3 },
            ext: { width: 62, height: 44 },
        });
    }

    // Row 5: Period label
    worksheet.getRow(5).height = 22;
    put(worksheet, 5, 1, `Periode : ${periodLabel}`, {
        bold: true,
        italic: true,
        size: 10,
        align: 'left',
        valign: 'center',
    });

    // Table Header: Rows 6 & 7
    worksheet.getRow(6).height = 24;
    worksheet.getRow(7).height = 22;

    // Col 1-3: Program Kerja 5S 5R
    put(worksheet, 6, 1, 'Program Kerja 5S 5R', HEADER_ORANGE);
    mergeCells(worksheet, 5, 0, 5, 2);

    put(worksheet, 7, 1, 'Periode', HEADER_GREY);
    put(worksheet, 7, 2, 'Uraian', HEADER_GREY);
    put(worksheet, 7, 3, 'Detail', HEADER_GREY);

    // Col 4: PIC
    put(worksheet, 6, 4, 'PIC', HEADER_ORANGE);
    mergeCells(worksheet, 5, 3, 6, 3);

    // Col 5: Kondisi Awal
    put(worksheet, 6, 5, 'Kondisi Awal', HEADER_ORANGE);
    mergeCells(worksheet, 5, 4, 6, 4);

    // Col 6-10: Tindakan
    put(worksheet, 6, 6, 'Tindakan', HEADER_ORANGE);
    mergeCells(worksheet, 5, 5, 5, 9);
    put(worksheet, 7, 6, 'Membersihkan', HEADER_GREY);
    put(worksheet, 7, 7, 'Merapikan', HEADER_GREY);
    put(worksheet, 7, 8, 'Membuang sampah', HEADER_GREY);
    put(worksheet, 7, 9, 'Mengecat', HEADER_GREY);
    put(worksheet, 7, 10, 'Lainnya', HEADER_GREY);

    // Col 11-14: Progres
    put(worksheet, 6, 11, 'Progres', HEADER_ORANGE);
    mergeCells(worksheet, 5, 10, 5, 13);
    put(worksheet, 7, 11, '0-25%', HEADER_GREY);
    put(worksheet, 7, 12, '26-50%', HEADER_GREY);
    put(worksheet, 7, 13, '51-75%', HEADER_GREY);
    put(worksheet, 7, 14, '76-100%', HEADER_GREY);

    // Col 15: Kondisi Akhir
    put(worksheet, 6, 15, 'Kondisi Akhir', HEADER_ORANGE);
    mergeCells(worksheet, 5, 14, 6, 14);

    // Col 16: Jumlah
    put(worksheet, 6, 16, 'Jumlah', HEADER_ORANGE);
    mergeCells(worksheet, 5, 15, 6, 15);

    // Col 17: Keterangan
    put(worksheet, 6, 17, 'Keterangan', HEADER_ORANGE);
    mergeCells(worksheet, 5, 16, 6, 16);

    // Populate Data
    let currentRow = 8;
    for (const week of weeks) {
        const startWeekRow = currentRow;
        for (const row of week.rows) {
            worksheet.getRow(currentRow).height = 36;

            put(worksheet, currentRow, 1, `Minggu ke ${week.minggu}`, CELL_CENTER);
            put(worksheet, currentRow, 2, `${row.programLabel}\n(${row.programIstilah})`, CELL_CENTER);
            put(worksheet, currentRow, 3, row.detail ?? '', CELL_LEFT);
            put(worksheet, currentRow, 4, row.pic ?? '', CELL_CENTER);
            put(worksheet, currentRow, 5, row.kondisi_awal ?? '', CELL_CENTER);

            put(worksheet, currentRow, 6, row.membersihkan ? '✓' : '', CELL_CENTER);
            put(worksheet, currentRow, 7, row.merapikan ? '✓' : '', CELL_CENTER);
            put(worksheet, currentRow, 8, row.membuang_sampah ? '✓' : '', CELL_CENTER);
            put(worksheet, currentRow, 9, row.mengecat ? '✓' : '', CELL_CENTER);
            put(worksheet, currentRow, 10, row.lainnya ? '✓' : '', CELL_CENTER);

            put(worksheet, currentRow, 11, row.progres === '0-25' ? '✓' : '', CELL_CENTER);
            put(worksheet, currentRow, 12, row.progres === '26-50' ? '✓' : '', CELL_CENTER);
            put(worksheet, currentRow, 13, row.progres === '51-75' ? '✓' : '', CELL_CENTER);
            put(worksheet, currentRow, 14, row.progres === '76-100' ? '✓' : '', CELL_CENTER);

            put(worksheet, currentRow, 15, row.kondisi_akhir ?? '', CELL_CENTER);
            put(worksheet, currentRow, 16, row.jumlah !== null ? row.jumlah : '', CELL_CENTER);
            put(worksheet, currentRow, 17, row.keterangan ?? '', CELL_LEFT);

            currentRow++;
        }

        // Merge "Minggu ke X" column for the week
        if (week.rows.length > 1) {
            mergeCells(worksheet, startWeekRow - 1, 0, currentRow - 2, 0);
        }
    }

    // Set Column Widths
    worksheet.columns = [
        { width: 14 }, // 1: Periode
        { width: 16 }, // 2: Uraian
        { width: 34 }, // 3: Detail
        { width: 18 }, // 4: PIC
        { width: 14 }, // 5: Kondisi Awal
        { width: 14 }, // 6: Membersihkan
        { width: 13 }, // 7: Merapikan
        { width: 16 }, // 8: Membuang sampah
        { width: 12 }, // 9: Mengecat
        { width: 12 }, // 10: Lainnya
        { width: 10 }, // 11: 0-25%
        { width: 10 }, // 12: 26-50%
        { width: 10 }, // 13: 51-75%
        { width: 10 }, // 14: 76-100%
        { width: 14 }, // 15: Kondisi Akhir
        { width: 10 }, // 16: Jumlah
        { width: 22 }, // 17: Keterangan
    ];

    await downloadWorkbook(workbook, filename);
}
