import ExcelJS from 'exceljs';
import {
    applyStyle,
    buildDocumentHeader,
    downloadWorkbook,
} from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/**
 * Excel of the Resume Bulanan (App\Services\Reports\ModuleResume), full-width
 * landscape (10 base columns A–J ≈ 1,730 px):
 * - Sheet "Resume": full all-in-one executive report (Kop, Identitas & Ringkasan
 *   Unit, 5 Visual KPI Cards + Tabel Perbandingan KPI, Ringkasan Arah Data,
 *   2-column framed 3D Charts & Akumulasi, and all Detail Tables with totals).
 * - Sheet "Data Tabel": all detail tables in unmerged tabular columns for
 *   sorting, filtering and pivot analysis.
 */

export type ResumeExcelData = {
    title: string;
    unit: {
        name: string;
        code?: string | null;
        service_unit?: string | null;
        type?: string | null;
        contract?: string | null;
        capacity_mw?: number;
        status?: string;
        machine_count?: number;
    };
    period: { label: string };
    meta?: { label: string; value: string }[];
    kpis: {
        label: string;
        value: string;
        previous?: string;
        good?: string;
        target_label?: string;
        avg?: string;
        max?: string;
        trend: string | null;
        delta: number | null;
        assessment: string | null;
    }[];
    insights: { tone: string; text: string }[];
    charts: { title: string; subtitle?: string; image: string }[];
    sections: {
        title: string;
        columns: string[];
        rows: string[][];
        footer?: string[];
        note?: string;
    }[];
};

const SECTION_BANNER: StyleSpec = {
    fill: '0B4F7A',
    color: 'FFFFFF',
    bold: true,
    size: 11,
    align: 'left',
    valign: 'center',
    border: true,
};

const SUBSECTION_BANNER: StyleSpec = {
    fill: 'EFF6FF',
    color: '0B4F7A',
    bold: true,
    size: 10,
    align: 'left',
    valign: 'center',
    border: true,
};

const HEADER: StyleSpec = {
    fill: 'FEF08A',
    bold: true,
    size: 9.5,
    align: 'center',
    valign: 'center',
    wrap: true,
    border: true,
};

const FOOTER_STYLE: StyleSpec = {
    fill: 'FEF9C3',
    bold: true,
    size: 9.5,
    align: 'right',
    valign: 'center',
    wrap: true,
    border: true,
};

const CELL: StyleSpec = {
    size: 9.5,
    align: 'left',
    valign: 'center',
    wrap: true,
    border: true,
};

const CELL_RIGHT: StyleSpec = {
    size: 9.5,
    align: 'right',
    valign: 'center',
    wrap: true,
    border: true,
};

const META_LABEL: StyleSpec = {
    fill: 'F1F5F9',
    color: '334155',
    bold: true,
    size: 9.5,
    align: 'left',
    valign: 'center',
    wrap: true,
    border: true,
};

const META_VALUE: StyleSpec = {
    fill: 'FFFFFF',
    color: '0F172A',
    bold: true,
    size: 9.5,
    align: 'left',
    valign: 'center',
    wrap: true,
    border: true,
};

const TONE_BADGE: Record<
    string,
    { label: string; fill: string; color: string }
> = {
    success: { label: 'MEMBAIK', fill: 'DCFCE7', color: '047857' },
    danger: { label: 'PERLU PERHATIAN', fill: 'FEE2E2', color: 'B91C1C' },
    warning: { label: 'WASPADA', fill: 'FEF3C7', color: 'B45309' },
    info: { label: 'SOROTAN DATA', fill: 'DBEAFE', color: '1D4ED8' },
    neutral: { label: 'INFORMASI', fill: 'F1F5F9', color: '475569' },
};

/**
 * 10 balanced columns (A–J):
 * - Each pair (A:B, C:D, E:F, G:H, I:J) = 48 chars (~346 px) for 5 equal KPI cards.
 * - Left half (A:E) = 120 chars (~865 px), Right half (F:J) = 120 chars (~865 px).
 * - Full width (A:J) = 240 chars (~1,730 px), filling a Full HD Excel window.
 */
const COLS = [8, 40, 24, 24, 24, 24, 24, 24, 24, 24];
const TOTAL_COLS = COLS.length;
const RIGHT_COL = 5; // 0-based index of column F
const CHART_WIDTH = 845;

