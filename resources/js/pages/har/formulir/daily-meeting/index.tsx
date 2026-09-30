import { Head, router, usePoll } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    CheckCircle2,
    Clock,
    Copy,
    ExternalLink,
    FileDown,
    ImagePlus,
    Lock,
    LockOpen,
    MapPin,
    Maximize2,
    PencilLine,
    Plus,
    QrCode,
    Save,
    Trash2,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { MeetingAttendanceForm } from '@/components/har/meeting-attendance-form';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import harFormulir from '@/routes/har/formulir';
import dailyMeetingRoutes from '@/routes/har/formulir/daily-meeting';
import pesertaRoutes from '@/routes/har/formulir/daily-meeting/peserta';
import type { IdName } from '@/types';

type Peserta = {
    uid: string | null;
    nama: string;
    asal: string | null;
    jabatan: string | null;
    ttd_url: string | null;
    hadir_pada: string | null;
    via: 'qr' | 'manual';
};

type Meeting = {
    id: number;
    tanggal: string;
    hari: string;
    acara: string;
    waktu: string | null;
    tempat: string | null;
    absensi_dibuka: boolean;
    peserta: Peserta[];
    eviden: { path: string; url: string }[];
    absensi_url: string;
    qr_svg: string;
};

type MeetingSummary = {
    id: number;
    tanggal: string;
    acara: string;
    waktu: string | null;
    tempat: string | null;
    peserta: number;
    eviden: number;
    absensi_dibuka: boolean;
};

type Props = {
    unit: IdName;
    filters: {
        unit_id: number;
        month: number;
        year: number;
        meeting_id: number | null;
    };
    options: {
        units: IdName[];
        years: number[];
        min_rows: number;
        max_eviden: number;
    };
    meetings: MeetingSummary[];
    meeting: Meeting | null;
    signers: {
        kiri: { jabatan: string; nama: string };
        kanan: { jabatan: string; nama: string };
    };
    today: string;
    can_write: boolean;
};

type Details = {
    acara: string;
    tanggal: string;
    mulai: string;
    selesai: string;
    zona: string;
    tempat: string;
};

const DAYS = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const ACARA_SUGGESTIONS = [
    'Meeting HAR',
    'Daily Meeting Pemeliharaan',
    'Safety Briefing Pemeliharaan',
    'Koordinasi Pekerjaan Pemeliharaan',
];
const TEMPAT_SUGGESTIONS = [
    'Ruang Meeting',
    'Control Room',
    'Workshop Pemeliharaan',
];

const parseIso = (iso: string) => {
    const [y, m, d] = iso.split('-').map(Number);

    return new Date(y, m - 1, d);
};

const longDate = (iso: string) => {
    const date = parseIso(iso);

    return `${DAYS[date.getDay()]}, ${date.getDate()} ${OPERASI_MONTHS[date.getMonth()]} ${date.getFullYear()}`;
};

