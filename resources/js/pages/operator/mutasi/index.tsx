import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    BellRing,
    CheckCircle2,
    Clock,
    Droplets,
    FileDown,
    FileSpreadsheet,
    Fuel,
    Handshake,
    History,
    Lock,
    NotebookPen,
    PenLine,
    Plus,
    Power,
    Save,
    Send,
    Trash2,
    Wrench,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { EntryBadge, MobileEntryCard, MobileEntryList, MobileFormScreen } from '@/components/mobile/entry-flow';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { downloadMutasiWorkbook } from '@/lib/operator-mutasi-excel';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import mutasiRoutes from '@/routes/operator/mutasi';
import type { IdName } from '@/types';

type Mesin = {
    machine_id: number | null;
    nama: string;
    /** Level tangki bahan bakar, cm (user, 2026-11-24: a number, no ✓). */
    level_bbm: string | null;
    /** The former "tambah bahan bakar" of older sheets — kept, no longer entered. */
    tambah_bbm: string | null;
    /** Tambah pelumas, liter. */
    tambah_pelumas?: string | null;
    pelumas: string | null;
    status: string | null;
};

/** A stored level from before (e.g. "✓") is not a number: it starts blank. */
const numberOrNull = (value: string | null | undefined) =>
    value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)) ? value : null;
type Tangki = { nama: string | null; level_cm: string | null };
type Alat = { nama: string | null; ada: boolean; jumlah: number | null };
type Kejadian = { jam: string | null; uraian: string };
type StartStopEvent = { jam: string; uraian: string; mesin: string; jenis: 'start' | 'stop' };
type PatrolFinding = { jam: string; mesin: string; peralatan: string; system: string; catatan: string; operator: string };
type Status = 'baru' | 'draft' | 'diserahkan' | 'diterima';

type Mutasi = {
    id: number | null;
    tanggal: string;
    shift: string;
    mesin: Mesin[];
    tangki: Tangki[];
    peralatan: Alat[];
    kejadian: Kejadian[];
    gangguan_mesin: string | null;
    catatan: string | null;
    regu_penyerah: string | null;
    penyerah_nama: string | null;
    paraf_penyerah_url: string | null;
    diserahkan_at: string | null;
    regu_penerima: string | null;
    penerima_nama: string | null;
    paraf_penerima_url: string | null;
    diterima_at: string | null;
    status: Status;
    carried_from: string | null;
};

type Props = {
    unit: IdName;
    filters: { unit_id: number; tanggal: string; shift: string };
    options: {
        units: IdName[];
        shifts: { value: string; label: string; jam: string }[];
        regu: string[];
        pelumas: string[];
        status_mesin: string[];
    };
    mutasi: Mutasi;
    /** Start / stop of the machines in this shift, from Start Stop Engine (read-only). */
    start_stop?: StartStopEvent[];
    /** Abnormal (TN) Patrol Check Mesin findings of this shift (read-only, handed over). */
    patrol_findings?: PatrolFinding[];
    riwayat: {
        id: number;
        tanggal: string;
        shift: string;
        regu_penyerah: string | null;
        regu_penerima: string | null;
        status: Status;
    }[];
    pending_receive?: {
        id: number;
        tanggal: string;
        shift: string;
        regu_penyerah: string | null;
        penyerah_nama: string | null;
        diserahkan_at: string | null;
    }[];
    me: {
        nama: string;
        regu: string | null;
        signature_url?: string | null;
        has_signature?: boolean;
    };
    can_write: boolean;
    can_receive: boolean;
    /** An operator who is not the Leader Shift: read only. */
    leader_only?: boolean;
};

const STATUS_LABEL: Record<Status, { label: string; tone: string }> = {
    baru: { label: 'Belum diisi', tone: 'bg-muted text-muted-foreground' },
    draft: {
        label: 'Draft',
        tone: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    },
    diserahkan: {
        label: 'Diserahkan · menunggu penerima',
        tone: 'bg-sky-500/15 text-sky-700 dark:text-sky-400',
    },
    diterima: {
        label: 'Diterima · terkunci',
        tone: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    },
};

const PELUMAS_LABEL: Record<string, string> = {
    normal: 'Normal',
    rendah: 'Rendah',
    tinggi: 'Tinggi',
};
const PELUMAS_TONE: Record<string, string> = {
    normal: 'border-emerald-600 bg-emerald-600 text-white',
    rendah: 'border-amber-500 bg-amber-500 text-white',
    tinggi: 'border-rose-600 bg-rose-600 text-white',
};
const STATUS_MESIN_LABEL: Record<string, string> = {
    operasi: 'Operasi',
    standby: 'Standby',
    gangguan: 'Gangguan',
};
const STATUS_MESIN_TONE: Record<string, string> = {
    operasi: 'border-emerald-600 bg-emerald-600 text-white',
    standby: 'border-sky-600 bg-sky-600 text-white',
    gangguan: 'border-rose-600 bg-rose-600 text-white',
};

/** Shift tabs: Pagi kuning, Sore oranye, Malam biru gelap — the chosen one filled. */
const SHIFT_TONE: Record<string, { active: string; idle: string }> = {
    pagi: {
        active: 'border-amber-500 bg-amber-400 text-amber-950',
        idle: 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
    },
    sore: {
        active: 'border-orange-600 bg-orange-500 text-white',
        idle: 'border-orange-300 bg-orange-50 text-orange-800 hover:bg-orange-100 dark:border-orange-800 dark:bg-orange-950/40 dark:text-orange-300',
    },
    malam: {
        active: 'border-indigo-800 bg-indigo-700 text-white',
        idle: 'border-indigo-300 bg-indigo-50 text-indigo-800 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300',
    },
};

const DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

const dateLabel = (iso: string) => {
    const [y, m, d] = iso.split('-').map(Number);
    const date = new Date(y, m - 1, d);

    return `${DAYS[date.getDay()]}, ${d} ${MONTHS[m - 1]} ${y}`;
};

