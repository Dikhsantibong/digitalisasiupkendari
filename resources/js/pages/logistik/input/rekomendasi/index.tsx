import { Head, router } from '@inertiajs/react';
import { Download, FileSpreadsheet, Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { OPERASI_MONTHS } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmInputToolbar } from '@/components/pdm/input-toolbar';
import type { PdmInputFilters } from '@/components/pdm/input-toolbar';
import { Button } from '@/components/ui/button';
import { downloadRekomendasiWorkbook } from '@/lib/logistik-excel';
import { dashboard } from '@/routes';
import logistikInput from '@/routes/logistik/input';
import rekomendasi from '@/routes/logistik/input/rekomendasi';
import type { IdName } from '@/types';

type Row = {
    id: number | null;
    no_urut: number;
    uraian: string;
    kondisi_existing: string;
    tindak_lanjut: string;
    keterangan: string;
};

type TextKey = 'uraian' | 'kondisi_existing' | 'tindak_lanjut' | 'keterangan';

type Props = {
    unit: IdName;
    filters: PdmInputFilters;
    options: { units: IdName[]; years: number[] };
    rows: Row[];
    has_saved: boolean;
    can_write: boolean;
};

const COLUMNS: { key: TextKey; label: string; className: string }[] = [
    { key: 'uraian', label: 'Uraian', className: 'w-[20%]' },
    { key: 'kondisi_existing', label: 'Kondisi Existing', className: 'w-[27%]' },
    { key: 'tindak_lanjut', label: 'Tindak Lanjut', className: 'w-[27%]' },
    { key: 'keterangan', label: 'Keterangan', className: '' },
];

const cellTextarea =
    'block min-h-14 w-full resize-y rounded-none border-0 bg-transparent px-1.5 py-1 text-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-100';

const blankRow = (no: number): Row => ({ id: null, no_urut: no, uraian: '', kondisi_existing: '', tindak_lanjut: '', keterangan: '' });

/**
 * Input Logistik & Gudang "Rekomendasi Logistik & Gudang": uraian, kondisi
 * existing, tindak lanjut and keterangan per unit & month, with PDF & Excel.
 */
export default function LogistikRekomendasiInput({ unit, filters, options, rows: initialRows, has_saved, can_write }: Props) {
    const [rows, setRows] = useState<Row[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [exporting, setExporting] = useState(false);

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} Tahun ${filters.year}`;
    const query = { unit_id: filters.unit_id, month: filters.month, year: filters.year };

    const visit = (patch: Partial<PdmInputFilters>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(rekomendasi.index().url, { ...filters, ...patch }, { preserveScroll: true });
    };

    const update = (index: number, key: TextKey, value: string) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, [key]: value } : row)));
        setDirty(true);
    };

    const renumber = (list: Row[]) => list.map((row, i) => ({ ...row, no_urut: i + 1 }));

    const addRow = () => {
        setRows((current) => [...current, blankRow(current.length + 1)]);
        setDirty(true);
    };

    const removeRow = (index: number) => {
        setRows((current) => renumber(current.filter((_, i) => i !== index)));
        setDirty(true);
    };

    const save = () => {
        setSaving(true);
        router.post(
            rekomendasi.store().url,
            { ...query, rows: rows.map(({ uraian, kondisi_existing, tindak_lanjut, keterangan }) => ({ uraian, kondisi_existing, tindak_lanjut, keterangan })) },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadRekomendasiWorkbook(unit.name, periodLabel, rows, `Rekomendasi_Logistik_Gudang_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`);
        } catch {
            toast.error('Gagal membuat file Excel.');
        } finally {
            setExporting(false);
        }
    };

    return (
        <>
            <Head title="Rekomendasi Logistik & Gudang" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Rekomendasi Logistik & Gudang"
                    description={`Kondisi existing, tindak lanjut, dan keterangan tiap uraian logistik & gudang — ${unit.name} · ${periodLabel}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => router.get(logistikInput.index().url)}>Kembali</Button>
                            <Button variant="outline" onClick={exportExcel} disabled={exporting} className="gap-1.5">
                                <FileSpreadsheet className="size-4 text-emerald-600" />
                                {exporting ? 'Menyiapkan…' : 'Excel'}
                            </Button>
                            <Button variant="outline" onClick={() => window.open(rekomendasi.pdf({ query }).url, '_blank')} className="gap-1.5">
                                <Download className="size-4 text-rose-600" />
                                PDF
                            </Button>
                            {can_write && (
                                <Button onClick={save} disabled={saving || !dirty} className="gap-1.5">
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <PdmInputToolbar filters={filters} options={options} onChange={visit} dirty={dirty} />

                {!has_saved && (
                    <p className="text-[13px] text-muted-foreground">
                        Belum ada rekomendasi tersimpan untuk periode ini — uraian standar sudah disiapkan. Baris yang seluruh kolomnya kosong tidak ikut disimpan.
                    </p>
                )}

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="w-full min-w-[860px] border-collapse text-xs">
                        <thead className="bg-[#9dd9f3] text-center text-[12px] font-semibold text-slate-900">
                            <tr>
                                <th className="w-12 border border-slate-400 p-2">NO.</th>
                                {COLUMNS.map((column) => (
                                    <th key={column.key} className={`border border-slate-400 p-2 ${column.className}`}>{column.label}</th>
                                ))}
                                {can_write && <th className="w-10 border border-slate-400 p-2" />}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={index} className="hover:bg-muted/30">
                                    <td className="border border-border p-1 text-center align-middle">{row.no_urut}</td>
                                    {COLUMNS.map((column) => (
                                        <td key={column.key} className="border border-border p-0 align-top">
                                            <textarea
                                                rows={2}
                                                value={row[column.key]}
                                                onChange={(e) => update(index, column.key, e.target.value)}
                                                className={cellTextarea}
                                                disabled={!can_write}
                                                aria-label={`${column.label} baris ${row.no_urut}`}
                                            />
                                        </td>
                                    ))}
                                    {can_write && (
                                        <td className="border border-border p-0 text-center align-middle">
                                            <Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => removeRow(index)} title="Hapus baris">
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {can_write && (
                    <div>
                        <Button variant="outline" size="sm" onClick={addRow} className="gap-1.5">
                            <Plus className="size-4" />
                            Tambah Baris
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

LogistikRekomendasiInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Logistik & Gudang', href: logistikInput.index() },
        { title: 'Rekomendasi Logistik & Gudang', href: rekomendasi.index() },
    ],
};
