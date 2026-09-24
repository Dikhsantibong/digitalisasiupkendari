import { Head, router } from '@inertiajs/react';
import { Download, FileSpreadsheet, FolderPlus, Plus, Save, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
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
import { downloadKesiapanApdWorkbook } from '@/lib/pdm-input-excel';
import { dashboard } from '@/routes';
import pdmInput from '@/routes/pdm/input';
import kesiapanApd from '@/routes/pdm/input/kesiapan-apd';
import type { IdName } from '@/types';

type AnswerKey = 'kelayakan_apd' | 'peralatan_jumlah' | 'peralatan_kelayakan' | 'sop_pnp' | 'sop_vendor' | 'p3k_kotak' | 'p3k_isi' | 'cara_kerja';

type Row = {
    id: number | null;
    kelompok: string;
    inspeksi: string;
    jumlah: number | null;
    satuan: string | null;
    keterangan: string;
} & Record<AnswerKey, string | null>;

type Meta = {
    catatan: string;
};

type Props = {
    unit: IdName;
    /** Kop dokumen, sama dengan kop PDF (App\Support\PdmInputKop). */
    kop_lines: string[];
    filters: PdmInputFilters;
    options: { units: IdName[]; years: number[]; answers: Record<AnswerKey, string[]> };
    rows: Row[];
    meta: Meta;
    has_saved: boolean;
    can_write: boolean;
};

/** Assessment columns in form order with their two-line headers. */
const ANSWER_COLUMNS: { key: AnswerKey; label: string }[] = [
    { key: 'kelayakan_apd', label: 'Layak/ Tdk layak' },
    { key: 'peralatan_jumlah', label: 'Jml memenuhi/ Tdk memenuhi' },
    { key: 'peralatan_kelayakan', label: 'Layak/ Tdk Layak' },
    { key: 'sop_pnp', label: 'Memenuhi/ Tdk memenuhi semua bidang pekerjaan PNP' },
    { key: 'sop_vendor', label: 'Memenuhi/ Tdk memenuhi semua bidang pekerjaan Vendor' },
    { key: 'p3k_kotak', label: 'Ada/ Tdk ada' },
    { key: 'p3k_isi', label: 'Ada/ Tdk ada' },
    { key: 'cara_kerja', label: 'Ergonomi/ Tdk Ergonomi' },
];

const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];

const blankRow = (kelompok: string): Row => ({
    id: null,
    kelompok,
    inspeksi: '',
    jumlah: null,
    satuan: '',
    keterangan: '',
    kelayakan_apd: null,
    peralatan_jumlah: null,
    peralatan_kelayakan: null,
    sop_pnp: null,
    sop_vendor: null,
    p3k_kotak: null,
    p3k_isi: null,
    cara_kerja: null,
});

const cellInput = 'h-7 rounded-none border-0 bg-transparent px-1 text-xs shadow-none focus-visible:ring-1';

