import ExcelJS from 'exceljs';
import {
    applyStyle,
    downloadWorkbook,
    loadImageBase64,
    mergeCells,
} from '@/lib/jadwal-excel';
import type { StyleSpec } from '@/lib/jadwal-excel';

/**
 * Excel export of the Lembar Mutasi Operator, laid out like the paper form and
 * resources/views/operator/mutasi-pdf.blade.php (same sections, same order).
 */

export type MutasiExcel = {
    unit: string;
    hariTanggal: string;
    shift: string;
    shifts: { value: string; jam: string }[];
    mesin: {
        nama: string;
        level_bbm: string | null;
        tambah_bbm: string | null;
        pelumas: string | null;
        status: string | null;
    }[];
    tangki: { nama: string | null; level_cm: string | null }[];
    peralatan: { nama: string | null; ada: boolean; jumlah: number | null }[];
    kejadian: { jam: string | null; uraian: string }[];
    gangguan_mesin: string | null;
    catatan: string | null;
    regu_penyerah: string | null;
    penyerah_nama: string | null;
    paraf_penyerah_url: string | null;
    regu_penerima: string | null;
    penerima_nama: string | null;
    paraf_penerima_url: string | null;
};

const COLS = 7;
const CELL: StyleSpec = {
    align: 'center',
    valign: 'center',
    border: true,
    wrap: true,
    size: 9,
};
const LEFT: StyleSpec = { ...CELL, align: 'left' };
const TOP_LEFT: StyleSpec = { ...LEFT, valign: 'top' };
const HEAD: StyleSpec = { ...CELL, bold: true, fill: 'D9D9D9' };

const put = (
    sheet: ExcelJS.Worksheet,
    row: number,
    col: number,
    value: string | number | null,
    spec: StyleSpec,
) => {
    const cell = sheet.getRow(row).getCell(col);
    cell.value = value ?? '';
    applyStyle(cell, spec);
};

/** Style every cell of a 1-based range, then merge it. */
const block = (
    sheet: ExcelJS.Worksheet,
    r1: number,
    c1: number,
    r2: number,
    c2: number,
    value: string | number | null,
    spec: StyleSpec,
) => {
    for (let r = r1; r <= r2; r++) {
        for (let c = c1; c <= c2; c++) {
            put(sheet, r, c, r === r1 && c === c1 ? value : null, spec);
        }
    }

    if (r1 !== r2 || c1 !== c2) {
        mergeCells(sheet, r1 - 1, c1 - 1, r2 - 1, c2 - 1);
    }
};

const mark = (value: string | null) => (value === '✓' ? '✓' : (value ?? ''));

async function addImage(
    workbook: ExcelJS.Workbook,
    sheet: ExcelJS.Worksheet,
    url: string | null,
    col: number,
    row: number,
    width: number,
    height: number,
) {
    if (!url) {
        return;
    }

    const base64 = await loadImageBase64(url);

    if (base64) {
        const id = workbook.addImage({
            base64,
            extension: url.endsWith('.jpg') ? 'jpeg' : 'png',
        });
        sheet.addImage(id, { tl: { col, row }, ext: { width, height } });
    }
}

