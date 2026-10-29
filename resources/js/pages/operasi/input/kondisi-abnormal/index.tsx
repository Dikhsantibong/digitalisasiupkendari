import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    ClipboardList,
    Download,
    Pencil,
    Plus,
    Save,
    Trash2,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import kondisiAbnormalRoutes from '@/routes/operasi/input/kondisi-abnormal';
import type { IdName } from '@/types';

type Jenis = 'abnormal' | 'gangguan';
type TindakKey = 'tl_pendek_sr' | 'tl_pendek_flm' | 'tl_pendek_lainnya';
type TextKey =
    | 'parameter_terkait'
    | 'tl_pendek_keterangan'
    | 'tl_panjang'
    | 'dampak_sistem'
    | 'dampak_pembangkit';

type Entry = {
    id: number;
    no: number;
    tanggal: string;
    jenis: Jenis;
    uraian_kondisi: string;
    is_abnormal: number;
    durasi_abnormal: number;
    is_gangguan: number;
    durasi_gangguan: number;
    durasi: number;
    jam_mulai: string;
    jam_selesai: string;
    machine_id: number | null;
    mesin: string;
    peralatan: string;
    input_by: string | null;
    can_edit: boolean;
} & Record<TindakKey, number> &
    Record<TextKey, string>;

type Props = {
    unit: IdName;
    filters: { unit_id: number; month: number; year: number };
    entries: Entry[];
    summary: {
        total_abnormal_count: number;
        total_abnormal_durasi: number;
        total_gangguan_count: number;
        total_gangguan_durasi: number;
        tindak_pendek: Record<TindakKey, number>;
    };
    rekap_harian: {
        tanggal: string;
        abnormal: number;
        durasi_abnormal: number;
        gangguan: number;
        durasi_gangguan: number;
    }[];
    options: {
        units: IdName[];
        years: number[];
        tindak_pendek: Record<TindakKey, string>;
        jenis: Record<Jenis, string>;
        machines: IdName[];
    };
    can_write: boolean;
    today: string;
};

type FormData = {
    unit_id: number;
    tanggal: string;
    jenis: Jenis;
    durasi: string;
    uraian_kondisi: string;
    jam_mulai: string;
    jam_selesai: string;
    machine_id: string;
    peralatan: string;
} & Record<TindakKey, boolean> &
    Record<TextKey, string>;

/** The follow-up & impact fields of a report. */
const DETAIL_TEXTS: { key: TextKey; label: string; placeholder: string }[] = [
    {
        key: 'parameter_terkait',
        label: 'Parameter terkait',
        placeholder: 'Mis. temperatur exhaust 520 °C, tekanan LO 2,1 bar…',
    },
    {
        key: 'tl_pendek_keterangan',
        label: 'Keterangan tindak lanjut jangka pendek',
        placeholder: 'No. SR, tindakan FLM, atau tindakan lainnya…',
    },
    {
        key: 'tl_panjang',
        label: 'Tindak lanjut jangka panjang',
        placeholder: 'Rencana perbaikan permanen, penggantian part, overhaul…',
    },
    {
        key: 'dampak_sistem',
        label: 'Dampak terhadap sistem',
        placeholder: 'Mis. defisit daya, pemadaman, beban dialihkan…',
    },
    {
        key: 'dampak_pembangkit',
        label: 'Dampak terhadap pembangkit',
        placeholder: 'Mis. derating, mesin standby, unit trip…',
    },
];

const JENIS_TONE: Record<Jenis, string> = {
    abnormal: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    gangguan: 'bg-rose-500/15 text-rose-700 dark:text-rose-400',
};

/** "12 Agu 2026". */
const formatDate = (date: string) => {
    const [year, month, day] = date.split('-').map(Number);

    return `${day} ${OPERASI_MONTHS[month - 1].slice(0, 3)} ${year}`;
};

/** Hours of a gangguan from its jam mulai to jam selesai (past midnight when it ends earlier). */
const gangguanHours = (start: string, end: string): number | null => {
    if (!/^\d{2}:\d{2}$/.test(start) || !/^\d{2}:\d{2}$/.test(end)) {
        return null;
    }

    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    let minutes = eh * 60 + em - (sh * 60 + sm);

    if (minutes <= 0) {
        minutes += 24 * 60;
    }

    return Math.round((minutes / 60) * 100) / 100;
};

const hours = (value: number) =>
    value > 0 ? `${Number(value.toFixed(2))} jam` : '–';

/**
 * Laporan Kondisi Abnormal & Gangguan: one report per kejadian. The operator
 * adds it with its tanggal (several a day are fine); the page shows the
 * month's rekap — per tanggal and the list of reports — and the PDF.
 */
