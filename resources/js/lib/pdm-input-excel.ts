import ExcelJS from 'exceljs';
import { applyStyle, buildDocumentHeader, downloadWorkbook, mergeCells, SPECS } from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/**
 * Excel exports of the PdM input forms (Kesiapan APD, Monitoring Sample,
 * Permit to Work), built with ExcelJS and the same PLN/MKP kop as the jadwal
 * exports. Each mirrors its PDF in resources/views/pdm/input.
 */

const CELL: StyleSpec = { align: 'center', valign: 'center', border: true, wrap: true };
const CELL_LEFT: StyleSpec = { align: 'left', valign: 'center', border: true, wrap: true };
const HEADER: StyleSpec = { ...SPECS.headerGrey, fill: '00B0F0' };
const HEADER_NAVY: StyleSpec = { ...SPECS.headerGrey, fill: '1F4E79', color: 'FFFFFF' };
const HEADER_ORANGE: StyleSpec = SPECS.headerOrange;

type Sheet = { workbook: ExcelJS.Workbook; worksheet: ExcelJS.Worksheet };

const newSheet = (name: string): Sheet => {
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet(name.slice(0, 31), {
        views: [{ showGridLines: false }],
        pageSetup: { orientation: 'landscape', paperSize: 9, fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
    });

    return { workbook, worksheet };
};

const put = (sheet: ExcelJS.Worksheet, row: number, col: number, value: string | number | null | undefined, spec?: StyleSpec) => {
    const cell = sheet.getRow(row).getCell(col);
    cell.value = value ?? '';

    if (spec) {
        applyStyle(cell, spec);
    }
};

const widths = (sheet: ExcelJS.Worksheet, values: number[]) => values.forEach((w, i) => (sheet.getColumn(i + 1).width = w));

// ---------------------------------------------------------------- Kesiapan APD

export type KesiapanApdExportRow = {
    kelompok: string;
    inspeksi: string;
    jumlah: number | null;
    satuan: string | null;
    kelayakan_apd: string | null;
    peralatan_jumlah: string | null;
    peralatan_kelayakan: string | null;
    sop_pnp: string | null;
    sop_vendor: string | null;
    p3k_kotak: string | null;
    p3k_isi: string | null;
    cara_kerja: string | null;
    keterangan: string | null;
};

export type KesiapanApdExportMeta = {
    catatan: string;
};

const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
const ANSWER_KEYS = ['kelayakan_apd', 'peralatan_jumlah', 'peralatan_kelayakan', 'sop_pnp', 'sop_vendor', 'p3k_kotak', 'p3k_isi', 'cara_kerja'] as const;

export async function downloadKesiapanApdWorkbook(unitName: string, periodLabel: string, rows: KesiapanApdExportRow[], meta: KesiapanApdExportMeta, filename: string) {
    const { workbook, worksheet } = newSheet('Kesiapan APD');
    const colWidths = [5, 30, 9, 9, 11, 13, 11, 16, 16, 9, 9, 12, 22];
    widths(worksheet, colWidths);

    await buildDocumentHeader({ workbook, worksheet }, {
        totalCols: 13,
        colWidths,
        titleLines: ['JASA PENDUKUNG TEKNIS 11 SITE', `PLN NP UP KENDARI ${unitName.toUpperCase()}`, 'KESIAPAN APD'],
        barTitle: `BAGIAN PdM PEMBANGKIT — ${periodLabel.toUpperCase()}`,
    });

    // Two-row header (rows 6-7).
    const top: [number, number, string][] = [[3, 5, 'Alat Pelindung Diri'], [6, 7, 'Peralatan Kerja/ Area Kerja'], [8, 9, 'SOP/ IK'], [10, 11, 'P3K'], [12, 12, 'Cara Kerja']];
    put(worksheet, 6, 1, 'No', HEADER);
    put(worksheet, 6, 2, 'Inspeksi', HEADER);
    put(worksheet, 6, 13, 'Keterangan', HEADER);
    mergeCells(worksheet, 5, 0, 6, 0);
    mergeCells(worksheet, 5, 1, 6, 1);
    mergeCells(worksheet, 5, 12, 6, 12);
    top.forEach(([from, to, label]) => {
        put(worksheet, 6, from, label, HEADER);

        if (to > from) {
            mergeCells(worksheet, 5, from - 1, 5, to - 1);
        }
    });
    ['JUMLAH', 'SATUAN', 'Layak/ Tdk layak', 'Jml memenuhi/ Tdk memenuhi', 'Layak/ Tdk Layak', 'Memenuhi/ Tidak memenuhi semua bidang pekerjaan PNP', 'Memenuhi/ Tidak memenuhi semua bidang pekerjaan Vendor', 'Ada/ Tdk ada', 'Ada/ Tdk ada', 'Ergonomi/ Tdk Ergonomi']
        .forEach((label, i) => put(worksheet, 7, i + 3, label, HEADER));

    for (let c = 1; c <= 13; c++) {
        applyStyle(worksheet.getRow(6).getCell(c), HEADER);
        applyStyle(worksheet.getRow(7).getCell(c), HEADER);
    }

    worksheet.getRow(7).height = 54;

    let r = 8;
    let group = -1;
    let number = 0;
    let lastKelompok: string | null = null;

    for (const row of rows) {
        if (row.kelompok !== lastKelompok) {
            lastKelompok = row.kelompok;
            group++;
            number = 0;
            put(worksheet, r, 1, ROMAN[group] ?? String(group + 1), { ...CELL, bold: true });
            put(worksheet, r, 2, row.kelompok, { ...CELL_LEFT, bold: true });
            mergeCells(worksheet, r - 1, 1, r - 1, 12);
            r++;
        }

        put(worksheet, r, 1, ++number, CELL);
        put(worksheet, r, 2, row.inspeksi, CELL_LEFT);
        put(worksheet, r, 3, row.jumlah ?? '', CELL);
        put(worksheet, r, 4, row.satuan ?? '', CELL);
        ANSWER_KEYS.forEach((key, i) => put(worksheet, r, i + 5, row[key] || '-', CELL));
        put(worksheet, r, 13, row.keterangan ?? '', CELL_LEFT);
        r++;
    }

    r++;
    put(worksheet, r, 1, `Catatan: ${meta.catatan}`, { italic: false, align: 'left', wrap: true });
    mergeCells(worksheet, r - 1, 0, r - 1, 12);

    await downloadWorkbook(workbook, filename);
}

// ------------------------------------------------------------ Monitoring Sample

export type SampleColumn = { key: string; label: string; type?: string; options?: string[] };
export type SampleSection = { key: string; title: string; default_rows: number; columns: SampleColumn[] };
export type SampleRekapRow = {
    jenis: string;
    target: number | null;
    terkirim: number;
    belum_terkirim: number;
    hasil_diterima: number;
    menunggu: number;
    perlu_tindak_lanjut: number;
    status: string;
    keterangan: string;
};

export async function downloadSampleMonitoringWorkbook(
    unitName: string,
    periodLabel: string,
    header: { lokasi: string; pic_monitoring: string; catatan: string },
    sections: SampleSection[],
    rows: Record<string, Record<string, string | null>[]>,
    rekap: SampleRekapRow[],
    filename: string,
) {
    const { workbook, worksheet } = newSheet('Monitoring Sample');
    const totalCols = 12;
    const colWidths = [5, 14, 14, 16, 16, 14, 11, 16, 16, 14, 14, 20];
    widths(worksheet, colWidths);

    await buildDocumentHeader({ workbook, worksheet }, {
        totalCols,
        colWidths,
        titleLines: ['JASA PENDUKUNG TEKNIS 6 - 11 SITE', `PLN NP UP KENDARI ${unitName.toUpperCase()}`, 'FORM MONITORING PEMERIKSAAN & PENGIRIMAN SAMPLE PDM'],
        barTitle: `BAGIAN PdM PEMBANGKIT — ${periodLabel.toUpperCase()}`,
    });

    let r = 6;
    const field = (label: string, value: string) => {
        put(worksheet, r, 1, label, { ...CELL_LEFT, bold: true, fill: 'DDEBF7' });
        mergeCells(worksheet, r - 1, 0, r - 1, 1);
        put(worksheet, r, 3, value, CELL_LEFT);
        mergeCells(worksheet, r - 1, 2, r - 1, 5);
        r++;
    };
    field('Unit / Lokasi', header.lokasi);
    field('PIC Monitoring', header.pic_monitoring);
    r++;

    const title = (text: string) => {
        put(worksheet, r, 1, text, { ...HEADER_NAVY, align: 'left' });
        mergeCells(worksheet, r - 1, 0, r - 1, totalCols - 1);
        r++;
    };

    const section = (key: string) => {
        const spec = sections.find((s) => s.key === key);

        if (!spec) {
            return;
        }

        title(spec.title);
        put(worksheet, r, 1, 'No', HEADER_NAVY);
        spec.columns.forEach((column, i) => put(worksheet, r, i + 2, column.label, HEADER_NAVY));
        worksheet.getRow(r).height = 30;
        r++;
        (rows[key] ?? []).forEach((row, index) => {
            put(worksheet, r, 1, index + 1, CELL);
            spec.columns.forEach((column, i) => put(worksheet, r, i + 2, row[column.key] ?? '', column.type === 'date' || column.type === 'select' ? CELL : CELL_LEFT));
            r++;
        });
        r++;
    };

    section('pengiriman');
    section('hasil');

    title('C. REKAP MONITORING');
    ['No', 'Jenis Sample', 'Target Pengiriman', 'Jumlah Terkirim', 'Jumlah Belum Terkirim', 'Jumlah Hasil Diterima', 'Jumlah Menunggu', 'Jumlah Perlu Tindak Lanjut', 'Status Monitoring', 'Keterangan']
        .forEach((label, i) => put(worksheet, r, i + 1, label, HEADER_NAVY));
    worksheet.getRow(r).height = 30;
    r++;
    rekap.forEach((row, index) => {
        const spec = row.jenis === 'TOTAL' ? { ...CELL, bold: true, fill: 'F2F2F2' } : CELL;
        [row.jenis === 'TOTAL' ? '' : index + 1, row.jenis, row.target ?? '-', row.terkirim, row.belum_terkirim, row.hasil_diterima, row.menunggu, row.perlu_tindak_lanjut, row.status, row.keterangan]
            .forEach((value, i) => put(worksheet, r, i + 1, value, i === 1 ? { ...spec, align: 'left' } : spec));
        r++;
    });
    r++;

    section('temuan');

    title('E. CATATAN MONITORING');
    put(worksheet, r, 1, header.catatan, { ...CELL_LEFT, valign: 'top' });
    mergeCells(worksheet, r - 1, 0, r + 2, totalCols - 1);

    await downloadWorkbook(workbook, filename);
}

// --------------------------------------------------------------- Permit to Work

export type PermitExportRow = { no_urut: number; uraian: string; tanggal: string | null; status: 'open' | 'close'; filled: boolean };

export async function downloadPermitToWorkWorkbook(unitName: string, periodLabel: string, rows: PermitExportRow[], filename: string) {
    const { workbook, worksheet } = newSheet('Laporan PTW');
    worksheet.pageSetup.orientation = 'portrait';
    const colWidths = [6, 46, 18, 14, 14];
    widths(worksheet, colWidths);

    await buildDocumentHeader({ workbook, worksheet }, {
        totalCols: 5,
        colWidths,
        titleLines: ['JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE & 6 SITE - KIT', `PLN NP UP KENDARI ${unitName.toUpperCase()}`, 'LAPORAN PERMIT TO WORK PEMBANGKIT'],
        barTitle: `LAPORAN PTW PEMBANGKIT — BAGIAN PdM — ${periodLabel.toUpperCase()}`,
    });

    put(worksheet, 6, 1, 'NO', HEADER_ORANGE);
    put(worksheet, 6, 2, 'URAIAN', HEADER_ORANGE);
    put(worksheet, 6, 3, 'TANGGAL', HEADER_ORANGE);
    put(worksheet, 6, 4, 'STATUS', HEADER_ORANGE);
    applyStyle(worksheet.getRow(6).getCell(5), HEADER_ORANGE);
    mergeCells(worksheet, 5, 3, 5, 4);
    mergeCells(worksheet, 5, 0, 6, 0);
    mergeCells(worksheet, 5, 1, 6, 1);
    mergeCells(worksheet, 5, 2, 6, 2);
    put(worksheet, 7, 4, 'OPEN', HEADER_ORANGE);
    put(worksheet, 7, 5, 'CLOSE', HEADER_ORANGE);
    [1, 2, 3].forEach((c) => applyStyle(worksheet.getRow(7).getCell(c), HEADER_ORANGE));

    let r = 8;

    for (const row of rows) {
        put(worksheet, r, 1, row.no_urut, CELL);
        put(worksheet, r, 2, row.uraian, CELL_LEFT);
        put(worksheet, r, 3, row.tanggal ? row.tanggal.split('-').reverse().join('/') : '', CELL);
        put(worksheet, r, 4, row.filled && row.status === 'open' ? '✓' : '', CELL);
        put(worksheet, r, 5, row.filled && row.status === 'close' ? '✓' : '', CELL);
        r++;
    }

    const total = { ...CELL, bold: true, fill: 'F2F2F2' };
    put(worksheet, r, 1, 'TOTAL', total);
    [2, 3].forEach((c) => applyStyle(worksheet.getRow(r).getCell(c), total));
    mergeCells(worksheet, r - 1, 0, r - 1, 2);
    put(worksheet, r, 4, rows.filter((row) => row.filled && row.status === 'open').length, total);
    put(worksheet, r, 5, rows.filter((row) => row.filled && row.status === 'close').length, total);

    await downloadWorkbook(workbook, filename);
}

// ------------------------------------------------------------ Generic PdM forms

export type PdmFormColumn = {
    key: string;
    label: string;
    type?: string;
    options?: string[];
    group?: string;
    unit?: string;
    width?: number;
    align?: string;
    /** `check` columns sharing this group allow one tick per row (e.g. Ya / Tidak / N/A). */
    exclusive?: string;
};
export type PdmFormField = { key: string; label: string; type?: string; position?: string; group?: string };
export type PdmFormSection = {
    key: string;
    title: string;
    note?: string;
    columns: PdmFormColumn[];
    rows?: Record<string, string | null>[];
    blank_rows?: number;
    fixed?: boolean;
    totals?: string[];
};
export type PdmFormDefinition = {
    key: string;
    title: string;
    description: string;
    orientation: 'portrait' | 'landscape';
    per_machine: boolean;
    /** Rows are numbered 1..n across all sections. */
    continuous_numbering?: boolean;
    header_color: 'orange' | 'navy' | 'cyan';
    fields: PdmFormField[];
    sections: PdmFormSection[];
};
export type PdmFormSummary = { title: string; columns: string[]; rows: string[][] } | null;

const HEADER_BY_COLOR: Record<string, StyleSpec> = { orange: HEADER_ORANGE, navy: HEADER_NAVY, cyan: HEADER };

/** Sum of a numeric column (blank / non-numeric cells count as 0). */
export const columnTotal = (rows: Record<string, string | null>[], key: string): string => {
    const sum = rows.reduce((acc, row) => {
        const value = row[key];

        return acc + (value !== null && value !== '' && Number.isFinite(Number(value)) ? Number(value) : 0);
    }, 0);

    return String(Math.round(sum * 100) / 100);
};

/** Row number offset of a section: rows of the sections before it when numbering runs across sections. */
export const sectionNumberOffset = (form: PdmFormDefinition, rows: Record<string, unknown[]>, sectionKey: string): number => {
    if (!form.continuous_numbering) {
        return 0;
    }

    const index = form.sections.findIndex((s) => s.key === sectionKey);

    return form.sections.slice(0, Math.max(index, 0)).reduce((sum, s) => sum + (rows[s.key]?.length ?? 0), 0);
};

export async function downloadPdmFormWorkbook(
    form: PdmFormDefinition,
    kopLines: [string, string, string],
    barTitle: string,
    header: Record<string, string | null>,
    rows: Record<string, Record<string, string | null>[]>,
    summary: PdmFormSummary,
    filename: string,
) {
    const { workbook, worksheet } = newSheet(form.title);
    worksheet.pageSetup.orientation = form.orientation;
    const totalCols = Math.max(...form.sections.map((s) => s.columns.length + 1), 6);
    const colWidths = Array.from({ length: totalCols }, (_, i) => (i === 0 ? 5 : 14));
    widths(worksheet, colWidths);

    await buildDocumentHeader({ workbook, worksheet }, { totalCols, colWidths, titleLines: kopLines, barTitle });

    const headerSpec = HEADER_BY_COLOR[form.header_color] ?? HEADER_ORANGE;
    let r = 6;

    const fieldBlock = (position: string) => {
        form.fields
            .filter((f) => (f.position ?? 'header') === position && f.type !== 'images')
            .forEach((field) => {
                put(worksheet, r, 1, field.label, { ...CELL_LEFT, bold: true, fill: 'F2F2F2' });
                mergeCells(worksheet, r - 1, 0, r - 1, 1);
                put(worksheet, r, 3, header[field.key] ?? '', CELL_LEFT);
                mergeCells(worksheet, r - 1, 2, r - 1, totalCols - 1);

                if (field.type === 'textarea') {
                    worksheet.getRow(r).height = 36;
                }

                r++;
            });
    };

    fieldBlock('header');
    r++;

    for (const section of form.sections) {
        put(worksheet, r, 1, section.title, { bold: true, align: 'left' });
        mergeCells(worksheet, r - 1, 0, r - 1, totalCols - 1);
        r++;

        if (section.note) {
            put(worksheet, r, 1, section.note, { italic: true, align: 'left' });
            mergeCells(worksheet, r - 1, 0, r - 1, totalCols - 1);
            r++;
        }

        const grouped = section.columns.some((c) => c.group);
        const units = section.columns.some((c) => c.unit);
        const headRows = 1 + (grouped ? 1 : 0) + (units ? 1 : 0);
        const top = r;
        put(worksheet, top, 1, 'No', headerSpec);

        if (headRows > 1) {
            mergeCells(worksheet, top - 1, 0, top - 1 + headRows - 1, 0);
        }

        section.columns.forEach((column, i) => {
            const col = i + 2;

            if (column.group) {
                if (i === 0 || section.columns[i - 1].group !== column.group) {
                    let span = 1;

                    while (section.columns[i + span]?.group === column.group) {
                        span++;
                    }

                    put(worksheet, top, col, column.group, headerSpec);

                    if (span > 1) {
                        mergeCells(worksheet, top - 1, col - 1, top - 1, col - 1 + span - 1);
                    }
                }

                put(worksheet, top + 1, col, column.label, headerSpec);

                if (units) {
                    put(worksheet, top + 2, col, column.unit ?? '', headerSpec);
                }
            } else {
                put(worksheet, top, col, column.label, headerSpec);
                const lastRow = column.unit ? top + headRows - 2 : top + headRows - 1;

                if (lastRow > top) {
                    mergeCells(worksheet, top - 1, col - 1, lastRow - 1, col - 1);
                }

                if (column.unit) {
                    put(worksheet, top + headRows - 1, col, column.unit, headerSpec);
                }
            }
        });

        for (let hr = top; hr < top + headRows; hr++) {
            for (let c = 1; c <= section.columns.length + 1; c++) {
                applyStyle(worksheet.getRow(hr).getCell(c), headerSpec);
            }
        }

        r = top + headRows;

        const list = rows[section.key] ?? [];
        const offset = sectionNumberOffset(form, rows, section.key);
        list.forEach((row, index) => {
            put(worksheet, r, 1, offset + index + 1, CELL);
            section.columns.forEach((column, i) => {
                const raw = column.type === 'check' ? (row[column.key] === '1' ? '✓' : '') : (row[column.key] ?? '');
                const numeric = column.type === 'number' && raw !== '' && Number.isFinite(Number(raw));
                const centered = column.align === 'c' || ['number', 'date', 'time', 'select', 'readonly', 'check'].includes(column.type ?? '');
                put(worksheet, r, i + 2, numeric ? Number(raw) : raw, centered ? CELL : CELL_LEFT);
            });
            r++;
        });

        if (section.totals?.length) {
            const total: StyleSpec = { ...CELL, bold: true, fill: 'F2F2F2' };
            put(worksheet, r, 1, 'TOTAL', total);
            applyStyle(worksheet.getRow(r).getCell(2), total);
            mergeCells(worksheet, r - 1, 0, r - 1, 1);
            section.columns.slice(1).forEach((column, i) => {
                put(worksheet, r, i + 3, section.totals?.includes(column.key) ? Number(columnTotal(list, column.key)) : '', total);
            });
            r++;
        }

        r++;
    }

    if (summary) {
        summary.columns.forEach((label, i) => put(worksheet, r, i + 1, label.toUpperCase(), headerSpec));
        r++;
        summary.rows.forEach((row) => {
            row.forEach((cell, i) => put(worksheet, r, i + 1, cell, CELL));
            r++;
        });
        r++;
    }

    fieldBlock('footer');

    await downloadWorkbook(workbook, filename);
}

// ----------------------------------------- Realisasi Pemeliharaan Prediktif

export type RealisasiExportRow = {
    uraian: string;
    mesin: string;
    rencana: number[];
    realisasi: number[];
    durasi: number | null;
    target: number;
    realisasi_count: number;
    kinerja: string;
};
export type RealisasiExportDay = { day: number; is_red: boolean };
export type RealisasiExportMeta = {
    doc_number: string;
    revision: string;
    effective_date: string;
};

export async function downloadRealisasiPrediktifWorkbook(
    unitName: string,
    periodLabel: string,
    days: RealisasiExportDay[],
    rows: RealisasiExportRow[],
    meta: RealisasiExportMeta,
    filename: string,
) {
    const { workbook, worksheet } = newSheet('Realisasi Prediktif');
    const totalCols = 4 + days.length + 4;
    const colWidths = [5, 18, 16, 10, ...days.map(() => 3.5), 9, 9, 10, 10];
    widths(worksheet, colWidths);

    await buildDocumentHeader({ workbook, worksheet }, {
        totalCols,
        colWidths,
        titleLines: ['JASA PENDUKUNG TEKNIS 11 SITE', `PLN NP UP KENDARI ${unitName.toUpperCase()}`, 'BAGIAN PdM PEMBANGKIT'],
        barTitle: 'REALISASI PEMELIHARAAN PREDIKTIF BULANAN',
    });

    put(worksheet, 6, 1, `SENTRAL ${unitName.toUpperCase()}`, { bold: true, align: 'left' });
    mergeCells(worksheet, 5, 0, 5, 3);
    put(worksheet, 6, totalCols - 3, `No. Dokumen: ${meta.doc_number} · Rev ${meta.revision} · ${meta.effective_date}`, { align: 'right' });
    mergeCells(worksheet, 5, totalCols - 4, 5, totalCols - 1);

    const top = 7;
    const grey = SPECS.headerGrey;
    ['NO', 'URAIAN', 'MESIN / TIPE / S.N', 'STATUS'].forEach((label, i) => {
        put(worksheet, top, i + 1, label, grey);
        mergeCells(worksheet, top - 1, i, top, i);
    });
    put(worksheet, top, 5, periodLabel.toUpperCase(), grey);
    mergeCells(worksheet, top - 1, 4, top - 1, 4 + days.length - 1);
    ['DURASI', 'TARGET', 'REALISASI', 'A. KINERJA'].forEach((label, i) => {
        const col = 5 + days.length + i;
        put(worksheet, top, col, label, grey);
        mergeCells(worksheet, top - 1, col - 1, top, col - 1);
    });

    for (let c = 1; c <= totalCols; c++) {
        applyStyle(worksheet.getRow(top).getCell(c), grey);
        applyStyle(worksheet.getRow(top + 1).getCell(c), grey);
    }

    days.forEach((day, i) => put(worksheet, top + 1, 5 + i, day.day, { ...grey, fill: day.is_red ? 'FF0000' : 'D9D9D9' }));

    let r = top + 2;
    rows.forEach((row, index) => {
        [index + 1, row.uraian, row.mesin].forEach((value, i) => {
            put(worksheet, r, i + 1, value, i === 0 ? CELL : { ...CELL_LEFT, bold: true });
            mergeCells(worksheet, r - 1, i, r, i);
        });
        put(worksheet, r, 4, 'RENCANA', { ...CELL, bold: true, fill: '00B0F0' });
        put(worksheet, r + 1, 4, 'REAL', { ...CELL, bold: true, fill: '92D050' });
        days.forEach((day, i) => {
            const spec: StyleSpec = day.is_red ? { ...CELL, fill: 'FF0000' } : CELL;
            put(worksheet, r, 5 + i, row.rencana.includes(day.day) ? 1 : '', spec);
            put(worksheet, r + 1, 5 + i, row.realisasi.includes(day.day) ? 1 : '', spec);
        });
        [row.durasi ?? '', row.target, row.realisasi_count, row.kinerja].forEach((value, i) => {
            const col = 5 + days.length + i;
            put(worksheet, r, col, value, CELL);
            mergeCells(worksheet, r - 1, col - 1, r, col - 1);
        });
        r += 2;
    });

    await downloadWorkbook(workbook, filename);
}
