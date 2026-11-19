import ExcelJS from 'exceljs';
import {
    applyStyle,
    downloadWorkbook,
    loadImageBase64,
    mergeCells,
} from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/** One SLA criterion scored by App\Services\Reports\SlaReport. */
export type SlaRow = {
    no: number;
    kriteria: string;
    target_kinerja: string;
    eviden: string;
    jenis: number;
    bobot: number;
    formula: string;
    target: number;
    target_percent: number;
    achieved: number | null;
    percent: number | null;
    denda: boolean | null;
    /** count: sources show their number of records (dokumen), else their kelengkapan %. */
    mode: 'count' | 'percent';
    sources: { label: string; filled: number; percent: number | null }[];
};

export type SlaData = {
    divisi: string;
    number: string;
    title: string;
    contract: string;
    contract_number: string;
    unit: { id: number; name: string };
    period: { month: number; year: number; label: string; state: string };
    rows: SlaRow[];
    total: {
        bobot: number;
        target: number;
        achieved: number;
        percent: number | null;
        denda_count: number;
        denda_bobot: number;
        measured: number;
    };
};

/** Denda rate of the tagihan bulan berjalan per 10 bobot (SlaCatalog::PENALTY_RATE). */
export const SLA_PENALTY_RATE = 0.09;

export const slaPenalty = (row: SlaRow, tagihan: number | null): number =>
    row.denda === true && tagihan !== null
        ? (row.bobot / 10) * SLA_PENALTY_RATE * tagihan
        : 0;

export const formatSlaNumber = (value: number | null): string =>
    value === null
        ? '—'
        : value.toLocaleString('id-ID', { maximumFractionDigits: 2 });

export const formatRupiah = (value: number): string =>
    `Rp ${Math.round(value).toLocaleString('id-ID')}`;

const NAVY = '1F3B64';
const HEAD: StyleSpec = {
    fill: NAVY,
    color: 'FFFFFF',
    bold: true,
    size: 9,
    align: 'center',
    valign: 'center',
    wrap: true,
    border: true,
};
const CELL: StyleSpec = {
    size: 9,
    align: 'center',
    valign: 'center',
    wrap: true,
    border: true,
};

const PAGE_SETUP: Partial<ExcelJS.PageSetup> = {
    paperSize: 9,
    orientation: 'landscape',
    fitToPage: true,
    fitToWidth: 1,
    fitToHeight: 0,
    margins: {
        left: 0.3,
        right: 0.3,
        top: 0.4,
        bottom: 0.4,
        header: 0.2,
        footer: 0.2,
    },
};

/**
 * Writes the SLA sheet: one kop with the PLN NP & MKP logos, then each
 * divisi's criteria table (Perhitungan Kinerja, Status Consequence, Rp denda
 * when a tagihan is given, TOTAL) one under another and, when `recap`, the
 * Rekapitulasi per divisi — the same layout as reports/sla-pdf.blade.php.
 */