export default function KondisiAbnormalInput({
    unit,
    filters,
    entries,
    summary,
    rekap_harian,
    options,
    can_write,
    today,
}: Props) {
    const compact = useCompactLayout();
    const [editing, setEditing] = useState<Entry | 'new' | null>(null);
    const tindak = Object.entries(options.tindak_pendek) as [
        TindakKey,
        string,
    ][];
    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;

    const visit = (patch: Partial<Props['filters']>) =>
        router.get(
            kondisiAbnormalRoutes.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const remove = (entry: Entry) => {
        if (
            window.confirm(
                `Hapus laporan ${entry.jenis} tanggal ${formatDate(entry.tanggal)}?`,
            )
        ) {
            router.delete(kondisiAbnormalRoutes.destroy(entry.id).url, {
                preserveScroll: true,
            });
        }
    };

    const pdfUrl = kondisiAbnormalRoutes.pdf({ query: filters }).url;
    // The sheet starts over from the saved reports after every save / visit.
    const sheetKey = entries.map((entry) => `${entry.id}:${entry.tanggal}:${entry.uraian_kondisi.length}`).join('|') + `@${filters.unit_id}-${filters.month}-${filters.year}`;
    const [newFormKey, setNewFormKey] = useState(0);
    const entryCards = (
                    <div className="flex flex-col gap-2">
                        {entries.map((entry) => (
                            <button
                                key={entry.id}
                                type="button"
                                onClick={() =>
                                    entry.can_edit && setEditing(entry)
                                }
                                className="flex flex-col gap-1.5 rounded-xl border border-border bg-card p-3 text-left disabled:cursor-default"
                            >
                                <span className="flex items-center justify-between gap-2">
                                    <span className="text-[12px] font-semibold text-muted-foreground">
                                        {formatDate(entry.tanggal)}
                                    </span>
                                    <span
                                        className={cn(
                                            'rounded-md px-1.5 py-0.5 text-[10.5px] font-bold uppercase',
                                            JENIS_TONE[entry.jenis],
                                        )}
                                    >
                                        {options.jenis[entry.jenis]} ·{' '}
                                        {hours(entry.durasi)}
                                    </span>
                                </span>
                                <span className="text-[14px] text-foreground">
                                    {entry.uraian_kondisi}
                                </span>
                                {(entry.tl_panjang ||
                                    entry.dampak_pembangkit) && (
                                    <span className="text-[12px] text-muted-foreground">
                                        {entry.tl_panjang ||
                                            entry.dampak_pembangkit}
                                    </span>
                                )}
                                <span className="flex flex-wrap items-center gap-1 text-[11px] text-muted-foreground">
                                    {tindak
                                        .filter(([key]) => entry[key] === 1)
                                        .map(([key, label]) => (
                                            <Badge
                                                key={key}
                                                variant="secondary"
                                            >
                                                {label}
                                            </Badge>
                                        ))}
                                    {entry.input_by && (
                                        <span>oleh {entry.input_by}</span>
                                    )}
                                    {entry.can_edit && (
                                        <Pencil className="ml-auto size-3.5" />
                                    )}
                                </span>
                            </button>
                        ))}
                    </div>
    );

    const tiles = (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Tile
                icon={<AlertTriangle className="size-5" />}
                tone="bg-amber-500/10 text-amber-600"
                label="Abnormal"
                value={`${summary.total_abnormal_count}×`}
                sub={hours(summary.total_abnormal_durasi)}
            />
            <Tile
                icon={<AlertOctagon className="size-5" />}
                tone="bg-rose-500/10 text-rose-600"
                label="Gangguan"
                value={`${summary.total_gangguan_count}×`}
                sub={hours(summary.total_gangguan_durasi)}
            />
            <Tile
                icon={<ClipboardList className="size-5" />}
                tone="bg-primary/10 text-primary"
                label="Total laporan"
                value={String(entries.length)}
                sub={periodLabel}
            />
            <Tile
                icon={<Wrench className="size-5" />}
                tone="bg-emerald-500/10 text-emerald-600"
                label="Tindak lanjut pendek"
                value={tindak
                    .map(
                        ([key, label]) =>
                            `${label} ${summary.tindak_pendek[key]}`,
                    )
                    .join(' · ')}
                small
            />
        </div>
    );

    const filtersCard = (
        <Card className="grid grid-cols-2 gap-3 p-3 py-3 sm:flex sm:flex-row sm:flex-wrap sm:items-end">
            <div className="col-span-2 sm:col-span-1">
                <OperasiSelect
                    label="Unit"
                    value={String(filters.unit_id)}
                    onChange={(v) => visit({ unit_id: Number(v) })}
                    options={options.units.map((u) => ({
                        value: String(u.id),
                        label: u.name,
                    }))}
                    className="w-full sm:w-56"
                />
            </div>
            <OperasiSelect
                label="Bulan"
                value={String(filters.month)}
                onChange={(v) => visit({ month: Number(v) })}
                options={OPERASI_MONTHS.map((label, index) => ({
                    value: String(index + 1),
                    label,
                }))}
                className="w-full sm:w-40"
            />
            <OperasiSelect
                label="Tahun"
                value={String(filters.year)}
                onChange={(v) => visit({ year: Number(v) })}
                options={options.years.map((y) => ({
                    value: String(y),
                    label: String(y),
                }))}
                className="w-full sm:w-28"
            />
        </Card>
    );

    if (compact) {
        return (
            <>
                <Head title="Kondisi Abnormal & Gangguan" />
                <div className="flex flex-col gap-3 p-4 pb-8">

                    {can_write && (
                        <div className="rounded-xl border border-border bg-card p-3">
                            <p className="mb-2 text-[14px] font-semibold text-foreground">
                                Laporan baru
                            </p>
                            <EntryDialog
                                key={`new-${newFormKey}`}
                                inline
                                entry={null}
                                unitId={filters.unit_id}
                                defaultDate={defaultDate(filters, today)}
                                options={options}
                                onClose={() => setNewFormKey((key) => key + 1)}
                            />
                        </div>
                    )}

                    {entries.length > 0 && (
                        <p className="mt-1 px-0.5 text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                            Laporan {periodLabel} · {entries.length}
                        </p>
                    )}
                    {entryCards}
                </div>

                {editing !== null && editing !== 'new' && (
                    <EntryDialog
                        key={editing.id}
                        entry={editing}
                        unitId={filters.unit_id}
                        defaultDate={defaultDate(filters, today)}
                        options={options}
                        onClose={() => setEditing(null)}
                        onDelete={() => remove(editing)}
                    />
                )}
            </>
        );
    }

    return (
        <>
            <Head title="Kondisi Abnormal & Gangguan" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Kondisi Abnormal & Gangguan"
                    description={`Laporkan setiap kejadian abnormal / gangguan dengan tanggalnya — satu hari boleh lebih dari satu laporan. ${unit.name} · ${periodLabel}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() => window.open(pdfUrl, '_blank')}
                                className="gap-1.5"
                            >
                                <Download className="size-4 text-rose-600" />
                                PDF Rekap
                            </Button>
                        </div>
                    }
                />

                {filtersCard}
                {tiles}

                <AbnormalSheet
                            key={sheetKey}
                            entries={entries}
                            filters={filters}
                            options={options}
                            canWrite={can_write}
                            defaultDate={defaultDate(filters, today)}
                        />

                        {rekap_harian.length > 0 && (
                        <Card className="gap-0 overflow-hidden p-0 py-0">
                            <div className="border-b border-border bg-muted/30 px-4 py-2 text-sm font-semibold">
                                Rekap per tanggal
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full border-collapse text-xs">
                                    <thead className="bg-[#ea7315]/15 text-left">
                                        <tr>
                                            <th className="border-b border-border px-3 py-2">
                                                Tanggal
                                            </th>
                                            <th className="border-b border-border px-3 py-2 text-center">
                                                Abnormal
                                            </th>
                                            <th className="border-b border-border px-3 py-2 text-center">
                                                Durasi abnormal
                                            </th>
                                            <th className="border-b border-border px-3 py-2 text-center">
                                                Gangguan
                                            </th>
                                            <th className="border-b border-border px-3 py-2 text-center">
                                                Durasi gangguan
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rekap_harian.map((day) => (
                                            <tr key={day.tanggal}>
                                                <td className="border-b border-border px-3 py-1.5">
                                                    {formatDate(day.tanggal)}
                                                </td>
                                                <td className="border-b border-border px-3 py-1.5 text-center tabular-nums">
                                                    {day.abnormal || '–'}
                                                </td>
                                                <td className="border-b border-border px-3 py-1.5 text-center tabular-nums">
                                                    {hours(day.durasi_abnormal)}
                                                </td>
                                                <td className="border-b border-border px-3 py-1.5 text-center tabular-nums">
                                                    {day.gangguan || '–'}
                                                </td>
                                                <td className="border-b border-border px-3 py-1.5 text-center tabular-nums">
                                                    {hours(day.durasi_gangguan)}
                                                </td>
                                            </tr>
                                        ))}
                                        <tr className="font-semibold">
                                            <td className="px-3 py-1.5">
                                                Total
                                            </td>
                                            <td className="px-3 py-1.5 text-center tabular-nums">
                                                {summary.total_abnormal_count}
                                            </td>
                                            <td className="px-3 py-1.5 text-center tabular-nums">
                                                {hours(
                                                    summary.total_abnormal_durasi,
                                                )}
                                            </td>
                                            <td className="px-3 py-1.5 text-center tabular-nums">
                                                {summary.total_gangguan_count}
                                            </td>
                                            <td className="px-3 py-1.5 text-center tabular-nums">
                                                {hours(
                                                    summary.total_gangguan_durasi,
                                                )}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                        )}
            </div>

        </>
    );
}

type SheetRow = {
    key: string;
    id: number | null;
    canEdit: boolean;
    input_by: string | null;
    tanggal: string;
    jenis: Jenis;
    durasi: string;
    uraian_kondisi: string;
    jam_mulai: string;
    jam_selesai: string;
    machine_id: string;
    peralatan: string;
} & Record<TindakKey, boolean> &
    Record<TextKey, string>;

let nextRowKey = 0;

const toSheetRow = (entry: Entry): SheetRow => ({
    key: `saved-${entry.id}`,
    id: entry.id,
    canEdit: entry.can_edit,
    input_by: entry.input_by,
    tanggal: entry.tanggal,
    jenis: entry.jenis,
    durasi: entry.durasi > 0 ? String(entry.durasi) : '',
    uraian_kondisi: entry.uraian_kondisi,
    jam_mulai: entry.jam_mulai,
    jam_selesai: entry.jam_selesai,
    machine_id: entry.machine_id ? String(entry.machine_id) : '',
    peralatan: entry.peralatan,
    tl_pendek_sr: entry.tl_pendek_sr === 1,
    tl_pendek_flm: entry.tl_pendek_flm === 1,
    tl_pendek_lainnya: entry.tl_pendek_lainnya === 1,
    parameter_terkait: entry.parameter_terkait,
    tl_pendek_keterangan: entry.tl_pendek_keterangan,
    tl_panjang: entry.tl_panjang,
    dampak_sistem: entry.dampak_sistem,
    dampak_pembangkit: entry.dampak_pembangkit,
});

const sheetCell =
    'min-h-8 w-full rounded-none border-0 bg-transparent px-1.5 py-1.5 text-xs shadow-none focus-visible:ring-1 md:text-xs';
const sheetTd = 'border border-border p-0 align-top';

/**
 * Desktop input like a spreadsheet: every report is a row typed straight into
 * the table, "Tambah Baris" adds one and one Simpan saves the month. Rows of
 * other operators are read-only for an operator.
 */
function AbnormalSheet({
    entries,
    filters,
    options,
    canWrite,
    defaultDate: initialDate,
}: {
    entries: Entry[];
    filters: Props['filters'];
    options: Props['options'];
    canWrite: boolean;
    defaultDate: string;
}) {
    const [rows, setRows] = useState<SheetRow[]>(() => entries.map(toSheetRow));
    const [deletedIds, setDeletedIds] = useState<number[]>([]);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const tindak = Object.entries(options.tindak_pendek) as [TindakKey, string][];
    const prefix = `${filters.year}-${String(filters.month).padStart(2, '0')}`;
    const lastDay = new Date(filters.year, filters.month, 0).getDate();

    const update = (index: number, patch: Partial<SheetRow>) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, ...patch } : row)));
        setDirty(true);
    };

    const addRow = () => {
        const previous = rows[rows.length - 1];
        setRows((current) => [
            ...current,
            {
                key: `new-${++nextRowKey}`,
                id: null,
                canEdit: true,
                input_by: null,
                // A new row starts on the last row's date (several kejadian a day are common).
                tanggal: previous?.tanggal.startsWith(prefix) ? previous.tanggal : initialDate,
                jenis: 'gangguan',
                durasi: '',
                uraian_kondisi: '',
                jam_mulai: '',
                jam_selesai: '',
                machine_id: '',
                peralatan: '',
                tl_pendek_sr: false,
                tl_pendek_flm: false,
                tl_pendek_lainnya: false,
                parameter_terkait: '',
                tl_pendek_keterangan: '',
                tl_panjang: '',
                dampak_sistem: '',
                dampak_pembangkit: '',
            },
        ]);
        setDirty(true);
    };

    const removeRow = (index: number) => {
        const row = rows[index];

        if (row.id !== null && !window.confirm(`Hapus laporan "${row.uraian_kondisi || `baris ${index + 1}`}"? Terhapus saat Simpan ditekan.`)) {
            return;
        }

        if (row.id !== null) {
            setDeletedIds((current) => [...current, row.id as number]);
        }

        setRows((current) => current.filter((_, i) => i !== index));
        setErrors({});
        setDirty(true);
    };

    const save = () => {
        setSaving(true);
        setErrors({});
        router.post(
            kondisiAbnormalRoutes.sync().url,
            {
                ...filters,
                deleted_ids: deletedIds,
                // Only the rows this user may change are sent.
                rows: rows
                    .filter((row) => row.canEdit)
                    .map((row) => ({
                        id: row.id,
                        tanggal: row.tanggal,
                        jenis: row.jenis,
                        durasi: row.durasi,
                        uraian_kondisi: row.uraian_kondisi,
                        jam_mulai: row.jam_mulai,
                        jam_selesai: row.jam_selesai,
                        machine_id: row.machine_id,
                        peralatan: row.peralatan,
                        tl_pendek_sr: row.tl_pendek_sr,
                        tl_pendek_flm: row.tl_pendek_flm,
                        tl_pendek_lainnya: row.tl_pendek_lainnya,
                        parameter_terkait: row.parameter_terkait,
                        tl_pendek_keterangan: row.tl_pendek_keterangan,
                        tl_panjang: row.tl_panjang,
                        dampak_sistem: row.dampak_sistem,
                        dampak_pembangkit: row.dampak_pembangkit,
                    })),
            },
            {
                preserveScroll: true,
                onError: (errs) => {
                    setErrors(errs);
                    toast.error(Object.values(errs)[0] ?? 'Periksa kembali isian tabel.');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    /** Errors come back indexed by the editable rows sent. */
    const editableIndex = (index: number) => rows.slice(0, index).filter((row) => row.canEdit).length;
    const errorOf = (index: number, field: string) => errors[`rows.${editableIndex(index)}.${field}`];

    const text = (index: number, row: SheetRow, key: TextKey | 'uraian_kondisi', placeholder?: string) => {
        const error = errorOf(index, key);

        return (
            <td className={cn(sheetTd, error && 'bg-destructive/5 ring-1 ring-destructive ring-inset')} title={error}>
                {row.canEdit && canWrite ? (
                    <Textarea
                        value={row[key]}
                        onChange={(e) => update(index, { [key]: e.target.value })}
                        rows={1}
                        placeholder={placeholder}
                        className={cn(sheetCell, 'resize-y')}
                    />
                ) : (
                    <div className="px-1.5 py-1.5 text-xs whitespace-pre-line">{row[key] || '–'}</div>
                )}
            </td>
        );
    };

    return (
        <Card className="gap-0 overflow-hidden p-0 py-0">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-muted/30 px-4 py-2">
                <span className="text-sm font-semibold">Daftar laporan</span>
                <div className="flex items-center gap-3">
                    {dirty && <span className="text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>}
                    {canWrite && (
                        <Button size="sm" onClick={save} disabled={saving || !dirty} className="gap-1.5">
                            <Save className="size-4" />
                            {saving ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    )}
                </div>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[1900px] border-collapse text-xs">
                    <thead className="bg-[#ea7315]/15 text-left">
                        <tr>
                            <th rowSpan={2} className="w-10 border border-border px-2 py-2 text-center">No</th>
                            <th rowSpan={2} className="w-36 border border-border px-2 py-2">Tanggal</th>
                            <th rowSpan={2} className="w-28 border border-border px-2 py-2">Jenis</th>
                            <th colSpan={4} className="border border-border bg-rose-500/10 px-2 py-1 text-center">Gangguan</th>
                            <th rowSpan={2} className="w-20 border border-border px-2 py-2">Durasi (jam)</th>
                            <th rowSpan={2} className="min-w-52 border border-border px-2 py-2">Indikasi / uraian</th>
                            <th rowSpan={2} className="min-w-40 border border-border px-2 py-2">Parameter terkait</th>
                            <th colSpan={tindak.length + 1} className="border border-border px-2 py-1 text-center">TL jangka pendek</th>
                            <th rowSpan={2} className="min-w-40 border border-border px-2 py-2">TL jangka panjang</th>
                            <th rowSpan={2} className="min-w-36 border border-border px-2 py-2">Dampak sistem</th>
                            <th rowSpan={2} className="min-w-36 border border-border px-2 py-2">Dampak pembangkit</th>
                            <th rowSpan={2} className="w-28 border border-border px-2 py-2">Pelapor</th>
                            {canWrite && <th rowSpan={2} className="w-9 border border-border" />}
                        </tr>
                        <tr>
                            <th className="w-24 border border-border bg-rose-500/10 px-1 py-1 text-center">Jam mulai</th>
                            <th className="w-24 border border-border bg-rose-500/10 px-1 py-1 text-center">Jam selesai</th>
                            <th className="min-w-32 border border-border bg-rose-500/10 px-2 py-1">Mesin</th>
                            <th className="min-w-36 border border-border bg-rose-500/10 px-2 py-1">Peralatan</th>
                            {tindak.map(([key, label]) => (
                                <th key={key} className="w-12 border border-border px-1 py-1 text-center">{label}</th>
                            ))}
                            <th className="min-w-36 border border-border px-2 py-1">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={17 + tindak.length} className="border border-border p-6 text-center text-[13px] text-muted-foreground">
                                    Belum ada laporan pada bulan ini.{canWrite && ' Tekan Tambah Baris untuk mengisi.'}
                                </td>
                            </tr>
                        )}
                        {rows.map((row, index) => {
                            const editable = row.canEdit && canWrite;
                            const dateError = errorOf(index, 'tanggal');
                            const durasiError = errorOf(index, 'durasi');
                            const gangguanRow = row.jenis === 'gangguan';
                            const computed = gangguanRow ? gangguanHours(row.jam_mulai, row.jam_selesai) : null;
                            const muted = <div className="px-1.5 py-1.5 text-center text-muted-foreground">–</div>;
                            const gangguanCell = (field: 'jam_mulai' | 'jam_selesai' | 'machine_id' | 'peralatan') => {
                                const error = errorOf(index, field);

                                return (
                                    <td className={cn(sheetTd, error && 'bg-destructive/5 ring-1 ring-destructive ring-inset')} title={error}>
                                        {!gangguanRow ? (
                                            muted
                                        ) : !editable ? (
                                            <div className="px-1.5 py-1.5">
                                                {field === 'machine_id'
                                                    ? options.machines.find((m) => String(m.id) === row.machine_id)?.name || '–'
                                                    : row[field] || '–'}
                                            </div>
                                        ) : field === 'machine_id' ? (
                                            <PdmCellSelect
                                                value={options.machines.find((m) => String(m.id) === row.machine_id)?.name ?? ''}
                                                onChange={(name) => update(index, { machine_id: String(options.machines.find((m) => m.name === name)?.id ?? '') })}
                                                options={options.machines.map((m) => m.name)}
                                                placeholder="Pilih…"
                                                className="h-8"
                                            />
                                        ) : (
                                            <Input
                                                type={field === 'peralatan' ? 'text' : 'time'}
                                                value={row[field]}
                                                onChange={(e) => update(index, { [field]: e.target.value })}
                                                placeholder={field === 'peralatan' ? 'Mis. cylinder head' : undefined}
                                                className={cn(sheetCell, 'h-8', field !== 'peralatan' && 'text-center')}
                                            />
                                        )}
                                    </td>
                                );
                            };

                            return (
                                <tr key={row.key} className="hover:bg-muted/30">
                                    <td className={cn(sheetTd, 'px-1 py-1.5 text-center')}>{index + 1}</td>
                                    <td className={cn(sheetTd, dateError && 'bg-destructive/5 ring-1 ring-destructive ring-inset')} title={dateError}>
                                        {editable ? (
                                            <Input
                                                type="date"
                                                min={`${prefix}-01`}
                                                max={`${prefix}-${String(lastDay).padStart(2, '0')}`}
                                                value={row.tanggal}
                                                onChange={(e) => update(index, { tanggal: e.target.value })}
                                                className={cn(sheetCell, 'h-8')}
                                            />
                                        ) : (
                                            <div className="px-1.5 py-1.5 whitespace-nowrap">{row.tanggal ? formatDate(row.tanggal) : '–'}</div>
                                        )}
                                    </td>
                                    <td className={sheetTd}>
                                        {editable ? (
                                            <PdmCellSelect
                                                value={options.jenis[row.jenis]}
                                                onChange={(label) =>
                                                    update(index, {
                                                        jenis: label === options.jenis.abnormal ? 'abnormal' : 'gangguan',
                                                    })
                                                }
                                                options={Object.values(options.jenis)}
                                                className={cn('h-8 font-semibold', row.jenis === 'gangguan' ? 'text-rose-600' : 'text-amber-600')}
                                            />
                                        ) : (
                                            <div className="px-1.5 py-1.5">
                                                <span className={cn('rounded px-1.5 py-0.5 text-[10.5px] font-bold uppercase', JENIS_TONE[row.jenis])}>{options.jenis[row.jenis]}</span>
                                            </div>
                                        )}
                                    </td>
                                    {gangguanCell('jam_mulai')}
                                    {gangguanCell('jam_selesai')}
                                    {gangguanCell('machine_id')}
                                    {gangguanCell('peralatan')}
                                    <td className={cn(sheetTd, durasiError && 'bg-destructive/5 ring-1 ring-destructive ring-inset')} title={durasiError}>
                                        {computed !== null ? (
                                            <div className="px-1.5 py-1.5 text-right font-semibold tabular-nums" title="Akumulasi dari jam mulai – jam selesai">{computed}</div>
                                        ) : editable ? (
                                            <Input
                                                type="number"
                                                min="0"
                                                step="0.1"
                                                value={row.durasi}
                                                onChange={(e) => update(index, { durasi: e.target.value })}
                                                placeholder="0"
                                                className={cn(sheetCell, 'h-8 text-right tabular-nums')}
                                            />
                                        ) : (
                                            <div className="px-1.5 py-1.5 text-right tabular-nums">{row.durasi || '–'}</div>
                                        )}
                                    </td>
                                    {text(index, row, 'uraian_kondisi', 'Mis. mesin #2 trip…')}
                                    {text(index, row, 'parameter_terkait', 'Mis. exhaust 520 °C…')}
                                    {tindak.map(([key, label]) => (
                                        <td key={key} className={cn(sheetTd, 'text-center align-middle')}>
                                            <Checkbox
                                                checked={row[key]}
                                                onCheckedChange={(checked) => update(index, { [key]: checked === true })}
                                                disabled={!editable}
                                                aria-label={`${label} baris ${index + 1}`}
                                            />
                                        </td>
                                    ))}
                                    {text(index, row, 'tl_pendek_keterangan', 'No. SR / tindakan…')}
                                    {text(index, row, 'tl_panjang')}
                                    {text(index, row, 'dampak_sistem')}
                                    {text(index, row, 'dampak_pembangkit')}
                                    <td className={cn(sheetTd, 'px-1.5 py-1.5 text-muted-foreground')}>{row.input_by ?? (row.id === null ? 'Baru' : '–')}</td>
                                    {canWrite && (
                                        <td className={cn(sheetTd, 'text-center align-middle')}>
                                            {row.canEdit && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-7 text-muted-foreground hover:text-destructive"
                                                    onClick={() => removeRow(index)}
                                                    title="Hapus baris"
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </Button>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            {canWrite && (
                <div className="flex flex-wrap items-center gap-3 border-t border-border px-4 py-2">
                    <Button variant="outline" size="sm" onClick={addRow} className="gap-1.5">
                        <Plus className="size-4" />
                        Tambah Baris
                    </Button>
                    <span className="text-[12px] text-muted-foreground">
                        Satu baris = satu kejadian; satu hari boleh lebih dari satu baris. Baris baru tanpa uraian tidak ikut disimpan.
                    </span>
                </div>
            )}
        </Card>
    );
}

/** Today when it falls in the period shown, else the first day of that month. */
function defaultDate(filters: Props['filters'], today: string): string {
    const prefix = `${filters.year}-${String(filters.month).padStart(2, '0')}`;

    return today.startsWith(prefix) ? today : `${prefix}-01`;
}

function EntryDialog({
    entry,
    unitId,
    defaultDate: initialDate,
    options,
    onClose,
    onDelete,
    inline = false,
}: {
    entry: Entry | null;
    unitId: number;
    defaultDate: string;
    options: Props['options'];
    onClose: () => void;
    onDelete?: () => void;
    /** The form right on the page (phones) instead of a dialog. */
    inline?: boolean;
}) {
    const tindak = Object.entries(options.tindak_pendek) as [
        TindakKey,
        string,
    ][];
    const form = useForm<FormData>({
        unit_id: unitId,
        tanggal: entry?.tanggal ?? initialDate,
        jenis: entry?.jenis ?? 'gangguan',
        durasi: entry && entry.durasi > 0 ? String(entry.durasi) : '',
        uraian_kondisi: entry?.uraian_kondisi ?? '',
        tl_pendek_sr: entry?.tl_pendek_sr === 1,
        tl_pendek_flm: entry?.tl_pendek_flm === 1,
        tl_pendek_lainnya: entry?.tl_pendek_lainnya === 1,
        parameter_terkait: entry?.parameter_terkait ?? '',
        tl_pendek_keterangan: entry?.tl_pendek_keterangan ?? '',
        tl_panjang: entry?.tl_panjang ?? '',
        dampak_sistem: entry?.dampak_sistem ?? '',
        dampak_pembangkit: entry?.dampak_pembangkit ?? '',
        jam_mulai: entry?.jam_mulai ?? '',
        jam_selesai: entry?.jam_selesai ?? '',
        machine_id: entry?.machine_id ? String(entry.machine_id) : '',
        peralatan: entry?.peralatan ?? '',
    });
    const gangguan = form.data.jenis === 'gangguan';
    const computed = gangguan ? gangguanHours(form.data.jam_mulai, form.data.jam_selesai) : null;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (entry) {
            form.put(kondisiAbnormalRoutes.update(entry.id).url, options);
        } else {
            form.post(kondisiAbnormalRoutes.store().url, options);
        }
    };

    const body = (
                <form onSubmit={submit} className="flex min-w-0 flex-col gap-3">
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Tanggal" error={form.errors.tanggal}>
                            <Input
                                type="date"
                                value={form.data.tanggal}
                                onChange={(e) =>
                                    form.setData('tanggal', e.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field label="Durasi (jam)" error={form.errors.durasi}>
                            <Input
                                type="number"
                                min="0"
                                step="0.1"
                                inputMode="decimal"
                                value={computed !== null ? String(computed) : form.data.durasi}
                                onChange={(e) =>
                                    form.setData('durasi', e.target.value)
                                }
                                placeholder="0"
                                readOnly={computed !== null}
                                className={cn(computed !== null && 'bg-muted font-semibold')}
                            />
                        </Field>
                    </div>

                    <Field label="Jenis" error={form.errors.jenis}>
                        <ChoiceChips
                            options={Object.keys(options.jenis)}
                            labels={options.jenis}
                            value={form.data.jenis}
                            onChange={(value) =>
                                value && form.setData('jenis', value as Jenis)
                            }
                            tone={(value) =>
                                value === 'gangguan'
                                    ? 'border-rose-600 bg-rose-600 text-white'
                                    : 'border-amber-500 bg-amber-500 text-white'
                            }
                        />
                    </Field>

                    {gangguan && (
                        <div className="grid grid-cols-2 gap-3 rounded-lg border border-rose-500/30 bg-rose-500/5 p-3">
                            <Field label="Jam mulai" error={form.errors.jam_mulai}>
                                <Input
                                    type="time"
                                    value={form.data.jam_mulai}
                                    onChange={(e) => form.setData('jam_mulai', e.target.value)}
                                />
                            </Field>
                            <Field label="Jam selesai" error={form.errors.jam_selesai}>
                                <Input
                                    type="time"
                                    value={form.data.jam_selesai}
                                    onChange={(e) => form.setData('jam_selesai', e.target.value)}
                                />
                            </Field>
                            <p className="col-span-2 -mt-1 text-[12px] text-muted-foreground">
                                Akumulasi:{' '}
                                <span className="font-semibold text-foreground">
                                    {computed !== null ? `${computed} jam` : '–'}
                                </span>
                                {computed !== null && form.data.jam_selesai <= form.data.jam_mulai && ' (melewati tengah malam)'}
                            </p>
                            <div className="col-span-2">
                                <Field label="Mesin" error={form.errors.machine_id}>
                                    <select
                                        value={form.data.machine_id}
                                        onChange={(e) => form.setData('machine_id', e.target.value)}
                                        className="h-9 w-full rounded-md border border-input bg-background px-2 text-[13px] text-foreground"
                                    >
                                        <option value="">Pilih mesin…</option>
                                        {options.machines.map((machine) => (
                                            <option key={machine.id} value={machine.id}>
                                                {machine.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>
                            <div className="col-span-2">
                                <Field label="Peralatan yang gangguan" error={form.errors.peralatan}>
                                    <Input
                                        value={form.data.peralatan}
                                        onChange={(e) => form.setData('peralatan', e.target.value)}
                                        placeholder="Mis. cylinder head, turbocharger…"
                                    />
                                </Field>
                            </div>
                        </div>
                    )}

                    <Field
                        label="Indikasi / uraian kondisi"
                        error={form.errors.uraian_kondisi}
                    >
                        <Textarea
                            value={form.data.uraian_kondisi}
                            onChange={(e) =>
                                form.setData('uraian_kondisi', e.target.value)
                            }
                            rows={2}
                            placeholder="Mis. mesin #2 trip, temperatur exhaust tinggi…"
                            required
                        />
                    </Field>

                    <Field
                        label={DETAIL_TEXTS[0].label}
                        error={form.errors.parameter_terkait}
                    >
                        <Textarea
                            value={form.data.parameter_terkait}
                            onChange={(e) =>
                                form.setData(
                                    'parameter_terkait',
                                    e.target.value,
                                )
                            }
                            rows={2}
                            placeholder={DETAIL_TEXTS[0].placeholder}
                        />
                    </Field>

                    <Field label="Tindak lanjut jangka pendek">
                        <div className="flex flex-wrap gap-4">
                            {tindak.map(([key, label]) => (
                                <label
                                    key={key}
                                    className="flex items-center gap-1.5 text-[13px] text-foreground"
                                >
                                    <Checkbox
                                        checked={form.data[key]}
                                        onCheckedChange={(checked) =>
                                            form.setData(key, checked === true)
                                        }
                                    />
                                    {label}
                                </label>
                            ))}
                        </div>
                    </Field>

                    {DETAIL_TEXTS.slice(1).map(
                        ({ key, label, placeholder }) => (
                            <Field
                                key={key}
                                label={label}
                                error={form.errors[key]}
                            >
                                <Textarea
                                    value={form.data[key]}
                                    onChange={(e) =>
                                        form.setData(key, e.target.value)
                                    }
                                    rows={2}
                                    placeholder={placeholder}
                                />
                            </Field>
                        ),
                    )}

                    <DialogFooter className="gap-2 sm:justify-between">
                        {onDelete ? (
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive"
                                onClick={onDelete}
                            >
                                <Trash2 className="size-4" />
                                Hapus
                            </Button>
                        ) : (
                            <span />
                        )}
                        <div className={cn('flex gap-2', inline && 'w-full')}>
                            {!inline && (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={onClose}
                                >
                                    Batal
                                </Button>
                            )}
                            <Button
                                type="submit"
                                size={inline ? 'lg' : 'default'}
                                className={cn(inline && 'w-full')}
                                disabled={
                                    form.processing ||
                                    form.data.uraian_kondisi.trim() === ''
                                }
                            >
                                {form.processing ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
    );

    if (inline) {
        return body;
    }

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90dvh] max-w-xl overflow-y-auto">
                <DialogTitle>
                    {entry
                        ? 'Ubah laporan'
                        : 'Laporan kondisi abnormal / gangguan'}
                </DialogTitle>
                <DialogDescription>
                    Isi satu laporan untuk setiap kejadian. Satu hari boleh
                    lebih dari satu laporan.
                </DialogDescription>
                {body}
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-1 text-[12px] text-muted-foreground">
            <span className="font-medium">{label}</span>
            {children}
            <InputError message={error} />
        </div>
    );
}

function Tile({
    icon,
    tone,
    label,
    value,
    sub,
    small = false,
}: {
    icon: ReactNode;
    tone: string;
    label: string;
    value: string;
    sub?: string;
    small?: boolean;
}) {
    return (
        <Card className="flex flex-row items-center gap-3 p-3 py-3">
            <span
                className={cn(
                    'flex size-9 shrink-0 items-center justify-center rounded-lg',
                    tone,
                )}
            >
                {icon}
            </span>
            <div className="min-w-0">
                <div className="text-[11px] font-medium text-muted-foreground uppercase">
                    {label}
                </div>
                <div
                    className={cn(
                        'font-bold text-foreground',
                        small ? 'text-[12px]' : 'text-base',
                    )}
                >
                    {value}
                </div>
                {sub && (
                    <div className="text-[11px] text-muted-foreground">
                        {sub}
                    </div>
                )}
            </div>
        </Card>
    );
}

KondisiAbnormalInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
        {
            title: 'Kondisi Abnormal & Gangguan',
            href: kondisiAbnormalRoutes.index(),
        },
    ],
};
