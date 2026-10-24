import { Head, router, useForm } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    ClipboardList,
    Download,
    Pencil,
    Plus,
    Trash2,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
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
    const add = () => setEditing('new');

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

    return (
        <>
            <Head title="Kondisi Abnormal & Gangguan" />
            <div
                className={cn(
                    'flex flex-1 flex-col gap-4 p-4 md:p-6',
                    compact && can_write && 'pb-28',
                )}
            >
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
                            {can_write && !compact && (
                                <Button onClick={add} className="gap-1.5">
                                    <Plus className="size-4" />
                                    Tambah Laporan
                                </Button>
                            )}
                        </div>
                    }
                />

                {filtersCard}
                {tiles}

                {entries.length === 0 ? (
                    <p className="rounded-md border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                        Belum ada laporan kondisi abnormal / gangguan pada{' '}
                        {periodLabel}.
                    </p>
                ) : compact ? (
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
                ) : (
                    <>
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

                        <Card className="gap-0 overflow-hidden p-0 py-0">
                            <div className="border-b border-border bg-muted/30 px-4 py-2 text-sm font-semibold">
                                Daftar laporan
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1200px] border-collapse text-xs">
                                    <thead className="bg-[#ea7315]/15 text-left">
                                        <tr>
                                            {[
                                                'No',
                                                'Tanggal',
                                                'Jenis',
                                                'Durasi',
                                                'Indikasi / uraian',
                                                'Parameter terkait',
                                                'TL jangka pendek',
                                                'TL jangka panjang',
                                                'Dampak sistem',
                                                'Dampak pembangkit',
                                                'Pelapor',
                                                '',
                                            ].map((label, index) => (
                                                <th
                                                    key={index}
                                                    className="border-b border-border px-2 py-2 font-semibold"
                                                >
                                                    {label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {entries.map((entry) => (
                                            <tr
                                                key={entry.id}
                                                className="align-top hover:bg-muted/30"
                                            >
                                                <td className="border-b border-border px-2 py-2 text-center">
                                                    {entry.no}
                                                </td>
                                                <td className="border-b border-border px-2 py-2 whitespace-nowrap">
                                                    {formatDate(entry.tanggal)}
                                                </td>
                                                <td className="border-b border-border px-2 py-2">
                                                    <span
                                                        className={cn(
                                                            'rounded px-1.5 py-0.5 text-[10.5px] font-bold uppercase',
                                                            JENIS_TONE[
                                                                entry.jenis
                                                            ],
                                                        )}
                                                    >
                                                        {
                                                            options.jenis[
                                                                entry.jenis
                                                            ]
                                                        }
                                                    </span>
                                                </td>
                                                <td className="border-b border-border px-2 py-2 whitespace-nowrap tabular-nums">
                                                    {hours(entry.durasi)}
                                                </td>
                                                <td className="border-b border-border px-2 py-2">
                                                    {entry.uraian_kondisi}
                                                </td>
                                                <td className="border-b border-border px-2 py-2">
                                                    {entry.parameter_terkait ||
                                                        '–'}
                                                </td>
                                                <td className="border-b border-border px-2 py-2">
                                                    <div className="flex flex-wrap gap-1">
                                                        {tindak
                                                            .filter(
                                                                ([key]) =>
                                                                    entry[
                                                                        key
                                                                    ] === 1,
                                                            )
                                                            .map(
                                                                ([
                                                                    key,
                                                                    label,
                                                                ]) => (
                                                                    <Badge
                                                                        key={
                                                                            key
                                                                        }
                                                                        variant="secondary"
                                                                    >
                                                                        {label}
                                                                    </Badge>
                                                                ),
                                                            )}
                                                    </div>
                                                    {entry.tl_pendek_keterangan && (
                                                        <div className="mt-0.5 text-muted-foreground">
                                                            {
                                                                entry.tl_pendek_keterangan
                                                            }
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="border-b border-border px-2 py-2">
                                                    {entry.tl_panjang || '–'}
                                                </td>
                                                <td className="border-b border-border px-2 py-2">
                                                    {entry.dampak_sistem || '–'}
                                                </td>
                                                <td className="border-b border-border px-2 py-2">
                                                    {entry.dampak_pembangkit ||
                                                        '–'}
                                                </td>
                                                <td className="border-b border-border px-2 py-2 text-muted-foreground">
                                                    {entry.input_by ?? '–'}
                                                </td>
                                                <td className="border-b border-border px-2 py-2">
                                                    {entry.can_edit && (
                                                        <div className="flex gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-7"
                                                                onClick={() =>
                                                                    setEditing(
                                                                        entry,
                                                                    )
                                                                }
                                                                title="Ubah laporan"
                                                            >
                                                                <Pencil className="size-3.5" />
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-7 text-muted-foreground hover:text-destructive"
                                                                onClick={() =>
                                                                    remove(
                                                                        entry,
                                                                    )
                                                                }
                                                                title="Hapus laporan"
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                            </Button>
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </Card>
                    </>
                )}
            </div>

            {compact && can_write && (
                <StickyActionBar>
                    <Button size="lg" onClick={add}>
                        <Plus className="size-4" />
                        Tambah Laporan
                    </Button>
                </StickyActionBar>
            )}

            {editing !== null && (
                <EntryDialog
                    key={editing === 'new' ? 'new' : editing.id}
                    entry={editing === 'new' ? null : editing}
                    unitId={filters.unit_id}
                    defaultDate={defaultDate(filters, today)}
                    options={options}
                    onClose={() => setEditing(null)}
                    onDelete={
                        editing !== 'new' && compact
                            ? () => remove(editing)
                            : undefined
                    }
                />
            )}
        </>
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
}: {
    entry: Entry | null;
    unitId: number;
    defaultDate: string;
    options: Props['options'];
    onClose: () => void;
    onDelete?: () => void;
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
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (entry) {
            form.put(kondisiAbnormalRoutes.update(entry.id).url, options);
        } else {
            form.post(kondisiAbnormalRoutes.store().url, options);
        }
    };

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
                                value={form.data.durasi}
                                onChange={(e) =>
                                    form.setData('durasi', e.target.value)
                                }
                                placeholder="0"
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
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={onClose}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
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