async function writeSlaSheet(
    workbook: ExcelJS.Workbook,
    slas: SlaData[],
    tagihan: number | null,
    recap: boolean,
    sheetName = 'SLA',
): Promise<void> {
    const first = slas[0];
    const sheet = workbook.addWorksheet(sheetName, {
        views: [{ showGridLines: false }],
        pageSetup: PAGE_SETUP,
    });

    const withAmount = tagihan !== null;
    const widths = [5, 34, 34, 28, 8, 7, 24, 11, 10, 11, 12, 9, 9];

    if (withAmount) {
        widths.push(16);
    }

    const cols = widths.length;
    widths.forEach((width, i) => (sheet.getColumn(i + 1).width = width));

    const put = (
        r: number,
        c: number,
        value: string | number,
        spec: StyleSpec,
        r2 = r,
        c2 = c,
    ) => {
        for (let y = r; y <= r2; y++) {
            for (let x = c; x <= c2; x++) {
                applyStyle(sheet.getRow(y + 1).getCell(x + 1), spec);
            }
        }

        const cell = sheet.getRow(r + 1).getCell(c + 1);
        cell.value = value;

        if (r2 !== r || c2 !== c) {
            mergeCells(sheet, r, c, r2, c2);
        }
    };

    // Kop: SLA title lines between the two logos (rows 1–5).
    [
        'SLA (SERVICE LEVEL AGREEMENT)',
        first.contract,
        first.contract_number,
        first.unit.name.toUpperCase(),
        `PERIODE ${first.period.label}`,
    ].forEach((line, i) => {
        put(
            i,
            2,
            line,
            {
                bold: true,
                italic: i === 0,
                size: i === 0 ? 13 : 10,
                align: 'center',
                valign: 'center',
            },
            i,
            cols - 4,
        );
        sheet.getRow(i + 1).height = i === 0 ? 20 : 15;
    });

    const pln = await loadImageBase64('/logo/sidebar-logo.png');

    if (pln) {
        const id = workbook.addImage({ base64: pln, extension: 'png' });
        sheet.addImage(id, {
            tl: { col: 0.2, row: 0.6 },
            ext: { width: 220, height: 58 },
        });
    }

    const mkp = await loadImageBase64('/logo/mkp.jpg');

    if (mkp) {
        const id = workbook.addImage({ base64: mkp, extension: 'jpeg' });
        sheet.addImage(id, {
            tl: { col: cols - 3 + 0.2, row: 0.6 },
            ext: { width: 84, height: 58 },
        });
    }

    const title: StyleSpec = {
        bold: true,
        color: NAVY,
        size: 10,
        align: 'center',
    };
    const bold: StyleSpec = { ...CELL, bold: true };
    let r = 6;

    for (const sla of slas) {
        put(r, 0, `${sla.number} ${sla.title}`, title, r, cols - 1);

        const h = r + 1;
        [
            'No.',
            'Kriteria',
            'Target Kinerja',
            'Eviden',
            'Jenis SLA',
            'Bobot',
            'Perhitungan Konsekuensi / Denda',
        ].forEach((label, i) => put(h, i, label, HEAD, h + 2, i));
        put(h, 7, 'Perhitungan Kinerja', HEAD, h, 10);
        put(h + 1, 7, 'Target Kinerja', HEAD, h + 1, 8);
        put(h + 1, 9, 'Pencapaian Kinerja', HEAD, h + 1, 10);
        put(h + 2, 7, 'Nilai Target Kinerja', HEAD);
        put(h + 2, 8, '% Target Kinerja', HEAD);
        put(h + 2, 9, 'Nilai Pencapaian Kinerja', HEAD);
        put(h + 2, 10, '% Pencapaian Target Kinerja', HEAD);
        put(h, 11, 'Status Consequence', HEAD, h, cols - 1);
        put(h + 1, 11, 'Denda', HEAD, h + 2, 11);
        put(h + 1, 12, 'Tidak Denda', HEAD, h + 2, 12);

        if (withAmount) {
            put(h + 1, 13, 'Nilai Denda', HEAD, h + 2, 13);
        }

        sheet.getRow(h + 3).height = 30;
        r = h + 3;
        let dendaTotal = 0;

        sla.rows.forEach((row) => {
            const fill = row.target > 1 ? '92D050' : 'FFFF00';
            const amount = slaPenalty(row, tagihan);
            dendaTotal += amount;

            put(r, 0, row.no, CELL);
            put(r, 1, row.kriteria, CELL);
            put(r, 2, row.target_kinerja, CELL);
            put(r, 3, row.eviden, CELL);
            put(r, 4, row.jenis, CELL);
            put(r, 5, row.bobot, CELL);
            put(r, 6, row.formula, CELL);
            put(r, 7, row.target, { ...CELL, fill });
            put(r, 8, `${row.target_percent}%`, CELL);
            put(r, 9, row.achieved ?? '—', {
                ...CELL,
                fill: row.denda ? 'FECACA' : fill,
            });
            put(
                r,
                10,
                row.percent === null ? 'Belum terukur' : `${row.percent}%`,
                CELL,
            );
            put(r, 11, row.denda === true ? '✓' : '', CELL);
            put(r, 12, row.denda === false ? '✓' : '', CELL);

            if (withAmount) {
                put(r, 13, amount > 0 ? formatRupiah(amount) : '—', CELL);
            }

            sheet.getRow(r + 1).height = 32;
            r++;
        });

        put(r, 0, 'TOTAL', bold, r, 4);
        put(r, 5, sla.total.bobot, bold);
        put(r, 6, '', bold);
        put(r, 7, sla.total.target, bold);
        put(r, 8, '100%', bold);
        put(r, 9, sla.total.achieved, bold);
        put(
            r,
            10,
            sla.total.percent === null ? '—' : `${sla.total.percent}%`,
            bold,
        );
        put(r, 11, sla.total.denda_count, bold);
        put(r, 12, sla.total.measured - sla.total.denda_count, bold);

        if (withAmount) {
            put(r, 13, formatRupiah(dendaTotal), bold);
        }

        r += 2;
    }

    if (recap) {
        put(r, 0, 'REKAPITULASI SLA SELURUH DIVISI', title, r, cols - 1);
        r++;
        // No · Divisi (B–D) · Bobot (E–F) · Target · Pencapaian (H–I) · % (J–K) · Denda · Tidak Denda · Nilai Denda.
        const heads: [number, number, string][] = [
            [0, 0, 'No.'],
            [1, 3, 'Divisi'],
            [4, 5, 'Bobot'],
            [6, 6, 'Nilai Target Kinerja'],
            [7, 8, 'Nilai Pencapaian Kinerja'],
            [9, 10, '% Pencapaian Target Kinerja'],
            [11, 11, 'Kriteria Denda'],
            [12, 12, 'Kriteria Tidak Denda'],
        ];

        if (withAmount) {
            heads.push([13, 13, 'Nilai Denda']);
        }

        heads.forEach(([c1, c2, label]) => put(r, c1, label, HEAD, r, c2));
        sheet.getRow(r + 1).height = 30;
        r++;

        const sum = {
            bobot: 0,
            target: 0,
            achieved: 0,
            denda: 0,
            ok: 0,
            amount: 0,
        };
        const percents: number[] = [];

        slas.forEach((sla, i) => {
            const amount = sla.rows.reduce(
                (total, row) => total + slaPenalty(row, tagihan),
                0,
            );
            sum.bobot += sla.total.bobot;
            sum.target += sla.total.target;
            sum.achieved += sla.total.achieved;
            sum.denda += sla.total.denda_count;
            sum.ok += sla.total.measured - sla.total.denda_count;
            sum.amount += amount;

            if (sla.total.percent !== null) {
                percents.push(sla.total.percent);
            }

            put(r, 0, i + 1, CELL);
            put(
                r,
                1,
                `${sla.number} ${sla.title}`,
                { ...CELL, align: 'left' },
                r,
                3,
            );
            put(r, 4, sla.total.bobot, CELL, r, 5);
            put(r, 6, sla.total.target, CELL);
            put(r, 7, sla.total.achieved, CELL, r, 8);
            put(
                r,
                9,
                sla.total.percent === null ? '—' : `${sla.total.percent}%`,
                CELL,
                r,
                10,
            );
            put(r, 11, sla.total.denda_count, CELL);
            put(r, 12, sla.total.measured - sla.total.denda_count, CELL);

            if (withAmount) {
                put(r, 13, amount > 0 ? formatRupiah(amount) : '—', CELL);
            }

            sheet.getRow(r + 1).height = 20;
            r++;
        });

        const average =
            percents.length === 0
                ? '—'
                : `${Math.round(percents.reduce((a, b) => a + b, 0) / percents.length)}%`;

        put(r, 0, 'TOTAL', bold, r, 3);
        put(r, 4, sum.bobot, bold, r, 5);
        put(r, 6, sum.target, bold);
        put(r, 7, Math.round(sum.achieved * 100) / 100, bold, r, 8);
        put(r, 9, average, bold, r, 10);
        put(r, 11, sum.denda, bold);
        put(r, 12, sum.ok, bold);

        if (withAmount) {
            put(r, 13, formatRupiah(sum.amount), bold);
        }

        r += 2;
    }

    put(
        r,
        0,
        'Nilai pencapaian dihitung otomatis dari kelengkapan inputan aplikasi pada periode ini. % Pencapaian di bawah 100% = Denda (bobot/10 × 9% × Tagihan bulan berjalan). "Belum terukur" = kriteria belum memiliki data di aplikasi (diverifikasi manual).' +
            (tagihan !== null
                ? ` Tagihan bulan berjalan: ${formatRupiah(tagihan)}.`
                : ''),
        { size: 8, italic: true, wrap: true, valign: 'top' },
        r + 1,
        cols - 1,
    );
}

