import ExcelJS from 'exceljs';
import {
    applyStyle,
    buildDocumentHeader,
    downloadWorkbook,
} from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/**
 * Excel of the Resume Bulanan (App\Services\Reports\ModuleResume): sheet
 * "Resume" with the standard kop, the KPI and every table (bordered, yellow
 * headers), and sheet "Grafik" with every chart as an image — the same static
 * SVG the PDF prints, drawn to PNG in the browser.
 */

export type ResumeExcelData = {
    title: string;
    unit: { name: string };
    period: { label: string };
    kpis: {
        label: string;
        value: string;
        trend: string | null;
        delta: number | null;
        assessment: string | null;
    }[];
    insights: { tone: string; text: string }[];
    charts: { title: string; subtitle?: string; svg: string }[];
    sections: {
        title: string;
        columns: string[];
        rows: string[][];
        note?: string;
    }[];
};

const HEADER: StyleSpec = {
    fill: 'FEF08A',
    bold: true,
    align: 'center',
    valign: 'center',
    wrap: true,
    border: true,
};
const CELL: StyleSpec = {
    align: 'left',
    valign: 'top',
    wrap: true,
    border: true,
};
const CELL_RIGHT: StyleSpec = {
    align: 'right',
    valign: 'top',
    wrap: true,
    border: true,
};
const CAPTION: StyleSpec = { bold: true, size: 11, align: 'left' };
const TONE: Record<string, string> = {
    success: '047857',
    danger: 'B91C1C',
    warning: 'B45309',
    info: '1D4ED8',
    neutral: '64748B',
};
const KPI_LABEL: StyleSpec = {
    fill: 'F1F5F9',
    bold: true,
    align: 'left',
    valign: 'center',
    wrap: true,
    border: true,
};

/** A standalone SVG string drawn to a PNG data URL (white background). */
async function svgToPng(
    svg: string,
    scale = 2,
): Promise<{ base64: string; width: number; height: number } | null> {
    const match = svg.match(/width='(\d+)' height='(\d+)'/);
    const width = Number(match?.[1] ?? 680);
    const height = Number(match?.[2] ?? 230);
    const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));

    try {
        const image = await new Promise<HTMLImageElement>((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = url;
        });
        const canvas = document.createElement('canvas');
        canvas.width = width * scale;
        canvas.height = height * scale;
        const context = canvas.getContext('2d');

        if (!context) {
            return null;
        }

        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        return { base64: canvas.toDataURL('image/png'), width, height };
    } catch {
        return null;
    } finally {
        URL.revokeObjectURL(url);
    }
}