export default function PdmKesiapanApdInput({ unit, kop_lines, filters, options, rows: initialRows, meta: initialMeta, has_saved, can_write }: Props) {
    const [rows, setRows] = useState<Row[]>(initialRows);
    const [meta, setMeta] = useState<Meta>(initialMeta);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [exporting, setExporting] = useState(false);

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const query = { unit_id: filters.unit_id, month: filters.month, year: filters.year };

    const visit = (patch: Partial<PdmInputFilters>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(kesiapanApd.index().url, { ...filters, ...patch }, { preserveScroll: true });
    };

    const updateRow = (index: number, patch: Partial<Row>) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, ...patch } : row)));
        setDirty(true);
    };

    const updateMeta = (patch: Partial<Meta>) => {
        setMeta((current) => ({ ...current, ...patch }));
        setDirty(true);
    };

    const addRow = (kelompok: string) => {
        const lastIndex = rows.map((row) => row.kelompok).lastIndexOf(kelompok);
        const next = [...rows];
        next.splice(lastIndex + 1, 0, blankRow(kelompok));
        setRows(next);
        setDirty(true);
    };

    const addGroup = () => {
        const name = window.prompt('Nama kelompok inspeksi baru', '');

        if (name && name.trim() !== '') {
            setRows([...rows, blankRow(name.trim())]);
            setDirty(true);
        }
    };

    const renameGroup = (from: string, to: string) => {
        setRows((current) => current.map((row) => (row.kelompok === from ? { ...row, kelompok: to } : row)));
        setDirty(true);
    };

    const removeRow = (index: number) => {
        setRows((current) => current.filter((_, i) => i !== index));
        setDirty(true);
    };

    const save = () => {
        const incomplete = rows.findIndex((row) => row.inspeksi.trim() === '');

        if (incomplete !== -1) {
            toast.error(`Baris ${incomplete + 1}: nama inspeksi wajib diisi.`);

            return;
        }

        setSaving(true);
        router.post(
            kesiapanApd.store().url,
            { ...query, rows, meta },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadKesiapanApdWorkbook(unit.name, periodLabel, rows, meta, `Kesiapan_APD_PdM_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`);
        } catch {
            toast.error('Gagal membuat file Excel.');
        } finally {
            setExporting(false);
        }
    };

    // Rows grouped by kelompok, keeping each row's index in `rows`.
    const groups: { kelompok: string; items: { row: Row; index: number }[] }[] = [];
    rows.forEach((row, index) => {
        const last = groups[groups.length - 1];

        if (last && last.kelompok === row.kelompok) {
            last.items.push({ row, index });
        } else {
            groups.push({ kelompok: row.kelompok, items: [{ row, index }] });
        }
    });

    return (
        <>
            <Head title="Kesiapan APD Bagian PdM Pembangkit" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Kesiapan APD Bagian PdM Pembangkit"
                    description={`Inspeksi kelayakan APD, peralatan kerja, SOP/IK, P3K, dan cara kerja tim PdM — ${unit.name} · ${periodLabel}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => router.get(pdmInput.index().url)}>Kembali</Button>
                            <Button variant="outline" onClick={exportExcel} disabled={exporting} className="gap-1.5">
                                <FileSpreadsheet className="size-4 text-emerald-600" />
                                {exporting ? 'Menyiapkan…' : 'Excel'}
                            </Button>
                            <Button variant="outline" onClick={() => window.open(kesiapanApd.pdf({ query }).url, '_blank')} className="gap-1.5">
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

                {!has_saved && (
                    <p className="text-[13px] text-muted-foreground">
                        Belum ada data tersimpan untuk periode ini — daftar APD standar sudah disiapkan, lengkapi lalu simpan. PDF &amp; Excel mencetak data yang tampil (PDF memakai data tersimpan).
                    </p>
                )}

                <div className="overflow-x-auto rounded-md border border-border bg-card">
                    <table className="w-full min-w-[1150px] border-collapse text-xs">
                        <thead className="bg-sky-500/90 text-center text-[11px] font-semibold text-slate-900">
                            <tr>
                                <th rowSpan={2} className="w-9 border border-slate-400 p-1">No</th>
                                <th rowSpan={2} className="min-w-44 border border-slate-400 p-1">Inspeksi</th>
                                <th colSpan={3} className="border border-slate-400 p-1">Alat Pelindung Diri</th>
                                <th colSpan={2} className="border border-slate-400 p-1">Peralatan Kerja/ Area Kerja</th>
                                <th colSpan={2} className="border border-slate-400 p-1">SOP/ IK</th>
                                <th colSpan={2} className="border border-slate-400 p-1">P3K</th>
                                <th className="border border-slate-400 p-1">Cara Kerja</th>
                                <th rowSpan={2} className="min-w-36 border border-slate-400 p-1">Keterangan</th>
                                {can_write && <th rowSpan={2} className="w-9 border border-slate-400 p-1" />}
                            </tr>
                            <tr>
                                <th className="w-16 border border-slate-400 p-1">JUMLAH</th>
                                <th className="w-20 border border-slate-400 p-1">SATUAN</th>
                                {ANSWER_COLUMNS.map((column) => (
                                    <th key={column.key} className="w-24 border border-slate-400 p-1 leading-tight font-medium">{column.label}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {groups.map((group, groupIndex) => (
                                <Fragment key={`${group.kelompok}-${groupIndex}`}>
                                    <tr className="bg-muted/50 font-semibold">
                                        <td className="border border-border p-1 text-center">{ROMAN[groupIndex] ?? groupIndex + 1}</td>
                                        <td colSpan={can_write ? 13 : 12} className="border border-border p-0">
                                            <div className="flex items-center gap-2 pr-2">
                                                <Input
                                                    value={group.kelompok}
                                                    onChange={(e) => renameGroup(group.kelompok, e.target.value)}
                                                    className={`${cellInput} font-semibold`}
                                                    disabled={!can_write}
                                                />
                                                {can_write && (
                                                    <Button variant="ghost" size="sm" className="h-7 shrink-0 gap-1 text-xs" onClick={() => addRow(group.kelompok)}>
                                                        <Plus className="size-3.5" />
                                                        Tambah Item
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                    {group.items.map(({ row, index }, itemIndex) => (
                                        <tr key={index} className="hover:bg-muted/30">
                                            <td className="border border-border p-1 text-center">{itemIndex + 1}</td>
                                            <td className="border border-border p-0">
                                                <Input value={row.inspeksi} onChange={(e) => updateRow(index, { inspeksi: e.target.value })} className={cellInput} disabled={!can_write} />
                                            </td>
                                            <td className="border border-border p-0">
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    value={row.jumlah ?? ''}
                                                    onChange={(e) => updateRow(index, { jumlah: e.target.value === '' ? null : Number(e.target.value) })}
                                                    className={`${cellInput} text-center`}
                                                    disabled={!can_write}
                                                />
                                            </td>
                                            <td className="border border-border p-0">
                                                <Input value={row.satuan ?? ''} onChange={(e) => updateRow(index, { satuan: e.target.value })} className={`${cellInput} text-center`} disabled={!can_write} />
                                            </td>
                                            {ANSWER_COLUMNS.map((column) => (
                                                <td key={column.key} className="border border-border p-0">
                                                    <PdmCellSelect
                                                        value={row[column.key] ?? ''}
                                                        onChange={(value) => updateRow(index, { [column.key]: value || null } as Partial<Row>)}
                                                        options={options.answers[column.key]}
                                                        className="justify-center"
                                                        disabled={!can_write}
                                                    />
                                                </td>
                                            ))}
                                            <td className="border border-border p-0">
                                                <Input value={row.keterangan} onChange={(e) => updateRow(index, { keterangan: e.target.value })} className={cellInput} disabled={!can_write} />
                                            </td>
                                            {can_write && (
                                                <td className="border border-border p-0 text-center">
                                                    <Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => removeRow(index)} title="Hapus baris">
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>

                {can_write && (
                    <div>
                        <Button variant="outline" size="sm" onClick={addGroup} className="gap-1.5">
                            <FolderPlus className="size-4" />
                            Tambah Kelompok Inspeksi
                        </Button>
                    </div>
                )}

                <div className="flex max-w-2xl flex-col gap-2 rounded-md border border-border p-3">
                    <Label htmlFor="catatan">Catatan</Label>
                    <textarea
                        id="catatan"
                        rows={4}
                        value={meta.catatan}
                        onChange={(e) => updateMeta({ catatan: e.target.value })}
                        disabled={!can_write}
                        className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </div>
            </div>
        </>
    );
}

PdmKesiapanApdInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input PdM & Maturity Level', href: pdmInput.index() },
        { title: 'Kesiapan APD', href: kesiapanApd.index() },
    ],
};