/** Excel of one divisi's SLA bulanan (landscape). */
export async function downloadSlaWorkbook(
    sla: SlaData,
    tagihan: number | null,
    filename: string,
): Promise<void> {
    const workbook = new ExcelJS.Workbook();
    await writeSlaSheet(workbook, [sla], tagihan, false);
    await downloadWorkbook(workbook, filename);
}

/** Payload of the Laporan SLA gabungan of one unit (SlaController::combinedData). */
export type SlaCombinedData = { unit?: string; cover: string; slas: SlaData[] };

/** The cover image (App\Services\Reports\SlaCover, A4 portrait) on its own sheet. */
function addCoverSheet(
    workbook: ExcelJS.Workbook,
    cover: string,
    name: string,
): void {
    const sheet = workbook.addWorksheet(name, {
        views: [{ showGridLines: false }],
        pageSetup: {
            ...PAGE_SETUP,
            orientation: 'portrait',
            fitToHeight: 1,
            margins: {
                left: 0,
                right: 0,
                top: 0,
                bottom: 0,
                header: 0,
                footer: 0,
            },
        },
    });
    const image = workbook.addImage({
        base64: cover.replace(/^data:image\/png;base64,/, ''),
        extension: 'png',
    });
    sheet.addImage(image, {
        tl: { col: 0, row: 0 },
        ext: { width: 620, height: 877 },
    });
}

