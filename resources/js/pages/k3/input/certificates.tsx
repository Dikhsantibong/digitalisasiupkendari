import { Head, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataGrid, { textEditor } from 'react-data-grid';
import type { Column, ColumnOrColumnGroup } from 'react-data-grid';
import 'react-data-grid/lib/styles.css';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { OPERASI_GRID_STYLES, useExcelPaste } from '@/components/operasi/grid';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import certificate from '@/routes/k3/input/certificate';
import type { IdName } from '@/types';

type Code = { code: string; name: string };

type RowData = {
    category_code: string | null;
    jenis: string | null;
    kapasitas: string | null;
    lokasi: string | null;
    merk_manufacture: string | null;
    no_seri: string | null;
    regulasi: string | null;
    ijin_awal_nomor: string | null;
    ijin_awal_tanggal: string | null;
    uji_terakhir_nomor: string | null;
    uji_terakhir_tanggal: string | null;
    uji_ulang_tanggal: string | null;
    batasan_uji: string | null;
    masa_berlaku_tahun: string | number | null;
    keterangan: string | null;
};

type GridRow = RowData & { _key: number; [key: string]: number | string | null };

type Props = {
    filters: { unit_id: number };
    rows: RowData[];
    options: { units: IdName[]; categories: Code[] };
    can_write: boolean;
};

const blankRow = (key: number): GridRow => ({
    _key: key,
    category_code: null,
    jenis: '',
    kapasitas: null,
    lokasi: null,
    merk_manufacture: null,
    no_seri: null,
    regulasi: null,
    ijin_awal_nomor: null,
    ijin_awal_tanggal: null,
    uji_terakhir_nomor: null,
    uji_terakhir_tanggal: null,
    uji_ulang_tanggal: null,
    batasan_uji: null,
    masa_berlaku_tahun: null,
    keterangan: null,
});

const hydrate = (rows: RowData[]): GridRow[] =>
    rows.length ? rows.map((row, index) => ({ ...row, _key: index })) : [blankRow(0)];

export default function CertificateInput({ filters, rows: initialRows, options, can_write }: Props) {
    const [rows, setRows] = useState<GridRow[]>(() => hydrate(initialRows));
    const [nextKey, setNextKey] = useState(Math.max(initialRows.length, 1));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = String(filters.unit_id);
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(hydrate(initialRows));
        setNextKey(Math.max(initialRows.length, 1));
        setDirty(false);
    }

    const gridHeight = Math.min(Math.max(rows.length, 3) * 35 + 44, 560);

    const removeRow = (key: number) => {
        setRows((current) => current.filter((row) => row._key !== key));
        setDirty(true);
    };

    const columns = useMemo<readonly ColumnOrColumnGroup<GridRow>[]>(() => {
        const editable = (key: keyof RowData, name: string, width = 130): Column<GridRow> => ({
            key: key as string,
            name,
            width,
            editable: can_write,
            renderEditCell: textEditor,
            headerCellClass: 'rdg-sub-header',
        });

        const cols: Column<GridRow>[] = [
            editable('category_code', 'Kategori', 100),
            editable('jenis', 'Jenis', 160),
            editable('kapasitas', 'Kapasitas', 110),
            editable('lokasi', 'Lokasi', 130),
            editable('merk_manufacture', 'Merk', 120),
            editable('no_seri', 'No. Seri', 120),
            editable('regulasi', 'Regulasi', 140),
            editable('ijin_awal_nomor', 'Ijin Awal No.', 120),
            editable('ijin_awal_tanggal', 'Ijin Awal Tgl', 120),
            editable('uji_terakhir_nomor', 'Uji Akhir No.', 120),
            editable('uji_terakhir_tanggal', 'Uji Akhir Tgl', 120),
            editable('uji_ulang_tanggal', 'Uji Ulang Tgl', 120),
            editable('batasan_uji', 'Batasan Uji', 130),
            editable('masa_berlaku_tahun', 'Masa (thn)', 90),
            editable('keterangan', 'Keterangan', 160),
        ];

        if (can_write) {
            cols.push({
                key: 'actions',
                name: 'Aksi',
                width: 64,
                headerCellClass: 'rdg-sub-header',
                cellClass: 'text-center',
                renderCell: ({ row }) => (
                    <button
                        type="button"
                        onClick={() => removeRow(row._key)}
                        aria-label="Hapus baris"
                        className="text-destructive"
                    >
                        <Trash2 className="mx-auto size-4" />
                    </button>
                ),
            });
        }

        return cols;
    }, [can_write]);

    const { onSelectedCellChange, onPaste } = useExcelPaste<GridRow>({
        columns,
        rows,
        onChange: (next) => {
            setRows(next);
            setDirty(true);
        },
    });

    const addRow = () => {
        setRows((current) => [...current, blankRow(nextKey)]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const payload = (row: GridRow): RowData => ({
        category_code: row.category_code,
        jenis: row.jenis,
        kapasitas: row.kapasitas,
        lokasi: row.lokasi,
        merk_manufacture: row.merk_manufacture,
        no_seri: row.no_seri,
        regulasi: row.regulasi,
        ijin_awal_nomor: row.ijin_awal_nomor,
        ijin_awal_tanggal: row.ijin_awal_tanggal || null,
        uji_terakhir_nomor: row.uji_terakhir_nomor,
        uji_terakhir_tanggal: row.uji_terakhir_tanggal || null,
        uji_ulang_tanggal: row.uji_ulang_tanggal || null,
        batasan_uji: row.batasan_uji,
        masa_berlaku_tahun:
            row.masa_berlaku_tahun === '' || row.masa_berlaku_tahun === null
                ? null
                : Number(row.masa_berlaku_tahun),
        keterangan: row.keterangan,
    });

    const save = () => {
        setSaving(true);
        router.post(
            certificate.store().url,
            { unit_id: filters.unit_id, rows: rows.map(payload) },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title="Input K3 — Sertifikat Peralatan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Sertifikasi Peralatan"
                    description="Data sertifikat & pengujian alat (crane, tangki, bejana tekan, dll.). Status kadaluarsa dihitung otomatis di menu Monitoring. Paste dari Excel didukung."
                    actions={
                        can_write && (
                            <div className="flex gap-2">
                                <Button variant="secondary" onClick={addRow}>
                                    <Plus className="size-4" />
                                    Tambah Baris
                                </Button>
                                <Button onClick={save} disabled={saving}>
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            </div>
                        )
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => router.get(certificate.index().url, { unit_id: Number(value) }, { preserveState: true, replace: true })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    {dirty && !saving && (
                        <span className="text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>
                    )}
                </div>

                <div className="operasi-grid overflow-hidden rounded-md border border-border" onPaste={onPaste}>
                    <style>{OPERASI_GRID_STYLES}</style>
                    <DataGrid
                        className="rdg-light"
                        style={{ blockSize: `${gridHeight}px` }}
                        columns={columns}
                        rows={rows}
                        rowKeyGetter={(row) => row._key}
                        onRowsChange={(next) => {
                            setRows(next);
                            setDirty(true);
                        }}
                        onSelectedCellChange={onSelectedCellChange}
                    />
                </div>

                {options.categories.length > 0 && (
                    <div className="rounded-md border border-border bg-card p-3 text-[12px] text-muted-foreground">
                        <span className="font-medium">Kode kategori:</span>{' '}
                        {options.categories.map((c) => `${c.code} (${c.name})`).join(' · ')}. Tanggal format YYYY-MM-DD.
                    </div>
                )}
            </div>
        </>
    );
}

CertificateInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Sertifikasi Peralatan', href: certificate.index() },
    ],
};
