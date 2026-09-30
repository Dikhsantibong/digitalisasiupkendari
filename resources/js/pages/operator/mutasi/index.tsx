import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
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
    Plus,
    Save,
    Send,
    Trash2,
    Wrench,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { SignaturePad } from '@/components/signature-pad';
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
    level_bbm: string | null;
    tambah_bbm: string | null;
    pelumas: string | null;
    status: string | null;
};
type Tangki = { nama: string | null; level_cm: string | null };
type Alat = { nama: string | null; ada: boolean; jumlah: number | null };
type Kejadian = { jam: string | null; uraian: string };
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
    riwayat: {
        id: number;
        tanggal: string;
        shift: string;
        regu_penyerah: string | null;
        regu_penerima: string | null;
        status: Status;
    }[];
    me: { nama: string; regu: string | null };
    can_write: boolean;
    can_receive: boolean;
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
    me,
    can_write,
    can_receive,
}: Props) {
    const compact = useCompactLayout();
    const [mesin, setMesin] = useState<Mesin[]>(mutasi.mesin);
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
    const [paraf, setParaf] = useState<string | null>(null);
    const [reguPenerima, setReguPenerima] = useState(
        me.regu && me.regu !== mutasi.regu_penyerah ? me.regu : '',
    );
    const [penerimaNama, setPenerimaNama] = useState(me.nama);
    const [parafPenerima, setParafPenerima] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const [exporting, setExporting] = useState(false);
    const locked = !can_write;
    const status = STATUS_LABEL[mutasi.status];
    const shiftInfo = options.shifts.find((s) => s.value === filters.shift);

    const touch = () => setDirty(true);

    const go = (query: Partial<Props['filters']>) => {
        if (
            dirty &&
            !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')
        ) {
            return;
        }

        router.get(mutasiRoutes.index().url, { ...filters, ...query });
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
                paraf: serahkan ? paraf : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onError: (errs) => setErrors(errs),
                onFinish: () => setSaving(false),
            },
        );
    };

    const terima = () => {
        if (mutasi.id === null) {
            return;
        }

        setSaving(true);
        router.post(
            mutasiRoutes.terima(mutasi.id).url,
            {
                regu_penerima: reguPenerima,
                penerima_nama: penerimaNama,
                paraf: parafPenerima,
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

    const canHandOver =
        !!reguPenyerah && (!!paraf || !!mutasi.paraf_penyerah_url);

    return (
        <>
            <Head title={`Lembar Mutasi Operator - ${unit.name}`} />
            <div
                className={cn(
                    'flex flex-1 flex-col gap-4 p-4 md:p-6',
                    compact && can_write && 'pb-28',
                )}
            >
                <PageHeader
                    title="Lembar Mutasi Operator"
                    description={`Logbook serah terima tugas antar regu — ${unit.name}.`}
                    actions={
                        mutasi.id !== null ? (
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        window.open(
                                            mutasiRoutes.pdf(
                                                mutasi.id as number,
                                            ).url,
                                            '_blank',
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
                                    {exporting ? 'Menyiapkan…' : 'Export Excel'}
                                </Button>
                            </div>
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
                        <div className="grid grid-cols-3 gap-1 rounded-lg bg-muted p-1">
                            {options.shifts.map((s) => (
                                <button
                                    key={s.value}
                                    type="button"
                                    onClick={() =>
                                        s.value !== filters.shift &&
                                        go({ shift: s.value })
                                    }
                                    className={cn(
                                        'flex min-w-0 flex-col items-center rounded-md px-2 py-1.5 transition',
                                        filters.shift === s.value
                                            ? 'bg-background text-foreground shadow-sm'
                                            : 'text-muted-foreground',
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
                    {locked && mutasi.status === 'diterima' && (
                        <p className="mt-1 flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-400">
                            <Lock className="size-3.5" />
                            Sudah diterima regu {mutasi.regu_penerima} — lembar
                            terkunci.
                        </p>
                    )}
                </div>

                {/* 1. Mesin */}
                <Section
                    icon={<Fuel className="size-4" />}
                    title="Mesin"
                    hint="Level tangki & tambah bahan bakar (liter, atau ✓ bila sudah dicek), level pelumas mesin/RA dan status mesin."
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
                                            label="Level tangki BBM"
                                            value={m.level_bbm}
                                            onChange={(v) =>
                                                setMesinField(i, {
                                                    level_bbm: v,
                                                })
                                            }
                                            disabled={locked}
                                        />
                                        <FuelInput
                                            label="Tambah BBM"
                                            value={m.tambah_bbm}
                                            onChange={(v) =>
                                                setMesinField(i, {
                                                    tambah_bbm: v,
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
                                                Level tangki BBM (L)
                                            </th>
                                            <th className="w-40 border border-border p-2">
                                                Tambah BBM (L)
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
                                                        value={m.tambah_bbm}
                                                        onChange={(v) =>
                                                            setMesinField(i, {
                                                                tambah_bbm: v,
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
                    hint="Catat kejadian selama shift berikut jamnya (terima tugas, start/stop mesin, pompa, dll)."
                >
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
                        hint="Mesin operasi / standby diambil dari status mesin di atas."
                    >
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

                {/* 7. Serah terima */}
                <Section
                    icon={<Handshake className="size-4" />}
                    title="Serah Terima Tugas"
                    hint="Regu penyerah paraf lalu serahkan; regu penerima paraf untuk menerima tugas."
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
                            {mutasi.paraf_penyerah_url && (
                                <Signed
                                    url={mutasi.paraf_penyerah_url}
                                    caption={`Diserahkan ${timeOf(mutasi.diserahkan_at)}`}
                                />
                            )}
                            {!locked && (
                                <>
                                    {mutasi.paraf_penyerah_url && (
                                        <p className="text-xs text-muted-foreground">
                                            Buat paraf baru di bawah bila ingin
                                            mengganti.
                                        </p>
                                    )}
                                    <SignaturePad onChange={setParaf} />
                                    {errors.paraf && (
                                        <p className="text-sm text-destructive">
                                            {errors.paraf}
                                        </p>
                                    )}
                                </>
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
                                    {mutasi.paraf_penerima_url && (
                                        <Signed
                                            url={mutasi.paraf_penerima_url}
                                            caption={`Diterima ${timeOf(mutasi.diterima_at)}`}
                                        />
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
                                    <SignaturePad onChange={setParafPenerima} />
                                    <Button
                                        onClick={terima}
                                        disabled={
                                            saving ||
                                            !reguPenerima ||
                                            !parafPenerima ||
                                            dirty
                                        }
                                        className="gap-1.5"
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
                                        ? 'Menunggu regu penerima.'
                                        : 'Dapat diisi setelah regu penyerah menyerahkan lembar ini.'}
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
                                    : 'Pilih regu penyerah dan buat paraf'
                            }
                        >
                            <Send className="size-4" />
                            {mutasi.status === 'diserahkan'
                                ? 'Simpan & Serahkan Ulang'
                                : 'Serahkan Tugas'}
                        </Button>
                    </div>
                )}

                {riwayat.length > 0 && (
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
            </div>

            {compact && can_write && (
                <StickyActionBar>
                    <div className="grid w-full grid-cols-2 gap-2">
                        <Button
                            variant="outline"
                            onClick={() => save(false)}
                            disabled={saving}
                            className="h-11 gap-1.5"
                        >
                            <Save className="size-4" />
                            Simpan
                        </Button>
                        <Button
                            onClick={() => save(true)}
                            disabled={saving || !canHandOver}
                            className="h-11 gap-1.5"
                        >
                            <Send className="size-4" />
                            Serahkan
                        </Button>
                    </div>
                </StickyActionBar>
            )}
        </>
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

/** Liter value, or "✓" when only checked (as on the paper form). */
function FuelInput({
    label,
    value,
    onChange,
    disabled,
}: {
    label?: string;
    value: string | null;
    onChange: (value: string | null) => void;
    disabled: boolean;
}) {
    const checked = value === '✓';

    return (
        <div className="flex min-w-0 flex-col gap-1">
            {label && (
                <span className="text-xs font-medium text-muted-foreground">
                    {label}
                </span>
            )}
            <div className="flex min-w-0 gap-1">
                <Input
                    inputMode="decimal"
                    value={checked ? '' : (value ?? '')}
                    onChange={(e) => onChange(e.target.value || null)}
                    disabled={disabled || checked}
                    placeholder={checked ? '✓ dicek' : 'Liter'}
                    className="h-10 min-w-0 flex-1"
                />
                <button
                    type="button"
                    disabled={disabled}
                    onClick={() => onChange(checked ? null : '✓')}
                    className={cn(
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-md border text-base font-bold transition disabled:opacity-60',
                        checked
                            ? 'border-emerald-600 bg-emerald-600 text-white'
                            : 'border-input text-muted-foreground',
                    )}
                    aria-label="Tandai sudah dicek"
                    title="Tandai ✓ (sudah dicek)"
                >
                    ✓
                </button>
            </div>
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
