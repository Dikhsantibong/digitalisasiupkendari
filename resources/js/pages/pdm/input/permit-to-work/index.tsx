import { Head, router } from '@inertiajs/react';
import { Download, FileSpreadsheet, Plus, Save } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { OPERASI_MONTHS } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmInputToolbar } from '@/components/pdm/input-toolbar';
import type { PdmInputFilters } from '@/components/pdm/input-toolbar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { downloadPermitToWorkWorkbook } from '@/lib/pdm-input-excel';
import { dashboard } from '@/routes';
import pdmInput from '@/routes/pdm/input';
import permitToWork from '@/routes/pdm/input/permit-to-work';
import type { IdName } from '@/types';

type Row = { id: number | null; no_urut: number; uraian: string; tanggal: string | null; status: 'open' | 'close' };

type Props = {
    unit: IdName;
    filters: PdmInputFilters;
    options: { units: IdName[]; years: number[] };
    rows: Row[];
    has_saved: boolean;
    can_write: boolean;
};

const cellInput = 'h-7 rounded-none border-0 bg-transparent px-1 text-xs shadow-none focus-visible:ring-1';

/** A row counts as a permit once it has an uraian or a date. */
const isFilled = (row: Row) => row.uraian.trim() !== '' || !!row.tanggal;

export default function PdmPermitToWorkInput({ unit, filters, options, rows: initialRows, has_saved, can_write }: Props) {
    const [rows, setRows] = useState<Row[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [exporting, setExporting] = useState(false);

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const query = { unit_id: filters.unit_id, month: filters.month, year: filters.year };
    const totalOpen = rows.filter((row) => isFilled(row) && row.status === 'open').length;
    const totalClose = rows.filter((row) => isFilled(row) && row.status === 'close').length;

    const visit = (patch: Partial<PdmInputFilters>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(permitToWork.index().url, { ...filters, ...patch }, { preserveScroll: true });
    };

    const update = (index: number, patch: Partial<Row>) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, ...patch } : row)));
        setDirty(true);
    };

    const addRow = () => {
        setRows((current) => [...current, { id: null, no_urut: current.length + 1, uraian: '', tanggal: null, status: 'open' }]);
        setDirty(true);
    };

    const save = () => {
        setSaving(true);
        router.post(
            permitToWork.store().url,
            { ...query, rows: rows.map(({ uraian, tanggal, status }) => ({ uraian, tanggal, status })) },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadPermitToWorkWorkbook(
                unit.name,
                periodLabel,
                rows.map((row) => ({ ...row, filled: isFilled(row) })),
                `Laporan_PTW_PdM_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`,
            );
        } catch {
            toast.error('Gagal membuat file Excel.');
        } finally {
            setExporting(false);
        }
    };

    return (
        <>
            <Head title="Laporan Permit to Work (PTW) Pembangkit" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Laporan Permit to Work (PTW) Pembangkit"
                    description={`Pencatatan izin kerja tim PdM dan status Open/Close — ${unit.name} · ${periodLabel}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => router.get(pdmInput.index().url)}>Kembali</Button>
                            <Button variant="outline" onClick={exportExcel} disabled={exporting} className="gap-1.5">
                                <FileSpreadsheet className="size-4 text-emerald-600" />
                                {exporting ? 'Menyiapkan…' : 'Excel'}
                            </Button>
                            <Button variant="outline" onClick={() => window.open(permitToWork.pdf({ query }).url, '_blank')} className="gap-1.5">
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

                {!has_saved && <p className="text-[13px] text-muted-foreground">Belum ada PTW tersimpan untuk periode ini. Baris tanpa uraian &amp; tanggal tidak ikut disimpan.</p>}

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="w-full min-w-[720px] border-collapse text-xs">
                        <thead className="bg-[#ed7d31] text-center text-[11px] font-semibold text-slate-900">
                            <tr>
                                <th colSpan={5} className="border border-slate-500 p-1 text-sm">LAPORAN PTW PEMBANGKIT</th>
                            </tr>
                            <tr>
                                <th rowSpan={2} className="w-12 border border-slate-500 p-1">NO</th>
                                <th rowSpan={2} className="border border-slate-500 p-1">URAIAN</th>
                                <th rowSpan={2} className="w-40 border border-slate-500 p-1">TANGGAL</th>
                                <th colSpan={2} className="border border-slate-500 p-1">STATUS</th>
                            </tr>
                            <tr>
                                <th className="w-24 border border-slate-500 p-1">OPEN</th>
                                <th className="w-24 border border-slate-500 p-1">CLOSE</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={index} className="hover:bg-muted/30">
                                    <td className="border border-border p-1 text-center">{index + 1}</td>
                                    <td className="border border-border p-0">
                                        <Input value={row.uraian} onChange={(e) => update(index, { uraian: e.target.value })} className={cellInput} disabled={!can_write} />
                                    </td>
                                    <td className="border border-border p-0">
                                        <Input type="date" value={row.tanggal ?? ''} onChange={(e) => update(index, { tanggal: e.target.value || null })} className={`${cellInput} text-center`} disabled={!can_write} />
                                    </td>
                                    {(['open', 'close'] as const).map((status) => (
                                        <td key={status} className="border border-border p-0 text-center">
                                            <input
                                                type="radio"
                                                name={`status-${index}`}
                                                checked={row.status === status}
                                                onChange={() => update(index, { status })}
                                                disabled={!can_write}
                                                className="size-4 accent-primary"
                                                aria-label={status}
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                            <tr className="bg-muted/50 font-semibold">
                                <td colSpan={3} className="border border-border p-1 text-center">TOTAL</td>
                                <td className="border border-border p-1 text-center">{totalOpen}</td>
                                <td className="border border-border p-1 text-center">{totalClose}</td>
                            </tr>
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

PdmPermitToWorkInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input PdM & Maturity Level', href: pdmInput.index() },
        { title: 'Laporan PTW', href: permitToWork.index() },
    ],
};