const LANDSCAPE: Partial<ExcelJS.PageSetup> = {
    orientation: 'landscape',
    paperSize: 9,
    fitToPage: true,
    fitToWidth: 1,
    fitToHeight: 0,
};

const THIN_BLACK = { style: 'thin' as const, color: { argb: 'FF000000' } };
const THIN_SLATE = { style: 'thin' as const, color: { argb: 'FFCBD5E1' } };

/** Width and height of a PNG data URI (read from its IHDR header). */
function pngSize(dataUri: string): { width: number; height: number } {
    const base64 = dataUri.slice(
        dataUri.indexOf(',') + 1,
        dataUri.indexOf(',') + 1 + 44,
    );
    const bytes = atob(base64);
    const read = (offset: number) =>
        ((bytes.charCodeAt(offset) << 24) |
            (bytes.charCodeAt(offset + 1) << 16) |
            (bytes.charCodeAt(offset + 2) << 8) |
            bytes.charCodeAt(offset + 3)) >>>
        0;

    return { width: read(16) || 1000, height: read(20) || 440 };
}

/**
 * Apply a style across every cell in the 1-based range [r1..r2, c1..c2], merge
 * it when larger than 1x1, and set the top-left cell's value.
 */
function styleMerged(
    sheet: ExcelJS.Worksheet,
    r1: number,
    c1: number,
    r2: number,
    c2: number,
    spec: StyleSpec,
    value?: string | number,
): void {
    for (let r = r1; r <= r2; r++) {
        for (let c = c1; c <= c2; c++) {
            applyStyle(sheet.getRow(r).getCell(c), spec);
        }
    }

    if (r2 > r1 || c2 > c1) {
        sheet.mergeCells(r1, c1, r2, c2);
    }

    if (value !== undefined) {
        sheet.getRow(r1).getCell(c1).value = value;
    }
}

/**
 * Fill a rectangular range with a solid background and draw a clean outer border
 * around its perimeter (used to frame the 3D chart canvases).
 */
function frameBox(
    sheet: ExcelJS.Worksheet,
    r1: number,
    c1: number,
    r2: number,
    c2: number,
    fillHex = 'FFFFFF',
): void {
    for (let r = r1; r <= r2; r++) {
        for (let c = c1; c <= c2; c++) {
            const cell = sheet.getRow(r).getCell(c);
            cell.fill = {
                type: 'pattern',
                pattern: 'solid',
                fgColor: { argb: `FF${fillHex}` },
            };
            cell.border = {
                top: r === r1 ? THIN_BLACK : THIN_SLATE,
                bottom: r === r2 ? THIN_BLACK : THIN_SLATE,
                left: c === c1 ? THIN_BLACK : undefined,
                right: c === c2 ? THIN_BLACK : undefined,
            };
        }
    }
}

/**
 * Compute 1-based [startCol, endCol] spans for `['No', ...columns]` so that any
 * table with <= 9 data columns stretches flush across all 10 columns (A:J).
 * Tables with > 9 data columns use 1 column per field.
 */
function computeColumnSpans(
    dataColCount: number,
    totalCols: number,
): [number, number][] {
    const spans: [number, number][] = [[1, 1]]; // Col A = 'No'

    if (dataColCount <= 0) {
        return spans;
    }

    const available = totalCols - 1; // 9 columns (B..J)

    if (dataColCount >= available) {
        for (let i = 0; i < dataColCount; i++) {
            spans.push([i + 2, i + 2]);
        }

        return spans;
    }

    const base = Math.floor(available / dataColCount);
    let remainder = available % dataColCount;
    let cursor = 2;

    for (let i = 0; i < dataColCount; i++) {
        const extra = remainder > 0 ? 1 : 0;

        if (remainder > 0) {
            remainder--;
        }

        const width = base + extra;
        spans.push([cursor, cursor + width - 1]);
        cursor += width;
    }

    return spans;
}

