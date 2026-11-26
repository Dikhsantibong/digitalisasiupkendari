import { Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Clock,
    Download,
    FileSpreadsheet,
    ImagePlus,
    Save,
    Sparkles,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { DayStrip } from '@/components/mobile/day-strip';
import {
    EntryBadge,
    MobileEntryCard,
    MobileEntryList,
    MobileFormScreen,
} from '@/components/mobile/entry-flow';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { usePermissions } from '@/hooks/use-permissions';
import { downloadOperasi5s5rWorkbook } from '@/lib/operasi-5s5r-excel';
import type { Operasi5s5rExportSlot } from '@/lib/operasi-5s5r-excel';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import program5s5rRoutes from '@/routes/har/input/program-5s5r';
import type { IdName } from '@/types';

type TindakanKey =
    'membersihkan' | 'merapikan' | 'membuang_sampah' | 'mengecat' | 'lainnya';

type Row = {
    day: number;
    shift: string;
    minggu: number;
    program: string;
    detail: string | null;
    pic: string | null;
    kondisi_awal: string | null;
    progres: string | null;
    kondisi_akhir: string | null;
    jumlah: number | null;
    keterangan: string | null;
    saved: boolean;
} & Record<TindakanKey, boolean>;

type ShiftSlot = {
    day: number;
    date: string;
    date_label: string;
    short_label: string;
    weekday: string;
    minggu: number;
    shift: string;
    slot_key: string;
    saved: boolean;
    filled_count: number;
    rows: Row[];
    evidence: { id: number; url: string }[];
};

type DayItem = {
    day: number;
    date: string;
    date_label: string;
    short_label: string;
    weekday: string;
    minggu: number;
    saved: boolean;
    saved_shifts_count: number;
    saved_programs_count: number;
    shifts: ShiftSlot[];
};

type Props = {
    unit: { id: number; name: string; service_unit_name: string | null };
    filters: {
        unit_id: number;
        month: number;
        year: number;
        day: number;
        shift: string;
    };
    user_context: {
        shift: string;
        operator_name: string;
        regu: string | null;
    };
    options: {
        units: IdName[];
        years: number[];
        days_in_month: number;
        shifts: string[];
        programs: {
            key: string;
            label: string;
            istilah: string;
            detail: string;
        }[];
        tindakan: { key: TindakanKey; label: string }[];
        progres: string[];
        kondisi: string[];
        max_evidence: number;
    };
    days: DayItem[];
    has_saved: boolean;
    can_write: boolean;
};

const th = 'border border-black p-1 dark:border-white/60';
const td = 'h-8 border border-black dark:border-white/60';
const cellTextarea =
    'block min-h-16 w-full resize-y rounded-none border-0 bg-transparent px-1 py-1 text-xs focus:bg-yellow-50 focus-visible:ring-0 focus-visible:outline-none dark:focus:bg-yellow-950/40';

/**
 * Input Program 5S 5R Pemeliharaan (Modul HAR):
 * per shift per hari (Pagi, Sore, Malam) lima program (Ringkas … Rajin) dengan
 * kondisi, tindakan, progres, keterangan dan foto eviden per shift per hari.
 */
export default function HarProgram5s5rInput({
    unit,
    filters,
    user_context,
    options,
    days: initialDays,
    has_saved,
    can_write,
}: Props) {
    const [days, setDays] = useState<DayItem[]>(initialDays);
    const [prevInitialDays, setPrevInitialDays] =
        useState<DayItem[]>(initialDays);

    if (initialDays !== prevInitialDays) {
        setPrevInitialDays(initialDays);
        setDays(initialDays);
    }

    const [selectedDay, setSelectedDay] = useState<number>(
        filters.day || initialDays[0]?.day || 1,
    );
    const [selectedShift, setSelectedShift] = useState<string>(
        filters.shift || user_context?.shift || options.shifts[0] || 'Pagi',
    );
    const [uploads, setUploads] = useState<Record<string, File[]>>({});
    const [dirtySlots, setDirtySlots] = useState<Record<string, boolean>>({});
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [exportingExcel, setExportingExcel] = useState(false);
    const compact = useCompactLayout();
    // Phone: the list of filled shifts, or one shift's form on its own screen.
    const [formOpen, setFormOpen] = useState(false);
    const [openProgram, setOpenProgram] = useState<string | null>(null);
    const { can } = usePermissions();

    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const programs = Object.fromEntries(
        options.programs.map((p) => [p.key, p]),
    );

    const allSlots = days.flatMap((d) => d.shifts);
    const savedSlots = allSlots.filter(
        (s) => s.rows.some((r) => r.saved) || s.evidence.length > 0,
    );
    const totalFilledRows = allSlots
        .flatMap((s) => s.rows)
        .filter((r) => r.saved).length;

    const activeDayItem =
        days.find((d) => d.day === selectedDay) ?? days[0] ?? null;
    const activeSlot =
        activeDayItem?.shifts.find((s) => s.shift === selectedShift) ??
        activeDayItem?.shifts[0] ??
        null;

    const markSlotDirty = (slotKey: string) => {
        setDirtySlots((current) => ({ ...current, [slotKey]: true }));
        setDirty(true);
    };

    const visit = (patch: Partial<Props['filters']>) => {
        if (
            dirty &&
            !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')
        ) {
            return;
        }

        router.get(
            program5s5rRoutes.index().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                day: selectedDay,
                shift: selectedShift,
                ...patch,
            },
            { preserveScroll: true },
        );
    };

    const setRow = (
        day: number,
        shift: string,
        program: string,
        patch: Partial<Row>,
    ) => {
        const slotKey = `${day}_${shift}`;
        setDays((current) =>
            current.map((d) =>
                d.day !== day
                    ? d
                    : {
                          ...d,
                          shifts: d.shifts.map((s) =>
                              s.shift !== shift
                                  ? s
                                  : {
                                        ...s,
                                        rows: s.rows.map((r) =>
                                            r.program === program
                                                ? { ...r, ...patch }
                                                : r,
                                        ),
                                    },
                          ),
                      },
            ),
        );
        markSlotDirty(slotKey);
    };

    const fillStandardForActiveSlot = () => {
        if (!activeSlot || !can_write) {
            return;
        }

        const defaultPic = user_context.operator_name
            ? `${user_context.operator_name} (Shift ${activeSlot.shift})`
            : 'K3L, Operasi Dan Pemeliharaan';

        setDays((current) =>
            current.map((d) =>
                d.day !== activeSlot.day
                    ? d
                    : {
                          ...d,
                          shifts: d.shifts.map((s) =>
                              s.shift !== activeSlot.shift
                                  ? s
                                  : {
                                        ...s,
                                        rows: s.rows.map((r) => ({
                                            ...r,
                                            pic: r.pic || defaultPic,
                                            kondisi_awal:
                                                r.kondisi_awal ?? 'Baik',
                                            membersihkan: true,
                                            merapikan: true,
                                            membuang_sampah:
                                                r.program === 'resik'
                                                    ? true
                                                    : r.membuang_sampah,
                                            progres: r.progres ?? '76-100',
                                            kondisi_akhir:
                                                r.kondisi_akhir ?? 'Baik',
                                            keterangan:
                                                r.keterangan ??
                                                `Terlaksana rutin Shift ${activeSlot.shift}`,
                                        })),
                                    },
                          ),
                      },
            ),
        );
        markSlotDirty(activeSlot.slot_key);
    };

    const removeSavedPhoto = (slotKey: string, id: number) => {
        setDays((current) =>
            current.map((d) => ({
                ...d,
                shifts: d.shifts.map((s) =>
                    s.slot_key !== slotKey
                        ? s
                        : {
                              ...s,
                              evidence: s.evidence.filter((p) => p.id !== id),
                          },
                ),
            })),
        );
        markSlotDirty(slotKey);
    };

    const addPhotos = (
        slotKey: string,
        files: FileList | null,
        room: number,
    ) => {
        if (!files) {
            return;
        }

        setUploads((current) => ({
            ...current,
            [slotKey]: [
                ...(current[slotKey] ?? []),
                ...Array.from(files),
            ].slice(0, room),
        }));
        markSlotDirty(slotKey);
    };

    const removeUpload = (slotKey: string, index: number) => {
        setUploads((current) => ({
            ...current,
            [slotKey]: (current[slotKey] ?? []).filter((_, i) => i !== index),
        }));
        markSlotDirty(slotKey);
    };

    const save = (onSaved?: () => void) => {
        if (!activeSlot) {
            return;
        }

        setSaving(true);
        const touchedKeys = Array.from(
            new Set([
                ...Object.keys(dirtySlots),
                ...Object.keys(uploads),
                activeSlot.slot_key,
            ]),
        );
        const touchedSlots = allSlots.filter((s) =>
            touchedKeys.includes(s.slot_key),
        );

        router.post(
            program5s5rRoutes.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: touchedSlots.flatMap((slot) =>
                    slot.rows.map((row) => {
                        const { saved, ...values } = row; // eslint-disable-line @typescript-eslint/no-unused-vars

                        return values;
                    }),
                ),
                synced_slots: touchedKeys,
                keep_evidence: Object.fromEntries(
                    touchedSlots.map((slot) => [
                        slot.slot_key,
                        slot.evidence.map((p) => p.id),
                    ]),
                ),
                evidence: uploads,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    const nextProps = page.props as unknown as Props;
                    setDays(nextProps.days);
                    setUploads({});
                    setDirtySlots({});
                    setDirty(false);
                    onSaved?.();
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const pdfUrl = (download: boolean) =>
        program5s5rRoutes.pdf({
            query: {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                ...(download ? { download: 1 } : {}),
            },
        }).url;

    const handleExportExcel = async () => {
        setExportingExcel(true);

        try {
            const slotsForExport =
                savedSlots.length > 0
                    ? savedSlots
                    : activeSlot
                      ? [activeSlot]
                      : [];
            const exportSlots: Operasi5s5rExportSlot[] = slotsForExport.map(
                (s) => ({
                    day: s.day,
                    dateLabel: s.date_label,
                    weekday: s.weekday,
                    shift: s.shift,
                    rows: s.rows.map((r) => ({
                        day: r.day,
                        shift: r.shift,
                        programKey: r.program,
                        programLabel: programs[r.program]?.label ?? r.program,
                        programIstilah: programs[r.program]?.istilah ?? '',
                        detail: r.detail,
                        pic: r.pic,
                        kondisi_awal: r.kondisi_awal,
                        membersihkan: r.membersihkan,
                        merapikan: r.merapikan,
                        membuang_sampah: r.membuang_sampah,
                        mengecat: r.mengecat,
                        lainnya: r.lainnya,
                        progres: r.progres,
                        kondisi_akhir: r.kondisi_akhir,
                        jumlah: r.jumlah,
                        keterangan: r.keterangan,
                    })),
                }),
            );

            const safeUnit = unit.name.replace(/[^a-zA-Z0-9_-]/g, '_');
            const safeMonth = String(filters.month).padStart(2, '0');
            const filename = `Program_5S5R_Pemeliharaan_${safeUnit}_${safeMonth}_${filters.year}.xlsx`;

            await downloadOperasi5s5rWorkbook(
                unit.name,
                `Bulan ${periodLabel}`,
                exportSlots,
                filename,
                'JADWAL PROGRAM 5S 5R PEMELIHARAAN',
            );
        } finally {
            setExportingExcel(false);
        }
    };

    const pending = activeSlot ? (uploads[activeSlot.slot_key] ?? []) : [];
    const room = activeSlot
        ? options.max_evidence - activeSlot.evidence.length
        : 0;

    if (compact) {
        return (
            <>
                <Head title={`Program 5S 5R Pemeliharaan - ${unit.name}`} />
                {!formOpen ? (
                    <MobileEntryList
                        heading={`Program 5S 5R ${periodLabel}`}
                        count={savedSlots.length}
                        empty={`Belum ada Program 5S 5R ${periodLabel}. Tekan Tambah untuk mengisi shift Anda.`}
                        canWrite={can_write}
                        onAdd={() => {
                            const now = new Date();

                            if (now.getFullYear() === filters.year && now.getMonth() + 1 === filters.month && days.some((d) => d.day === now.getDate())) {
                                setSelectedDay(now.getDate());
                            }

                            if (user_context?.shift) {
                                setSelectedShift(user_context.shift);
                            }

                            setFormOpen(true);
                        }}
                        onSave={() => save()}
                        saving={saving}
                        dirty={dirty}
                    >
                        {savedSlots.map((slot) => (
                            <MobileEntryCard
                                key={slot.slot_key}
                                title={`${slot.weekday}, ${slot.date_label}`}
                                subtitle={`Shift ${slot.shift}`}
                                badge={
                                    <EntryBadge tone={slot.rows.filter((r) => r.saved).length >= 5 ? 'done' : 'open'}>
                                        {slot.rows.filter((r) => r.saved).length}/5 terisi
                                    </EntryBadge>
                                }
                                meta={[['Foto eviden', `${slot.evidence.length} foto`]]}
                                onClick={() => {
                                    setSelectedDay(slot.day);
                                    setSelectedShift(slot.shift);
                                    setFormOpen(true);
                                }}
                            />
                        ))}
                    </MobileEntryList>
                ) : (
                    <MobileFormScreen
                        title={activeSlot ? `${activeSlot.date_label} · Shift ${activeSlot.shift}` : 'Program 5S 5R'}
                        subtitle="Program 5S 5R"
                        onBack={() => setFormOpen(false)}
                        canWrite={can_write}
                        onSave={() => save(() => setFormOpen(false))}
                        saving={saving}
                        saveDisabled={!dirty}
                    >
                    <DayStrip
                        items={days.map((d) => ({
                            key: String(d.day),
                            label: String(d.day),
                            sub: d.weekday,
                            done: d.shifts.some(
                                (s) =>
                                    s.rows.some((r) => r.saved) ||
                                    s.evidence.length > 0,
                            ),
                        }))}
                        value={String(activeDayItem?.day ?? '')}
                        onChange={(key) => setSelectedDay(Number(key))}
                    />

                    {/* Pilihan Shift Harian */}
                    <div className="grid grid-cols-3 gap-2">
                        {options.shifts.map((shiftName) => {
                            const slot = activeDayItem?.shifts.find(
                                (s) => s.shift === shiftName,
                            );
                            const isSelected = selectedShift === shiftName;
                            const isDone =
                                (slot?.rows.some((r) => r.saved) ?? false) ||
                                (slot?.evidence.length ?? 0) > 0;

                            return (
                                <button
                                    key={shiftName}
                                    type="button"
                                    onClick={() => setSelectedShift(shiftName)}
                                    className={`flex flex-col items-center justify-center rounded-xl border px-2.5 py-2 text-center transition active:scale-95 ${
                                        isSelected
                                            ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                                            : 'border-border bg-card text-foreground'
                                    }`}
                                >
                                    <span className="flex items-center gap-1 text-[13px] font-semibold">
                                        Shift {shiftName}
                                        {isDone && (
                                            <CheckCircle2
                                                className={`size-3.5 ${
                                                    isSelected
                                                        ? 'text-primary-foreground'
                                                        : 'text-emerald-600'
                                                }`}
                                            />
                                        )}
                                    </span>
                                    <span
                                        className={`text-[11px] ${
                                            isSelected
                                                ? 'text-primary-foreground/85'
                                                : 'text-muted-foreground'
                                        }`}
                                    >
                                        {isDone
                                            ? `${slot?.rows.filter((r) => r.saved).length ?? 0}/5 terisi`
                                            : 'Belum diisi'}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    {activeSlot && (
                        <div className="flex items-center justify-between gap-2 rounded-xl border border-primary/20 bg-primary/5 px-3 py-2">
                            <div className="min-w-0">
                                <p className="text-[13px] font-semibold text-foreground">
                                    {activeSlot.weekday},{' '}
                                    {activeSlot.date_label} · Shift{' '}
                                    {activeSlot.shift}
                                </p>
                                <p className="truncate text-[11px] text-muted-foreground">
                                    Petugas:{' '}
                                    {user_context.operator_name ||
                                        'Pemeliharaan'}
                                </p>
                            </div>
                            {can_write && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="secondary"
                                    onClick={fillStandardForActiveSlot}
                                    className="h-8 shrink-0 gap-1 text-xs"
                                >
                                    <Sparkles className="size-3.5" />
                                    Isi Standar
                                </Button>
                            )}
                        </div>
                    )}

                    {activeSlot?.rows.map((row) => {
                        const program = programs[row.program];
                        const isOpen = openProgram === row.program;
                        const summary = [
                            row.kondisi_awal && row.kondisi_akhir
                                ? `${row.kondisi_awal} → ${row.kondisi_akhir}`
                                : row.kondisi_awal,
                            row.progres && `Progres ${row.progres}%`,
                            row.pic && `PIC ${row.pic}`,
                        ]
                            .filter(Boolean)
                            .join(' · ');

                        return (
                            <div
                                key={`${activeSlot.slot_key}-${row.program}`}
                                className={`flex flex-col gap-3 rounded-xl border bg-card p-3 ${isOpen ? 'border-primary/50' : 'border-border'}`}
                            >
                                <button
                                    type="button"
                                    onClick={() =>
                                        setOpenProgram(
                                            isOpen ? null : row.program,
                                        )
                                    }
                                    className="flex items-start gap-2 text-left"
                                    aria-expanded={isOpen}
                                >
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-[14px] font-semibold text-foreground">
                                            {program?.label}
                                        </span>
                                        <span className="block text-[12px] text-muted-foreground italic">
                                            {program?.istilah}
                                        </span>
                                        {!isOpen && (
                                            <span className="mt-0.5 block truncate text-[12px] text-muted-foreground not-italic">
                                                {summary ||
                                                    'Belum diisi — ketuk untuk mengisi'}
                                            </span>
                                        )}
                                    </span>
                                    <ChevronDown
                                        className={`mt-0.5 size-4 shrink-0 text-muted-foreground transition ${isOpen ? 'rotate-180' : ''}`}
                                    />
                                </button>
                                {isOpen && (
                                    <>
                                        <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                            Detail
                                            <Textarea
                                                value={row.detail ?? ''}
                                                onChange={(e) =>
                                                    setRow(
                                                        activeSlot.day,
                                                        activeSlot.shift,
                                                        row.program,
                                                        {
                                                            detail: e.target
                                                                .value,
                                                        },
                                                    )
                                                }
                                                rows={2}
                                                disabled={!can_write}
                                            />
                                        </label>
                                        <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                            PIC / Pelaksana Shift
                                            <Input
                                                value={row.pic ?? ''}
                                                onChange={(e) =>
                                                    setRow(
                                                        activeSlot.day,
                                                        activeSlot.shift,
                                                        row.program,
                                                        { pic: e.target.value },
                                                    )
                                                }
                                                disabled={!can_write}
                                            />
                                        </label>
                                        <div className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                            Kondisi awal
                                            <ChoiceChips
                                                options={options.kondisi}
                                                value={row.kondisi_awal ?? ''}
                                                onChange={(v) =>
                                                    setRow(
                                                        activeSlot.day,
                                                        activeSlot.shift,
                                                        row.program,
                                                        {
                                                            kondisi_awal:
                                                                v || null,
                                                        },
                                                    )
                                                }
                                                disabled={!can_write}
                                            />
                                        </div>
                                        <div className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                            Tindakan
                                            <div className="flex flex-wrap gap-1.5">
                                                {options.tindakan.map((t) => (
                                                    <button
                                                        key={t.key}
                                                        type="button"
                                                        role="checkbox"
                                                        aria-checked={
                                                            row[t.key]
                                                        }
                                                        onClick={() =>
                                                            setRow(
                                                                activeSlot.day,
                                                                activeSlot.shift,
                                                                row.program,
                                                                {
                                                                    [t.key]:
                                                                        !row[
                                                                            t
                                                                                .key
                                                                        ],
                                                                },
                                                            )
                                                        }
                                                        disabled={!can_write}
                                                        className={`flex min-h-9 items-center gap-1.5 rounded-lg border px-3 text-[13px] font-medium transition active:scale-95 disabled:opacity-60 ${
                                                            row[t.key]
                                                                ? 'border-primary bg-primary text-primary-foreground'
                                                                : 'border-border bg-background text-foreground'
                                                        }`}
                                                    >
                                                        {row[t.key] && (
                                                            <Check className="size-3.5" />
                                                        )}
                                                        {t.label}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                        <div className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                            Progres
                                            <ChoiceChips
                                                options={options.progres}
                                                value={row.progres ?? ''}
                                                onChange={(v) =>
                                                    setRow(
                                                        activeSlot.day,
                                                        activeSlot.shift,
                                                        row.program,
                                                        {
                                                            progres: v || null,
                                                        },
                                                    )
                                                }
                                                disabled={!can_write}
                                            />
                                        </div>
                                        <div className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                            Kondisi akhir
                                            <ChoiceChips
                                                options={options.kondisi}
                                                value={row.kondisi_akhir ?? ''}
                                                onChange={(v) =>
                                                    setRow(
                                                        activeSlot.day,
                                                        activeSlot.shift,
                                                        row.program,
                                                        {
                                                            kondisi_akhir:
                                                                v || null,
                                                        },
                                                    )
                                                }
                                                disabled={!can_write}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                                Keterangan
                                                <Input
                                                    value={row.keterangan ?? ''}
                                                    onChange={(e) =>
                                                        setRow(
                                                            activeSlot.day,
                                                            activeSlot.shift,
                                                            row.program,
                                                            {
                                                                keterangan:
                                                                    e.target
                                                                        .value,
                                                            },
                                                        )
                                                    }
                                                    disabled={!can_write}
                                                />
                                            </label>
                                        </div>
                                    </>
                                )}
                            </div>
                        );
                    })}

                    {activeSlot && (
                        <div className="flex flex-col gap-2 rounded-xl border border-border bg-card p-3">
                            <p className="text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                                Foto eviden {activeSlot.date_label} · Shift{' '}
                                {activeSlot.shift}
                            </p>
                            <div className="grid grid-cols-2 gap-2">
                                {activeSlot.evidence.map((photo) => (
                                    <span key={photo.id} className="relative">
                                        <img
                                            src={photo.url}
                                            alt={`Eviden ${activeSlot.date_label} Shift ${activeSlot.shift}`}
                                            className="aspect-video w-full rounded-lg object-cover"
                                        />
                                        {can_write && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeSavedPhoto(
                                                        activeSlot.slot_key,
                                                        photo.id,
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
                                {pending.map((file, i) => (
                                    <span
                                        key={`${file.name}-${i}`}
                                        className="relative"
                                    >
                                        <img
                                            src={URL.createObjectURL(file)}
                                            alt={file.name}
                                            className="aspect-video w-full rounded-lg object-cover ring-2 ring-amber-400"
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                removeUpload(
                                                    activeSlot.slot_key,
                                                    i,
                                                )
                                            }
                                            className="absolute top-1 right-1 rounded-full bg-destructive p-1 text-white"
                                            aria-label="Batalkan foto"
                                        >
                                            <X className="size-3.5" />
                                        </button>
                                    </span>
                                ))}
                            </div>
                            {can_write && room - pending.length > 0 && (
                                <label className="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-input text-[13px] text-muted-foreground active:bg-muted">
                                    <ImagePlus className="size-4" />
                                    Ambil / pilih foto ({room -
                                        pending.length}{' '}
                                    tersisa)
                                    <input
                                        type="file"
                                        accept="image/*"
                                        multiple
                                        className="sr-only"
                                        onChange={(e) => {
                                            addPhotos(
                                                activeSlot.slot_key,
                                                e.target.files,
                                                room,
                                            );
                                            e.target.value = '';
                                        }}
                                    />
                                </label>
                            )}
                        </div>
                    )}
                    </MobileFormScreen>
                )}
            </>
        );
    }

    return (
        <>
            <Head title={`Program 5S 5R Pemeliharaan - ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Program 5S 5R Pemeliharaan"
                    description={`Pelaksanaan Ringkas, Rapi, Resik, Rawat, Rajin per shift per hari — ${unit.name} · ${periodLabel}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {can('har.input.view') && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.get(harInput.index().url)
                                    }
                                >
                                    Kembali
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                onClick={handleExportExcel}
                                disabled={exportingExcel}
                                className="gap-1.5"
                            >
                                <FileSpreadsheet className="size-4 text-emerald-600" />
                                {exportingExcel ? 'Mengunduh…' : 'Excel'}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    window.open(pdfUrl(false), '_blank')
                                }
                                className="gap-1.5"
                            >
                                <Download className="size-4 text-rose-600" />
                                PDF
                            </Button>
                            {can_write && (
                                <Button
                                    onClick={() => save()}
                                    disabled={saving || !dirty}
                                    className="gap-1.5"
                                >
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end justify-between gap-3 rounded-md border border-border bg-card p-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <OperasiSelect
                            label="Unit"
                            value={String(filters.unit_id)}
                            onChange={(value) =>
                                visit({ unit_id: Number(value) })
                            }
                            options={options.units.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            }))}
                        />
                        <OperasiSelect
                            label="Bulan"
                            value={String(filters.month)}
                            onChange={(value) =>
                                visit({ month: Number(value) })
                            }
                            options={OPERASI_MONTHS.map((label, index) => ({
                                value: String(index + 1),
                                label,
                            }))}
                        />
                        <OperasiSelect
                            label="Tahun"
                            value={String(filters.year)}
                            onChange={(value) => visit({ year: Number(value) })}
                            options={options.years.map((y) => ({
                                value: String(y),
                                label: String(y),
                            }))}
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2 text-[13px]">
                        {user_context.operator_name && (
                            <Badge variant="outline" className="gap-1.5 py-1">
                                <Clock className="size-3.5 text-primary" />
                                {user_context.operator_name}
                                {` · Shift ${user_context.shift}`}
                            </Badge>
                        )}
                        {dirty ? (
                            <StatusBadge tone="warning">
                                Ada perubahan belum disimpan
                            </StatusBadge>
                        ) : has_saved || savedSlots.length > 0 ? (
                            <StatusBadge tone="success">
                                {savedSlots.length} shift terisi (
                                {totalFilledRows} program)
                            </StatusBadge>
                        ) : (
                            <StatusBadge tone="neutral">
                                Belum ada data
                            </StatusBadge>
                        )}
                    </div>
                </div>

                {/* Navigator Hari (1-31) & Shift (Pagi, Sore, Malam) */}
                <div className="flex flex-col gap-3 rounded-md border border-border bg-card p-3.5">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <CalendarDays className="size-4 text-primary" />
                            <span className="text-xs font-semibold tracking-wide text-foreground uppercase">
                                Pilih Tanggal Pelaksanaan ({periodLabel})
                            </span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 px-2 text-xs"
                                disabled={selectedDay <= 1}
                                onClick={() =>
                                    setSelectedDay((d) => Math.max(1, d - 1))
                                }
                            >
                                <ChevronLeft className="size-3.5" />
                                Sebelumnya
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-7 px-2 text-xs"
                                disabled={selectedDay >= options.days_in_month}
                                onClick={() =>
                                    setSelectedDay((d) =>
                                        Math.min(options.days_in_month, d + 1),
                                    )
                                }
                            >
                                Berikutnya
                                <ChevronRight className="size-3.5" />
                            </Button>
                        </div>
                    </div>

                    <div className="flex gap-1.5 overflow-x-auto pb-1">
                        {days.map((d) => {
                            const filledShifts = d.shifts.filter(
                                (s) =>
                                    s.rows.some((r) => r.saved) ||
                                    s.evidence.length > 0,
                            ).length;
                            const isSelected = d.day === selectedDay;

                            return (
                                <button
                                    key={d.day}
                                    type="button"
                                    onClick={() => setSelectedDay(d.day)}
                                    className={`flex min-w-13 shrink-0 flex-col items-center rounded-lg border px-2 py-1.5 text-center transition ${
                                        isSelected
                                            ? 'border-primary bg-primary text-primary-foreground shadow-xs'
                                            : filledShifts > 0
                                              ? 'border-emerald-500/40 bg-emerald-500/10 text-foreground hover:border-emerald-500'
                                              : 'border-border bg-background text-foreground hover:border-primary/50'
                                    }`}
                                >
                                    <span
                                        className={`text-[10px] font-medium ${
                                            isSelected
                                                ? 'text-primary-foreground/85'
                                                : 'text-muted-foreground'
                                        }`}
                                    >
                                        {d.weekday}
                                    </span>
                                    <span className="text-sm font-bold tabular-nums">
                                        {String(d.day).padStart(2, '0')}
                                    </span>
                                    <span
                                        className={`mt-0.5 text-[10px] font-semibold ${
                                            isSelected
                                                ? 'text-primary-foreground'
                                                : filledShifts > 0
                                                  ? 'text-emerald-600 dark:text-emerald-400'
                                                  : 'text-muted-foreground/60'
                                        }`}
                                    >
                                        {filledShifts}/3
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <div className="grid gap-2.5 pt-1 sm:grid-cols-3">
                        {options.shifts.map((shiftName) => {
                            const slot = activeDayItem?.shifts.find(
                                (s) => s.shift === shiftName,
                            );
                            const isSelected = selectedShift === shiftName;
                            const savedCount =
                                slot?.rows.filter((r) => r.saved).length ?? 0;
                            const photoCount =
                                (slot?.evidence.length ?? 0) +
                                (slot
                                    ? (uploads[slot.slot_key]?.length ?? 0)
                                    : 0);
                            const isDone = savedCount > 0 || photoCount > 0;
                            const isUserShift =
                                user_context.shift === shiftName;

                            return (
                                <button
                                    key={shiftName}
                                    type="button"
                                    onClick={() => setSelectedShift(shiftName)}
                                    className={`flex items-center justify-between rounded-lg border p-3 text-left transition ${
                                        isSelected
                                            ? 'border-primary bg-primary/10 ring-1 ring-primary'
                                            : 'border-border bg-background hover:border-primary/40'
                                    }`}
                                >
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-bold text-foreground">
                                                Shift {shiftName}
                                            </span>
                                            {isUserShift && (
                                                <Badge
                                                    variant="secondary"
                                                    className="px-1.5 py-0 text-[10px]"
                                                >
                                                    Shift Aktif
                                                </Badge>
                                            )}
                                        </div>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {activeDayItem?.weekday},{' '}
                                            {activeDayItem?.date_label}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        {isDone ? (
                                            <Badge className="bg-emerald-600 text-white hover:bg-emerald-600">
                                                {savedCount}/5 Program
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline">
                                                Belum diisi
                                            </Badge>
                                        )}
                                        {photoCount > 0 && (
                                            <p className="mt-1 text-[11px] font-medium text-muted-foreground">
                                                {photoCount} foto eviden
                                            </p>
                                        )}
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* Kop Surat */}
                <div className="rounded-md border border-border bg-muted/10 p-4 text-center">
                    <div className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                        Jasa Pendukung Teknik 6 Site UP Kendari
                    </div>
                    <div className="text-sm font-semibold text-foreground uppercase">
                        {unit.name}
                    </div>
                    <div className="text-xs text-muted-foreground uppercase">
                        Laporan Project
                    </div>
                    <div className="mt-0.5 text-base font-bold tracking-tight text-foreground uppercase">
                        Jadwal Program 5S 5R Pemeliharaan
                    </div>
                    <div className="mt-1 flex flex-wrap items-center justify-center gap-2 text-xs text-muted-foreground">
                        <span>Periode {periodLabel}</span>
                        {activeSlot && (
                            <Badge variant="default" className="text-xs">
                                {activeSlot.weekday}, {activeSlot.date_label} —
                                Shift {activeSlot.shift} (Minggu ke-
                                {activeSlot.minggu})
                            </Badge>
                        )}
                        {can_write && activeSlot && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={fillStandardForActiveSlot}
                                className="h-7 gap-1.5 text-xs"
                            >
                                <Sparkles className="size-3.5 text-primary" />
                                Isi Standar Shift {activeSlot.shift}
                            </Button>
                        )}
                    </div>
                </div>

                {/* Tabel Input Pemeliharaan (Excel look per .ai/rules/pages-har.md) */}
                {activeSlot && (
                    <div className="overflow-x-auto bg-card">
                        <table className="w-full min-w-[1400px] border-collapse border-2 border-black text-xs dark:border-white">
                            <thead className="bg-yellow-200 text-center text-[11px] font-bold text-black uppercase dark:bg-yellow-800 dark:text-yellow-50">
                                <tr>
                                    <th className={th} colSpan={3}>
                                        Program Kerja 5S 5R
                                    </th>
                                    <th className={`${th} w-36`} rowSpan={2}>
                                        PIC
                                    </th>
                                    <th className={`${th} w-28`} rowSpan={2}>
                                        Kondisi Awal
                                    </th>
                                    <th
                                        className={th}
                                        colSpan={options.tindakan.length}
                                    >
                                        Tindakan
                                    </th>
                                    <th className={`${th} w-28`} rowSpan={2}>
                                        Progres
                                    </th>
                                    <th className={`${th} w-28`} rowSpan={2}>
                                        Kondisi Akhir
                                    </th>
                                    <th className={`${th} w-44`} rowSpan={2}>
                                        Keterangan
                                    </th>
                                    <th className={`${th} w-48`} rowSpan={2}>
                                        Eviden Shift
                                    </th>
                                </tr>
                                <tr>
                                    <th className={`${th} w-28`}>
                                        Tanggal / Shift
                                    </th>
                                    <th className={`${th} w-24`}>Uraian</th>
                                    <th className={`${th} min-w-56`}>Detail</th>
                                    {options.tindakan.map((t) => (
                                        <th
                                            key={t.key}
                                            className={`${th} w-20`}
                                        >
                                            {t.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {activeSlot.rows.map((row, index) => (
                                    <tr
                                        key={`${activeSlot.slot_key}-${row.program}`}
                                        className="align-middle hover:bg-muted/30"
                                    >
                                        {index === 0 && (
                                            <td
                                                className={`${td} bg-muted/30 p-2 text-center font-semibold`}
                                                rowSpan={activeSlot.rows.length}
                                            >
                                                <div className="text-xs font-bold text-foreground">
                                                    {activeSlot.weekday},{' '}
                                                    {activeSlot.date_label}
                                                </div>
                                                <Badge
                                                    variant="secondary"
                                                    className="mt-1.5"
                                                >
                                                    Shift {activeSlot.shift}
                                                </Badge>
                                                <div className="mt-1 text-[11px] font-normal text-muted-foreground">
                                                    Minggu ke-
                                                    {activeSlot.minggu}
                                                </div>
                                            </td>
                                        )}
                                        <td
                                            className={`${td} px-1 text-center`}
                                        >
                                            <div className="font-medium">
                                                {programs[row.program]?.label}
                                            </div>
                                            <div className="text-[11px] text-muted-foreground italic">
                                                (
                                                {programs[row.program]?.istilah}
                                                )
                                            </div>
                                        </td>
                                        <td className={`${td} p-0`}>
                                            <textarea
                                                value={row.detail ?? ''}
                                                onChange={(e) =>
                                                    setRow(
                                                        activeSlot.day,
                                                        activeSlot.shift,
                                                        row.program,
                                                        {
                                                            detail: e.target
                                                                .value,
                                                        },
                                                    )
                                                }
                                                className={cellTextarea}
                                                disabled={!can_write}
                                                aria-label={`Detail ${programs[row.program]?.label} tanggal ${activeSlot.day} shift ${activeSlot.shift}`}
                                            />
                                        </td>
                                        <td className={`${td} p-0`}>
                                            <textarea
                                                value={row.pic ?? ''}
                                                onChange={(e) =>
                                                    setRow(
                                                        activeSlot.day,
                                                        activeSlot.shift,
                                                        row.program,
                                                        { pic: e.target.value },
                                                    )
                                                }
                                                className={`${cellTextarea} text-center`}
                                                disabled={!can_write}
                                                aria-label={`PIC ${programs[row.program]?.label} tanggal ${activeSlot.day} shift ${activeSlot.shift}`}
                                            />
                                        </td>
                                        <td className={`${td} p-0`}>
                                            <PdmCellSelect
                                                value={row.kondisi_awal ?? ''}
                                                onChange={(v) =>
                                                    setRow(
                                                        activeSlot.day,
                                                        activeSlot.shift,
                                                        row.program,
                                                        {
                                                            kondisi_awal:
                                                                v || null,
                                                        },
                                                    )
                                                }
                                                options={options.kondisi}
                                                className="justify-center"
                                                disabled={!can_write}
                                            />
                                        </td>
                                        {options.tindakan.map((t) => (
                                            <td
                                                key={t.key}
                                                className={`${td} text-center`}
                                            >
                                                <button
                                                    type="button"
                                                    role="checkbox"
                                                    aria-checked={row[t.key]}
                                                    aria-label={`${t.label} ${programs[row.program]?.label} tanggal ${activeSlot.day} shift ${activeSlot.shift}`}
                                                    onClick={() =>
                                                        setRow(
                                                            activeSlot.day,
                                                            activeSlot.shift,
                                                            row.program,
                                                            {
                                                                [t.key]:
                                                                    !row[t.key],
                                                            },
                                                        )
                                                    }
                                                    disabled={!can_write}
                                                    className={`mx-auto flex size-5 items-center justify-center rounded border transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${
                                                        row[t.key]
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'border-input bg-background hover:border-primary'
                                                    }`}
                                                >
                                                    {row[t.key] && (
                                                        <Check className="size-3.5" />
                                                    )}
                                                </button>
                                            </td>
                                        ))}
                                        <td className={`${td} p-0`}>
                                            <PdmCellSelect
                                                value={row.progres ?? ''}
                                                onChange={(v) =>
                                                    setRow(
                                                        activeSlot.day,
                                                        activeSlot.shift,
                                                        row.program,
                                                        { progres: v || null },
                                                    )
                                                }
                                                options={options.progres}
                                                className="justify-center"
                                                disabled={!can_write}
                                            />
                                        </td>
                                        <td className={`${td} p-0`}>
                                            <PdmCellSelect
                                                value={row.kondisi_akhir ?? ''}
                                                onChange={(v) =>
                                                    setRow(
                                                        activeSlot.day,
                                                        activeSlot.shift,
                                                        row.program,
                                                        {
                                                            kondisi_akhir:
                                                                v || null,
                                                        },
                                                    )
                                                }
                                                options={options.kondisi}
                                                className="justify-center"
                                                disabled={!can_write}
                                            />
                                        </td>
                                        <td className={`${td} p-0`}>
                                            <textarea
                                                value={row.keterangan ?? ''}
                                                onChange={(e) =>
                                                    setRow(
                                                        activeSlot.day,
                                                        activeSlot.shift,
                                                        row.program,
                                                        {
                                                            keterangan:
                                                                e.target.value,
                                                        },
                                                    )
                                                }
                                                className={cellTextarea}
                                                disabled={!can_write}
                                                aria-label={`Keterangan ${programs[row.program]?.label} tanggal ${activeSlot.day} shift ${activeSlot.shift}`}
                                            />
                                        </td>
                                        {index === 0 && (
                                            <td
                                                className={`${td} p-2 align-top`}
                                                rowSpan={activeSlot.rows.length}
                                            >
                                                <div className="flex flex-col items-center gap-2">
                                                    {activeSlot.evidence.map(
                                                        (photo) => (
                                                            <span
                                                                key={photo.id}
                                                                className="relative"
                                                            >
                                                                <img
                                                                    src={
                                                                        photo.url
                                                                    }
                                                                    alt={`Eviden ${activeSlot.date_label} Shift ${activeSlot.shift}`}
                                                                    className="h-24 w-36 rounded object-cover"
                                                                />
                                                                {can_write && (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() =>
                                                                            removeSavedPhoto(
                                                                                activeSlot.slot_key,
                                                                                photo.id,
                                                                            )
                                                                        }
                                                                        className="absolute -top-1 -right-1 rounded-full bg-destructive p-0.5 text-white"
                                                                        aria-label="Hapus foto"
                                                                    >
                                                                        <X className="size-3" />
                                                                    </button>
                                                                )}
                                                            </span>
                                                        ),
                                                    )}
                                                    {pending.map((file, i) => (
                                                        <span
                                                            key={`${file.name}-${i}`}
                                                            className="relative"
                                                        >
                                                            <img
                                                                src={URL.createObjectURL(
                                                                    file,
                                                                )}
                                                                alt={file.name}
                                                                className="h-24 w-36 rounded object-cover ring-2 ring-amber-400"
                                                            />
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    removeUpload(
                                                                        activeSlot.slot_key,
                                                                        i,
                                                                    )
                                                                }
                                                                className="absolute -top-1 -right-1 rounded-full bg-destructive p-0.5 text-white"
                                                                aria-label="Batalkan foto"
                                                            >
                                                                <X className="size-3" />
                                                            </button>
                                                        </span>
                                                    ))}
                                                    {can_write &&
                                                        room - pending.length >
                                                            0 && (
                                                            <label className="flex cursor-pointer items-center gap-1 rounded border border-dashed border-input px-2 py-1.5 text-[11px] text-muted-foreground hover:border-primary hover:text-primary">
                                                                <ImagePlus className="size-3.5" />
                                                                Tambah foto (
                                                                {room -
                                                                    pending.length}
                                                                )
                                                                <input
                                                                    type="file"
                                                                    accept="image/*"
                                                                    multiple
                                                                    className="sr-only"
                                                                    onChange={(
                                                                        e,
                                                                    ) => {
                                                                        addPhotos(
                                                                            activeSlot.slot_key,
                                                                            e
                                                                                .target
                                                                                .files,
                                                                            room,
                                                                        );
                                                                        e.target.value =
                                                                            '';
                                                                    }}
                                                                />
                                                            </label>
                                                        )}
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Rekap Pelaksanaan Shift Bulan Ini */}
                <div className="rounded-md border border-border bg-card p-4">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 className="text-sm font-bold text-foreground">
                                Rekap Pelaksanaan 5S 5R Pemeliharaan Per Shift
                                Bulan Ini
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Klik salah satu baris untuk membuka atau
                                mengubah detail pelaksanaan pada tanggal & shift
                                tersebut.
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {savedSlots.length} Shift Terlaksana
                        </Badge>
                    </div>

                    {savedSlots.length === 0 ? (
                        <p className="py-4 text-center text-xs text-muted-foreground">
                            Belum ada pelaksanaan 5S 5R Pemeliharaan yang
                            disimpan pada periode {periodLabel}.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse text-xs">
                                <thead className="bg-muted/50 text-left text-[11px] font-semibold text-muted-foreground uppercase">
                                    <tr>
                                        <th className="border-b border-border px-3 py-2">
                                            Tanggal
                                        </th>
                                        <th className="border-b border-border px-3 py-2">
                                            Shift
                                        </th>
                                        <th className="border-b border-border px-3 py-2">
                                            PIC
                                        </th>
                                        <th className="border-b border-border px-3 py-2 text-center">
                                            Program Terisi
                                        </th>
                                        <th className="border-b border-border px-3 py-2">
                                            Progres & Kondisi Akhir
                                        </th>
                                        <th className="border-b border-border px-3 py-2 text-center">
                                            Eviden
                                        </th>
                                        <th className="border-b border-border px-3 py-2 text-right">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {savedSlots.map((slot) => {
                                        const filled = slot.rows.filter(
                                            (r) => r.saved,
                                        );
                                        const isCurrent =
                                            slot.day === selectedDay &&
                                            slot.shift === selectedShift;
                                        const firstFilled =
                                            filled[0] ?? slot.rows[0];

                                        return (
                                            <tr
                                                key={slot.slot_key}
                                                className={`border-b border-border transition hover:bg-muted/30 ${
                                                    isCurrent
                                                        ? 'bg-primary/5 font-medium'
                                                        : ''
                                                }`}
                                            >
                                                <td className="px-3 py-2">
                                                    {slot.weekday},{' '}
                                                    {slot.date_label}
                                                </td>
                                                <td className="px-3 py-2">
                                                    <Badge variant="outline">
                                                        Shift {slot.shift}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-2">
                                                    {firstFilled?.pic ?? '—'}
                                                </td>
                                                <td className="px-3 py-2 text-center">
                                                    <Badge variant="secondary">
                                                        {filled.length} / 5
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    {filled
                                                        .map(
                                                            (r) =>
                                                                `${programs[r.program]?.label}: ${r.progres ? `${r.progres}%` : '—'} (${r.kondisi_akhir ?? '—'})`,
                                                        )
                                                        .join(' · ')}
                                                </td>
                                                <td className="px-3 py-2 text-center">
                                                    {slot.evidence.length} foto
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant={
                                                            isCurrent
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                        className="h-7 text-xs"
                                                        onClick={() => {
                                                            setSelectedDay(
                                                                slot.day,
                                                            );
                                                            setSelectedShift(
                                                                slot.shift,
                                                            );
                                                        }}
                                                    >
                                                        {isCurrent
                                                            ? 'Sedang Dibuka'
                                                            : 'Buka Shift'}
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

HarProgram5s5rInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harInput.index() },
        { title: 'Program 5S 5R', href: program5s5rRoutes.index() },
    ],
};