export async function downloadMutasiWorkbook(
    data: MutasiExcel,
    filename: string,
): Promise<void> {
    const workbook = new ExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Lembar Mutasi', {
        views: [{ showGridLines: false }],
        pageSetup: {
            orientation: 'portrait',
            paperSize: 9,
            fitToPage: true,
            fitToWidth: 1,
            fitToHeight: 0,
        },
    });
    [20, 16, 16, 10, 10, 10, 36].forEach(
        (width, i) => (sheet.getColumn(i + 1).width = width),
    );

    // Logos + title.
    sheet.getRow(1).height = 36;
    await addImage(
        workbook,
        sheet,
        '/logo/sidebar-logo.png',
        0.1,
        0.1,
        150,
        40,
    );
    await addImage(workbook, sheet, '/logo/mkp.jpg', 6.45, 0.1, 58, 42);
    block(
        sheet,
        2,
        1,
        2,
        COLS,
        `LEMBAR MUTASI OPERATOR ${data.unit.toUpperCase()}`,
        { bold: true, size: 13, align: 'center', valign: 'center' },
    );
    block(
        sheet,
        3,
        1,
        3,
        COLS,
        `${data.hariTanggal} · Shift ${data.shift} (${data.shifts.find((s) => s.value === data.shift)?.jam ?? ''})`,
        { size: 9, align: 'center', italic: true },
    );

    // Mesin.
    let r = 5;
    block(sheet, r, 1, r + 1, 1, 'MESIN', HEAD);
    block(sheet, r, 2, r + 1, 2, 'Level tangki bahan bakar (Liter)', HEAD);
    block(sheet, r, 3, r + 1, 3, 'Tambah bahan bakar (Liter)', HEAD);
    block(sheet, r, 4, r, 6, 'Level pelumas Mesin/RA (✓)', HEAD);
    block(sheet, r, 7, r + 1, 7, 'Catatan', HEAD);
    ['NORMAL', 'RENDAH', 'TINGGI'].forEach((label, i) =>
        put(sheet, r + 1, 4 + i, label, HEAD),
    );
    sheet.getRow(r).height = 28;
    r += 2;

    const mesinStart = r;
    const mesinRows = Math.max(1, data.mesin.length);

    for (let i = 0; i < mesinRows; i++) {
        const m = data.mesin[i];
        put(sheet, r + i, 1, m?.nama ?? '', LEFT);
        put(sheet, r + i, 2, mark(m?.level_bbm ?? null), CELL);
        put(sheet, r + i, 3, mark(m?.tambah_bbm ?? null), CELL);
        ['normal', 'rendah', 'tinggi'].forEach((level, j) =>
            put(sheet, r + i, 4 + j, m?.pelumas === level ? '✓' : '', {
                ...CELL,
                bold: true,
            }),
        );
    }

    block(
        sheet,
        mesinStart,
        7,
        mesinStart + mesinRows - 1,
        7,
        data.catatan ?? '',
        TOP_LEFT,
    );
    r += mesinRows + 1;

    // Tangki bulanan + lain-lain.
    put(sheet, r, 1, 'Tangki Bulanan (25KL)', HEAD);
    put(sheet, r, 2, 'Level BBM (CM)', HEAD);
    block(sheet, r, 3, r, 5, 'Lain-Lain', HEAD);
    put(sheet, r, 6, 'Ada (✓)', HEAD);
    put(sheet, r, 7, 'Jumlah', HEAD);
    r++;

    const alatRows = Math.max(6, data.peralatan.length, data.tangki.length);

    for (let i = 0; i < alatRows; i++) {
        const tank = data.tangki[i];
        const alat = data.peralatan[i];
        put(sheet, r + i, 1, tank?.nama ?? '', {
            ...CELL,
            bold: true,
            size: 11,
        });
        put(sheet, r + i, 2, tank?.level_cm ?? '', { ...CELL, size: 11 });
        block(sheet, r + i, 3, r + i, 5, `${i + 1}. ${alat?.nama ?? ''}`, LEFT);
        put(sheet, r + i, 6, alat?.ada ? '✓' : '', { ...CELL, bold: true });
        put(sheet, r + i, 7, alat?.jumlah ?? '', CELL);
    }

    r += alatRows + 1;

    // Gangguan feeder / start-stop.
    block(sheet, r, 1, r, COLS, 'Gangguan feeder / start-stop Mesin', {
        ...HEAD,
        align: 'left',
    });
    r++;
    put(sheet, r, 1, 'Jam', HEAD);
    block(sheet, r, 2, r, COLS, 'Uraian', HEAD);
    r++;

    const kejadianRows = Math.max(12, data.kejadian.length);

    for (let i = 0; i < kejadianRows; i++) {
        const k = data.kejadian[i];
        put(sheet, r + i, 1, k?.jam ?? '', CELL);
        block(sheet, r + i, 2, r + i, COLS, k?.uraian ?? '', LEFT);
    }

    r += kejadianRows + 1;

    // Gangguan mesin + mesin operasi / standby.
    const operasi = data.mesin.filter((m) => m.status === 'operasi');
    const standby = data.mesin.filter((m) => m.status === 'standby');
    const statusRows = Math.max(3, operasi.length, standby.length);
    block(sheet, r, 1, r, 5, 'Gangguan Mesin', { ...HEAD, align: 'left' });
    put(sheet, r, 6, 'Mesin Operasi', HEAD);
    put(sheet, r, 7, 'Mesin Standby', HEAD);
    r++;
    block(
        sheet,
        r,
        1,
        r + statusRows - 1,
        5,
        data.gangguan_mesin ?? '',
        TOP_LEFT,
    );

    for (let i = 0; i < statusRows; i++) {
        put(sheet, r + i, 6, `${i + 1}. ${operasi[i]?.nama ?? ''}`, LEFT);
        put(sheet, r + i, 7, `${i + 1}. ${standby[i]?.nama ?? ''}`, LEFT);
    }

    r += statusRows + 1;

    // Serah terima.
    block(sheet, r, 1, r, 2, 'REGU PENYERAH', HEAD);
    block(sheet, r, 3, r, 5, `Tanggal: ${data.hariTanggal}`, LEFT);
    block(sheet, r, 6, r, 7, 'REGU PENERIMA', HEAD);
    r++;
    block(sheet, r, 1, r + 2, 1, data.regu_penyerah ?? '', {
        ...CELL,
        bold: true,
        size: 16,
    });
    block(sheet, r, 2, r + 2, 2, '', CELL);
    block(sheet, r, 6, r + 2, 6, data.regu_penerima ?? '', {
        ...CELL,
        bold: true,
        size: 16,
    });
    block(sheet, r, 7, r + 2, 7, '', CELL);
    data.shifts.forEach((s, i) =>
        block(
            sheet,
            r + i,
            3,
            r + i,
            5,
            `Pukul ${s.jam} ( ${s.value === data.shift ? '✓' : '  '} )`,
            LEFT,
        ),
    );
    [r, r + 1, r + 2].forEach((row) => (sheet.getRow(row).height = 18));
    await addImage(
        workbook,
        sheet,
        data.paraf_penyerah_url,
        1.15,
        r - 1 + 0.2,
        100,
        44,
    );
    await addImage(
        workbook,
        sheet,
        data.paraf_penerima_url,
        6.1,
        r - 1 + 0.2,
        100,
        44,
    );
    r += 3;
    put(sheet, r, 1, 'Regu', { ...CELL, size: 8 });
    put(
        sheet,
        r,
        2,
        `Paraf${data.penyerah_nama ? ` — ${data.penyerah_nama}` : ''}`,
        { ...CELL, size: 8 },
    );
    block(sheet, r, 3, r, 5, '', CELL);
    put(sheet, r, 6, 'Regu', { ...CELL, size: 8 });
    put(
        sheet,
        r,
        7,
        `Paraf${data.penerima_nama ? ` — ${data.penerima_nama}` : ''}`,
        { ...CELL, size: 8 },
    );

    await downloadWorkbook(workbook, filename);
}