/** Semantic style override for status/achievement cells in detail tables. */
function statusCellStyle(text: string, base: StyleSpec): StyleSpec {
    const lower = text.trim().toLowerCase();

    if (
        lower === 'tercapai' ||
        lower === 'normal' ||
        lower === 'berlaku' ||
        lower === 'aman' ||
        lower === 'final' ||
        lower === '100 %' ||
        lower === '100,00 %'
    ) {
        return { ...base, fill: 'DCFCE7', color: '047857', bold: true };
    }

    if (
        lower === 'belum tercapai' ||
        lower === 'waspada' ||
        lower === 'segera habis' ||
        lower === 'di bawah rop'
    ) {
        return { ...base, fill: 'FEF3C7', color: 'B45309', bold: true };
    }

    if (
        lower === 'alarm' ||
        lower === 'expired' ||
        lower === 'habis' ||
        lower.includes('ditolak')
    ) {
        return { ...base, fill: 'FEE2E2', color: 'B91C1C', bold: true };
    }

    return base;
}

function formatKpiTrend(kpi: ResumeExcelData['kpis'][number]): {
    text: string;
    fill: string;
    color: string;
} {
    if (kpi.trend === null || kpi.delta === null) {
        return {
            text: 'Bulan berjalan (belum ada pembanding)',
            fill: 'F1F5F9',
            color: '475569',
        };
    }

    if (kpi.trend === 'tetap') {
        return {
            text: '= Stabil vs bulan lalu',
            fill: 'F1F5F9',
            color: '334155',
        };
    }

    const arrow = kpi.trend === 'naik' ? '▲' : '▼';
    const pct = Math.abs(kpi.delta).toLocaleString('id-ID', {
        maximumFractionDigits: 1,
    });
    const status = (kpi.assessment ?? 'stabil').toUpperCase();

    return {
        text: `${arrow} ${pct}% vs bulan lalu · ${status}`,
        fill:
            kpi.assessment === 'membaik'
                ? 'DCFCE7'
                : kpi.assessment === 'memburuk'
                  ? 'FEE2E2'
                  : 'F1F5F9',
        color:
            kpi.assessment === 'membaik'
                ? '047857'
                : kpi.assessment === 'memburuk'
                  ? 'B91C1C'
                  : '475569',
    };
}

