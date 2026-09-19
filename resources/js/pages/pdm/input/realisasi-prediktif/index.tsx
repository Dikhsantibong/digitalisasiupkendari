import { Head, router } from '@inertiajs/react';
import { Download, FileSpreadsheet, Plus, Save, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
import { toast } from 'sonner';
import { OPERASI_MONTHS } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmInputToolbar } from '@/components/pdm/input-toolbar';
import type { PdmInputFilters } from '@/components/pdm/input-toolbar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { downloadRealisasiPrediktifWorkbook } from '@/lib/pdm-input-excel';
import type { RealisasiExportMeta } from '@/lib/pdm-input-excel';
import { dashboard } from '@/routes';
import pdmInput from '@/routes/pdm/input';
import realisasiPrediktif from '@/routes/pdm/input/realisasi-prediktif';
import type { IdName } from '@/types';

type Day = { day: number; dow: string; is_red: boolean };

type Row = {
    id: number | null;
    uraian: string;
    mesin: string;
    rencana: number[];
    realisasi: number[];
    durasi: number | null;
    keterangan: string;
};

type Props = {
    unit: IdName;
    filters: PdmInputFilters;
    options: { units: IdName[]; years: number[] };
    days: Day[];
    rows: Row[];
    meta: RealisasiExportMeta & { sentral: string };
    has_saved: boolean;
    can_write: boolean;
};

const cellInput = 'h-7 rounded-none border-0 bg-transparent px-1 text-xs shadow-none focus-visible:ring-1';

const kinerja = (row: Row) => (row.rencana.length > 0 ? `${Math.round((row.realisasi.length / row.rencana.length) * 100)}%` : '-');

