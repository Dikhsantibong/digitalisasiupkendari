import { Head, router } from '@inertiajs/react';
import { Download, FileSpreadsheet, Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { OPERASI_MONTHS } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { PdmDocumentHeader } from '@/components/pdm/document-header';
import { PdmInputToolbar } from '@/components/pdm/input-toolbar';
import type { PdmInputFilters } from '@/components/pdm/input-toolbar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { downloadSampleMonitoringWorkbook } from '@/lib/pdm-input-excel';
import type { SampleColumn, SampleRekapRow, SampleSection } from '@/lib/pdm-input-excel';
import { dashboard } from '@/routes';
import pdmInput from '@/routes/pdm/input';
import sampleMonitoring from '@/routes/pdm/input/sample-monitoring';
import type { IdName } from '@/types';

type RowData = Record<string, string | null>;

type Target = { jenis: string; target: number | null; keterangan: string };

type Props = {
    unit: IdName;
    /** Kop dokumen, sama dengan kop PDF (App\Support\PdmInputKop). */
    kop_lines: string[];
    filters: PdmInputFilters;
    options: { units: IdName[]; years: number[]; jenis_sample: string[] };
    sections: SampleSection[];
    document: {
        id: number | null;
        lokasi: string;
        pic_monitoring: string;
        catatan: string;
        targets: Target[];
        rows: Record<string, RowData[]>;
        rekap: SampleRekapRow[];
    };
    has_saved: boolean;
    can_write: boolean;
};

const cellInput = 'h-7 rounded-none border-0 bg-transparent px-1 text-xs shadow-none focus-visible:ring-1';

const blank = (section: SampleSection): RowData => Object.fromEntries(section.columns.map((c) => [c.key, null]));

export default function PdmSampleMonitoringInput({ unit, kop_lines, filters, options, sections, document, has_saved, can_write }: Props) {
    const [header, setHeader] = useState({ lokasi: document.lokasi, pic_monitoring: document.pic_monitoring, catatan: document.catatan });
    const [targets, setTargets] = useState<Target[]>(document.targets);
    const [rows, setRows] = useState<Record<string, RowData[]>>(document.rows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [exporting, setExporting] = useState(false);

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const query = { unit_id: filters.unit_id, month: filters.month, year: filters.year };
    const touch = () => setDirty(true);

    const visit = (patch: Partial<PdmInputFilters>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(sampleMonitoring.index().url, { ...filters, ...patch }, { preserveScroll: true });
    };

    const setCell = (section: string, index: number, key: string, value: string) => {
        setRows((current) => ({
            ...current,
            [section]: current[section].map((row, i) => (i === index ? { ...row, [key]: value === '' ? null : value } : row)),
        }));
        touch();
    };

    const addRow = (section: SampleSection) => {
        setRows((current) => ({ ...current, [section.key]: [...current[section.key], blank(section)] }));
        touch();
    };

    const removeRow = (section: string, index: number) => {
        setRows((current) => ({ ...current, [section]: current[section].filter((_, i) => i !== index) }));
        touch();
    };

    const setTarget = (jenis: string, patch: Partial<Target>) => {
        setTargets((current) => current.map((t) => (t.jenis === jenis ? { ...t, ...patch } : t)));
        touch();
    };

    const save = () => {
        setSaving(true);
        router.post(
            sampleMonitoring.store().url,
            { ...query, ...header, targets, rows },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadSampleMonitoringWorkbook(unit.name, periodLabel, header, sections, rows, document.rekap, `Monitoring_Sample_PdM_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`);
        } catch {
            toast.error('Gagal membuat file Excel.');
        } finally {
            setExporting(false);
        }
    };

    const editor = (section: SampleSection, column: SampleColumn, row: RowData, index: number) => {
        const value = row[column.key] ?? '';
        const onChange = (next: string) => setCell(section.key, index, column.key, next);

        if (column.type === 'select' || column.type === 'jenis') {
            const choices = column.type === 'jenis' ? options.jenis_sample : (column.options ?? []);

            return (
                <PdmCellSelect value={value} onChange={onChange} options={choices} disabled={!can_write} />
            );
        }

        return (
            <Input type={column.type === 'date' ? 'date' : 'text'} value={value} onChange={(e) => onChange(e.target.value)} className={cellInput} disabled={!can_write} />
        );
    };

    const sectionTable = (key: string) => {
        const section = sections.find((s) => s.key === key);

        if (!section) {
            return null;
        }

        return (
            <div className="flex flex-col gap-2">
                <div className="flex items-center justify-between rounded-sm bg-[#1f4e79] px-3 py-1.5 text-xs font-semibold text-white">
                    <span>{section.title}</span>
                    {can_write && (
                        <Button variant="ghost" size="sm" onClick={() => addRow(section)} className="h-6 gap-1 text-xs text-white hover:bg-white/15 hover:text-white">
                            <Plus className="size-3.5" />
                            Tambah Baris
                        </Button>
                    )}
                </div>
                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="w-full min-w-[1150px] border-collapse text-xs">
                        <thead className="bg-[#1f4e79]/10 text-center text-[11px] font-semibold">
                            <tr>
                                <th className="w-9 border border-border p-1">No</th>
                                {section.columns.map((column) => (
                                    <th key={column.key} className="border border-border p-1 leading-tight">{column.label}</th>
                                ))}
                                {can_write && <th className="w-9 border border-border p-1" />}
                            </tr>
                        </thead>
                        <tbody>
                            {rows[key].map((row, index) => (
                                <tr key={index} className="hover:bg-muted/30">
                                    <td className="border border-border p-1 text-center">{index + 1}</td>
                                    {section.columns.map((column) => (
                                        <td key={column.key} className="border border-border p-0">{editor(section, column, row, index)}</td>
                                    ))}
                                    {can_write && (
                                        <td className="border border-border p-0 text-center">
                                            <Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => removeRow(key, index)} title="Hapus baris">
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        );
    };

    return (
        <>
            <Head title="Form Monitoring Pemeriksaan & Pengiriman Sample PdM" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Form Monitoring Pemeriksaan & Pengiriman Sample PdM"
                    description={`Pengiriman sampel oli/trafo, hasil analisis laboratorium, rekap, dan tindak lanjut temuan — ${unit.name} · ${periodLabel}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => router.get(pdmInput.index().url)}>Kembali</Button>
                            <Button variant="outline" onClick={exportExcel} disabled={exporting} className="gap-1.5">
                                <FileSpreadsheet className="size-4 text-emerald-600" />
                                {exporting ? 'Menyiapkan…' : 'Excel'}
                            </Button>
                            <Button variant="outline" onClick={() => window.open(sampleMonitoring.pdf({ query }).url, '_blank')} className="gap-1.5">
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

                <PdmDocumentHeader lines={kop_lines} period={periodLabel} />

                <div className="grid gap-3 rounded-md border border-border bg-card p-3 md:grid-cols-2">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="lokasi">Unit / Lokasi</Label>
                        <Input id="lokasi" value={header.lokasi} onChange={(e) => {
 setHeader({ ...header, lokasi: e.target.value }); touch(); 
}} disabled={!can_write} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="pic">PIC Monitoring</Label>
                        <Input id="pic" value={header.pic_monitoring} onChange={(e) => {
 setHeader({ ...header, pic_monitoring: e.target.value }); touch(); 
}} disabled={!can_write} />
                    </div>
                </div>

                {!has_saved && (
                    <p className="text-[13px] text-muted-foreground">Belum ada data tersimpan untuk periode ini. Baris kosong tidak ikut disimpan.</p>
                )}

                {sectionTable('pengiriman')}
                {sectionTable('hasil')}

                <div className="flex flex-col gap-2">
                    <div className="rounded-sm bg-[#1f4e79] px-3 py-1.5 text-xs font-semibold text-white">C. REKAP MONITORING</div>
                    <div className="overflow-x-auto rounded-md border border-border bg-card">
                        <table className="w-full min-w-[1000px] border-collapse text-xs">
                            <thead className="bg-[#1f4e79]/10 text-center text-[11px] font-semibold">
                                <tr>
                                    {['No', 'Jenis Sample', 'Target Pengiriman', 'Jumlah Terkirim', 'Jumlah Belum Terkirim', 'Jumlah Hasil Diterima', 'Jumlah Menunggu', 'Jumlah Perlu Tindak Lanjut', 'Status Monitoring', 'Keterangan'].map((label) => (
                                        <th key={label} className="border border-border p-1 leading-tight">{label}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {document.rekap.map((rekap, index) => {
                                    const target = targets.find((t) => t.jenis === rekap.jenis);
                                    const isTotal = rekap.jenis === 'TOTAL';

                                    return (
                                        <tr key={rekap.jenis} className={isTotal ? 'bg-muted/50 font-semibold' : ''}>
                                            <td className="border border-border p-1 text-center">{isTotal ? '' : index + 1}</td>
                                            <td className="border border-border p-1">{rekap.jenis}</td>
                                            <td className="w-28 border border-border p-0 text-center">
                                                {target ? (
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        value={target.target ?? ''}
                                                        onChange={(e) => setTarget(rekap.jenis, { target: e.target.value === '' ? null : Number(e.target.value) })}
                                                        className={`${cellInput} text-center`}
                                                        disabled={!can_write}
                                                    />
                                                ) : (
                                                    rekap.target ?? '-'
                                                )}
                                            </td>
                                            {[rekap.terkirim, rekap.belum_terkirim, rekap.hasil_diterima, rekap.menunggu, rekap.perlu_tindak_lanjut].map((value, i) => (
                                                <td key={i} className="border border-border p-1 text-center">{value}</td>
                                            ))}
                                            <td className="border border-border p-1 text-center">{rekap.status}</td>
                                            <td className="border border-border p-0">
                                                {target ? (
                                                    <Input value={target.keterangan} onChange={(e) => setTarget(rekap.jenis, { keterangan: e.target.value })} className={cellInput} disabled={!can_write} />
                                                ) : null}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <p className="text-[12px] text-muted-foreground">
                        Jumlah terkirim, hasil diterima, menunggu, dan perlu tindak lanjut dihitung otomatis dari bagian A &amp; B setelah disimpan.
                    </p>
                </div>

                {sectionTable('temuan')}

                <div className="flex flex-col gap-2">
                    <div className="rounded-sm bg-[#1f4e79] px-3 py-1.5 text-xs font-semibold text-white">E. CATATAN MONITORING</div>
                    <textarea
                        rows={4}
                        value={header.catatan}
                        onChange={(e) => {
 setHeader({ ...header, catatan: e.target.value }); touch(); 
}}
                        disabled={!can_write}
                        className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </div>
            </div>
        </>
    );
}

PdmSampleMonitoringInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input PdM & Maturity Level', href: pdmInput.index() },
        { title: 'Monitoring Sample PdM', href: sampleMonitoring.index() },
    ],
};