export async function downloadResumeWorkbook(
    data: ResumeExcelData,
    filename: string,
): Promise<void> {
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Resume', {
        views: [{ showGridLines: false }],
        pageSetup: LANDSCAPE,
    });

    const maxSectionCols = Math.max(
        TOTAL_COLS,
        ...data.sections.map((section) => section.columns.length + 1),
    );

    for (let i = 0; i < maxSectionCols; i++) {
        sheet.getColumn(i + 1).width = COLS[i] ?? 18;
    }

    await buildDocumentHeader(
        { workbook, worksheet: sheet },
        {
            totalCols: TOTAL_COLS,
            colWidths: COLS,
            titleLines: [
                'PT PLN NUSANTARA POWER — UP KENDARI',
                `${data.unit.name.toUpperCase()}${data.unit.service_unit ? ` · ${data.unit.service_unit.toUpperCase()}` : ''}`,
                `PERIODE ${data.period.label.toUpperCase()}`,
            ],
            barTitle: data.title.toUpperCase(),
        },
    );

    let row = 6;
    const sectionBanner = (text: string) => {
        styleMerged(sheet, row, 1, row, TOTAL_COLS, SECTION_BANNER, text);
        sheet.getRow(row).height = 24;
        row++;
    };

    // Identitas & Ringkasan Eksekutif Unit (2pairs per row across A:E and F:J).
    const metaItems =
        data.meta && data.meta.length > 0
            ? data.meta
            : [
                  { label: 'Unit Pembangkit', value: data.unit.name },
                  {
                      label: 'Unit Layanan (UL)',
                      value: data.unit.service_unit ?? 'UP Kendari',
                  },
                  { label: 'Periode Laporan', value: data.period.label },
                  { label: 'Dokumen Resume', value: data.title },
              ];

    for (let i = 0; i < metaItems.length; i += 2) {
        const left = metaItems[i];
        const right = metaItems[i + 1];
        styleMerged(sheet, row, 1, row, 2, META_LABEL, left.label);
        styleMerged(sheet, row, 3, row, 5, META_VALUE, left.value);

        if (right) {
            styleMerged(sheet, row, 6, row, 7, META_LABEL, right.label);
            styleMerged(sheet, row, 8, row, 10, META_VALUE, right.value);
        } else {
            styleMerged(sheet, row, 6, row, 10, META_VALUE, '');
        }

        sheet.getRow(row).height = 20;
        row++;
    }

    row++;

    // =========================================================================
    // A. INDIKATOR UTAMA (5 VISUAL CARDS + TABEL PERBANDINGAN KPI)
    // =========================================================================
    sectionBanner(
        'A. INDIKATOR KINERJA UTAMA (KPI) & PERBANDINGAN TERHADAP BULAN LALU',
    );

    // 5 Side-by-side KPI Cards across columns (1-2, 3-4, 5-6, 7-8, 9-10).
    for (let chunkStart = 0; chunkStart < data.kpis.length; chunkStart += 5) {
        const chunk = data.kpis.slice(chunkStart, chunkStart + 5);
        const rTitle = row;
        const rValue = row + 1;
        const rSub = row + 2;
        const rTrend = row + 3;

        chunk.forEach((kpi, idx) => {
            const c1 = idx * 2 + 1;
            const c2 = c1 + 1;
            const trendInfo = formatKpiTrend(kpi);

            styleMerged(
                sheet,
                rTitle,
                c1,
                rTitle,
                c2,
                {
                    fill: 'E2E8F0',
                    color: '1E293B',
                    bold: true,
                    size: 9,
                    align: 'center',
                    valign: 'center',
                    wrap: true,
                    border: true,
                },
                kpi.label.toUpperCase(),
            );
            styleMerged(
                sheet,
                rValue,
                c1,
                rValue,
                c2,
                {
                    fill: 'FFFFFF',
                    color: '0F172A',
                    bold: true,
                    size: 14,
                    align: 'center',
                    valign: 'center',
                    border: true,
                },
                kpi.value,
            );
            styleMerged(
                sheet,
                rSub,
                c1,
                rSub,
                c2,
                {
                    fill: 'F8FAFC',
                    color: '475569',
                    size: 8.5,
                    align: 'center',
                    valign: 'center',
                    wrap: true,
                    border: true,
                },
                `Bulan lalu: ${kpi.previous ?? '—'} · Puncak: ${kpi.max ?? '—'}`,
            );
            styleMerged(
                sheet,
                rTrend,
                c1,
                rTrend,
                c2,
                {
                    fill: trendInfo.fill,
                    color: trendInfo.color,
                    bold: true,
                    size: 8.5,
                    align: 'center',
                    valign: 'center',
                    wrap: true,
                    border: true,
                },
                trendInfo.text,
            );
        });

        sheet.getRow(rTitle).height = 20;
        sheet.getRow(rValue).height = 28;
        sheet.getRow(rSub).height = 18;
        sheet.getRow(rTrend).height = 20;
        row += 4;
    }

    // Detailed KPI Comparison Table across A:J.
    const kpiHeaders: [number, number, string][] = [
        [1, 1, 'No'],
        [2, 2, 'Indikator Kinerja Utama (KPI)'],
        [3, 3, 'Realisasi Bulan Ini'],
        [4, 4, 'Realisasi Bulan Lalu'],
        [5, 5, 'Perubahan (%)'],
        [6, 6, 'Rata-rata Tren'],
        [7, 7, 'Nilai Tertinggi'],
        [8, 8, 'Kaidah Evaluasi'],
        [9, 10, 'Status & Arah Kinerja'],
    ];
    kpiHeaders.forEach(([c1, c2, label]) => {
        styleMerged(sheet, row, c1, row, c2, HEADER, label);
    });
    sheet.getRow(row).height = 24;
    row++;

    data.kpis.forEach((kpi, idx) => {
        const trendInfo = formatKpiTrend(kpi);
        const zebraFill = idx % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
        const deltaText =
            kpi.delta === null
                ? '—'
                : `${kpi.delta > 0 ? '+' : ''}${kpi.delta.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`;

        styleMerged(
            sheet,
            row,
            1,
            row,
            1,
            { ...CELL, fill: zebraFill, align: 'center' },
            idx + 1,
        );
        styleMerged(
            sheet,
            row,
            2,
            row,
            2,
            { ...CELL, fill: zebraFill, bold: true },
            kpi.label,
        );
        styleMerged(
            sheet,
            row,
            3,
            row,
            3,
            { ...CELL_RIGHT, fill: zebraFill, bold: true },
            kpi.value,
        );
        styleMerged(
            sheet,
            row,
            4,
            row,
            4,
            { ...CELL_RIGHT, fill: zebraFill },
            kpi.previous ?? '—',
        );
        styleMerged(
            sheet,
            row,
            5,
            row,
            5,
            { ...CELL_RIGHT, fill: zebraFill, bold: kpi.delta !== null },
            deltaText,
        );
        styleMerged(
            sheet,
            row,
            6,
            row,
            6,
            { ...CELL_RIGHT, fill: zebraFill },
            kpi.avg ?? '—',
        );
        styleMerged(
            sheet,
            row,
            7,
            row,
            7,
            { ...CELL_RIGHT, fill: zebraFill },
            kpi.max ?? '—',
        );
        styleMerged(
            sheet,
            row,
            8,
            row,
            8,
            { ...CELL, fill: zebraFill, align: 'center' },
            kpi.target_label ??
                (kpi.good === 'down'
                    ? 'Makin rendah makin baik'
                    : 'Makin tinggi makin baik'),
        );
        styleMerged(
            sheet,
            row,
            9,
            row,
            10,
            {
                ...CELL,
                fill: trendInfo.fill,
                color: trendInfo.color,
                bold: true,
                align: 'center',
            },
            trendInfo.text,
        );
        sheet.getRow(row).height = 20;
        row++;
    });

    row++;

    // =========================================================================
    // B. RINGKASAN EKSEKUTIF & ANALISIS ARAH DATA
    // =========================================================================
    sectionBanner('B. RINGKASAN EKSEKUTIF & ANALISIS ARAH DATA');

    styleMerged(sheet, row, 1, row, 1, HEADER, 'No');
    styleMerged(sheet, row, 2, row, 2, HEADER, 'Kategori / Indikasi');
    styleMerged(
        sheet,
        row,
        3,
        row,
        TOTAL_COLS,
        HEADER,
        'Uraian Analisis Arah Data & Temuan Utama',
    );
    sheet.getRow(row).height = 24;
    row++;

    data.insights.forEach((line, idx) => {
        const badge = TONE_BADGE[line.tone] ?? TONE_BADGE.neutral;
        const zebraFill = idx % 2 === 1 ? 'F8FAFC' : 'FFFFFF';

        styleMerged(
            sheet,
            row,
            1,
            row,
            1,
            { ...CELL, fill: zebraFill, align: 'center' },
            idx + 1,
        );
        styleMerged(
            sheet,
            row,
            2,
            row,
            2,
            {
                ...CELL,
                fill: badge.fill,
                color: badge.color,
                bold: true,
                align: 'center',
            },
            badge.label,
        );
        styleMerged(
            sheet,
            row,
            3,
            row,
            TOTAL_COLS,
            { ...CELL, fill: zebraFill, color: '0F172A' },
            line.text,
        );
        sheet.getRow(row).height = line.text.length > 130 ? 30 : 20;
        row++;
    });

    row++;

    // =========================================================================
    // C. VISUALISASI GRAFIK 3D & AKUMULASI (2 FRAMED CHARTS PER ROW ACROSS A:J)
    // =========================================================================
    sectionBanner('C. VISUALISASI GRAFIK 3D & AKUMULASI LENGKAP');

    for (let i = 0; i < data.charts.length; i += 2) {
        const pair = data.charts.slice(i, i + 2);
        const rTitle = row;
        const rSub = row + 1;

        pair.forEach((chart, offset) => {
            const c1 = offset === 0 ? 1 : RIGHT_COL + 1;
            const c2 = offset === 0 ? RIGHT_COL : TOTAL_COLS;

            styleMerged(
                sheet,
                rTitle,
                c1,
                rTitle,
                c2,
                {
                    fill: '1E3A8A',
                    color: 'FFFFFF',
                    bold: true,
                    size: 10,
                    align: 'left',
                    valign: 'center',
                    wrap: true,
                    border: true,
                },
                `  C.${i + offset + 1}. ${chart.title}`,
            );
            styleMerged(
                sheet,
                rSub,
                c1,
                rSub,
                c2,
                {
                    fill: 'F1F5F9',
                    color: '334155',
                    italic: true,
                    size: 9,
                    align: 'left',
                    valign: 'center',
                    wrap: true,
                    border: true,
                },
                `  ${chart.subtitle ?? 'Visualisasi grafik 3D periode berjalan'}`,
            );
        });

        sheet.getRow(rTitle).height = 22;
        sheet.getRow(rSub).height = 18;
        row += 2;

        // Compute image heights and allocate fixed-height framed canvas rows (18pt = 24px per row).
        const sizes = pair.map((chart) => {
            const size = pngSize(chart.image);
            const height = Math.round((size.height / size.width) * CHART_WIDTH);

            return { height };
        });
        const tallest = Math.max(...sizes.map((s) => s.height));
        const bodyRows = Math.ceil((tallest + 14) / 24);
        const rBodyStart = row;
        const rBodyEnd = row + bodyRows - 1;

        for (let r = rBodyStart; r <= rBodyEnd; r++) {
            sheet.getRow(r).height = 18;
        }

        pair.forEach((chart, offset) => {
            const c1 = offset === 0 ? 1 : RIGHT_COL + 1;
            const c2 = offset === 0 ? RIGHT_COL : TOTAL_COLS;
            frameBox(sheet, rBodyStart, c1, rBodyEnd, c2, 'FFFFFF');

            const id = workbook.addImage({
                base64: chart.image,
                extension: 'png',
            });
            sheet.addImage(id, {
                tl: {
                    col: (offset === 0 ? 0 : RIGHT_COL) + 0.08,
                    row: rBodyStart - 1 + 0.2,
                },
                ext: { width: CHART_WIDTH, height: sizes[offset].height },
            });
        });

        row = rBodyEnd + 1;
        sheet.getRow(row).height = 10;
        row++;
    }

    // =========================================================================
    // D. TABEL RINCIAN DATA, AKUMULASI, STATUS LAPORAN & KELENGKAPAN INPUT
    // =========================================================================
    sectionBanner(
        'D. TABEL RINCIAN DATA, AKUMULASI, STATUS LAPORAN & KELENGKAPAN INPUT',
    );

    data.sections.forEach((section, index) => {
        const spans = computeColumnSpans(section.columns.length, TOTAL_COLS);
        const tableEndCol = spans[spans.length - 1][1];

        styleMerged(
            sheet,
            row,
            1,
            row,
            Math.max(TOTAL_COLS, tableEndCol),
            SUBSECTION_BANNER,
            `  D.${index + 1}. ${section.title}`,
        );
        sheet.getRow(row).height = 22;
        row++;

        ['No', ...section.columns].forEach((column, c) => {
            const [c1, c2] = spans[c];
            styleMerged(sheet, row, c1, row, c2, HEADER, column);
        });
        sheet.getRow(row).height = 26;
        row++;

        if (section.rows.length === 0) {
            styleMerged(
                sheet,
                row,
                1,
                row,
                Math.max(TOTAL_COLS, tableEndCol),
                {
                    ...CELL,
                    fill: 'F8FAFC',
                    color: '64748B',
                    italic: true,
                    align: 'center',
                },
                'Belum ada data pada periode ini.',
            );
            sheet.getRow(row).height = 20;
            row++;
        } else {
            section.rows.forEach((values, r) => {
                const zebraFill = r % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
                styleMerged(
                    sheet,
                    row,
                    1,
                    row,
                    1,
                    { ...CELL, fill: zebraFill, align: 'center' },
                    r + 1,
                );
                values.forEach((value, c) => {
                    const [c1, c2] = spans[c + 1];
                    const base = c === 0 ? CELL : CELL_RIGHT;
                    const styled = statusCellStyle(String(value ?? ''), {
                        ...base,
                        fill: zebraFill,
                    });
                    styleMerged(sheet, row, c1, row, c2, styled, value);
                });
                sheet.getRow(row).height = 19;
                row++;
            });
        }

        if (section.footer && section.footer.length > 0) {
            styleMerged(sheet, row, 1, row, 1, FOOTER_STYLE, '');
            section.footer.forEach((value, c) => {
                const span = spans[c + 1];

                if (!span) {
                    return;
                }

                const [c1, c2] = span;
                styleMerged(
                    sheet,
                    row,
                    c1,
                    row,
                    c2,
                    c === 0 ? { ...FOOTER_STYLE, align: 'left' } : FOOTER_STYLE,
                    value,
                );
            });
            sheet.getRow(row).height = 22;
            row++;
        }

        if (section.note) {
            styleMerged(
                sheet,
                row,
                1,
                row,
                Math.max(TOTAL_COLS, tableEndCol),
                { size: 9, italic: true, color: '475569', align: 'left' },
                `* ${section.note}`,
            );
            sheet.getRow(row).height = 16;
            row++;
        }

        row++;
    });

    // =========================================================================
    // Sheet 2: "Data Tabel" — unmerged 1-column-per-field tables for analysis
    // =========================================================================
    const tables = workbook.addWorksheet('Data Tabel', {
        views: [{ showGridLines: false }],
        pageSetup: LANDSCAPE,
    });
    const tableCols = Math.max(
        TOTAL_COLS,
        ...data.sections.map((section) => section.columns.length + 1),
    );
    const tableColWidths = Array.from({ length: tableCols }, (_, i) =>
        i === 0 ? 8 : i === 1 ? 38 : 22,
    );
    tableColWidths.forEach(
        (width, i) => (tables.getColumn(i + 1).width = width),
    );

    await buildDocumentHeader(
        { workbook, worksheet: tables },
        {
            totalCols: tableCols,
            colWidths: tableColWidths,
            titleLines: [
                'PT PLN NUSANTARA POWER — UP KENDARI',
                `${data.unit.name.toUpperCase()}${data.unit.service_unit ? ` · ${data.unit.service_unit.toUpperCase()}` : ''}`,
                `PERIODE ${data.period.label.toUpperCase()}`,
            ],
            barTitle: `LAMPIRAN TABEL RINCIAN — ${data.title.toUpperCase()}`,
        },
    );

    let tRow = 6;
    data.sections.forEach((section, index) => {
        const colSpan = section.columns.length + 1;
        styleMerged(
            tables,
            tRow,
            1,
            tRow,
            colSpan,
            SUBSECTION_BANNER,
            `${index + 1}. ${section.title}`,
        );
        tables.getRow(tRow).height = 22;
        tRow++;

        ['No', ...section.columns].forEach((column, c) => {
            const cell = tables.getRow(tRow).getCell(c + 1);
            cell.value = column;
            applyStyle(cell, HEADER);
        });
        tables.getRow(tRow).height = 26;
        tRow++;

        if (section.rows.length === 0) {
            styleMerged(
                tables,
                tRow,
                1,
                tRow,
                colSpan,
                { ...CELL, italic: true, align: 'center', color: '64748B' },
                'Belum ada data.',
            );
            tables.getRow(tRow).height = 20;
            tRow++;
        } else {
            section.rows.forEach((values, r) => {
                const zebraFill = r % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
                const no = tables.getRow(tRow).getCell(1);
                no.value = r + 1;
                applyStyle(no, { ...CELL, fill: zebraFill, align: 'center' });
                values.forEach((value, c) => {
                    const cell = tables.getRow(tRow).getCell(c + 2);
                    cell.value = value;
                    const base = c === 0 ? CELL : CELL_RIGHT;
                    applyStyle(
                        cell,
                        statusCellStyle(String(value ?? ''), {
                            ...base,
                            fill: zebraFill,
                        }),
                    );
                });
                tables.getRow(tRow).height = 19;
                tRow++;
            });
        }

        if (section.footer && section.footer.length > 0) {
            const no = tables.getRow(tRow).getCell(1);
            no.value = '';
            applyStyle(no, FOOTER_STYLE);
            section.footer.forEach((value, c) => {
                const cell = tables.getRow(tRow).getCell(c + 2);
                cell.value = value;
                applyStyle(
                    cell,
                    c === 0 ? { ...FOOTER_STYLE, align: 'left' } : FOOTER_STYLE,
                );
            });
            tables.getRow(tRow).height = 22;
            tRow++;
        }

        if (section.note) {
            const cell = tables.getRow(tRow).getCell(1);
            cell.value = `* ${section.note}`;
            applyStyle(cell, { size: 9, italic: true, align: 'left' });
            tRow++;
        }

        tRow++;
    });

    await downloadWorkbook(workbook, filename);
}