const timeOf = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleTimeString('id-ID', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';

/** "08.00 - 09.30 WITA" ↔ {mulai, selesai, zona}. */
const parseWaktu = (
    waktu: string | null,
): Pick<Details, 'mulai' | 'selesai' | 'zona'> => {
    const times = (waktu ?? '').match(/\d{1,2}[.:]\d{2}/g) ?? [];
    const zona = (waktu ?? '').match(/WITA|WIB|WIT/)?.[0] ?? 'WITA';
    const norm = (t?: string) =>
        t ? t.replace('.', ':').padStart(5, '0') : '';

    return { mulai: norm(times[0]), selesai: norm(times[1]), zona };
};

const composeWaktu = (d: Details) =>
    d.mulai
        ? `${d.mulai.replace(':', '.')}${d.selesai ? ` - ${d.selesai.replace(':', '.')}` : ''} ${d.zona}`
        : '';

/**
 * Daily Meeting Pemeliharaan (Akses 1): the Koordinator Pemeliharaan / Project
 * Leader creates a meeting (acara, hari/tanggal, waktu, tempat), shows its QR
 * code, and the attendees scan it to sign in (nama, asal perusahaan, jabatan,
 * canvas signature). The attendance list updates live; export to PDF (daftar
 * hadir + eviden) per meeting or for the whole month.
 */
export default function HarDailyMeetingPage({
    unit,
    filters,
    options,
    meetings,
    meeting,
    signers,
    today,
    can_write,
}: Props) {
    const { can } = usePermissions();
    const [creating, setCreating] = useState(false);
    // The device date (the server clock runs in UTC, a day behind WITA in the early morning).
    const [localToday] = useState(
        () => new Date().toLocaleDateString('en-CA') || today,
    );
    const [shownMeetingId, setShownMeetingId] = useState(filters.meeting_id);
    const month = OPERASI_MONTHS[filters.month - 1];

    // A freshly created (or picked) meeting replaces the "new meeting" form with its QR.
    if (filters.meeting_id !== shownMeetingId) {
        setShownMeetingId(filters.meeting_id);

        if (filters.meeting_id !== null) {
            setCreating(false);
        }
    }

    const go = (query: Partial<Props['filters']>) => {
        setCreating(false);
        router.get(dailyMeetingRoutes.index().url, {
            unit_id: filters.unit_id,
            month: filters.month,
            year: filters.year,
            ...query,
        });
    };

    const startNew = () => {
        setCreating(true);

        if (meeting) {
            router.get(
                dailyMeetingRoutes.index().url,
                {
                    unit_id: filters.unit_id,
                    month: filters.month,
                    year: filters.year,
                },
                { preserveState: true },
            );
        }
    };

    const showForm =
        can_write && (creating || (meeting === null && meetings.length === 0));
    const defaultDate = localToday.startsWith(
        `${filters.year}-${String(filters.month).padStart(2, '0')}`,
    )
        ? localToday
        : `${filters.year}-${String(filters.month).padStart(2, '0')}-01`;

    return (
        <>
            <Head title={`Daily Meeting Pemeliharaan - ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Daily Meeting Pemeliharaan"
                    description="Buat meeting, tampilkan QR code, peserta scan & isi absensi + tanda tangan di HP masing-masing. Daftar hadir langsung siap diekspor ke PDF."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {meetings.length > 0 && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        window.open(
                                            dailyMeetingRoutes.pdfBulan({
                                                query: {
                                                    unit_id: filters.unit_id,
                                                    month: filters.month,
                                                    year: filters.year,
                                                },
                                            }).url,
                                            '_blank',
                                        )
                                    }
                                    className="gap-1.5"
                                >
                                    <FileDown className="size-4 text-rose-600" />
                                    PDF {month} ({meetings.length})
                                </Button>
                            )}
                            {can('har.input.view') && (
                                <Button
                                    variant="ghost"
                                    onClick={() =>
                                        router.get(harFormulir.index().url, {
                                            unit_id: filters.unit_id,
                                        })
                                    }
                                >
                                    Kembali
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-card p-3">
                    {options.units.length > 1 && (
                        <OperasiSelect
                            label="Unit"
                            value={String(filters.unit_id)}
                            onChange={(v) =>
                                go({ unit_id: Number(v), meeting_id: null })
                            }
                            options={options.units.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            }))}
                        />
                    )}
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(v) =>
                            go({ month: Number(v), meeting_id: null })
                        }
                        options={OPERASI_MONTHS.map((label, i) => ({
                            value: String(i + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(v) =>
                            go({ year: Number(v), meeting_id: null })
                        }
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                </div>

                <div className="grid grid-cols-1 items-start gap-4 lg:grid-cols-[300px_minmax(0,1fr)]">
                    <aside
                        className="flex min-w-0 flex-col gap-2"
                        aria-label="Daftar meeting"
                    >
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold text-foreground">
                                Meeting {month} {filters.year}
                            </h2>
                            <Badge variant="secondary">{meetings.length}</Badge>
                        </div>
                        {can_write && (
                            <Button
                                onClick={startNew}
                                className="w-full gap-1.5"
                            >
                                <Plus className="size-4" />
                                Buat Meeting Baru
                            </Button>
                        )}
                        {meetings.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-border p-4 text-center text-xs text-muted-foreground">
                                Belum ada meeting bulan ini.
                            </p>
                        ) : (
                            <div className="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible">
                                {meetings.map((m) => {
                                    const active =
                                        meeting?.id === m.id && !creating;

                                    return (
                                        <button
                                            key={m.id}
                                            type="button"
                                            onClick={() =>
                                                go({ meeting_id: m.id })
                                            }
                                            className={cn(
                                                'flex w-64 shrink-0 flex-col gap-1 rounded-lg border p-3 text-left transition lg:w-full',
                                                active
                                                    ? 'border-primary bg-primary/10'
                                                    : 'border-border bg-card hover:bg-muted',
                                            )}
                                        >
                                            <span className="text-[11px] font-medium text-muted-foreground">
                                                {longDate(m.tanggal)}
                                            </span>
                                            <span className="line-clamp-1 text-sm font-semibold text-foreground">
                                                {m.acara}
                                            </span>
                                            <span className="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-muted-foreground">
                                                {m.waktu && (
                                                    <span>{m.waktu}</span>
                                                )}
                                                <span className="flex items-center gap-0.5">
                                                    <Users className="size-3" />
                                                    {m.peserta} hadir
                                                </span>
                                                <span
                                                    className={cn(
                                                        'rounded-full px-1.5 py-px text-[10px] font-medium',
                                                        m.absensi_dibuka
                                                            ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                                            : 'bg-muted text-muted-foreground',
                                                    )}
                                                >
                                                    {m.absensi_dibuka
                                                        ? 'Absensi dibuka'
                                                        : 'Ditutup'}
                                                </span>
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </aside>

                    <section className="min-w-0">
                        {showForm ? (
                            <div className="flex flex-col gap-4">
                                <Stepper step={1} />
                                <div className="rounded-lg border border-border bg-card p-4 sm:p-5">
                                    <h2 className="text-base font-semibold text-foreground">
                                        Detail Meeting
                                    </h2>
                                    <p className="mb-4 text-sm text-muted-foreground">
                                        Isi acara, hari/tanggal, waktu dan
                                        tempat. Setelah dibuat, QR code absensi
                                        langsung tampil.
                                    </p>
                                    <DetailsForm
                                        unitId={filters.unit_id}
                                        initial={{
                                            acara: 'Meeting HAR',
                                            tanggal: defaultDate,
                                            ...parseWaktu(null),
                                            tempat: '',
                                        }}
                                        submitLabel="Buat Meeting & Tampilkan QR"
                                        onCancel={
                                            meetings.length > 0
                                                ? () => setCreating(false)
                                                : undefined
                                        }
                                    />
                                </div>
                            </div>
                        ) : meeting ? (
                            <MeetingWorkspace
                                key={meeting.id}
                                meeting={meeting}
                                unitId={filters.unit_id}
                                maxEviden={options.max_eviden}
                                signers={signers}
                                canWrite={can_write}
                            />
                        ) : (
                            <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border p-10 text-center">
                                <QrCode className="size-10 text-muted-foreground" />
                                <p className="font-semibold text-foreground">
                                    Pilih meeting di daftar
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {can_write
                                        ? 'atau buat meeting baru untuk menampilkan QR absensi.'
                                        : 'untuk melihat daftar hadirnya.'}
                                </p>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

function Stepper({ step }: { step: 1 | 2 | 3 }) {
    const steps = ['Detail Meeting', 'QR Absensi', 'Daftar Hadir & PDF'];

    return (
        <ol className="flex items-center gap-2 text-xs">
            {steps.map((label, i) => {
                const n = i + 1;
                const done = n < step;
                const current = n === step;

                return (
                    <li key={label} className="flex flex-1 items-center gap-2">
                        <span
                            className={cn(
                                'flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                                done || current
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {done ? <Check className="size-3.5" /> : n}
                        </span>
                        <span
                            className={cn(
                                'hidden font-medium sm:inline',
                                current
                                    ? 'text-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {label}
                        </span>
                        {n < steps.length && (
                            <span className="h-px flex-1 bg-border" />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}

/** Acara, hari/tanggal, waktu (mulai–selesai + zona) and tempat of a meeting. */
function DetailsForm({
    unitId,
    meetingId,
    initial,
    submitLabel,
    onCancel,
    onSaved,
}: {
    unitId: number;
    meetingId?: number;
    initial: Details;
    submitLabel: string;
    onCancel?: () => void;
    onSaved?: () => void;
}) {
    const [d, setD] = useState<Details>(initial);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const set = (patch: Partial<Details>) =>
        setD((prev) => ({ ...prev, ...patch }));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setSaving(true);
        router.post(
            dailyMeetingRoutes.store().url,
            {
                unit_id: unitId,
                id: meetingId ?? null,
                acara: d.acara,
                tanggal: d.tanggal,
                waktu: composeWaktu(d),
                tempat: d.tempat,
            },
            {
                onSuccess: () => onSaved?.(),
                onError: (errs) => setErrors(errs),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-4">
            <FormField
                label="Acara"
                icon={<Users className="size-4" />}
                error={errors.acara}
            >
                <Input
                    value={d.acara}
                    onChange={(e) => set({ acara: e.target.value })}
                    className="h-10"
                    required
                />
                <div className="flex flex-wrap gap-1.5">
                    {ACARA_SUGGESTIONS.filter((s) => s !== d.acara).map((s) => (
                        <button
                            key={s}
                            type="button"
                            onClick={() => set({ acara: s })}
                            className="rounded-full border border-border px-2.5 py-0.5 text-[11px] text-muted-foreground hover:border-primary hover:text-primary"
                        >
                            {s}
                        </button>
                    ))}
                </div>
            </FormField>
            <FormField
                label="Hari / Tanggal"
                icon={<CalendarDays className="size-4" />}
                error={errors.tanggal}
            >
                <div className="flex flex-wrap items-center gap-3">
                    <Input
                        type="date"
                        value={d.tanggal}
                        onChange={(e) => set({ tanggal: e.target.value })}
                        className="h-10 w-44"
                        required
                    />
                    {d.tanggal && (
                        <span className="text-sm font-medium text-foreground">
                            {longDate(d.tanggal)}
                        </span>
                    )}
                </div>
            </FormField>
            <FormField
                label="Waktu"
                icon={<Clock className="size-4" />}
                error={errors.waktu}
            >
                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        type="time"
                        value={d.mulai}
                        onChange={(e) => set({ mulai: e.target.value })}
                        className="h-10 w-32"
                        aria-label="Jam mulai"
                    />
                    <span className="text-sm text-muted-foreground">s/d</span>
                    <Input
                        type="time"
                        value={d.selesai}
                        onChange={(e) => set({ selesai: e.target.value })}
                        className="h-10 w-32"
                        aria-label="Jam selesai (opsional)"
                    />
                    <Select
                        value={d.zona}
                        onValueChange={(v) => set({ zona: v })}
                    >
                        <SelectTrigger
                            className="h-10 w-24"
                            aria-label="Zona waktu"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {['WITA', 'WIB', 'WIT'].map((z) => (
                                <SelectItem key={z} value={z}>
                                    {z}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </FormField>
            <FormField
                label="Tempat"
                icon={<MapPin className="size-4" />}
                error={errors.tempat}
            >
                <Input
                    value={d.tempat}
                    onChange={(e) => set({ tempat: e.target.value })}
                    placeholder="mis. Ruang Meeting PLTD"
                    className="h-10"
                />
                <div className="flex flex-wrap gap-1.5">
                    {TEMPAT_SUGGESTIONS.filter((s) => s !== d.tempat).map(
                        (s) => (
                            <button
                                key={s}
                                type="button"
                                onClick={() => set({ tempat: s })}
                                className="rounded-full border border-border px-2.5 py-0.5 text-[11px] text-muted-foreground hover:border-primary hover:text-primary"
                            >
                                {s}
                            </button>
                        ),
                    )}
                </div>
            </FormField>
            <div className="flex flex-wrap justify-end gap-2 pt-1">
                {onCancel && (
                    <Button type="button" variant="ghost" onClick={onCancel}>
                        Batal
                    </Button>
                )}
                <Button
                    type="submit"
                    disabled={saving || !d.acara.trim() || !d.tanggal}
                    className="gap-1.5"
                >
                    {meetingId ? (
                        <Save className="size-4" />
                    ) : (
                        <QrCode className="size-4" />
                    )}
                    {saving ? 'Menyimpan…' : submitLabel}
                </Button>
            </div>
        </form>
    );
}

type Tab = 'qr' | 'hadir' | 'eviden';

function MeetingWorkspace({
    meeting,
    unitId,
    maxEviden,
    signers,
    canWrite,
}: {
    meeting: Meeting;
    unitId: number;
    maxEviden: number;
    signers: Props['signers'];
    canWrite: boolean;
}) {
    const [tab, setTab] = useState<Tab>(
        meeting.absensi_dibuka && canWrite ? 'qr' : 'hadir',
    );
    const [fullscreen, setFullscreen] = useState(false);
    const [editing, setEditing] = useState(false);
    const [manualOpen, setManualOpen] = useState(false);
    const [copied, setCopied] = useState(false);

    // Live attendance while the QR code is on screen.
    const { start, stop } = usePoll(
        4000,
        { only: ['meeting', 'meetings'] },
        { autoStart: false },
    );
    const live =
        meeting.absensi_dibuka &&
        (tab === 'qr' || tab === 'hadir' || fullscreen);

    useEffect(() => {
        if (live) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [live, start, stop]);

    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(meeting.absensi_url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            window.prompt('Salin link absensi:', meeting.absensi_url);
        }
    };

    const toggleAbsensi = () =>
        router.post(
            dailyMeetingRoutes.absensi(meeting.id).url,
            { dibuka: !meeting.absensi_dibuka },
            { preserveScroll: true },
        );

    const removeMeeting = () => {
        if (
            window.confirm(
                `Hapus meeting "${meeting.acara}" (${longDate(meeting.tanggal)}) beserta daftar hadir, tanda tangan dan foto evidennya?`,
            )
        ) {
            router.delete(dailyMeetingRoutes.destroy(meeting.id).url);
        }
    };

    const removePeserta = (p: Peserta) => {
        if (p.uid && window.confirm(`Hapus ${p.nama} dari daftar hadir?`)) {
            router.delete(
                pesertaRoutes.destroy({ dailyMeeting: meeting.id, uid: p.uid })
                    .url,
                { preserveScroll: true },
            );
        }
    };

    const step = meeting.peserta.length > 0 ? 3 : 2;

    return (
        <div className="flex flex-col gap-4">
            <Stepper step={step} />

            <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4 sm:flex-row sm:items-start">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-lg font-semibold text-foreground">
                            {meeting.acara}
                        </h2>
                        <span
                            className={cn(
                                'rounded-full px-2 py-0.5 text-[11px] font-medium',
                                meeting.absensi_dibuka
                                    ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {meeting.absensi_dibuka
                                ? 'Absensi dibuka'
                                : 'Absensi ditutup'}
                        </span>
                    </div>
                    <div className="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        <span className="flex items-center gap-1.5">
                            <CalendarDays className="size-4" />
                            {longDate(meeting.tanggal)}
                        </span>
                        {meeting.waktu && (
                            <span className="flex items-center gap-1.5">
                                <Clock className="size-4" />
                                {meeting.waktu}
                            </span>
                        )}
                        {meeting.tempat && (
                            <span className="flex items-center gap-1.5">
                                <MapPin className="size-4" />
                                {meeting.tempat}
                            </span>
                        )}
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            window.open(
                                dailyMeetingRoutes.pdf(meeting.id).url,
                                '_blank',
                            )
                        }
                        className="gap-1.5"
                    >
                        <FileDown className="size-4 text-rose-600" />
                        Export PDF
                    </Button>
                    {canWrite && (
                        <>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setEditing(true)}
                                className="gap-1.5"
                            >
                                <PencilLine className="size-4" />
                                Ubah Detail
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={removeMeeting}
                                className="gap-1.5 text-muted-foreground hover:text-destructive"
                            >
                                <Trash2 className="size-4" />
                                Hapus
                            </Button>
                        </>
                    )}
                </div>
            </div>

            <div className="flex gap-1 rounded-lg bg-muted p-1" role="tablist">
                {(
                    [
                        ['qr', 'QR Absensi', 'QR', QrCode],
                        [
                            'hadir',
                            `Daftar Hadir (${meeting.peserta.length})`,
                            `Hadir (${meeting.peserta.length})`,
                            Users,
                        ],
                        [
                            'eviden',
                            `Foto Eviden (${meeting.eviden.length})`,
                            `Foto (${meeting.eviden.length})`,
                            ImagePlus,
                        ],
                    ] as const
                ).map(([key, label, short, Icon]) => (
                    <button
                        key={key}
                        type="button"
                        role="tab"
                        aria-selected={tab === key}
                        onClick={() => setTab(key)}
                        className={cn(
                            'flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-2 text-xs font-medium transition sm:text-sm',
                            tab === key
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground',
                        )}
                    >
                        <Icon className="size-4 shrink-0" />
                        <span className="truncate sm:hidden">{short}</span>
                        <span className="hidden truncate sm:inline">
                            {label}
                        </span>
                    </button>
                ))}
            </div>

            {tab === 'qr' && (
                <div className="grid gap-4 rounded-lg border border-border bg-card p-4 md:grid-cols-[auto_minmax(0,1fr)] md:p-6">
                    <div className="relative mx-auto w-full max-w-[300px]">
                        <div
                            className={cn(
                                'aspect-square w-full rounded-xl border border-border bg-white p-3 [&_svg]:h-full [&_svg]:w-full',
                                !meeting.absensi_dibuka &&
                                    'opacity-25 blur-[2px]',
                            )}
                            dangerouslySetInnerHTML={{ __html: meeting.qr_svg }}
                        />
                        {!meeting.absensi_dibuka && (
                            <div className="absolute inset-0 flex flex-col items-center justify-center gap-1 text-center">
                                <Lock className="size-8 text-foreground" />
                                <span className="text-sm font-semibold text-foreground">
                                    Absensi ditutup
                                </span>
                            </div>
                        )}
                    </div>
                    <div className="flex flex-col gap-4">
                        <div>
                            <h3 className="text-base font-semibold text-foreground">
                                Scan untuk absen
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Peserta scan QR ini dengan kamera HP, lalu
                                mengisi nama, asal perusahaan, jabatan dan tanda
                                tangan. Tidak perlu login.
                            </p>
                        </div>
                        <div className="flex items-center gap-3 rounded-lg bg-muted/60 p-3">
                            <Users className="size-8 text-primary" />
                            <div>
                                <p className="text-2xl leading-none font-bold text-foreground">
                                    {meeting.peserta.length}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    peserta sudah hadir
                                    {live ? ' · diperbarui otomatis' : ''}
                                </p>
                            </div>
                        </div>
                        {meeting.peserta.length > 0 && (
                            <ul className="flex flex-col gap-1 text-sm">
                                {[...meeting.peserta]
                                    .slice(-4)
                                    .reverse()
                                    .map((p) => (
                                        <li
                                            key={p.uid ?? p.nama}
                                            className="flex items-center gap-2"
                                        >
                                            <CheckCircle2 className="size-4 shrink-0 text-emerald-500" />
                                            <span className="truncate font-medium text-foreground">
                                                {p.nama}
                                            </span>
                                            <span className="ml-auto shrink-0 text-xs text-muted-foreground">
                                                {timeOf(p.hadir_pada)}
                                            </span>
                                        </li>
                                    ))}
                            </ul>
                        )}
                        <div className="flex flex-wrap gap-2">
                            <Button
                                onClick={() => setFullscreen(true)}
                                className="gap-1.5"
                                disabled={!meeting.absensi_dibuka}
                            >
                                <Maximize2 className="size-4" />
                                Tampilkan Layar Penuh
                            </Button>
                            <Button
                                variant="outline"
                                onClick={copyLink}
                                className="gap-1.5"
                            >
                                {copied ? (
                                    <Check className="size-4 text-emerald-600" />
                                ) : (
                                    <Copy className="size-4" />
                                )}
                                {copied ? 'Link disalin' : 'Salin Link'}
                            </Button>
                            {canWrite && (
                                <Button
                                    variant="outline"
                                    onClick={() => setManualOpen(true)}
                                    className="gap-1.5"
                                    disabled={!meeting.absensi_dibuka}
                                >
                                    <UserPlus className="size-4" />
                                    Isi di Perangkat Ini
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    window.open(
                                        meeting.absensi_url,
                                        '_blank',
                                        'noopener',
                                    )
                                }
                                className="gap-1.5"
                            >
                                <ExternalLink className="size-4" />
                                Buka Form
                            </Button>
                        </div>
                        {canWrite && (
                            <Button
                                variant={
                                    meeting.absensi_dibuka
                                        ? 'secondary'
                                        : 'default'
                                }
                                onClick={toggleAbsensi}
                                className="w-fit gap-1.5"
                            >
                                {meeting.absensi_dibuka ? (
                                    <Lock className="size-4" />
                                ) : (
                                    <LockOpen className="size-4" />
                                )}
                                {meeting.absensi_dibuka
                                    ? 'Tutup Absensi'
                                    : 'Buka Kembali Absensi'}
                            </Button>
                        )}
                    </div>
                </div>
            )}

            {tab === 'hadir' && (
                <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 className="text-base font-semibold text-foreground">
                                Daftar Hadir
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Ditandatangani {signers.kiri.jabatan} (
                                {signers.kiri.nama || '—'}) &amp;{' '}
                                {signers.kanan.jabatan} (
                                {signers.kanan.nama || '—'}) pada PDF.
                            </p>
                        </div>
                        {canWrite && meeting.absensi_dibuka && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setManualOpen(true)}
                                className="gap-1.5"
                            >
                                <UserPlus className="size-4" />
                                Tambah Peserta Manual
                            </Button>
                        )}
                    </div>
                    {meeting.peserta.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border p-8 text-center">
                            <QrCode className="size-8 text-muted-foreground" />
                            <p className="text-sm text-muted-foreground">
                                Belum ada peserta. Tampilkan QR code agar
                                peserta bisa absen.
                            </p>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setTab('qr')}
                            >
                                Lihat QR Absensi
                            </Button>
                        </div>
                    ) : (
                        <ol className="grid grid-cols-1 gap-2 md:grid-cols-2">
                            {meeting.peserta.map((p, i) => (
                                <li
                                    key={p.uid ?? `${p.nama}-${i}`}
                                    className="flex min-w-0 flex-wrap items-center gap-2.5 rounded-lg border border-border p-2.5 sm:flex-nowrap sm:gap-3"
                                >
                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-muted-foreground">
                                        {i + 1}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-foreground">
                                            {p.nama}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {[p.jabatan, p.asal]
                                                .filter(Boolean)
                                                .join(' · ') || '—'}
                                        </p>
                                        <p className="text-[11px] text-muted-foreground">
                                            {timeOf(p.hadir_pada)} ·{' '}
                                            {p.via === 'qr'
                                                ? 'Scan QR'
                                                : 'Manual'}
                                        </p>
                                    </div>
                                    {/* Phones: the signature takes its own line under the name. */}
                                    <div className="order-last w-full pl-[2.375rem] sm:order-none sm:w-auto sm:shrink-0 sm:pl-0">
                                        <div className="flex h-12 w-full items-center justify-center rounded border border-border bg-white sm:w-24">
                                            {p.ttd_url ? (
                                                <img
                                                    src={p.ttd_url}
                                                    alt={`Tanda tangan ${p.nama}`}
                                                    className="max-h-11 max-w-[10rem] object-contain sm:max-w-[5.5rem]"
                                                />
                                            ) : (
                                                <span className="text-[10px] text-slate-400">
                                                    tanpa ttd
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    {canWrite && p.uid && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => removePeserta(p)}
                                            className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                                            aria-label={`Hapus ${p.nama}`}
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}
                </div>
            )}

            {tab === 'eviden' && (
                <EvidenPanel
                    meeting={meeting}
                    unitId={unitId}
                    maxEviden={maxEviden}
                    canWrite={canWrite}
                />
            )}

            <Dialog open={editing} onOpenChange={setEditing}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Ubah Detail Meeting</DialogTitle>
                        <DialogDescription>
                            Daftar hadir dan tanda tangan peserta tetap
                            tersimpan.
                        </DialogDescription>
                    </DialogHeader>
                    <DetailsForm
                        unitId={unitId}
                        meetingId={meeting.id}
                        initial={{
                            acara: meeting.acara,
                            tanggal: meeting.tanggal,
                            ...parseWaktu(meeting.waktu),
                            tempat: meeting.tempat ?? '',
                        }}
                        submitLabel="Simpan Perubahan"
                        onCancel={() => setEditing(false)}
                        onSaved={() => setEditing(false)}
                    />
                </DialogContent>
            </Dialog>

            <Dialog open={manualOpen} onOpenChange={setManualOpen}>
                <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Absensi di Perangkat Ini</DialogTitle>
                        <DialogDescription>
                            Untuk peserta yang tidak membawa HP: isi data lalu
                            minta peserta tanda tangan.
                        </DialogDescription>
                    </DialogHeader>
                    <MeetingAttendanceForm
                        action={pesertaRoutes.store(meeting.id).url}
                        submitLabel="Catat Hadir"
                        onSuccess={() => setManualOpen(false)}
                    />
                </DialogContent>
            </Dialog>

            {fullscreen && (
                <div className="fixed inset-0 z-50 flex flex-col items-center justify-center gap-6 overflow-y-auto bg-white p-6 text-slate-900">
                    <button
                        type="button"
                        onClick={() => setFullscreen(false)}
                        className="absolute top-4 right-4 rounded-full p-2 text-slate-500 hover:bg-slate-100"
                        aria-label="Tutup layar penuh"
                    >
                        <X className="size-7" />
                    </button>
                    <div className="text-center">
                        <p className="text-sm font-medium tracking-wide text-slate-500 uppercase">
                            Scan untuk absen
                        </p>
                        <h2 className="text-2xl font-bold sm:text-4xl">
                            {meeting.acara}
                        </h2>
                        <p className="mt-1 text-base text-slate-600 sm:text-lg">
                            {longDate(meeting.tanggal)}
                            {meeting.waktu ? ` · ${meeting.waktu}` : ''}
                            {meeting.tempat ? ` · ${meeting.tempat}` : ''}
                        </p>
                    </div>
                    <div
                        className="aspect-square w-[min(70vh,86vw)] [&_svg]:h-full [&_svg]:w-full"
                        dangerouslySetInnerHTML={{ __html: meeting.qr_svg }}
                    />
                    <p className="flex items-center gap-2 text-xl font-semibold sm:text-2xl">
                        <Users className="size-7 text-emerald-600" />
                        {meeting.peserta.length} peserta hadir
                    </p>
                </div>
            )}
        </div>
    );
}

function EvidenPanel({
    meeting,
    unitId,
    maxEviden,
    canWrite,
}: {
    meeting: Meeting;
    unitId: number;
    maxEviden: number;
    canWrite: boolean;
}) {
    const [kept, setKept] = useState(meeting.eviden);
    const [uploads, setUploads] = useState<File[]>([]);
    const [saving, setSaving] = useState(false);
    const room = maxEviden - kept.length - uploads.length;
    const dirty = uploads.length > 0 || kept.length !== meeting.eviden.length;

    const save = () => {
        setSaving(true);
        router.post(
            dailyMeetingRoutes.store().url,
            {
                unit_id: unitId,
                id: meeting.id,
                acara: meeting.acara,
                tanggal: meeting.tanggal,
                waktu: meeting.waktu ?? '',
                tempat: meeting.tempat ?? '',
                update_eviden: 1,
                keep_eviden: kept.map((photo) => photo.path),
                eviden: uploads,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setUploads([]),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4">
            <div>
                <h3 className="text-base font-semibold text-foreground">
                    Foto Eviden
                </h3>
                <p className="text-xs text-muted-foreground">
                    Dicetak sebagai lembar ke-2 PDF (maks. {maxEviden} foto).
                </p>
            </div>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {kept.map((photo) => (
                    <span key={photo.path} className="relative">
                        <img
                            src={photo.url}
                            alt="Eviden meeting"
                            className="aspect-[4/3] w-full rounded-md object-cover"
                        />
                        {canWrite && (
                            <button
                                type="button"
                                onClick={() =>
                                    setKept((list) =>
                                        list.filter(
                                            (p) => p.path !== photo.path,
                                        ),
                                    )
                                }
                                className="absolute top-1 right-1 rounded-full bg-destructive p-1 text-white"
                                aria-label="Hapus foto"
                            >
                                <X className="size-3.5" />
                            </button>
                        )}
                    </span>
                ))}
                {uploads.map((file, i) => (
                    <span key={`${file.name}-${i}`} className="relative">
                        <img
                            src={URL.createObjectURL(file)}
                            alt={file.name}
                            className="aspect-[4/3] w-full rounded-md object-cover ring-2 ring-amber-400"
                        />
                        <button
                            type="button"
                            onClick={() =>
                                setUploads((list) =>
                                    list.filter((_, j) => j !== i),
                                )
                            }
                            className="absolute top-1 right-1 rounded-full bg-destructive p-1 text-white"
                            aria-label="Batalkan foto"
                        >
                            <X className="size-3.5" />
                        </button>
                    </span>
                ))}
                {canWrite && room > 0 && (
                    <label className="flex aspect-[4/3] w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed border-input text-xs text-muted-foreground hover:border-primary hover:text-primary">
                        <ImagePlus className="size-6" />
                        Tambah foto ({room})
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            className="sr-only"
                            onChange={(e) => {
                                const files = Array.from(
                                    e.target.files ?? [],
                                ).slice(0, room);
                                setUploads((list) => [...list, ...files]);
                                e.target.value = '';
                            }}
                        />
                    </label>
                )}
            </div>
            {kept.length === 0 && uploads.length === 0 && !canWrite && (
                <p className="text-sm text-muted-foreground">
                    Belum ada foto eviden.
                </p>
            )}
            {canWrite && (
                <Button
                    onClick={save}
                    disabled={saving || !dirty}
                    className="w-fit gap-1.5"
                >
                    <Save className="size-4" />
                    {saving ? 'Menyimpan…' : 'Simpan Foto'}
                </Button>
            )}
        </div>
    );
}

function FormField({
    label,
    icon,
    error,
    children,
}: {
    label: string;
    icon: ReactNode;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <span className="flex items-center gap-1.5 text-sm font-medium text-foreground">
                <span className="text-muted-foreground">{icon}</span>
                {label}
            </span>
            {children}
            {error && <span className="text-sm text-destructive">{error}</span>}
        </div>
    );
}

HarDailyMeetingPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir Pemeliharaan', href: harFormulir.index() },
        { title: 'Daily Meeting', href: dailyMeetingRoutes.index() },
    ],
};