/**
 * Excel of the Laporan SLA gabungan: sheet "Sampul" with the cover image and
 * sheet "SLA" with every divisi and the Rekapitulasi.
 */
export async function downloadSlaCombinedWorkbook(
    data: SlaCombinedData,
    tagihan: number | null,
    filename: string,
): Promise<void> {
    const workbook = new ExcelJS.Workbook();
    addCoverSheet(workbook, data.cover, 'Sampul');
    await writeSlaSheet(workbook, data.slas, tagihan, true);
    await downloadWorkbook(workbook, filename);
}

/** An Excel sheet name: at most 31 characters, none of : \ / ? * [ ]. */
const sheetName = (text: string): string =>
    text.replace(/[:\\/?*[\]]/g, ' ').slice(0, 31);

/**
 * Excel of the Laporan SLA gabungan of every accessible unit ("Semua unit")
 * in one file: per unit a "Sampul" sheet and an "SLA" sheet (every divisi +
 * Rekapitulasi), in unit order.
 */
export async function downloadSlaCombinedUnitsWorkbook(
    units: SlaCombinedData[],
    tagihan: number | null,
    filename: string,
): Promise<void> {
    const workbook = new ExcelJS.Workbook();

    for (const [index, unit] of units.entries()) {
        const label = `${index + 1}. ${unit.unit ?? unit.slas[0]?.unit.name ?? 'Unit'}`;
        addCoverSheet(workbook, unit.cover, sheetName(`Sampul ${label}`));
        await writeSlaSheet(
            workbook,
            unit.slas,
            tagihan,
            true,
            sheetName(`SLA ${label}`),
        );
    }

    await downloadWorkbook(workbook, filename);
}