const timeOf = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleString('id-ID', {
              day: '2-digit',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '';

const nowHm = () => new Date().toTimeString().slice(0, 5);

/**
 * Lembar Mutasi Operator (logbook mutasi harian): the shift handover sheet of
 * a unit per date + shift. The handing-over regu fills machine fuel & lube,
 * tank level, equipment, feeder/start-stop events and faults, then signs
 * "Serahkan"; the receiving regu signs "Terima", which locks the sheet.
 */
export default function OperatorMutasiPage(props: Props) {
    const { filters, mutasi } = props;

    // Re-mount the form whenever another sheet (or a newer state of it) is loaded.
    return (
        <MutasiForm
            key={`${filters.unit_id}-${filters.tanggal}-${filters.shift}-${mutasi.id}-${mutasi.status}-${mutasi.diserahkan_at}`}
            {...props}
        />
    );
}

function MutasiForm({
    unit,
    filters,
    options,
    mutasi,
    riwayat,
    pending_receive = [],
    me,
    can_write,
    can_receive,
    leader_only = false,
    start_stop = [],
    patrol_findings = [],
}: Props) {
    const compact = useCompactLayout();
    // Phone: a sheet opens on its own screen (?form=1); without it the page is the list of sheets.
    const formOpen =
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('form') === '1';
    const [mesin, setMesin] = useState<Mesin[]>(() =>
        mutasi.mesin.map((m) => ({ ...m, level_bbm: numberOrNull(m.level_bbm), tambah_pelumas: numberOrNull(m.tambah_pelumas) })),
    );
    const [tangki, setTangki] = useState<Tangki[]>(
        mutasi.tangki.length ? mutasi.tangki : [{ nama: '1', level_cm: null }],
    );
    const [peralatan, setPeralatan] = useState<Alat[]>(mutasi.peralatan);
    const [kejadian, setKejadian] = useState<Kejadian[]>(mutasi.kejadian);
    const [gangguan, setGangguan] = useState(mutasi.gangguan_mesin ?? '');
    const [catatan, setCatatan] = useState(mutasi.catatan ?? '');
    const [reguPenyerah, setReguPenyerah] = useState(
        mutasi.regu_penyerah ?? me.regu ?? '',
    );
    const [penyerahNama, setPenyerahNama] = useState(
        mutasi.penyerah_nama ?? me.nama,
    );
    const [reguPenerima, setReguPenerima] = useState(
        me.regu && me.regu !== mutasi.regu_penyerah
            ? me.regu
            : (options.regu.find((r) => r !== mutasi.regu_penyerah) ?? ''),
    );
    const [penerimaNama, setPenerimaNama] = useState(me.nama);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const [exporting, setExporting] = useState(false);
    const locked = !can_write;
    const status = STATUS_LABEL[mutasi.status];
    const shiftInfo = options.shifts.find((s) => s.value === filters.shift);

    const touch = () => setDirty(true);

    const go = (query: Partial<Props['filters']> & { form?: 1 | null }) => {
        if (
            dirty &&
            !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')
        ) {
            return;
        }

        // The phone form screen stays open while another date / shift is picked.
        router.get(mutasiRoutes.index().url, { ...filters, ...(formOpen ? { form: 1 } : {}), ...query });
    };

    const setMesinField = (index: number, patch: Partial<Mesin>) => {
        setMesin((rows) =>
            rows.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );
        touch();
    };

    const save = (serahkan: boolean) => {
        setSaving(true);
        router.post(
            mutasiRoutes.store().url,
            {
                unit_id: filters.unit_id,
                tanggal: filters.tanggal,
                shift: filters.shift,
                mesin,
                tangki,
                peralatan,
                kejadian: kejadian.filter((k) => k.uraian.trim() !== ''),
                gangguan_mesin: gangguan,
                catatan,
                regu_penyerah: reguPenyerah || null,
                penyerah_nama: penyerahNama,
                serahkan: serahkan ? 1 : 0,
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onError: (errs) => setErrors(errs),
                onFinish: () => setSaving(false),
            },
        );
    };

    const terima = (targetId?: number, targetRegu?: string) => {
        const id = targetId ?? mutasi.id;
        const regu = targetRegu ?? reguPenerima;
        if (id === null || !regu) {
            return;
        }

        setSaving(true);
        router.post(
            mutasiRoutes.terima(id).url,
            {
                regu_penerima: regu,
                penerima_nama: penerimaNama,
            },
            {
                preserveScroll: true,
                onError: (errs) => setErrors(errs),
                onFinish: () => setSaving(false),
            },
        );
    };

    /** Excel of the saved sheet (same content as the PDF). */
    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadMutasiWorkbook(
                {
                    unit: unit.name,
                    hariTanggal: dateLabel(mutasi.tanggal),
                    shift: mutasi.shift,
                    shifts: options.shifts,
                    mesin: mutasi.mesin,
                    tangki: mutasi.tangki,
                    peralatan: mutasi.peralatan,
                    kejadian: mutasi.kejadian,
                    start_stop: start_stop,
                    patrol_findings: patrol_findings,
                    gangguan_mesin: mutasi.gangguan_mesin,
                    catatan: mutasi.catatan,
                    regu_penyerah: mutasi.regu_penyerah,
                    penyerah_nama: mutasi.penyerah_nama,
                    paraf_penyerah_url: mutasi.paraf_penyerah_url,
                    regu_penerima: mutasi.regu_penerima,
                    penerima_nama: mutasi.penerima_nama,
                    paraf_penerima_url: mutasi.paraf_penerima_url,
                },
                `Mutasi_Operator_${unit.name.replace(/\s+/g, '_')}_${mutasi.tanggal.replaceAll('-', '')}_${mutasi.shift}.xlsx`,
            );
        } finally {
            setExporting(false);
        }
    };

    const shiftRow = (sh: Props['options']['shifts'][number] | undefined) =>
        sh ? (
            <span className="flex items-center justify-between gap-2">
                <span>Pukul; {sh.jam.toLowerCase()}</span>
                <span className="font-bold">( {sh.value === filters.shift ? '✓' : '\u00a0\u00a0'} )</span>
            </span>
        ) : null;

    const canHandOver = !!reguPenyerah;

    if (compact && !formOpen) {
        // Phone (user, 2026-11-26): the unit's sheets as cards; Tambah opens the current shift's sheet,
        // a card opens that sheet, each on its own screen (?form=1); saving returns to this list.
        return (
            <>
                <Head title={`Lembar Mutasi Operator - ${unit.name}`} />
                {pending_receive.length > 0 && (
                    <div className="flex flex-col gap-2 px-4 pt-3">
                        {pending_receive.map((item) => {
                            const quickRegu =
                                me.regu && me.regu !== item.regu_penyerah
                                    ? me.regu
                                    : null;

                            return (
                                <div
                                    key={item.id}
                                    className="flex flex-col gap-2.5 rounded-2xl border border-sky-300 bg-sky-50/90 p-3.5 text-sky-950 shadow-xs dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100"
                                >
                                    <div className="flex items-start gap-2.5">
                                        <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-xl bg-sky-600/15 text-sky-700 dark:text-sky-300">
                                            <BellRing className="size-4" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-[13px] font-semibold">
                                                Serah terima menunggu penerimaan
                                            </p>
                                            <p className="text-[12px] opacity-85">
                                                {dateLabel(item.tanggal)} ·
                                                Shift{' '}
                                                <span className="capitalize">
                                                    {item.shift}
                                                </span>{' '}
                                                · dari Regu{' '}
                                                {item.regu_penyerah ?? '-'}
                                                {item.penyerah_nama
                                                    ? ` (${item.penyerah_nama})`
                                                    : ''}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="flex-1 bg-white/80 dark:bg-background"
                                            onClick={() =>
                                                go({
                                                    tanggal: item.tanggal,
                                                    shift: item.shift,
                                                    form: 1,
                                                })
                                            }
                                        >
                                            Lihat Lembar
                                        </Button>
                                        {quickRegu && (
                                            <Button
                                                size="sm"
                                                disabled={saving}
                                                className="flex-1 gap-1.5"
                                                onClick={() =>
                                                    terima(item.id, quickRegu)
                                                }
                                            >
                                                <CheckCircle2 className="size-4" />
                                                Terima Tugas
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
                <MobileEntryList
                    heading="Lembar mutasi"
                    count={riwayat.length}
                    empty="Belum ada lembar mutasi. Tekan Tambah untuk mengisi lembar shift Anda."
                    canWrite={can_write}
                    onAdd={() => router.get(mutasiRoutes.index().url, { unit_id: filters.unit_id, form: 1 })}
                    onSave={() => undefined}
                >
                    {riwayat.map((r) => (
                        <MobileEntryCard
                            key={r.id}
                            title={dateLabel(r.tanggal)}
                            subtitle={`Shift ${r.shift.charAt(0).toUpperCase()}${r.shift.slice(1)}`}
                            badge={
                                <EntryBadge tone={r.status === 'diterima' ? 'done' : r.status === 'diserahkan' ? 'info' : 'open'}>
                                    {r.status}
                                </EntryBadge>
                            }
                            meta={[
                                ['Penyerah', r.regu_penyerah ? `Regu ${r.regu_penyerah}` : ''],
                                ['Penerima', r.regu_penerima ? `Regu ${r.regu_penerima}` : ''],
                            ]}
                            onClick={() => go({ tanggal: r.tanggal, shift: r.shift, form: 1 })}
                        />
                    ))}
                </MobileEntryList>
            </>
        );
    }

    return (
        <>
            <Head title={`Lembar Mutasi Operator - ${unit.name}`} />
            <MutasiFrame
                phone={compact}
                className={cn(
                    'flex flex-1 flex-col gap-4 p-4 md:p-6',
                    compact && (can_write || can_receive) && 'pb-28',
                )}
                screen={{
                    title: `Mutasi ${dateLabel(filters.tanggal)}`,
                    subtitle: `Shift ${shiftInfo?.label ?? filters.shift} · ${status.label}`,
                    onBack: () => go({ form: null }),
                    canWrite: can_write,
                    onSave: () => save(false),
                    saving,
                    extraAction:
                        can_receive && mutasi.status === 'diserahkan' ? (
                            <Button
                                size="lg"
                                onClick={() => terima()}
                                disabled={saving || !reguPenerima || dirty}
                                className="gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700"
                            >
                                <CheckCircle2 className="size-4" />
                                Terima Tugas
                            </Button>
                        ) : can_write ? (
                            <Button
                                size="lg"
                                onClick={() => save(true)}
                                disabled={saving || !canHandOver}
                                className="gap-1.5"
                            >
                                <Send className="size-4" />
                                Serahkan
                            </Button>
                        ) : undefined,
                }}
            >
                <PageHeader
                    title="Lembar Mutasi Operator"
                    description={`Logbook serah terima tugas antar regu — ${unit.name}.`}
                    actions={
                        mutasi.id !== null ? (
                            compact ? (
                                <Button
                                    variant="outline"
                                    size="lg"
                                    onClick={() =>
                                        router.get(
                                            `/operator/mutasi/${mutasi.id}/preview`,
                                        )
                                    }
                                    className="w-full justify-center gap-2 border-rose-200 bg-rose-50/70 font-semibold text-rose-700 hover:bg-rose-100/80 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300"
                                >
                                    <FileDown className="size-4 text-rose-600 dark:text-rose-400" />
                                    Export PDF (Pratinjau)
                                </Button>
                            ) : (
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            router.get(
                                                `/operator/mutasi/${mutasi.id}/preview`,
                                            )
                                        }
                                        className="gap-1.5"
                                    >
                                        <FileDown className="size-4 text-rose-600" />
                                        Export PDF
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={exportExcel}
                                        disabled={exporting}
                                        className="gap-1.5"
                                        title={
                                            dirty
                                                ? 'Excel memakai data yang sudah tersimpan'
                                                : undefined
                                        }
                                    >
                                        <FileSpreadsheet className="size-4 text-emerald-600" />
                                        {exporting
                                            ? 'Menyiapkan…'
                                            : 'Export Excel'}
                                    </Button>
                                </div>
                            )
                        ) : undefined
                    }
                />

                {/* Date + shift */}
                <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-3 sm:flex-row sm:flex-wrap sm:items-end">
                    {options.units.length > 1 && (
                        <OperasiSelect
                            label="Unit"
                            value={String(filters.unit_id)}
                            onChange={(v) => go({ unit_id: Number(v) })}
                            options={options.units.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            }))}
                            className="w-full sm:w-52"
                        />
                    )}
                    <label className="flex flex-col gap-1 text-xs font-medium text-muted-foreground">
                        Tanggal
                        <Input
                            type="date"
                            value={filters.tanggal}
                            onChange={(e) =>
                                e.target.value &&
                                go({ tanggal: e.target.value })
                            }
                            className="h-10 w-full sm:w-44"
                        />
                    </label>
                    <div className="flex min-w-0 flex-col gap-1 text-xs font-medium text-muted-foreground">
                        Shift
                        <div className="grid grid-cols-3 gap-1.5">
                            {options.shifts.map((s) => (
                                <button
                                    key={s.value}
                                    type="button"
                                    onClick={() =>
                                        s.value !== filters.shift &&
                                        go({ shift: s.value })
                                    }
                                    className={cn(
                                        'flex min-w-0 flex-col items-center rounded-md border px-2 py-1.5 transition',
                                        filters.shift === s.value
                                            ? (SHIFT_TONE[s.value]?.active ?? 'bg-background text-foreground') + ' shadow-sm'
                                            : (SHIFT_TONE[s.value]?.idle ?? 'border-transparent text-muted-foreground'),
                                    )}
                                >
                                    <span className="text-sm font-semibold">
                                        {s.label}
                                    </span>
                                    <span className="truncate text-[10px]">
                                        {s.jam
                                            .replace(' WITA', '')
                                            .replace(' s/d ', '–')}
                                    </span>
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 sm:ml-auto">
                        <span
                            className={cn(
                                'rounded-full px-2.5 py-1 text-xs font-medium',
                                status.tone,
                            )}
                        >
                            {status.label}
                        </span>
                    </div>
                </div>

                <div className="rounded-lg border border-border bg-card px-4 py-3 text-sm">
                    <span className="font-semibold text-foreground">
                        {dateLabel(filters.tanggal)}
                    </span>
                    <span className="text-muted-foreground">
                        {' '}
                        · Shift {shiftInfo?.label} ({shiftInfo?.jam})
                    </span>
                    {mutasi.carried_from && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            Lembar baru — daftar tangki, peralatan & status
                            mesin diambil dari lembar {mutasi.carried_from}.
                        </p>
                    )}
                    {leader_only && (
                        <p className="mt-1 flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400">
                            <Lock className="size-3.5" />
                            Lembar mutasi diisi, diserahkan dan diterima oleh Leader Shift. Anda dapat melihatnya saja.
                        </p>
                    )}
                    {locked && mutasi.status === 'diterima' && (
                        <p className="mt-1 flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-400">
                            <Lock className="size-3.5" />
                            Sudah diterima regu {mutasi.regu_penerima} — lembar
                            terkunci.
                        </p>
                    )}
                </div>

                {can_receive && mutasi.status === 'diserahkan' && (
                    <div className="flex flex-col gap-3 rounded-xl border border-sky-300 bg-sky-50/90 p-3.5 text-sky-950 shadow-xs sm:flex-row sm:items-center sm:justify-between dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
                        <div className="flex items-start gap-2.5">
                            <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-sky-600/15 text-sky-700 dark:text-sky-300">
                                <BellRing className="size-4" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-sm font-semibold">
                                    Menunggu Penerimaan Serah Terima Tugas
                                </p>
                                <p className="text-xs opacity-85">
                                    Diserahkan oleh Regu{' '}
                                    {mutasi.regu_penyerah ?? '-'}
                                    {mutasi.penyerah_nama
                                        ? ` (${mutasi.penyerah_nama})`
                                        : ''}
                                    {mutasi.diserahkan_at
                                        ? ` · ${timeOf(mutasi.diserahkan_at)}`
                                        : ''}
                                    . Tanda tangan otomatis diambil dari profil
                                    Anda.
                                </p>
                            </div>
                        </div>
                        <Button
                            size="sm"
                            onClick={() => terima()}
                            disabled={saving || !reguPenerima || dirty}
                            className="w-full shrink-0 gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700 sm:w-auto"
                            title={
                                dirty
                                    ? 'Simpan perubahan dulu'
                                    : !reguPenerima
                                      ? 'Pilih regu penerima di bagian Serah Terima'
                                      : undefined
                            }
                        >
                            <CheckCircle2 className="size-4" />
                            {saving
                                ? 'Menerima…'
                                : reguPenerima
                                  ? `Terima Tugas (Regu ${reguPenerima})`
                                  : 'Terima Tugas'}
                        </Button>
                    </div>
                )}

                {compact ? (
                <>
                {/* 1. Mesin */}
                <Section
                    icon={<Fuel className="size-4" />}
                    title="Mesin"
                    hint="Level tangki bahan bakar (cm), tambah pelumas (liter), level pelumas mesin/RA dan status mesin."
                >
                    {mesin.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Belum ada mesin aktif pada unit ini.
                        </p>
                    )}
                    {compact ? (
                        <div className="flex flex-col gap-3">
                            {mesin.map((m, i) => (
                                <div
                                    key={`${m.machine_id}-${i}`}
                                    className="flex flex-col gap-3 rounded-lg border border-border p-3"
                                >
                                    <p className="font-semibold text-foreground">
                                        {m.nama}
                                    </p>
                                    <div className="grid grid-cols-2 gap-2">
                                        <FuelInput
                                            label="Level tangki BBM (cm)"
                                            unit="cm"
                                            value={m.level_bbm}
                                            onChange={(v) =>
                                                setMesinField(i, {
                                                    level_bbm: v,
                                                })
                                            }
                                            disabled={locked}
                                        />
                                        <FuelInput
                                            label="Tambah pelumas (L)"
                                            unit="Liter"
                                            value={m.tambah_pelumas ?? null}
                                            onChange={(v) =>
                                                setMesinField(i, {
                                                    tambah_pelumas: v,
                                                })
                                            }
                                            disabled={locked}
                                        />
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <span className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                            <Droplets className="size-3.5" />
                                            Level pelumas
                                        </span>
                                        <ChoiceChips
                                            options={options.pelumas}
                                            value={m.pelumas ?? ''}
                                            onChange={(v) =>
                                                setMesinField(i, {
                                                    pelumas: v || null,
                                                })
                                            }
                                            disabled={locked}
                                            labels={PELUMAS_LABEL}
                                            tone={(o) => PELUMAS_TONE[o]}
                                        />
                                    </div>
                                    <div className="flex flex-col gap-1.5">
                                        <span className="text-xs font-medium text-muted-foreground">
                                            Status mesin
                                        </span>
                                        <ChoiceChips
                                            options={options.status_mesin}
                                            value={m.status ?? ''}
                                            onChange={(v) =>
                                                setMesinField(i, {
                                                    status: v || null,
                                                })
                                            }
                                            disabled={locked}
                                            labels={STATUS_MESIN_LABEL}
                                            tone={(o) => STATUS_MESIN_TONE[o]}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        mesin.length > 0 && (
                            <div className="overflow-x-auto rounded-md border border-border">
                                <table className="w-full min-w-[760px] border-collapse text-sm">
                                    <thead className="bg-muted/60 text-xs">
                                        <tr>
                                            <th className="border border-border p-2 text-left">
                                                Mesin
                                            </th>
                                            <th className="w-40 border border-border p-2">
                                                Level tangki BBM (cm)
                                            </th>
                                            <th className="w-40 border border-border p-2">
                                                Tambah pelumas (L)
                                            </th>
                                            <th className="border border-border p-2">
                                                Level pelumas mesin/RA
                                            </th>
                                            <th className="border border-border p-2">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {mesin.map((m, i) => (
                                            <tr key={`${m.machine_id}-${i}`}>
                                                <td className="border border-border p-2 font-semibold">
                                                    {m.nama}
                                                </td>
                                                <td className="border border-border p-1.5">
                                                    <FuelInput
                                                        unit="cm"
                                                        value={m.level_bbm}
                                                        onChange={(v) =>
                                                            setMesinField(i, {
                                                                level_bbm: v,
                                                            })
                                                        }
                                                        disabled={locked}
                                                    />
                                                </td>
                                                <td className="border border-border p-1.5">
                                                    <FuelInput
                                                        unit="Liter"
                                                        value={m.tambah_pelumas ?? null}
                                                        onChange={(v) =>
                                                            setMesinField(i, {
                                                                tambah_pelumas: v,
                                                            })
                                                        }
                                                        disabled={locked}
                                                    />
                                                </td>
                                                <td className="border border-border p-1.5">
                                                    <ChoiceChips
                                                        options={
                                                            options.pelumas
                                                        }
                                                        value={m.pelumas ?? ''}
                                                        onChange={(v) =>
                                                            setMesinField(i, {
                                                                pelumas:
                                                                    v || null,
                                                            })
                                                        }
                                                        disabled={locked}
                                                        labels={PELUMAS_LABEL}
                                                        tone={(o) =>
                                                            PELUMAS_TONE[o]
                                                        }
                                                    />
                                                </td>
                                                <td className="border border-border p-1.5">
                                                    <ChoiceChips
                                                        options={
                                                            options.status_mesin
                                                        }
                                                        value={m.status ?? ''}
                                                        onChange={(v) =>
                                                            setMesinField(i, {
                                                                status:
                                                                    v || null,
                                                            })
                                                        }
                                                        disabled={locked}
                                                        labels={
                                                            STATUS_MESIN_LABEL
                                                        }
                                                        tone={(o) =>
                                                            STATUS_MESIN_TONE[o]
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )
                    )}
                </Section>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {/* 2. Tangki bulanan */}
                    <Section
                        icon={<Fuel className="size-4" />}
                        title="Tangki Bulanan (25 KL)"
                        hint="Nomor tangki dan level BBM dalam cm."
                    >
                        {tangki.map((t, i) => (
                            <div key={i} className="flex items-end gap-2">
                                <label className="flex w-24 flex-col gap-1 text-xs font-medium text-muted-foreground">
                                    Tangki
                                    <Input
                                        value={t.nama ?? ''}
                                        onChange={(e) => {
                                            setTangki((rows) =>
                                                rows.map((r, j) =>
                                                    j === i
                                                        ? {
                                                              ...r,
                                                              nama: e.target
                                                                  .value,
                                                          }
                                                        : r,
                                                ),
                                            );
                                            touch();
                                        }}
                                        disabled={locked}
                                        className="h-10"
                                    />
                                </label>
                                <label className="flex min-w-0 flex-1 flex-col gap-1 text-xs font-medium text-muted-foreground">
                                    Level BBM (cm)
                                    <Input
                                        inputMode="decimal"
                                        value={t.level_cm ?? ''}
                                        onChange={(e) => {
                                            setTangki((rows) =>
                                                rows.map((r, j) =>
                                                    j === i
                                                        ? {
                                                              ...r,
                                                              level_cm:
                                                                  e.target
                                                                      .value,
                                                          }
                                                        : r,
                                                ),
                                            );
                                            touch();
                                        }}
                                        disabled={locked}
                                        className="h-10"
                                        placeholder="mis. 120"
                                    />
                                </label>
                                {!locked && tangki.length > 1 && (
                                    <RemoveButton
                                        label="Hapus tangki"
                                        onClick={() => {
                                            setTangki((rows) =>
                                                rows.filter((_, j) => j !== i),
                                            );
                                            touch();
                                        }}
                                    />
                                )}
                            </div>
                        ))}
                        {!locked && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setTangki((rows) => [
                                        ...rows,
                                        {
                                            nama: String(rows.length + 1),
                                            level_cm: null,
                                        },
                                    ]);
                                    touch();
                                }}
                                className="w-fit gap-1"
                            >
                                <Plus className="size-3.5" />
                                Tambah tangki
                            </Button>
                        )}
                    </Section>

                    {/* 3. Lain-lain */}
                    <Section
                        icon={<Wrench className="size-4" />}
                        title="Lain-Lain (Peralatan)"
                        hint="Centang bila ada, lalu isi jumlahnya."
                    >
                        {peralatan.map((a, i) => (
                            <div key={i} className="flex items-center gap-2">
                                <Checkbox
                                    checked={a.ada}
                                    onCheckedChange={(v) => {
                                        setPeralatan((rows) =>
                                            rows.map((r, j) =>
                                                j === i
                                                    ? { ...r, ada: v === true }
                                                    : r,
                                            ),
                                        );
                                        touch();
                                    }}
                                    disabled={locked}
                                    className="size-5"
                                    aria-label={`Ada ${a.nama ?? ''}`}
                                />
                                <Input
                                    value={a.nama ?? ''}
                                    onChange={(e) => {
                                        setPeralatan((rows) =>
                                            rows.map((r, j) =>
                                                j === i
                                                    ? {
                                                          ...r,
                                                          nama: e.target.value,
                                                      }
                                                    : r,
                                            ),
                                        );
                                        touch();
                                    }}
                                    disabled={locked}
                                    placeholder="Nama peralatan"
                                    className="h-10 min-w-0 flex-1"
                                />
                                <Input
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    value={a.jumlah ?? ''}
                                    onChange={(e) => {
                                        setPeralatan((rows) =>
                                            rows.map((r, j) =>
                                                j === i
                                                    ? {
                                                          ...r,
                                                          jumlah:
                                                              e.target.value ===
                                                              ''
                                                                  ? null
                                                                  : Number(
                                                                        e.target
                                                                            .value,
                                                                    ),
                                                      }
                                                    : r,
                                            ),
                                        );
                                        touch();
                                    }}
                                    disabled={locked}
                                    placeholder="Jml"
                                    className="h-10 w-16 shrink-0"
                                    aria-label="Jumlah"
                                />
                                {!locked && (
                                    <RemoveButton
                                        label="Hapus peralatan"
                                        onClick={() => {
                                            setPeralatan((rows) =>
                                                rows.filter((_, j) => j !== i),
                                            );
                                            touch();
                                        }}
                                    />
                                )}
                            </div>
                        ))}
                        {!locked && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setPeralatan((rows) => [
                                        ...rows,
                                        { nama: '', ada: false, jumlah: null },
                                    ]);
                                    touch();
                                }}
                                className="w-fit gap-1"
                            >
                                <Plus className="size-3.5" />
                                Tambah peralatan
                            </Button>
                        )}
                    </Section>
                </div>

                {/* 4. Gangguan feeder / start-stop */}
                <Section
                    icon={<Clock className="size-4" />}
                    title="Gangguan Feeder / Start-Stop Mesin"
                    hint="Start/stop mesin diambil otomatis dari menu Start Stop Engine. Catat kejadian lain selama shift berikut jamnya (terima tugas, gangguan feeder, pompa, dll)."
                >
                    <StartStopList events={start_stop} />
                    {kejadian.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Belum ada kejadian dicatat.
                        </p>
                    )}
                    {kejadian.map((k, i) => (
                        <div key={i} className="flex items-start gap-2">
                            <Input
                                type="time"
                                value={k.jam ?? ''}
                                onChange={(e) => {
                                    setKejadian((rows) =>
                                        rows.map((r, j) =>
                                            j === i
                                                ? {
                                                      ...r,
                                                      jam:
                                                          e.target.value ||
                                                          null,
                                                  }
                                                : r,
                                        ),
                                    );
                                    touch();
                                }}
                                disabled={locked}
                                className="h-10 w-[6.5rem] shrink-0"
                                aria-label="Jam"
                            />
                            <Textarea
                                value={k.uraian}
                                onChange={(e) => {
                                    setKejadian((rows) =>
                                        rows.map((r, j) =>
                                            j === i
                                                ? {
                                                      ...r,
                                                      uraian: e.target.value,
                                                  }
                                                : r,
                                        ),
                                    );
                                    touch();
                                }}
                                disabled={locked}
                                rows={compact ? 2 : 1}
                                placeholder="Uraian kejadian"
                                className="min-h-10 min-w-0 flex-1"
                            />
                            {!locked && (
                                <RemoveButton
                                    label="Hapus kejadian"
                                    onClick={() => {
                                        setKejadian((rows) =>
                                            rows.filter((_, j) => j !== i),
                                        );
                                        touch();
                                    }}
                                />
                            )}
                        </div>
                    ))}
                    {errors['kejadian.0.jam'] && (
                        <p className="text-sm text-destructive">
                            {errors['kejadian.0.jam']}
                        </p>
                    )}
                    {!locked && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                setKejadian((rows) => [
                                    ...rows,
                                    { jam: nowHm(), uraian: '' },
                                ]);
                                touch();
                            }}
                            className="w-fit gap-1"
                        >
                            <Plus className="size-3.5" />
                            Tambah kejadian (jam sekarang)
                        </Button>
                    )}
                </Section>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {/* 5. Gangguan mesin */}
                    <Section
                        icon={<AlertTriangle className="size-4" />}
                        title="Gangguan Mesin"
                        hint="Temuan abnormal (TN) Patrol Check Mesin pada shift ini ikut diserahterimakan otomatis. Mesin operasi / standby diambil dari status mesin di atas."
                    >
                        <PatrolFindingList findings={patrol_findings} />
                        <Textarea
                            value={gangguan}
                            onChange={(e) => {
                                setGangguan(e.target.value);
                                touch();
                            }}
                            disabled={locked}
                            rows={3}
                            placeholder="mis. CM 7 kebocoran pada sisi lube hose"
                        />
                        <div className="grid grid-cols-2 gap-2 text-sm">
                            <MachineList
                                title="Mesin Operasi"
                                names={mesin
                                    .filter((m) => m.status === 'operasi')
                                    .map((m) => m.nama)}
                                tone="text-emerald-700 dark:text-emerald-400"
                            />
                            <MachineList
                                title="Mesin Standby"
                                names={mesin
                                    .filter((m) => m.status === 'standby')
                                    .map((m) => m.nama)}
                                tone="text-sky-700 dark:text-sky-400"
                            />
                        </div>
                    </Section>

                    {/* 6. Catatan */}
                    <Section
                        icon={<NotebookPen className="size-4" />}
                        title="Catatan"
                        hint="Catatan tambahan untuk regu berikutnya."
                    >
                        <Textarea
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                touch();
                            }}
                            disabled={locked}
                            rows={5}
                        />
                    </Section>
                </div>

                </>
                ) : (
                <>
                {/* Desktop: the paper form's layout, filled straight in its cells. */}
                <div className="overflow-x-auto rounded-md border border-border bg-white p-4 text-black dark:bg-card dark:text-foreground">
                    <div className="min-w-[1100px]">
                        <div className="mb-3 grid grid-cols-[160px_1fr_160px] items-center gap-3">
                            <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-10 w-auto object-contain" />
                            <h2 className="text-center text-lg font-bold tracking-wide uppercase">Lembar Mutasi Operator {unit.name}</h2>
                            <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="ml-auto h-10 w-auto object-contain" />
                        </div>

                        {/* Mesin · tangki · lain-lain, with Catatan on the right */}
                        <div className="grid grid-cols-[1fr_260px] border-2 border-black dark:border-white">
                            <div className="border-r-2 border-black dark:border-white">
                                <table className="w-full border-collapse text-xs">
                                    <thead>
                                        <tr>
                                            <th rowSpan={2} className={cn(paperTh, 'w-44')}>MESIN</th>
                                            <th rowSpan={2} className={paperTh}>Level tangki bahan bakar (cm)</th>
                                            <th rowSpan={2} className={paperTh}>Tambah pelumas (Liter)</th>
                                            <th colSpan={options.pelumas.length} className={paperTh}>Level pelumas Mesin/RA (✓)</th>
                                        </tr>
                                        <tr>
                                            {options.pelumas.map((level) => (
                                                <th key={level} className={cn(paperTh, 'w-20 uppercase')}>{PELUMAS_LABEL[level] ?? level}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {mesin.length === 0 && (
                                            <tr>
                                                <td colSpan={3 + options.pelumas.length} className={cn(paperTd, 'p-3 text-center text-muted-foreground')}>Belum ada mesin aktif pada unit ini.</td>
                                            </tr>
                                        )}
                                        {mesin.map((m, i) => (
                                            <tr key={`${m.machine_id}-${i}`}>
                                                <td className={cn(paperTd, 'px-2 font-semibold uppercase')}>{m.nama}</td>
                                                <td className={paperTd}>
                                                    <PaperFuel unit="cm" value={m.level_bbm} onChange={(v) => setMesinField(i, { level_bbm: v })} disabled={locked} />
                                                </td>
                                                <td className={paperTd}>
                                                    <PaperFuel unit="Liter" value={m.tambah_pelumas ?? null} onChange={(v) => setMesinField(i, { tambah_pelumas: v })} disabled={locked} />
                                                </td>
                                                {options.pelumas.map((level) => (
                                                    <td key={level} className={paperTd}>
                                                        <PaperTick
                                                            checked={m.pelumas === level}
                                                            onClick={() => setMesinField(i, { pelumas: m.pelumas === level ? null : level })}
                                                            disabled={locked}
                                                            label={`Pelumas ${PELUMAS_LABEL[level] ?? level} ${m.nama}`}
                                                        />
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>

                                <div className="grid grid-cols-[1fr_1.4fr] border-t-2 border-black dark:border-white">
                                    <table className="w-full border-collapse border-r-2 border-black text-xs dark:border-white">
                                        <thead>
                                            <tr>
                                                <th className={paperTh}>Tangki Bulanan (25KL)</th>
                                                <th className={paperTh}>Level BBM (CM)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {tangki.map((t, i) => (
                                                <tr key={i}>
                                                    <td className={paperTd}>
                                                        <input
                                                            value={t.nama ?? ''}
                                                            onChange={(e) => {
                                                                setTangki((rows) => rows.map((r, j) => (j === i ? { ...r, nama: e.target.value } : r)));
                                                                touch();
                                                            }}
                                                            disabled={locked}
                                                            className={cn(paperInput, 'text-lg font-bold')}
                                                            aria-label="Tangki"
                                                        />
                                                    </td>
                                                    <td className={paperTd}>
                                                        <div className="flex items-center">
                                                            <input
                                                                inputMode="decimal"
                                                                value={t.level_cm ?? ''}
                                                                onChange={(e) => {
                                                                    setTangki((rows) => rows.map((r, j) => (j === i ? { ...r, level_cm: e.target.value } : r)));
                                                                    touch();
                                                                }}
                                                                disabled={locked}
                                                                placeholder="cm"
                                                                className={cn(paperInput, 'text-lg')}
                                                                aria-label="Level BBM (cm)"
                                                            />
                                                            {!locked && tangki.length > 1 && (
                                                                <PaperRemove
                                                                    label="Hapus tangki"
                                                                    onClick={() => {
                                                                        setTangki((rows) => rows.filter((_, j) => j !== i));
                                                                        touch();
                                                                    }}
                                                                />
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                            {!locked && (
                                                <tr>
                                                    <td colSpan={2} className={paperTd}>
                                                        <PaperAdd
                                                            label="Tambah tangki"
                                                            onClick={() => {
                                                                setTangki((rows) => [...rows, { nama: String(rows.length + 1), level_cm: null }]);
                                                                touch();
                                                            }}
                                                        />
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>

                                    <table className="w-full border-collapse text-xs">
                                        <thead>
                                            <tr>
                                                <th className={cn(paperTh, 'text-left')}>Lain-Lain :</th>
                                                <th className={cn(paperTh, 'w-16')}>ada (✓)</th>
                                                <th className={cn(paperTh, 'w-20')}>jumlah</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {peralatan.map((a, i) => (
                                                <tr key={i}>
                                                    <td className={paperTd}>
                                                        <div className="flex items-center gap-1 pl-1.5">
                                                            <span className="tabular-nums">{i + 1},</span>
                                                            <input
                                                                value={a.nama ?? ''}
                                                                onChange={(e) => {
                                                                    setPeralatan((rows) => rows.map((r, j) => (j === i ? { ...r, nama: e.target.value } : r)));
                                                                    touch();
                                                                }}
                                                                disabled={locked}
                                                                placeholder="Nama peralatan"
                                                                className={cn(paperInput, 'text-left')}
                                                            />
                                                            {!locked && (
                                                                <PaperRemove
                                                                    label="Hapus peralatan"
                                                                    onClick={() => {
                                                                        setPeralatan((rows) => rows.filter((_, j) => j !== i));
                                                                        touch();
                                                                    }}
                                                                />
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className={paperTd}>
                                                        <PaperTick
                                                            checked={a.ada}
                                                            onClick={() => {
                                                                setPeralatan((rows) => rows.map((r, j) => (j === i ? { ...r, ada: !r.ada } : r)));
                                                                touch();
                                                            }}
                                                            disabled={locked}
                                                            label={`Ada ${a.nama ?? ''}`}
                                                        />
                                                    </td>
                                                    <td className={paperTd}>
                                                        <input
                                                            type="number"
                                                            inputMode="numeric"
                                                            min={0}
                                                            value={a.jumlah ?? ''}
                                                            onChange={(e) => {
                                                                setPeralatan((rows) =>
                                                                    rows.map((r, j) => (j === i ? { ...r, jumlah: e.target.value === '' ? null : Number(e.target.value) } : r)),
                                                                );
                                                                touch();
                                                            }}
                                                            disabled={locked}
                                                            className={paperInput}
                                                            aria-label="Jumlah"
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                            {!locked && (
                                                <tr>
                                                    <td colSpan={3} className={paperTd}>
                                                        <PaperAdd
                                                            label="Tambah peralatan"
                                                            onClick={() => {
                                                                setPeralatan((rows) => [...rows, { nama: '', ada: false, jumlah: null }]);
                                                                touch();
                                                            }}
                                                        />
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div className="flex flex-col">
                                <div className={cn(paperTh, 'border-0 border-b border-black')}>Catatan</div>
                                <textarea
                                    value={catatan}
                                    onChange={(e) => {
                                        setCatatan(e.target.value);
                                        touch();
                                    }}
                                    disabled={locked}
                                    className="min-h-40 flex-1 resize-none bg-[repeating-linear-gradient(transparent,transparent_27px,#94a3b8_28px)] bg-transparent p-2 text-xs leading-7 outline-none focus:bg-yellow-50/60 dark:focus:bg-yellow-950/30"
                                    aria-label="Catatan"
                                />
                            </div>
                        </div>

                        {/* Gangguan feeder / start-stop */}
                        <div className="mt-3 border-2 border-black dark:border-white">
                            <div className={cn(paperTh, 'border-0 border-b border-black text-left')}>Gangguan feeder/star-stop Mesin ;</div>
                            <table className="w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th className={cn(paperTh, 'w-24')}>Jam</th>
                                        <th className={cn(paperTh, 'text-left')}>Uraian</th>
                                        {!locked && <th className={cn(paperTh, 'w-9')} />}
                                    </tr>
                                </thead>
                                <tbody>
                                    {start_stop.map((e, i) => (
                                        <tr key={`ss-${i}`} className="bg-sky-50/60 dark:bg-sky-950/30">
                                            <td className={cn(paperTd, 'text-center font-semibold tabular-nums')}>{e.jam}</td>
                                            <td className={cn(paperTd, 'px-2')} colSpan={locked ? 1 : 2}>
                                                {e.uraian}
                                                <span className="ml-1 text-[10px] text-muted-foreground">(Start Stop Engine)</span>
                                            </td>
                                        </tr>
                                    ))}
                                    {kejadian.length === 0 && start_stop.length === 0 && (
                                        <tr>
                                            <td colSpan={locked ? 2 : 3} className={cn(paperTd, 'p-3 text-center text-muted-foreground')}>Belum ada kejadian dicatat.</td>
                                        </tr>
                                    )}
                                    {kejadian.map((k, i) => (
                                        <tr key={i}>
                                            <td className={cn(paperTd, errors[`kejadian.${i}.jam`] && 'bg-destructive/10')} title={errors[`kejadian.${i}.jam`]}>
                                                <input
                                                    type="time"
                                                    value={k.jam ?? ''}
                                                    onChange={(e) => {
                                                        setKejadian((rows) => rows.map((r, j) => (j === i ? { ...r, jam: e.target.value || null } : r)));
                                                        touch();
                                                    }}
                                                    disabled={locked}
                                                    className={paperInput}
                                                    aria-label="Jam"
                                                />
                                            </td>
                                            <td className={paperTd}>
                                                <input
                                                    value={k.uraian}
                                                    onChange={(e) => {
                                                        setKejadian((rows) => rows.map((r, j) => (j === i ? { ...r, uraian: e.target.value } : r)));
                                                        touch();
                                                    }}
                                                    disabled={locked}
                                                    placeholder="Uraian kejadian (terima tugas, start/stop mesin, pompa, …)"
                                                    className={cn(paperInput, 'text-left')}
                                                    aria-label="Uraian kejadian"
                                                />
                                            </td>
                                            {!locked && (
                                                <td className={cn(paperTd, 'text-center')}>
                                                    <PaperRemove
                                                        label="Hapus kejadian"
                                                        onClick={() => {
                                                            setKejadian((rows) => rows.filter((_, j) => j !== i));
                                                            touch();
                                                        }}
                                                    />
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                    {!locked && (
                                        <tr>
                                            <td colSpan={3} className={paperTd}>
                                                <PaperAdd
                                                    label="Tambah baris (jam sekarang)"
                                                    onClick={() => {
                                                        setKejadian((rows) => [...rows, { jam: nowHm(), uraian: '' }]);
                                                        touch();
                                                    }}
                                                />
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Gangguan mesin · mesin operasi / standby */}
                        <div className="mt-3 grid grid-cols-[1fr_420px] border-2 border-black dark:border-white">
                            <div className="flex flex-col border-r-2 border-black dark:border-white">
                                <div className={cn(paperTh, 'border-0 border-b border-black text-left')}>Gangguan Mesin ;</div>
                                {patrol_findings.length > 0 && (
                                    <div className="border-b border-black/40 bg-amber-50 p-2 text-xs dark:bg-amber-950/30">
                                        {patrol_findings.map((f, i) => (
                                            <p key={i}>
                                                ⚠ {f.jam} <b>{f.mesin}</b> — {f.peralatan}: {f.catatan}{' '}
                                                <span className="text-muted-foreground">(Patrol Check, {f.operator})</span>
                                            </p>
                                        ))}
                                    </div>
                                )}
                                <textarea
                                    value={gangguan}
                                    onChange={(e) => {
                                        setGangguan(e.target.value);
                                        touch();
                                    }}
                                    disabled={locked}
                                    placeholder="mis. CM 7 kebocoran pada sisi lube hose"
                                    className="min-h-28 flex-1 resize-none bg-[repeating-linear-gradient(transparent,transparent_27px,#94a3b8_28px)] bg-transparent p-2 text-xs leading-7 outline-none focus:bg-yellow-50/60 dark:focus:bg-yellow-950/30"
                                    aria-label="Gangguan mesin"
                                />
                            </div>
                            <table className="w-full border-collapse text-xs">
                                <thead>
                                    <tr>
                                        <th className={cn(paperTh, 'text-left')}>Mesin</th>
                                        {options.status_mesin.map((st) => (
                                            <th key={st} className={cn(paperTh, 'w-24')}>Mesin {STATUS_MESIN_LABEL[st] ?? st}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {mesin.map((m, i) => (
                                        <tr key={`${m.machine_id}-${i}`}>
                                            <td className={cn(paperTd, 'px-2')}>
                                                {i + 1}, <span className="font-semibold uppercase">{m.nama}</span>
                                            </td>
                                            {options.status_mesin.map((st) => (
                                                <td key={st} className={paperTd}>
                                                    <PaperTick
                                                        checked={m.status === st}
                                                        onClick={() => setMesinField(i, { status: m.status === st ? null : st })}
                                                        disabled={locked}
                                                        label={`${STATUS_MESIN_LABEL[st] ?? st} ${m.nama}`}
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Regu penyerah · tanggal & pukul · regu penerima (paraf di bagian Serah Terima) */}
                        <table className="mt-3 w-full border-collapse border-2 border-black text-xs dark:border-white">
                            <thead>
                                <tr>
                                    <th colSpan={2} className={paperTh}>REGU PENYERAH</th>
                                    <th className={cn(paperTh, 'text-left')}>Tanggal : {dateLabel(filters.tanggal)}</th>
                                    <th colSpan={2} className={paperTh}>REGU PENERIMA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td rowSpan={options.shifts.length} className={cn(paperTd, 'w-28 text-center text-2xl font-bold')}>{reguPenyerah || '–'}</td>
                                    <td rowSpan={options.shifts.length} className={cn(paperTd, 'w-40 text-center')}>
                                        {mutasi.paraf_penyerah_url ? <img src={mutasi.paraf_penyerah_url} alt="Paraf penyerah" className="mx-auto h-12 object-contain" /> : <span className="text-muted-foreground">Paraf</span>}
                                    </td>
                                    <td className={cn(paperTd, 'px-2')}>{shiftRow(options.shifts[0])}</td>
                                    <td rowSpan={options.shifts.length} className={cn(paperTd, 'w-28 text-center text-2xl font-bold')}>{mutasi.regu_penerima || '–'}</td>
                                    <td rowSpan={options.shifts.length} className={cn(paperTd, 'w-40 text-center')}>
                                        {mutasi.paraf_penerima_url ? <img src={mutasi.paraf_penerima_url} alt="Paraf penerima" className="mx-auto h-12 object-contain" /> : <span className="text-muted-foreground">Paraf</span>}
                                    </td>
                                </tr>
                                {options.shifts.slice(1).map((sh) => (
                                    <tr key={sh.value}>
                                        <td className={cn(paperTd, 'px-2')}>{shiftRow(sh)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                </>
                )}

                {/* 7. Serah terima */}
                <Section
                    icon={<Handshake className="size-4" />}
                    title="Serah Terima Tugas"
                    hint="Tanda tangan otomatis diambil dari data tanda tangan profil. Regu penyerah klik Serahkan, regu penerima cukup klik Terima Tugas."
                >
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div className="flex flex-col gap-3 rounded-lg border border-border p-3">
                            <p className="text-sm font-semibold text-foreground">
                                Regu Penyerah
                            </p>
                            <ChoiceChips
                                options={options.regu}
                                value={reguPenyerah}
                                onChange={(v) => {
                                    setReguPenyerah(v);
                                    touch();
                                }}
                                disabled={locked}
                                labels={Object.fromEntries(
                                    options.regu.map((r) => [r, `Regu ${r}`]),
                                )}
                            />
                            {errors.regu_penyerah && (
                                <p className="text-sm text-destructive">
                                    {errors.regu_penyerah}
                                </p>
                            )}
                            <Input
                                value={penyerahNama}
                                onChange={(e) => {
                                    setPenyerahNama(e.target.value);
                                    touch();
                                }}
                                disabled={locked}
                                placeholder="Nama operator"
                                className="h-10"
                            />
                            {mutasi.diserahkan_at ? (
                                mutasi.paraf_penyerah_url ? (
                                    <Signed
                                        url={mutasi.paraf_penyerah_url}
                                        caption={`Diserahkan ${timeOf(mutasi.diserahkan_at)}`}
                                    />
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        Diserahkan {timeOf(mutasi.diserahkan_at)}
                                    </p>
                                )
                            ) : (
                                !locked && (
                                    <ProfileSignaturePreview
                                        signatureUrl={me.signature_url}
                                        hasSignature={me.has_signature}
                                    />
                                )
                            )}
                        </div>

                        <div className="flex flex-col gap-3 rounded-lg border border-border p-3">
                            <p className="text-sm font-semibold text-foreground">
                                Regu Penerima
                            </p>
                            {mutasi.status === 'diterima' ? (
                                <>
                                    <p className="text-sm">
                                        Regu{' '}
                                        <span className="font-semibold">
                                            {mutasi.regu_penerima}
                                        </span>
                                        {mutasi.penerima_nama
                                            ? ` · ${mutasi.penerima_nama}`
                                            : ''}
                                    </p>
                                    {mutasi.paraf_penerima_url ? (
                                        <Signed
                                            url={mutasi.paraf_penerima_url}
                                            caption={`Diterima ${timeOf(mutasi.diterima_at)}`}
                                        />
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            Diterima {timeOf(mutasi.diterima_at)}
                                        </p>
                                    )}
                                </>
                            ) : can_receive ? (
                                <>
                                    <ChoiceChips
                                        options={options.regu.filter(
                                            (r) => r !== mutasi.regu_penyerah,
                                        )}
                                        value={reguPenerima}
                                        onChange={setReguPenerima}
                                        labels={Object.fromEntries(
                                            options.regu.map((r) => [
                                                r,
                                                `Regu ${r}`,
                                            ]),
                                        )}
                                    />
                                    {errors.regu_penerima && (
                                        <p className="text-sm text-destructive">
                                            {errors.regu_penerima}
                                        </p>
                                    )}
                                    <Input
                                        value={penerimaNama}
                                        onChange={(e) =>
                                            setPenerimaNama(e.target.value)
                                        }
                                        placeholder="Nama operator penerima"
                                        className="h-10"
                                    />
                                    <ProfileSignaturePreview
                                        signatureUrl={me.signature_url}
                                        hasSignature={me.has_signature}
                                    />
                                    <Button
                                        onClick={() => terima()}
                                        disabled={
                                            saving || !reguPenerima || dirty
                                        }
                                        className="gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700"
                                        title={
                                            dirty
                                                ? 'Simpan perubahan dulu'
                                                : undefined
                                        }
                                    >
                                        <CheckCircle2 className="size-4" />
                                        Terima Tugas
                                    </Button>
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {mutasi.status === 'diserahkan'
                                        ? 'Menunggu regu penerima mengonfirmasi penerimaan tugas.'
                                        : 'Dapat diterima setelah regu penyerah menyerahkan lembar ini.'}
                                </p>
                            )}
                        </div>
                    </div>
                </Section>

                {can_write && !compact && (
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button
                            variant="outline"
                            onClick={() => save(false)}
                            disabled={saving}
                            className="gap-1.5"
                        >
                            <Save className="size-4" />
                            Simpan Draft
                        </Button>
                        <Button
                            onClick={() => save(true)}
                            disabled={saving || !canHandOver}
                            className="gap-1.5"
                            title={
                                canHandOver
                                    ? undefined
                                    : 'Pilih regu penyerah terlebih dahulu'
                            }
                        >
                            <Send className="size-4" />
                            {mutasi.status === 'diserahkan'
                                ? 'Simpan & Serahkan Ulang'
                                : 'Serahkan Tugas'}
                        </Button>
                    </div>
                )}

                {!compact && riwayat.length > 0 && (
                    <Section
                        icon={<History className="size-4" />}
                        title="Riwayat Lembar"
                        hint="Lembar mutasi terakhir unit ini."
                    >
                        <div className="flex flex-col gap-1.5">
                            {riwayat.map((r) => (
                                <button
                                    key={r.id}
                                    type="button"
                                    onClick={() =>
                                        go({
                                            tanggal: r.tanggal,
                                            shift: r.shift,
                                        })
                                    }
                                    className={cn(
                                        'flex items-center gap-2 rounded-md border px-3 py-2 text-left text-sm transition hover:bg-muted',
                                        r.tanggal === filters.tanggal &&
                                            r.shift === filters.shift
                                            ? 'border-primary bg-primary/5'
                                            : 'border-border',
                                    )}
                                >
                                    <span className="min-w-0 flex-1 truncate">
                                        {dateLabel(r.tanggal)} ·{' '}
                                        <span className="capitalize">
                                            {r.shift}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {' '}
                                            · {r.regu_penyerah ?? '-'} →{' '}
                                            {r.regu_penerima ?? '-'}
                                        </span>
                                    </span>
                                    <span
                                        className={cn(
                                            'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium',
                                            STATUS_LABEL[r.status].tone,
                                        )}
                                    >
                                        {r.status}
                                    </span>
                                </button>
                            ))}
                        </div>
                    </Section>
                )}
            </MutasiFrame>

        </>
    );
}

/** The paper form's grid cells (black lines, yellow headers like the logsheet). */
const paperTh = 'border border-black bg-yellow-200 px-1.5 py-1 text-center text-[11px] font-bold text-black dark:border-white dark:bg-yellow-800 dark:text-yellow-50';
const paperTd = 'h-8 border border-black p-0 dark:border-white/60';
const paperInput =
    'h-8 w-full min-w-0 bg-transparent px-1.5 text-center text-xs outline-none focus:bg-yellow-50 focus:ring-0 disabled:cursor-not-allowed dark:focus:bg-yellow-950/40';

/** A cell ticked (✓) with one click, like the boxes on the paper form. */
function PaperTick({ checked, onClick, disabled, label }: { checked: boolean; onClick: () => void; disabled: boolean; label: string }) {
    return (
        <button
            type="button"
            role="checkbox"
            aria-checked={checked}
            aria-label={label}
            onClick={onClick}
            disabled={disabled}
            className={cn(
                'flex h-8 w-full items-center justify-center text-base font-bold transition hover:bg-yellow-50 disabled:cursor-not-allowed dark:hover:bg-yellow-950/40',
                checked ? 'text-emerald-700 dark:text-emerald-400' : 'text-transparent hover:text-muted-foreground/40',
            )}
        >
            ✓
        </button>
    );
}

/** A number typed in the cell (cm or liter) — no ✓ any more (user, 2026-11-24). */
function PaperFuel({ value, onChange, disabled, unit }: { value: string | null; onChange: (value: string | null) => void; disabled: boolean; unit: string }) {
    return (
        <input
            inputMode="decimal"
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value.replace(',', '.').replace(/[^0-9.]/g, '') || null)}
            disabled={disabled}
            placeholder={unit}
            className={paperInput}
            aria-label={unit}
        />
    );
}

function PaperAdd({ label, onClick }: { label: string; onClick: () => void }) {
    return (
        <button type="button" onClick={onClick} className="flex h-8 w-full items-center gap-1 px-2 text-xs text-primary hover:bg-muted/60">
            <Plus className="size-3.5" />
            {label}
        </button>
    );
}

function PaperRemove({ label, onClick }: { label: string; onClick: () => void }) {
    return (
        <button type="button" onClick={onClick} title={label} aria-label={label} className="flex size-7 shrink-0 items-center justify-center text-muted-foreground hover:text-destructive">
            <X className="size-3.5" />
        </button>
    );
}

function Section({
    icon,
    title,
    hint,
    children,
}: {
    icon: ReactNode;
    title: string;
    hint: string;
    children: ReactNode;
}) {
    return (
        <section className="flex min-w-0 flex-col gap-3 rounded-lg border border-border bg-card p-4">
            <div>
                <h2 className="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                    <span className="text-primary">{icon}</span>
                    {title}
                </h2>
                <p className="text-xs text-muted-foreground">{hint}</p>
            </div>
            {children}
        </section>
    );
}

/** A number (cm or liter) — the ✓ button was dropped (user, 2026-11-24). */
function FuelInput({
    label,
    unit,
    value,
    onChange,
    disabled,
}: {
    label?: string;
    unit: string;
    value: string | null;
    onChange: (value: string | null) => void;
    disabled: boolean;
}) {
    return (
        <div className="flex min-w-0 flex-col gap-1">
            {label && (
                <span className="text-xs font-medium text-muted-foreground">
                    {label}
                </span>
            )}
            <Input
                inputMode="decimal"
                value={value ?? ''}
                onChange={(e) =>
                    onChange(
                        e.target.value
                            .replace(',', '.')
                            .replace(/[^0-9.]/g, '') || null,
                    )
                }
                disabled={disabled}
                placeholder={unit}
                aria-label={label ?? unit}
                className="h-10 min-w-0"
            />
        </div>
    );
}

/** Abnormal (TN) items of the shift's Patrol Check Mesin — handed over with the sheet (edit them in Patrol Check). */
function PatrolFindingList({ findings }: { findings: PatrolFinding[] }) {
    if (findings.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-1.5 rounded-md border border-amber-500/40 bg-amber-500/10 p-2">
            <p className="flex items-center gap-1.5 text-xs font-medium text-amber-800 dark:text-amber-300">
                <AlertTriangle className="size-3.5" />
                Abnormal dari Patrol Check ({findings.length})
            </p>
            {findings.map((f, i) => (
                <div key={i} className="text-sm">
                    <span className="font-semibold tabular-nums">{f.jam}</span>{' '}
                    <span className="font-medium">{f.mesin}</span> — {f.peralatan}
                    {f.catatan && <span>: {f.catatan}</span>}
                    <span className="block text-xs text-muted-foreground">
                        {f.system} · dicek {f.operator}
                    </span>
                </div>
            ))}
        </div>
    );
}

/** The shift's start / stop from Start Stop Engine — read-only (edit them in that menu). */
function StartStopList({ events }: { events: StartStopEvent[] }) {
    return (
        <div className="flex flex-col gap-1.5 rounded-md border border-sky-500/30 bg-sky-500/5 p-2">
            <p className="flex items-center gap-1.5 text-xs font-medium text-sky-800 dark:text-sky-300">
                <Power className="size-3.5" />
                Dari Start Stop Engine
            </p>
            {events.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    Belum ada start/stop mesin pada shift ini.
                </p>
            ) : (
                events.map((e, i) => (
                    <div key={i} className="flex items-start gap-2 text-sm">
                        <span className="w-12 shrink-0 font-semibold tabular-nums">
                            {e.jam}
                        </span>
                        <span
                            className={cn(
                                'min-w-0',
                                e.jenis === 'stop' && 'text-rose-700 dark:text-rose-300',
                            )}
                        >
                            {e.uraian}
                        </span>
                    </div>
                ))
            )}
        </div>
    );
}

function MachineList({
    title,
    names,
    tone,
}: {
    title: string;
    names: string[];
    tone: string;
}) {
    return (
        <div className="rounded-md border border-border p-2">
            <p className="text-xs font-medium text-muted-foreground">{title}</p>
            {names.length === 0 ? (
                <p className="text-xs text-muted-foreground">—</p>
            ) : (
                names.map((n) => (
                    <p key={n} className={cn('text-sm font-medium', tone)}>
                        {n}
                    </p>
                ))
            )}
        </div>
    );
}

function ProfileSignaturePreview({
    signatureUrl,
    hasSignature,
}: {
    signatureUrl?: string | null;
    hasSignature?: boolean;
}) {
    if (hasSignature && signatureUrl) {
        return (
            <div className="flex items-center gap-3 rounded-lg border border-border/80 bg-muted/30 p-2.5">
                <div className="flex h-12 w-28 shrink-0 items-center justify-center rounded border border-border bg-white p-1">
                    <img
                        src={signatureUrl}
                        alt="Tanda tangan profil"
                        className="max-h-10 max-w-[6.5rem] object-contain"
                    />
                </div>
                <p className="text-xs text-muted-foreground">
                    Tanda tangan otomatis diambil dari data tanda tangan profil
                    Anda.
                </p>
            </div>
        );
    }

    return (
        <div className="flex items-start gap-2 rounded-lg border border-dashed border-amber-400/70 bg-amber-50/70 p-2.5 text-xs text-amber-900 dark:border-amber-700/70 dark:bg-amber-950/30 dark:text-amber-200">
            <PenLine className="mt-0.5 size-3.5 shrink-0" />
            <span>
                Tanda tangan profil belum diatur.{' '}
                <Link
                    href="/settings/profile"
                    className="font-semibold underline underline-offset-2"
                >
                    Atur di Profil & Pengaturan
                </Link>{' '}
                agar otomatis tampil saat serah terima.
            </span>
        </div>
    );
}

function Signed({ url, caption }: { url: string; caption: string }) {
    return (
        <div className="flex items-center gap-3">
            <div className="flex h-14 w-32 items-center justify-center rounded border border-border bg-white">
                <img
                    src={url}
                    alt="Paraf"
                    className="max-h-12 max-w-[7.5rem] object-contain"
                />
            </div>
            <span className="text-xs text-muted-foreground">{caption}</span>
        </div>
    );
}

function RemoveButton({
    label,
    onClick,
}: {
    label: string;
    onClick: () => void;
}) {
    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={onClick}
            className="size-10 shrink-0 text-muted-foreground hover:text-destructive"
            aria-label={label}
            title={label}
        >
            {label.startsWith('Hapus kejadian') ? (
                <Trash2 className="size-4" />
            ) : (
                <X className="size-4" />
            )}
        </Button>
    );
}

OperatorMutasiPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Lembar Mutasi Operator', href: mutasiRoutes.index() },
    ],
};

/**
 * The sheet's frame: the page body on desktop, the phone form screen (own top
 * bar, Kembali / Simpan / Serahkan at the bottom) inside the mobile shell.
 */
function MutasiFrame({
    phone,
    className,
    screen,
    children,
}: {
    phone: boolean;
    className: string;
    screen: Omit<ComponentProps<typeof MobileFormScreen>, 'children'>;
    children: ReactNode;
}) {
    if (phone) {
        return <MobileFormScreen {...screen}>{children}</MobileFormScreen>;
    }

    return <div className={className}>{children}</div>;
}
