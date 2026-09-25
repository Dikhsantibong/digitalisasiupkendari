import { Head, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Eraser,
    Printer,
    Save,
    Sparkles,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import absensi from '@/routes/operator/absensi';
import type { IdName } from '@/types';

type Day = {
    day: number;
    name: string;
    is_weekend: boolean;
    is_holiday: boolean;
    holiday: string | null;
};

type Employee = {
    id: number;
    name: string;
    nip: string | null;
    position: string | null;
    regu: string | null;
    is_shift_leader: boolean;
    cells: Record<string, string>;
};

type Section = { key: string; label: string; employees: Employee[] };

type Code = {
    code: string;
    label: string;
    type: string;
    hitung_hadir: boolean;
    jam_mulai: string | null;
    jam_selesai: string | null;
};

type Props = {
    filters: { unit_id: number; year: number; month: number };
    period_label: string;
    options: { units: IdName[]; years: number[] };
    days: Day[];
    sections: Section[];
    codes: Code[];
    patterns: { regu: string; sequence: string }[];
    can_write: boolean;
};

/** cells[employeeId][day] = code */
type Cells = Record<number, Record<number, string>>;

/** Cell colours follow the Excel sheet the unit already uses. */
const CODE_STYLE: Record<string, string> = {
    P: 'bg-white text-slate-900',
    S: 'bg-sky-500 text-white',
    M: 'bg-slate-400 text-white',
    OFF: 'bg-red-600 text-white',
    C: 'bg-black text-white',
    SKT: 'bg-cyan-300 text-cyan-950',
    I: 'bg-sky-200 text-sky-950',
    A: 'bg-neutral-700 text-white',
};

/** Recap columns, in the order of the Excel sheet. */
const RECAP = [
    { code: 'P', label: 'Pagi' },
    { code: 'S', label: 'Sore' },
    { code: 'M', label: 'Malam' },
    { code: 'OFF', label: 'Off' },
    { code: 'SKT', label: 'Sakit' },
    { code: 'I', label: 'Izin' },
    { code: 'A', label: 'Alpha' },
    { code: 'C', label: 'Cuti' },
];

/** Keyboard shortcut → code for picking the brush. */
const SHORTCUT: Record<string, string> = {
    p: 'P',
    s: 'S',
    m: 'M',
    o: 'OFF',
    c: 'C',
    k: 'SKT',
    i: 'I',
    a: 'A',
};

const PRINT_STYLES = `
@media print {
    @page { size: A4 landscape; margin: 8mm; }
    body * { visibility: hidden !important; }
    .absensi-print, .absensi-print * { visibility: visible !important; }
    .absensi-print { position: absolute; inset: 0 auto auto 0; width: 100%; }
    .absensi-scroll { overflow: visible !important; }
    .absensi-print table { font-size: 7px; }
    .absensi-print .sticky { position: static !important; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}`;

const cellStyle = (code: string | undefined) =>
    code ? (CODE_STYLE[code] ?? 'bg-muted text-foreground') : '';

function initialCells(sections: Section[]): Cells {
    const out: Cells = {};

    for (const section of sections) {
        for (const employee of section.employees) {
            out[employee.id] = Object.fromEntries(
                Object.entries(employee.cells).map(([day, code]) => [
                    Number(day),
                    code,
                ]),
            );
        }
    }

    return out;
}

export default function AbsensiPage(props: Props) {
    // Re-mount the sheet whenever the server data changes (save, isi otomatis, filter).
    return (
        <AbsensiSheet
            key={JSON.stringify([props.filters, props.sections])}
            {...props}
        />
    );
}

function AbsensiSheet({
    filters,
    period_label,
    options,
    days,
    sections,
    codes,
    patterns,
    can_write,
}: Props) {
    const original = useMemo(() => initialCells(sections), [sections]);
    const [cells, setCells] = useState<Cells>(original);
    const [brush, setBrush] = useState<string>('P');
    const [saving, setSaving] = useState(false);
    const [generateOpen, setGenerateOpen] = useState(false);
    const painting = useRef(false);
    const compact = useCompactLayout();

    const codeMap = useMemo(
        () => new Map(codes.map((c) => [c.code, c])),
        [codes],
    );
    const unitName =
        options.units.find((u) => u.id === filters.unit_id)?.name ?? '';

    const changes = useMemo(() => {
        const out: { employee_id: number; day: number; code: string | null }[] =
            [];

        for (const [employeeId, row] of Object.entries(cells)) {
            for (const d of days) {
                const now = row[d.day] ?? '';
                const before = original[Number(employeeId)]?.[d.day] ?? '';

                if (now !== before) {
                    out.push({
                        employee_id: Number(employeeId),
                        day: d.day,
                        code: now === '' ? null : now,
                    });
                }
            }
        }

        return out;
    }, [cells, original, days]);

    const dirty = changes.length > 0;

    useEffect(() => {
        const stop = () => {
            painting.current = false;
        };
        window.addEventListener('pointerup', stop);

        return () => window.removeEventListener('pointerup', stop);
    }, []);

    useEffect(() => {
        if (!can_write) {
            return;
        }

        const onKey = (e: KeyboardEvent) => {
            const target = e.target as HTMLElement;

            if (
                e.ctrlKey ||
                e.metaKey ||
                e.altKey ||
                ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) ||
                target.isContentEditable
            ) {
                return;
            }

            const key = e.key.toLowerCase();

            if (key === 'backspace' || key === 'delete') {
                setBrush('');
            } else if (SHORTCUT[key] && codeMap.has(SHORTCUT[key])) {
                setBrush(SHORTCUT[key]);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [can_write, codeMap]);

    const paint = (employeeId: number, day: number) => {
        setCells((current) => {
            if ((current[employeeId]?.[day] ?? '') === brush) {
                return current;
            }

            const row = { ...current[employeeId] };

            if (brush === '') {
                delete row[day];
            } else {
                row[day] = brush;
            }

            return { ...current, [employeeId]: row };
        });
    };

    const visit = (patch: Partial<Props['filters']>) => {
        if (
            dirty &&
            !window.confirm(
                'Ada perubahan yang belum disimpan. Tinggalkan halaman ini?',
            )
        ) {
            return;
        }

        router.get(
            absensi.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    };

    const shiftMonth = (delta: number) => {
        const date = new Date(filters.year, filters.month - 1 + delta, 1);
        visit({ year: date.getFullYear(), month: date.getMonth() + 1 });
    };

    const save = () => {
        setSaving(true);
        router.post(
            absensi.store().url,
            { ...filters, cells: changes },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    const generate = () => {
        setGenerateOpen(false);
        router.post(absensi.generate().url, filters, { preserveScroll: true });
    };

    const recapOf = (employeeId: number) => {
        const counts: Record<string, number> = {};
        let present = 0;
        let scheduled = 0;

        for (const code of Object.values(cells[employeeId] ?? {})) {
            counts[code] = (counts[code] ?? 0) + 1;
            scheduled++;

            if (codeMap.get(code)?.hitung_hadir) {
                present++;
            }
        }

        return {
            counts,
            percent:
                scheduled > 0 ? Math.round((present / scheduled) * 100) : null,
        };
    };

    const dayTotals = useMemo(() => {
        const totals: Record<string, Record<number, number>> = {};

        for (const row of Object.values(cells)) {
            for (const [day, code] of Object.entries(row)) {
                totals[code] ??= {};
                totals[code][Number(day)] =
                    (totals[code][Number(day)] ?? 0) + 1;
            }
        }

        return totals;
    }, [cells]);

    const dayHeaderClass = (d: Day) =>
        d.is_holiday
            ? 'bg-red-600 text-white'
            : d.is_weekend
              ? 'bg-amber-300 text-amber-950'
              : 'bg-card';
    const holidays = days.filter((d) => d.is_holiday);
    const shiftCodes = ['P', 'S', 'M']
        .map((c) => codeMap.get(c))
        .filter((c): c is Code => c !== undefined && c.jam_mulai !== null);
    const recapCodes = RECAP.filter((r) => codeMap.has(r.code));
    const totalEmployees = sections.reduce(
        (sum, s) => sum + s.employees.length,
        0,
    );
    const rowNumbers = new Map(
        sections
            .flatMap((s) => s.employees)
            .map((e, index) => [e.id, index + 1]),
    );

    const generateDialog = (
        <Dialog open={generateOpen} onOpenChange={setGenerateOpen}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Isi otomatis jadwal {period_label}?
                    </DialogTitle>
                    <DialogDescription>
                        Operator mengikuti pola regunya (melanjutkan siklus
                        bulan lalu bila ada). Non Shift diisi Pagi pada hari
                        kerja dan OFF pada Sabtu, Minggu, dan libur nasional.
                        Kode Cuti, Sakit, Izin, dan Alpha yang sudah diisi tetap
                        dipertahankan.
                        {dirty && ' Perubahan yang belum disimpan akan hilang.'}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="secondary"
                        onClick={() => setGenerateOpen(false)}
                    >
                        Batal
                    </Button>
                    <Button onClick={generate}>
                        <Sparkles className="size-4" />
                        Isi Otomatis
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );

    const setCode = (employeeId: number, day: number, code: string) => {
        setCells((current) => {
            const row = { ...current[employeeId] };

            if (code === '') {
                delete row[day];
            } else {
                row[day] = code;
            }

            return { ...current, [employeeId]: row };
        });
    };

    if (compact) {
        return (
            <AbsensiMobile
                filters={filters}
                options={options}
                period_label={period_label}
                days={days}
                sections={sections}
                codes={codes}
                can_write={can_write}
                cells={cells}
                onSetCode={setCode}
                percentOf={(employeeId) => recapOf(employeeId).percent}
                changeCount={changes.length}
                saving={saving}
                onSave={save}
                onGenerate={() => setGenerateOpen(true)}
                onVisit={visit}
                onShiftMonth={shiftMonth}
                generateDialog={generateDialog}
            />
        );
    }

    return (
        <>
            <Head title="Jadwal Shift" />
            <style>{PRINT_STYLES}</style>
            <div className="flex min-w-0 flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Jadwal Shift"
                    description="Satu lembar per unit per bulan: Kerja Shift (operator per regu) dan Non Shift. Pilih kode, lalu klik atau geser di sel tanggal."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() => window.print()}
                            >
                                <Printer className="size-4" />
                                Cetak
                            </Button>
                            {can_write && (
                                <>
                                    <Button
                                        variant="outline"
                                        onClick={() => setGenerateOpen(true)}
                                    >
                                        <Sparkles className="size-4" />
                                        Isi Otomatis
                                    </Button>
                                    <Button
                                        onClick={save}
                                        disabled={!dirty || saving}
                                    >
                                        <Save className="size-4" />
                                        {saving
                                            ? 'Menyimpan…'
                                            : dirty
                                              ? `Simpan (${changes.length})`
                                              : 'Tersimpan'}
                                    </Button>
                                </>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                        className="w-52"
                    />
                    <div className="flex items-end gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-9"
                            onClick={() => shiftMonth(-1)}
                            aria-label="Bulan sebelumnya"
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
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
                            className="w-36"
                        />
                        <OperasiSelect
                            label="Tahun"
                            value={String(filters.year)}
                            onChange={(value) => visit({ year: Number(value) })}
                            options={options.years.map((y) => ({
                                value: String(y),
                                label: String(y),
                            }))}
                            className="w-24"
                        />
                        <Button
                            variant="outline"
                            size="icon"
                            className="size-9"
                            onClick={() => shiftMonth(1)}
                            aria-label="Bulan berikutnya"
                        >
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>
                    {dirty && (
                        <span className="self-center text-[12.5px] font-medium text-amber-600">
                            ● {changes.length} sel belum disimpan
                        </span>
                    )}
                </div>

                {can_write && totalEmployees > 0 && (
                    <div className="flex flex-wrap items-center gap-1.5 rounded-lg border border-border bg-card p-2">
                        <span className="px-1.5 text-[12px] font-medium text-muted-foreground">
                            Kode:
                        </span>
                        {codes.map((c) => (
                            <button
                                key={c.code}
                                type="button"
                                onClick={() => setBrush(c.code)}
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[12px] transition',
                                    brush === c.code
                                        ? 'border-primary bg-primary/10 ring-2 ring-primary/30'
                                        : 'border-border hover:bg-muted',
                                )}
                                title={`Shortcut: ${
                                    Object.keys(SHORTCUT)
                                        .find((k) => SHORTCUT[k] === c.code)
                                        ?.toUpperCase() ?? '-'
                                }`}
                            >
                                <span
                                    className={cn(
                                        'inline-flex h-5 min-w-7 items-center justify-center rounded border border-border px-1 text-[10px] font-bold',
                                        cellStyle(c.code),
                                    )}
                                >
                                    {c.code}
                                </span>
                                {c.label}
                            </button>
                        ))}
                        <button
                            type="button"
                            onClick={() => setBrush('')}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[12px] transition',
                                brush === ''
                                    ? 'border-primary bg-primary/10 ring-2 ring-primary/30'
                                    : 'border-border hover:bg-muted',
                            )}
                        >
                            <Eraser className="size-3.5" />
                            Hapus
                        </button>
                        <span className="ml-auto hidden px-1.5 text-[11px] text-muted-foreground lg:inline">
                            Tip: tekan huruf P/S/M/O/C/K/I/A untuk ganti kode
                        </span>
                    </div>
                )}

                {totalEmployees === 0 ? (
                    <div className="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Belum ada pegawai pada unit ini. Tambahkan lewat Master
                        Pegawai: isi <b>Regu (A–D)</b> untuk operator shift,
                        atau jabatan Project Leader / Koordinator untuk Non
                        Shift.
                    </div>
                ) : (
                    <div className="absensi-print flex flex-col gap-3">
                        <div className="text-center">
                            <p className="text-[15px] font-bold tracking-wide text-foreground uppercase">
                                Jadwal Kerja {unitName}
                            </p>
                            <p className="text-[12.5px] text-muted-foreground">
                                Periode {period_label}
                            </p>
                        </div>

                        <div className="absensi-scroll overflow-x-auto rounded-lg border border-border bg-card">
                            <table className="w-full border-collapse text-[11.5px] select-none">
                                <thead>
                                    <tr className="bg-[#5b2c8f] text-white">
                                        <th
                                            rowSpan={2}
                                            className="sticky left-0 z-20 w-8 border border-white/20 bg-[#5b2c8f] px-1"
                                        >
                                            No
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="w-24 border border-white/20 px-1.5"
                                        >
                                            NIP
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="w-36 border border-white/20 px-1.5"
                                        >
                                            Jabatan
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="sticky left-8 z-20 min-w-44 border border-white/20 bg-[#5b2c8f] px-1.5"
                                        >
                                            Nama
                                        </th>
                                        {days.map((d) => (
                                            <th
                                                key={d.day}
                                                className={cn(
                                                    'h-16 w-7 min-w-7 border border-border px-0 align-bottom font-medium',
                                                    dayHeaderClass(d),
                                                    !d.is_weekend &&
                                                        !d.is_holiday &&
                                                        'text-foreground',
                                                )}
                                                title={d.holiday ?? d.name}
                                            >
                                                <span className="inline-block rotate-180 pb-1 text-[10px] [writing-mode:vertical-rl]">
                                                    {d.name}
                                                </span>
                                            </th>
                                        ))}
                                        <th
                                            colSpan={recapCodes.length}
                                            className="border border-white/20 px-1.5"
                                        >
                                            Rekap Absensi
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="w-16 border border-white/20 bg-rose-200 px-1 text-rose-950"
                                        >
                                            % Hadir
                                        </th>
                                    </tr>
                                    <tr>
                                        {days.map((d) => (
                                            <th
                                                key={d.day}
                                                className={cn(
                                                    'border border-border py-0.5 text-center text-[10.5px] font-semibold tabular-nums',
                                                    dayHeaderClass(d),
                                                )}
                                            >
                                                {String(d.day).padStart(2, '0')}
                                            </th>
                                        ))}
                                        {recapCodes.map((r) => (
                                            <th
                                                key={r.code}
                                                className={cn(
                                                    'w-9 min-w-9 border border-border px-0.5 text-[10px] font-semibold',
                                                    cellStyle(r.code),
                                                )}
                                            >
                                                {r.label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {sections.map((section) => (
                                        <SectionRows
                                            key={section.key}
                                            section={section}
                                            dataColumns={
                                                days.length +
                                                recapCodes.length +
                                                1
                                            }
                                        >
                                            {section.employees.map(
                                                (employee) => {
                                                    const { counts, percent } =
                                                        recapOf(employee.id);

                                                    return (
                                                        <tr
                                                            key={employee.id}
                                                            className="group"
                                                        >
                                                            <td className="sticky left-0 z-10 border border-border bg-card text-center tabular-nums group-hover:bg-muted">
                                                                {rowNumbers.get(
                                                                    employee.id,
                                                                )}
                                                            </td>
                                                            <td className="border border-border px-1.5 whitespace-nowrap tabular-nums">
                                                                {employee.nip ??
                                                                    '—'}
                                                            </td>
                                                            <td
                                                                className="max-w-36 truncate border border-border px-1.5"
                                                                title={
                                                                    employee.position ??
                                                                    ''
                                                                }
                                                            >
                                                                {employee.position ??
                                                                    '—'}
                                                            </td>
                                                            <td className="sticky left-8 z-10 border border-border bg-card px-1.5 whitespace-nowrap group-hover:bg-muted">
                                                                <span className="font-medium">
                                                                    {
                                                                        employee.name
                                                                    }
                                                                </span>
                                                                {employee.regu && (
                                                                    <span className="ml-1.5 rounded bg-primary/10 px-1 text-[10px] font-semibold text-primary">
                                                                        Regu{' '}
                                                                        {
                                                                            employee.regu
                                                                        }
                                                                    </span>
                                                                )}
                                                                {employee.is_shift_leader && (
                                                                    <span className="ml-1 rounded bg-amber-500/15 px-1 text-[10px] font-semibold text-amber-700 dark:text-amber-400">
                                                                        Leader
                                                                    </span>
                                                                )}
                                                            </td>
                                                            {days.map((d) => {
                                                                const code =
                                                                    cells[
                                                                        employee
                                                                            .id
                                                                    ]?.[d.day];

                                                                return (
                                                                    <td
                                                                        key={
                                                                            d.day
                                                                        }
                                                                        onPointerDown={(
                                                                            e,
                                                                        ) => {
                                                                            if (
                                                                                !can_write
                                                                            ) {
                                                                                return;
                                                                            }

                                                                            e.preventDefault();
                                                                            painting.current = true;
                                                                            paint(
                                                                                employee.id,
                                                                                d.day,
                                                                            );
                                                                        }}
                                                                        onPointerEnter={() => {
                                                                            if (
                                                                                can_write &&
                                                                                painting.current
                                                                            ) {
                                                                                paint(
                                                                                    employee.id,
                                                                                    d.day,
                                                                                );
                                                                            }
                                                                        }}
                                                                        className={cn(
                                                                            'h-7 border border-border p-0 text-center text-[10px] font-bold',
                                                                            cellStyle(
                                                                                code,
                                                                            ),
                                                                            !code &&
                                                                                (d.is_holiday
                                                                                    ? 'bg-red-50'
                                                                                    : d.is_weekend
                                                                                      ? 'bg-amber-50'
                                                                                      : ''),
                                                                            can_write &&
                                                                                'cursor-pointer hover:outline-2 hover:-outline-offset-2 hover:outline-primary',
                                                                        )}
                                                                    >
                                                                        {code ??
                                                                            ''}
                                                                    </td>
                                                                );
                                                            })}
                                                            {recapCodes.map(
                                                                (r) => (
                                                                    <td
                                                                        key={
                                                                            r.code
                                                                        }
                                                                        className="border border-border text-center tabular-nums"
                                                                    >
                                                                        {counts[
                                                                            r
                                                                                .code
                                                                        ] ?? 0}
                                                                    </td>
                                                                ),
                                                            )}
                                                            <td
                                                                className={cn(
                                                                    'border border-border px-1 text-center font-semibold tabular-nums',
                                                                    percent ===
                                                                        null
                                                                        ? 'text-muted-foreground'
                                                                        : percent >=
                                                                            70
                                                                          ? 'bg-emerald-50 text-emerald-700'
                                                                          : 'bg-rose-50 text-rose-700',
                                                                )}
                                                            >
                                                                {percent ===
                                                                null
                                                                    ? '—'
                                                                    : `${percent}%`}
                                                            </td>
                                                        </tr>
                                                    );
                                                },
                                            )}
                                        </SectionRows>
                                    ))}
                                </tbody>
                                <tfoot>
                                    {recapCodes.map((r, index) => (
                                        <tr key={r.code}>
                                            {index === 0 && (
                                                <td
                                                    colSpan={4}
                                                    rowSpan={recapCodes.length}
                                                    className="sticky left-0 z-10 border border-border bg-muted text-center text-[12px] font-bold tracking-wide uppercase"
                                                >
                                                    Total per Hari
                                                </td>
                                            )}
                                            {days.map((d) => (
                                                <td
                                                    key={d.day}
                                                    className="border border-border text-center text-[10px] tabular-nums"
                                                >
                                                    {dayTotals[r.code]?.[
                                                        d.day
                                                    ] ?? 0}
                                                </td>
                                            ))}
                                            <td
                                                colSpan={recapCodes.length + 1}
                                                className={cn(
                                                    'border border-border px-2 text-[10.5px] font-semibold',
                                                    cellStyle(r.code),
                                                )}
                                            >
                                                {r.label}
                                            </td>
                                        </tr>
                                    ))}
                                </tfoot>
                            </table>
                        </div>

                        <div className="grid gap-3 text-[11.5px] md:grid-cols-3">
                            <div className="flex flex-col gap-1.5 rounded-lg border border-border bg-card p-3">
                                <p className="font-semibold text-foreground">
                                    Keterangan Kode
                                </p>
                                <div className="grid grid-cols-2 gap-1">
                                    {codes.map((c) => (
                                        <span
                                            key={c.code}
                                            className="inline-flex items-center gap-1.5"
                                        >
                                            <span
                                                className={cn(
                                                    'inline-flex h-4.5 min-w-7 items-center justify-center rounded border border-border px-1 text-[9.5px] font-bold',
                                                    cellStyle(c.code),
                                                )}
                                            >
                                                {c.code}
                                            </span>
                                            {c.label}
                                        </span>
                                    ))}
                                </div>
                                <div className="mt-1 flex flex-wrap gap-3">
                                    <span className="inline-flex items-center gap-1.5">
                                        <span className="size-3 rounded-sm bg-amber-300" />{' '}
                                        Sabtu–Minggu
                                    </span>
                                    <span className="inline-flex items-center gap-1.5">
                                        <span className="size-3 rounded-sm bg-red-600" />{' '}
                                        Libur nasional
                                    </span>
                                </div>
                            </div>
                            <div className="flex flex-col gap-1 rounded-lg border border-border bg-card p-3">
                                <p className="font-semibold text-foreground">
                                    Jam Kerja
                                </p>
                                <p className="text-muted-foreground">Shift:</p>
                                {shiftCodes.map((c) => (
                                    <p key={c.code}>
                                        {c.label}: {c.jam_mulai?.slice(0, 5)} –{' '}
                                        {c.jam_selesai?.slice(0, 5)} WITA
                                    </p>
                                ))}
                                <p className="mt-1 text-muted-foreground">
                                    Non Shift:
                                </p>
                                <p>Senin–Kamis: 08.00 – 16.30 WITA</p>
                                <p>Jumat: 07.30 – 16.30 WITA</p>
                            </div>
                            <div className="flex flex-col gap-1 rounded-lg border border-border bg-card p-3">
                                <p className="font-semibold text-foreground">
                                    Pola Regu (Isi Otomatis)
                                </p>
                                {patterns.map((p) => (
                                    <p key={p.regu}>
                                        <span className="font-semibold">
                                            Regu {p.regu}:
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            {p.sequence}
                                        </span>
                                    </p>
                                ))}
                                {holidays.length > 0 && (
                                    <>
                                        <p className="mt-1 font-semibold text-foreground">
                                            Libur Nasional
                                        </p>
                                        {holidays.map((d) => (
                                            <p key={d.day}>
                                                {d.day} — {d.holiday}
                                            </p>
                                        ))}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {generateDialog}
        </>
    );
}

function SectionRows({
    section,
    dataColumns,
    children,
}: {
    section: Section;
    dataColumns: number;
    children: ReactNode;
}) {
    return (
        <>
            <tr>
                <td
                    colSpan={4}
                    className="sticky left-0 z-10 border border-border bg-neutral-900 px-2 py-1 text-[11px] font-bold tracking-wider text-white uppercase"
                >
                    {section.label}{' '}
                    <span className="font-normal text-white/60">
                        · {section.employees.length} orang
                    </span>
                </td>
                <td
                    colSpan={dataColumns}
                    className="border border-border bg-neutral-900"
                />
            </tr>
            {section.employees.length === 0 ? (
                <tr>
                    <td
                        colSpan={4 + dataColumns}
                        className="border border-border px-3 py-3 text-center text-muted-foreground"
                    >
                        {section.key === 'shift'
                            ? 'Belum ada operator ber-regu pada unit ini.'
                            : 'Belum ada Project Leader / Koordinator pada unit ini.'}
                    </td>
                </tr>
            ) : (
                children
            )}
        </>
    );
}

/**
 * Phone layout: no table. Pick a date from the strip, then tap a code on each
 * employee's card. Shares state (cells, save, isi otomatis) with the sheet.
 */
function AbsensiMobile({
    filters,
    options,
    period_label,
    days,
    sections,
    codes,
    can_write,
    cells,
    onSetCode,
    percentOf,
    changeCount,
    saving,
    onSave,
    onGenerate,
    onVisit,
    onShiftMonth,
    generateDialog,
}: Pick<
    Props,
    | 'filters'
    | 'options'
    | 'period_label'
    | 'days'
    | 'sections'
    | 'codes'
    | 'can_write'
> & {
    cells: Cells;
    onSetCode: (employeeId: number, day: number, code: string) => void;
    percentOf: (employeeId: number) => number | null;
    changeCount: number;
    saving: boolean;
    onSave: () => void;
    onGenerate: () => void;
    onVisit: (patch: Partial<Props['filters']>) => void;
    onShiftMonth: (delta: number) => void;
    generateDialog: ReactNode;
}) {
    const [selectedDay, setSelectedDay] = useState(() => {
        const today = new Date();

        return today.getFullYear() === filters.year &&
            today.getMonth() + 1 === filters.month
            ? today.getDate()
            : 1;
    });
    const selectedRef = useRef<HTMLButtonElement>(null);
    const day = days.find((d) => d.day === selectedDay) ?? days[0];
    const employees = sections.flatMap((s) => s.employees);
    const filledOn = (d: number) =>
        employees.filter((e) => cells[e.id]?.[d]).length;

    useEffect(() => {
        selectedRef.current?.scrollIntoView({
            inline: 'center',
            block: 'nearest',
        });
    }, [selectedDay]);

    return (
        <>
            <Head title="Jadwal Shift" />
            <div
                className={cn('flex flex-col gap-3 p-4', can_write && 'pb-28')}
            >
                <PageHeader
                    title="Jadwal Shift"
                    description="Pilih tanggal, lalu ketuk kode untuk setiap pegawai."
                />

                <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-3">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) =>
                            onVisit({ unit_id: Number(value) })
                        }
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                        className="w-full"
                    />
                    <div className="flex items-center justify-between gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => onShiftMonth(-1)}
                            aria-label="Bulan sebelumnya"
                        >
                            <ChevronLeft className="size-4" />
                        </Button>
                        <span className="text-[15px] font-semibold text-foreground">
                            {period_label}
                        </span>
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => onShiftMonth(1)}
                            aria-label="Bulan berikutnya"
                        >
                            <ChevronRight className="size-4" />
                        </Button>
                    </div>
                </div>

                <div className="-mx-4 flex snap-x gap-1.5 overflow-x-auto px-4 pb-1">
                    {days.map((d) => {
                        const selected = d.day === selectedDay;
                        const complete =
                            employees.length > 0 &&
                            filledOn(d.day) === employees.length;

                        return (
                            <button
                                key={d.day}
                                ref={selected ? selectedRef : undefined}
                                type="button"
                                onClick={() => setSelectedDay(d.day)}
                                className={cn(
                                    'flex w-12 shrink-0 snap-center flex-col items-center gap-0.5 rounded-xl border py-2 transition',
                                    selected
                                        ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                        : d.is_holiday
                                          ? 'border-red-200 bg-red-50 text-red-700'
                                          : d.is_weekend
                                            ? 'border-amber-200 bg-amber-50 text-amber-800'
                                            : 'border-border bg-card text-foreground',
                                )}
                            >
                                <span className="text-[10px] font-medium opacity-80">
                                    {d.name.slice(0, 3)}
                                </span>
                                <span className="text-[16px] leading-none font-bold tabular-nums">
                                    {d.day}
                                </span>
                                <span
                                    className={cn(
                                        'size-1.5 rounded-full',
                                        complete
                                            ? selected
                                                ? 'bg-white'
                                                : 'bg-emerald-500'
                                            : 'bg-transparent',
                                    )}
                                />
                            </button>
                        );
                    })}
                </div>

                <div className="flex items-center justify-between gap-2 px-0.5">
                    <p className="text-[13px] font-semibold text-foreground">
                        {day.name}, {day.day} {period_label}
                    </p>
                    <span className="text-[11.5px] text-muted-foreground">
                        {filledOn(day.day)}/{employees.length} terisi
                    </span>
                </div>
                {day.is_holiday && (
                    <p className="-mt-2 px-0.5 text-[12px] font-medium text-red-600">
                        Libur nasional: {day.holiday}
                    </p>
                )}

                {employees.length === 0 && (
                    <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                        Belum ada pegawai pada unit ini.
                    </p>
                )}

                {sections
                    .filter((section) => section.employees.length > 0)
                    .map((section) => (
                        <div key={section.key} className="flex flex-col gap-2">
                            <p className="px-0.5 text-[11.5px] font-semibold tracking-wide text-muted-foreground uppercase">
                                {section.label} · {section.employees.length}{' '}
                                orang
                            </p>
                            {section.employees.map((employee) => {
                                const current =
                                    cells[employee.id]?.[day.day] ?? '';
                                const percent = percentOf(employee.id);

                                return (
                                    <div
                                        key={employee.id}
                                        className="flex flex-col gap-2.5 rounded-xl border border-border bg-card p-3"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate text-[14px] font-semibold text-foreground">
                                                    {employee.name}
                                                </p>
                                                <p className="truncate text-[11.5px] text-muted-foreground">
                                                    {employee.regu
                                                        ? `Regu ${employee.regu}${employee.is_shift_leader ? ' (Leader Shift)' : ''} · `
                                                        : ''}
                                                    {employee.position ?? '—'}
                                                    {percent !== null &&
                                                        ` · Hadir ${percent}%`}
                                                </p>
                                            </div>
                                            <span
                                                className={cn(
                                                    'inline-flex h-8 min-w-12 shrink-0 items-center justify-center rounded-lg border border-border px-2 text-[12px] font-bold',
                                                    current
                                                        ? cellStyle(current)
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {current || '—'}
                                            </span>
                                        </div>
                                        {can_write && (
                                            <div className="grid grid-cols-4 gap-1.5">
                                                {codes.map((c) => {
                                                    const active =
                                                        current === c.code;

                                                    return (
                                                        <button
                                                            key={c.code}
                                                            type="button"
                                                            onClick={() =>
                                                                onSetCode(
                                                                    employee.id,
                                                                    day.day,
                                                                    active
                                                                        ? ''
                                                                        : c.code,
                                                                )
                                                            }
                                                            className={cn(
                                                                'flex flex-col items-center rounded-lg border py-1.5 transition active:scale-95',
                                                                active
                                                                    ? cn(
                                                                          cellStyle(
                                                                              c.code,
                                                                          ),
                                                                          'border-transparent ring-2 ring-primary ring-offset-1',
                                                                      )
                                                                    : 'border-border bg-background',
                                                            )}
                                                        >
                                                            <span className="text-[12px] leading-tight font-bold">
                                                                {c.code}
                                                            </span>
                                                            <span
                                                                className={cn(
                                                                    'text-[9.5px] leading-tight',
                                                                    active
                                                                        ? 'opacity-90'
                                                                        : 'text-muted-foreground',
                                                                )}
                                                            >
                                                                {c.label}
                                                            </span>
                                                        </button>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ))}
            </div>

            {can_write && (
                <div className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-2 gap-2 border-t border-border bg-background/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur">
                    <Button size="lg" variant="outline" onClick={onGenerate}>
                        <Sparkles className="size-4" />
                        Isi Otomatis
                    </Button>
                    <Button
                        size="lg"
                        onClick={onSave}
                        disabled={changeCount === 0 || saving}
                    >
                        <Save className="size-4" />
                        {saving
                            ? 'Menyimpan…'
                            : changeCount > 0
                              ? `Simpan (${changeCount})`
                              : 'Tersimpan'}
                    </Button>
                </div>
            )}

            {generateDialog}
        </>
    );
}

AbsensiPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal Shift', href: absensi.index() },
    ],
};