export default function PdmRealisasiPrediktifInput({ unit, filters, options, days, rows: initialRows, meta: initialMeta, has_saved, can_write }: Props) {
    const [rows, setRows] = useState<Row[]>(initialRows);
    const [meta, setMeta] = useState(initialMeta);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [exporting, setExporting] = useState(false);

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const query = { unit_id: filters.unit_id, month: filters.month, year: filters.year };

    const visit = (patch: Partial<PdmInputFilters>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(realisasiPrediktif.index().url, { ...filters, ...patch }, { preserveScroll: true });
    };

    const update = (index: number, patch: Partial<Row>) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, ...patch } : row)));
        setDirty(true);
    };

    const toggleDay = (index: number, kind: 'rencana' | 'realisasi', day: number) => {
        if (!can_write) {
            return;
        }

        const list = rows[index][kind];
        update(index, { [kind]: list.includes(day) ? list.filter((d) => d !== day) : [...list, day].sort((a, b) => a - b) });
    };

    const updateMeta = (patch: Partial<Props['meta']>) => {
        setMeta((current) => ({ ...current, ...patch }));
        setDirty(true);
    };

    const save = () => {
        if (rows.some((row) => row.uraian.trim() === '')) {
            toast.error('Uraian setiap baris wajib diisi.');

            return;
        }

        setSaving(true);
        router.post(realisasiPrediktif.store().url, { ...query, rows, meta }, { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) });
    };

    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadRealisasiPrediktifWorkbook(
                unit.name,
                periodLabel,
                days,
                rows.map((row) => ({ ...row, target: row.rencana.length, realisasi_count: row.realisasi.length, kinerja: kinerja(row) })),
                meta,
                `Realisasi_Pemeliharaan_Prediktif_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`,
            );
        } catch {
            toast.error('Gagal membuat file Excel.');
        } finally {
            setExporting(false);
        }
    };

    const dayCell = (index: number, kind: 'rencana' | 'realisasi', day: Day) => {
        const marked = rows[index][kind].includes(day.day);

        return (
            <td
                key={day.day}
                onClick={() => toggleDay(index, kind, day.day)}
                className={`h-7 w-6 cursor-pointer border border-border text-center text-[11px] font-semibold select-none ${day.is_red ? 'bg-red-500/80 text-white' : marked ? (kind === 'rencana' ? 'bg-sky-200 dark:bg-sky-900' : 'bg-lime-200 dark:bg-lime-900') : 'hover:bg-muted'}`}
                title={`${kind === 'rencana' ? 'Rencana' : 'Realisasi'} tanggal ${day.day}`}
            >
                {marked ? '1' : ''}
            </td>
        );
    };

    return (
        <>
            <Head title="Realisasi Pemeliharaan Prediktif Bulanan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Realisasi Pemeliharaan Prediktif Bulanan"
                    description={`Rencana & realisasi harian kegiatan predictive per mesin — ${unit.name} · ${periodLabel}. Klik sel tanggal untuk menandai.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => router.get(pdmInput.index().url)}>Kembali</Button>
                            <Button variant="outline" onClick={exportExcel} disabled={exporting} className="gap-1.5">
                                <FileSpreadsheet className="size-4 text-emerald-600" />
                                {exporting ? 'Menyiapkan…' : 'Excel'}
                            </Button>
                            <Button variant="outline" onClick={() => window.open(realisasiPrediktif.pdf({ query }).url, '_blank')} className="gap-1.5">
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

                {!has_saved && <p className="text-[13px] text-muted-foreground">Belum ada data tersimpan untuk periode ini — kegiatan standar sudah disiapkan.</p>}

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="w-full min-w-[1250px] border-collapse text-xs">
                        <thead className="bg-muted text-center text-[11px] font-semibold">
                            <tr>
                                <th rowSpan={2} className="w-8 border border-border p-1">NO</th>
                                <th rowSpan={2} className="min-w-32 border border-border p-1">URAIAN</th>
                                <th rowSpan={2} className="min-w-32 border border-border p-1">MESIN / TIPE / S.N</th>
                                <th rowSpan={2} className="w-16 border border-border p-1">STATUS</th>
                                <th colSpan={days.length} className="border border-border p-1">{periodLabel.toUpperCase()}</th>
                                <th rowSpan={2} className="w-16 border border-border p-1">DURASI</th>
                                <th rowSpan={2} className="w-14 border border-border p-1">TARGET</th>
                                <th rowSpan={2} className="w-16 border border-border p-1">REALISASI</th>
                                <th rowSpan={2} className="w-16 border border-border p-1">A. KINERJA</th>
                                {can_write && <th rowSpan={2} className="w-8 border border-border" />}
                            </tr>
                            <tr>
                                {days.map((day) => (
                                    <th key={day.day} className={`w-6 border border-border p-0.5 ${day.is_red ? 'bg-red-600 text-white' : ''}`} title={day.dow}>{day.day}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, index) => (
                                <Fragment key={index}>
                                    <tr>
                                        <td rowSpan={2} className="border border-border p-1 text-center">{index + 1}</td>
                                        <td rowSpan={2} className="border border-border p-0">
                                            <Input value={row.uraian} onChange={(e) => update(index, { uraian: e.target.value })} className={`${cellInput} font-semibold`} disabled={!can_write} />
                                        </td>
                                        <td rowSpan={2} className="border border-border p-0">
                                            <Input value={row.mesin} onChange={(e) => update(index, { mesin: e.target.value })} className={cellInput} placeholder="ZHEJIANG #1" disabled={!can_write} />
                                        </td>
                                        <td className="border border-border bg-sky-500 p-1 text-center text-[10px] font-bold text-white">RENCANA</td>
                                        {days.map((day) => dayCell(index, 'rencana', day))}
                                        <td rowSpan={2} className="border border-border p-0">
                                            <Input
                                                type="number"
                                                min={0}
                                                step="0.01"
                                                value={row.durasi ?? ''}
                                                onChange={(e) => update(index, { durasi: e.target.value === '' ? null : Number(e.target.value) })}
                                                className={`${cellInput} text-center`}
                                                disabled={!can_write}
                                            />
                                        </td>
                                        <td rowSpan={2} className="border border-border p-1 text-center">{row.rencana.length}</td>
                                        <td rowSpan={2} className="border border-border p-1 text-center">{row.realisasi.length}</td>
                                        <td rowSpan={2} className="border border-border p-1 text-center font-semibold">{kinerja(row)}</td>
                                        {can_write && (
                                            <td rowSpan={2} className="border border-border p-0 text-center">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-7 text-muted-foreground hover:text-destructive"
                                                    onClick={() => {
                                                        setRows((current) => current.filter((_, i) => i !== index));
                                                        setDirty(true);
                                                    }}
                                                    title="Hapus kegiatan"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            </td>
                                        )}
                                    </tr>
                                    <tr>
                                        <td className="border border-border bg-lime-500 p-1 text-center text-[10px] font-bold text-white">REAL</td>
                                        {days.map((day) => dayCell(index, 'realisasi', day))}
                                    </tr>
                                </Fragment>
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
                                setRows((current) => [...current, { id: null, uraian: '', mesin: '', rencana: [], realisasi: [], durasi: null, keterangan: '' }]);
                                setDirty(true);
                            }}
                        >
                            <Plus className="size-4" />
                            Tambah Kegiatan
                        </Button>
                    </div>
                )}

                <div className="flex max-w-xl flex-col gap-2 rounded-md border border-border p-3">
                    <span className="text-[13px] font-medium">Dokumen</span>
                    <div className="grid grid-cols-3 gap-2">
                        <div className="flex flex-col gap-1">
                            <Label>No. Dokumen</Label>
                            <Input value={meta.doc_number} onChange={(e) => updateMeta({ doc_number: e.target.value })} disabled={!can_write} />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label>Revisi</Label>
                            <Input value={meta.revision} onChange={(e) => updateMeta({ revision: e.target.value })} disabled={!can_write} />
                        </div>
                        <div className="flex flex-col gap-1">
                            <Label>Tanggal</Label>
                            <Input value={meta.effective_date} onChange={(e) => updateMeta({ effective_date: e.target.value })} disabled={!can_write} />
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

PdmRealisasiPrediktifInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input PdM & Maturity Level', href: pdmInput.index() },
        { title: 'Realisasi Pemeliharaan Prediktif', href: realisasiPrediktif.index() },
    ],
};