export async function downloadResumeWorkbook(
    data: ResumeExcelData,
    filename: string,
): Promise<void> {
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Resume', {
        views: [{ showGridLines: false }],
        pageSetup: {
            orientation: 'landscape',
            paperSize: 9,
            fitToPage: true,
            fitToWidth: 1,
            fitToHeight: 0,
        },
    });
    const totalCols = Math.max(
        6,
        ...data.sections.map((section) => section.columns.length + 1),
    );
    const colWidths = Array.from({ length: totalCols }, (_, i) =>
        i === 0 ? 6 : i === 1 ? 34 : 18,
    );
    colWidths.forEach((width, i) => (sheet.getColumn(i + 1).width = width));

    await buildDocumentHeader(
        { workbook, worksheet: sheet },
        {
            totalCols,
            colWidths,
            titleLines: [
                'PT PLN NUSANTARA POWER — UP KENDARI',
                data.unit.name.toUpperCase(),
                `PERIODE ${data.period.label.toUpperCase()}`,
            ],
            barTitle: data.title.toUpperCase(),
        },
    );

    let row = 7;
    const caption = (text: string) => {
        const cell = sheet.getRow(row).getCell(1);
        cell.value = text;
        applyStyle(cell, CAPTION);
        row++;
    };

    caption('A. INDIKATOR UTAMA');

    for (const kpi of data.kpis) {
        const label = sheet.getRow(row).getCell(1);
        label.value = kpi.label;
        applyStyle(label, KPI_LABEL);
        sheet.mergeCells(row, 1, row, 2);
        const value = sheet.getRow(row).getCell(3);
        value.value = kpi.value;
        applyStyle(value, { ...CELL_RIGHT, bold: true });
        const trend = sheet.getRow(row).getCell(4);
        trend.value =
            kpi.trend === null || kpi.delta === null
                ? 'Bulan berjalan'
                : kpi.trend === 'tetap'
                  ? '= Stabil vs bulan lalu'
                  : `${kpi.trend === 'naik' ? '▲' : '▼'} ${Math.abs(kpi.delta).toLocaleString('id-ID', { maximumFractionDigits: 1 })}% vs bulan lalu · ${kpi.assessment}`;
        applyStyle(trend, {
            ...CELL,
            bold: true,
            color:
                kpi.assessment === 'membaik'
                    ? '047857'
                    : kpi.assessment === 'memburuk'
                      ? 'B91C1C'
                      : '64748B',
        });
        sheet.mergeCells(row, 4, row, Math.max(4, totalCols));
        row++;
    }

    row++;

    caption('B. RINGKASAN ARAH DATA');

    for (const line of data.insights) {
        const cell = sheet.getRow(row).getCell(1);
        cell.value = `• ${line.text}`;
        applyStyle(cell, {
            align: 'left',
            valign: 'top',
            wrap: true,
            color: TONE[line.tone] ?? '334155',
        });
        sheet.mergeCells(row, 1, row, totalCols);
        sheet.getRow(row).height = line.text.length > 140 ? 30 : 16;
        row++;
    }

    row++;

    caption('C. TABEL RINCIAN (grafik & akumulasi ada di sheet "Grafik")');
    data.sections.forEach((section, index) => {
        caption(`${index + 1}. ${section.title}`);
        ['No', ...section.columns].forEach((column, c) => {
            const cell = sheet.getRow(row).getCell(c + 1);
            cell.value = column;
            applyStyle(cell, HEADER);
        });
        sheet.getRow(row).height = 30;
        row++;

        if (section.rows.length === 0) {
            const cell = sheet.getRow(row).getCell(1);
            cell.value = 'Belum ada data.';
            applyStyle(cell, {
                ...CELL,
                italic: true,
                align: 'center',
            } as StyleSpec);
            sheet.mergeCells(row, 1, row, section.columns.length + 1);
            row++;
        }

        section.rows.forEach((values, r) => {
            const no = sheet.getRow(row).getCell(1);
            no.value = r + 1;
            applyStyle(no, { ...CELL, align: 'center' });
            values.forEach((value, c) => {
                const cell = sheet.getRow(row).getCell(c + 2);
                cell.value = value;
                applyStyle(cell, c === 0 ? CELL : CELL_RIGHT);
            });
            row++;
        });

        if (section.note) {
            const cell = sheet.getRow(row).getCell(1);
            cell.value = section.note;
            applyStyle(cell, {
                size: 9,
                italic: true,
                align: 'left',
            } as StyleSpec);
            row++;
        }

        row++;
    });

    // Sheet "Grafik" (landscape): every chart as an image, two per row.
    const charts = workbook.addWorksheet('Grafik', {
        views: [{ showGridLines: false }],
        pageSetup: {
            orientation: 'landscape',
            paperSize: 9,
            fitToPage: true,
            fitToWidth: 1,
            fitToHeight: 0,
        },
    });
    charts.getColumn(1).width = 100;
    charts.getColumn(2).width = 100;
    let top = 0;

    for (let i = 0; i < data.charts.length; i += 2) {
        let tallest = 0;

        for (const [offset, chart] of data.charts.slice(i, i + 2).entries()) {
            const title = charts.getRow(top + 1).getCell(offset + 1);
            title.value = `${i + offset + 1}. ${chart.title}${chart.subtitle ? ` — ${chart.subtitle}` : ''}`;
            applyStyle(title, { ...CAPTION, wrap: true });
            const png = await svgToPng(chart.svg);

            if (png) {
                const id = workbook.addImage({
                    base64: png.base64,
                    extension: 'png',
                });
                charts.addImage(id, {
                    tl: { col: offset, row: top + 1 },
                    ext: { width: png.width, height: png.height },
                });
                tallest = Math.max(tallest, png.height);
            }
        }

        charts.getRow(top + 1).height = 30;
        top += Math.ceil(tallest / 20) + 3;
    }

    await downloadWorkbook(workbook, filename);
}
