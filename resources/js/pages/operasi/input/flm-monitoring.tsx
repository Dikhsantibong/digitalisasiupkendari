import { Head, router } from '@inertiajs/react';
import { Download, FileSpreadsheet, Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { downloadFlmMonitoringWorkbook } from '@/lib/operasi-flm-excel';
import type { FlmMonitoringRow } from '@/lib/operasi-flm-excel';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import flmMonitoring from '@/routes/operasi/input/flm-monitoring';
import type { IdName } from '@/types';

type Filters = { unit_id: number; month: number; year: number };

type Props = {
    unit: IdName;
    filters: Filters;
    options: { units: IdName[]; years: number[]; kondisi_awal: Record<string, string> };
    rows: FlmMonitoringRow[];
    has_saved: boolean;
    can_write: boolean;
};

const cellInput = 'h-8 rounded-none border-0 bg-transparent px-1 text-xs shadow-none focus-visible:ring-1';
const th = 'border border-slate-400 p-1';
const td = 'border border-border';

const blankRow = (no: number): FlmMonitoringRow => ({ no_urut: no, mesin: '', tanggal: null, masalah: '', kondisi_awal: [], kondisi_akhir: '', catatan: '', status: 'open' });

/** "20 Agustus 2026". */
const formatDate = (date: string) => {
    const [year, month, day] = date.split('-').map(Number);

    return `${day} ${OPERASI_MONTHS[month - 1]} ${year}`;
};

/**
 * Input Operasi "Monitoring FLM": first line maintenance findings per unit &
 * month with the kondisi awal actions, kondisi akhir, catatan and status.
 */
export default function OperasiFlmMonitoringInput({ unit, filters, options, rows: initialRows, has_saved, can_write }: Props) {
    const [rows, setRows] = useState<FlmMonitoringRow[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [exporting, setExporting] = useState(false);

    const kondisi = Object.entries(options.kondisi_awal);
    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const query = { unit_id: filters.unit_id, month: filters.month, year: filters.year };

    const visit = (patch: Partial<Filters>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(flmMonitoring.index().url, { ...filters, ...patch }, { preserveScroll: true });
    };

    const update = (index: number, patch: Partial<FlmMonitoringRow>) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, ...patch } : row)));
        setDirty(true);
    };

    const toggleKondisi = (index: number, key: string, checked: boolean) => {
        const list = rows[index].kondisi_awal;
        update(index, { kondisi_awal: checked ? [...list, key] : list.filter((k) => k !== key) });
    };

    const renumber = (list: FlmMonitoringRow[]) => list.map((row, i) => ({ ...row, no_urut: i + 1 }));

    const save = () => {
        setSaving(true);
        router.post(
            flmMonitoring.store().url,
            { ...query, rows: rows.map(({ mesin, tanggal, masalah, kondisi_awal, kondisi_akhir, catatan, status }) => ({ mesin, tanggal, masalah, kondisi_awal, kondisi_akhir, catatan, status })) },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadFlmMonitoringWorkbook(unit.name, periodLabel, options.kondisi_awal, rows, formatDate, `Monitoring_FLM_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`);
        } catch {
            toast.error('Gagal membuat file Excel.');
        } finally {
            setExporting(false);
        }
    };

    return (
        <>
            <Head title="Monitoring FLM" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Monitoring FLM"
                    description={`Temuan first line maintenance mesin & peralatan, tindakan awal, kondisi akhir dan status — ${unit.name} · ${periodLabel}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => router.get(operasiInput.index().url)}>Kembali</Button>
                            <Button variant="outline" onClick={exportExcel} disabled={exporting} className="gap-1.5">
                                <FileSpreadsheet className="size-4 text-emerald-600" />
                                {exporting ? 'Menyiapkan…' : 'Excel'}
                            </Button>
                            <Button variant="outline" onClick={() => window.open(flmMonitoring.pdf({ query }).url, '_blank')} className="gap-1.5">
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

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(value) => visit({ unit_id: Number(value) })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} className="w-56" />
                    <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(value) => visit({ month: Number(value) })} options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))} />
                    <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(value) => visit({ year: Number(value) })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} className="w-28" />
                    {dirty && <span className="pb-2 text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>}
                </div>

                {!has_saved && <p className="text-[13px] text-muted-foreground">Belum ada temuan FLM tersimpan untuk periode ini. Baris tanpa mesin, tanggal &amp; masalah tidak ikut disimpan.</p>}

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="w-full min-w-[1200px] border-collapse text-xs">
                        <thead className="bg-[#9dd9f3] text-center text-[11px] font-semibold text-slate-900">
                            <tr>
                                <th rowSpan={2} className={`${th} w-10`}>No</th>
                                <th rowSpan={2} className={`${th} min-w-40`}>Mesin / Peralatan</th>
                                <th rowSpan={2} className={`${th} w-36`}>Tanggal</th>
                                <th rowSpan={2} className={`${th} min-w-52`}>Masalah Awal yg ditemukan</th>
                                <th colSpan={kondisi.length} className={th}>Kondisi Awal</th>
                                <th rowSpan={2} className={`${th} min-w-28`}>Kondisi Akhir</th>
                                <th rowSpan={2} className={`${th} min-w-48`}>Catatan FLM</th>
                                <th rowSpan={2} className={`${th} w-24`}>Status</th>
                                {can_write && <th rowSpan={2} className={`${th} w-9`} />}
                            </tr>
                            <tr>
                                {kondisi.map(([key, label]) => (
                                    <th key={key} className={`${th} w-20 text-[10px]`}>{label}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <tr key={index} className="hover:bg-muted/30">
                                    <td className={`${td} p-1 text-center`}>{row.no_urut}</td>
                                    <td className={`${td} p-0`}>
                                        <Input value={row.mesin} onChange={(e) => update(index, { mesin: e.target.value })} className={cellInput} placeholder="Fuel Transfer Pump" disabled={!can_write} />
                                    </td>
                                    <td className={`${td} p-0`}>
                                        <Input type="date" value={row.tanggal ?? ''} onChange={(e) => update(index, { tanggal: e.target.value || null })} className={`${cellInput} text-center`} disabled={!can_write} />
                                    </td>
                                    <td className={`${td} p-0`}>
                                        <Input value={row.masalah} onChange={(e) => update(index, { masalah: e.target.value })} className={cellInput} disabled={!can_write} />
                                    </td>
                                    {kondisi.map(([key, label]) => (
                                        <td key={key} className={`${td} p-1 text-center`}>
                                            <Checkbox
                                                checked={row.kondisi_awal.includes(key)}
                                                onCheckedChange={(checked) => toggleKondisi(index, key, checked === true)}
                                                disabled={!can_write}
                                                aria-label={`${label} baris ${row.no_urut}`}
                                            />
                                        </td>
                                    ))}
                                    <td className={`${td} p-0`}>
                                        <Input value={row.kondisi_akhir} onChange={(e) => update(index, { kondisi_akhir: e.target.value })} className={`${cellInput} text-center`} placeholder="Ditadah / Backlog" disabled={!can_write} />
                                    </td>
                                    <td className={`${td} p-0`}>
                                        <Input value={row.catatan} onChange={(e) => update(index, { catatan: e.target.value })} className={cellInput} disabled={!can_write} />
                                    </td>
                                    <td className={`${td} p-0`}>
                                        <PdmCellSelect
                                            value={row.status.toUpperCase()}
                                            onChange={(value) => update(index, { status: value === 'CLOSE' ? 'close' : 'open' })}
                                            options={['OPEN', 'CLOSE']}
                                            className="h-8 justify-center font-semibold"
                                            disabled={!can_write}
                                        />
                                    </td>
                                    {can_write && (
                                        <td className={`${td} p-0 text-center`}>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7 text-muted-foreground hover:text-destructive"
                                                onClick={() => {
                                                    setRows((current) => renumber(current.filter((_, i) => i !== index)));
                                                    setDirty(true);
                                                }}
                                                title="Hapus baris"
                                            >
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
                        <Button
                            variant="outline"
                            size="sm"
                            className="gap-1.5"
                            onClick={() => {
                                setRows((current) => [...current, blankRow(current.length + 1)]);
                                setDirty(true);
                            }}
                        >
                            <Plus className="size-4" />
                            Tambah Baris
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

OperasiFlmMonitoringInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
        { title: 'Monitoring FLM', href: flmMonitoring.index() },
    ],
};
