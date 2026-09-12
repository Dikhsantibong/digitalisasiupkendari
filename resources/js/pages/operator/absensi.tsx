import { Head, router } from '@inertiajs/react';
import { CalendarCog, Printer, Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import DataGrid, { textEditor } from 'react-data-grid';
import type { Column } from 'react-data-grid';
import 'react-data-grid/lib/styles.css';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { OPERASI_GRID_STYLES, useExcelPaste } from '@/components/operasi/grid';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import absensi from '@/routes/operator/absensi';
import type { IdName } from '@/types';

type Day = {
    day: number;
    dow: string;
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
    cells: Record<string, string> | Record<string, never>;
};

type Code = { code: string; label: string; type: string; hitung_hadir: boolean };

type Props = {
    filters: { unit_id: number; year: number; month: number; group_type: string };
    options: {
        units: IdName[];
        years: number[];
        group_types: { value: string; label: string }[];
    };
    days: Day[];
    employees: Employee[];
    codes: Code[];
    patterns: { regu: string; sequence: string }[];
    can_write: boolean;
};

type GridRow = {
    _key: number;
    nip: string | null;
    name: string;
    regu: string | null;
    [key: string]: string | number | null;
};

const ABSENSI_STYLES = `
.operasi-grid .rdg-cell { text-align: center; }
.operasi-grid .rdg-name-cell { text-align: left; }
.operasi-grid .rdg-holiday { background: #fde8e8 !important; color: #9b1c1c; }
.operasi-grid .rdg-weekend { background: #fef3c7 !important; }
.operasi-grid .rdg-recap-header { background: #eef0f3; color: #475569; }
`;

// react-data-grid is virtualised and prints poorly, so a plain table (hidden on
// screen) carries the printout. The visibility trick isolates it from the app.
const PRINT_STYLES = `
@media print {
    @page { size: landscape; margin: 10mm; }
    body * { visibility: hidden; }
    .absensi-print, .absensi-print * { visibility: visible; }
    .absensi-print { position: absolute; inset: 0; }
    .no-print { display: none !important; }
}
.absensi-print table { border-collapse: collapse; width: 100%; font-size: 9px; }
.absensi-print th, .absensi-print td { border: 1px solid #000; padding: 1px 2px; text-align: center; }
.absensi-print td.n { text-align: left; white-space: nowrap; }
.absensi-print .hol { background: #fde8e8; }
.absensi-print .wknd { background: #fef3c7; }
`;

const hydrate = (employees: Employee[], days: Day[]): GridRow[] =>
    employees.map((employee) => {
        const row: GridRow = {
            _key: employee.id,
            nip: employee.nip,
            name: employee.name,
            regu: employee.regu,
        };

        for (const { day } of days) {
            row[`d${day}`] = (employee.cells as Record<string, string>)[String(day)] ?? null;
        }

        return row;
    });

export default function AbsensiSchedule({ filters, options, days, employees, codes, patterns, can_write }: Props) {
    const [rows, setRows] = useState<GridRow[]>(() => hydrate(employees, days));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [startDay, setStartDay] = useState(1);

    // Re-hydrate when the filters change OR when the server sends new cells
    // (e.g. after "Generate pola" reloads the page).
    const signature = `${filters.unit_id}-${filters.year}-${filters.month}-${filters.group_type}-${JSON.stringify(
        employees.map((e) => [e.id, e.cells]),
    )}`;
    const [lastSignature, setLastSignature] = useState(signature);

    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(hydrate(employees, days));
        setDirty(false);
    }

    const presentSet = useMemo(
        () => new Set(codes.filter((c) => c.hitung_hadir).map((c) => c.code)),
        [codes],
    );

    const dayKeys = useMemo(() => days.map((d) => `d${d.day}`), [days]);

    const recapFor = (row: GridRow, code: string): number =>
        dayKeys.reduce((sum, key) => sum + (row[key] === code ? 1 : 0), 0);

    const percentFor = (row: GridRow): string => {
        let present = 0;
        let scheduled = 0;

        for (const key of dayKeys) {
            const value = row[key];

            if (value === null || value === '') {
continue;
}

            scheduled += 1;

            if (presentSet.has(String(value))) {
present += 1;
}
        }

        return scheduled > 0 ? `${Math.round((present / scheduled) * 100)}%` : '—';
    };

    const columns = useMemo<readonly Column<GridRow>[]>(() => {
        const cols: Column<GridRow>[] = [
            { key: 'nip', name: 'NIP', frozen: true, width: 120, headerCellClass: 'rdg-sub-header' },
            {
                key: 'name',
                name: 'Nama',
                frozen: true,
                minWidth: 200,
                headerCellClass: 'rdg-sub-header',
                cellClass: 'rdg-name-cell',
            },
            { key: 'regu', name: 'Regu', frozen: true, width: 60, headerCellClass: 'rdg-sub-header' },
        ];

        for (const day of days) {
            const tone = day.is_holiday ? 'rdg-holiday' : day.is_weekend ? 'rdg-weekend' : '';
            cols.push({
                key: `d${day.day}`,
                name: `${day.day} ${day.dow}`,
                width: 40,
                editable: can_write,
                renderEditCell: textEditor,
                headerCellClass: `rdg-sub-header ${tone}`.trim(),
                cellClass: tone || undefined,
            });
        }

        for (const code of codes) {
            cols.push({
                key: `r_${code.code}`,
                name: code.code,
                width: 42,
                headerCellClass: 'rdg-recap-header',
                cellClass: 'rdg-derived-cell',
                renderCell: ({ row }) => <span>{recapFor(row, code.code) || ''}</span>,
            });
        }

        cols.push({
            key: 'pct',
            name: '% Hadir',
            width: 70,
            headerCellClass: 'rdg-recap-header',
            cellClass: 'rdg-derived-cell',
            renderCell: ({ row }) => <span>{percentFor(row)}</span>,
        });

        return cols;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [days, codes, can_write]);

    const { onSelectedCellChange, onPaste } = useExcelPaste<GridRow>({
        columns,
        rows,
        onChange: (next) => {
            setRows(normalise(next));
            setDirty(true);
        },
    });

    const normalise = (next: GridRow[]): GridRow[] =>
        next.map((row) => {
            const copy = { ...row };

            for (const key of dayKeys) {
                const value = copy[key];

                if (value === null || value === '') {
                    copy[key] = null;
                    continue;
                }

                // Keep the raw uppercase code; unknown codes are nulled on save.
                copy[key] = String(value).toUpperCase();
            }

            return copy;
        });

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(absensi.index().url, { ...filters, ...patch }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const save = () => {
        setSaving(true);
        const cells: { employee_id: number; day: number; code: string | null }[] = [];

        for (const row of rows) {
            for (const day of days) {
                cells.push({ employee_id: row._key, day: day.day, code: (row[`d${day.day}`] as string) ?? null });
            }
        }

        router.post(
            absensi.store().url,
            { ...filters, cells },
            { preserveScroll: true, onSuccess: () => setDirty(false), onFinish: () => setSaving(false) },
        );
    };

    const generate = () => {
        router.post(
            absensi.generate().url,
            { ...filters, start_day: startDay },
            { preserveScroll: true },
        );
    };

    const isShift = filters.group_type === 'shift';
    const unitName = options.units.find((u) => u.id === filters.unit_id)?.name ?? '';
    const periodLabel = `${OPERASI_MONTHS[filters.month - 1]} ${filters.year}`;
    const groupLabel = options.group_types.find((g) => g.value === filters.group_type)?.label ?? '';

    return (
        <>
            <Head title="Absensi & Jadwal Kerja Shift" />
            <style>{PRINT_STYLES}</style>
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Absensi & Jadwal Kerja Shift"
                    description="Jadwal shift & kehadiran pegawai per unit & bulan. Sel bisa diisi manual atau di-generate dari pola regu, lalu tetap bisa diedit. Rekap & % kehadiran dihitung otomatis."
                    actions={
                        <div className="flex flex-wrap gap-2 no-print">
                            <Button variant="secondary" onClick={() => window.print()}>
                                <Printer className="size-4" />
                                Cetak
                            </Button>
                            {can_write && isShift && patterns.length > 0 && (
                                <div className="flex items-center gap-1 rounded-md border border-border px-2">
                                    <span className="text-[12px] text-muted-foreground">Mulai tgl</span>
                                    <input
                                        type="number"
                                        min={1}
                                        max={31}
                                        value={startDay}
                                        onChange={(e) => setStartDay(Math.max(1, Math.min(31, Number(e.target.value) || 1)))}
                                        className="w-12 bg-transparent text-sm outline-none"
                                    />
                                    <Button variant="ghost" size="sm" onClick={generate}>
                                        <CalendarCog className="size-4" />
                                        Generate Pola
                                    </Button>
                                </div>
                            )}
                            {can_write && (
                                <Button onClick={save} disabled={saving}>
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 no-print">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    <OperasiSelect
                        label="Kelompok"
                        value={filters.group_type}
                        onChange={(value) => visit({ group_type: value })}
                        options={options.group_types}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                    />
                    {dirty && !saving && (
                        <span className="text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>
                    )}
                </div>

                {employees.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Belum ada pegawai {isShift ? 'shift (dengan regu)' : 'non-shift'} pada unit ini. Tambahkan lewat Master Pegawai
                        {isShift ? ' dan isi kolom Regu (A/B/C).' : '.'}
                    </div>
                ) : (
                    <div className="operasi-grid overflow-hidden rounded-md border border-border" onPaste={onPaste}>
                        <style>{OPERASI_GRID_STYLES}{ABSENSI_STYLES}</style>
                        <DataGrid
                            className="rdg-light"
                            style={{ blockSize: `${Math.min(Math.max(rows.length, 3) * 35 + 44, 620)}px` }}
                            columns={columns}
                            rows={rows}
                            rowKeyGetter={(row) => row._key}
                            onRowsChange={(next) => {
                                setRows(normalise(next));
                                setDirty(true);
                            }}
                            onSelectedCellChange={onSelectedCellChange}
                        />
                    </div>
                )}

                <div className="absensi-print hidden print:block">
                    <div style={{ textAlign: 'center', marginBottom: 8 }}>
                        <div style={{ fontWeight: 700, fontSize: 12, textTransform: 'uppercase' }}>
                            Jadwal Kerja {groupLabel} — {unitName}
                        </div>
                        <div style={{ fontSize: 11 }}>Periode {periodLabel}</div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th className="n">NIP</th>
                                <th className="n">Nama</th>
                                <th>Regu</th>
                                {days.map((d) => (
                                    <th key={d.day} className={d.is_holiday ? 'hol' : d.is_weekend ? 'wknd' : ''}>
                                        {d.day}
                                        <br />
                                        {d.dow}
                                    </th>
                                ))}
                                {codes.map((c) => (
                                    <th key={c.code}>{c.code}</th>
                                ))}
                                <th>%</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row._key}>
                                    <td className="n">{row.nip}</td>
                                    <td className="n">{row.name}</td>
                                    <td>{row.regu}</td>
                                    {days.map((d) => {
                                        const value = row[`d${d.day}`];

                                        return (
                                            <td key={d.day} className={d.is_holiday ? 'hol' : d.is_weekend ? 'wknd' : ''}>
                                                {value ?? ''}
                                            </td>
                                        );
                                    })}
                                    {codes.map((c) => (
                                        <td key={c.code}>{recapFor(row, c.code) || ''}</td>
                                    ))}
                                    <td>{percentFor(row)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-col gap-1 rounded-md border border-border bg-card p-3 text-[12px] text-muted-foreground no-print">
                    <p className="font-medium text-foreground">Kode kehadiran:</p>
                    <div>{codes.map((c) => `${c.code} (${c.label})`).join(' · ')}</div>
                    <p className="pt-1">
                        Kolom merah = hari libur nasional, kuning = hari Minggu. Ketik kode langsung pada sel; paste dari Excel didukung.
                        Kode tak dikenal akan dikosongkan saat simpan.
                    </p>
                </div>
            </div>
        </>
    );
}

AbsensiSchedule.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Absensi & Jadwal', href: absensi.index() },
    ],
};
