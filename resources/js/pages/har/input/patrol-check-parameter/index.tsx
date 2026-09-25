import { Head, router } from '@inertiajs/react';
import { Download, Save } from 'lucide-react';
import { useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import parameterRoutes from '@/routes/har/input/patrol-check-parameter';
import type { IdName } from '@/types';

type Column = { key: string; label: string; type: 'text' | 'number'; path: string[]; width: number };
type HeaderCell = { label: string; colspan: number; rowspan: number };
type Day = { key: string; label: string; sub: string | null; is_red: boolean };
type Readings = Record<string, Record<string, string>>;

type Props = {
    sheet: { title: string; columns: Column[]; header_rows: HeaderCell[][]; widths: number[] };
    days: Day[];
    unit: IdName;
    filters: { unit_id: number; month: number; year: number; machine_id: number | null };
    options: { units: IdName[]; years: number[]; machines: IdName[] };
    readings: Readings;
    has_saved: boolean;
    can_write: boolean;
};

const th = 'border border-slate-400 px-1 py-1 text-[10px] font-semibold';
const cellInput =
    'h-7 w-full min-w-0 bg-transparent px-1 text-center text-xs outline-none focus:bg-background focus:ring-1 focus:ring-primary disabled:cursor-not-allowed';

/**
 * Patrol Check Parameter Mesin — pembacaan harian per mesin (engine,
 * generator, trafo, baterai). Definisi kolom: App\Support\HarPatrolCheckParameter.
 */
export default function HarPatrolCheckParameterPage({ sheet, days, unit, filters, options, readings: savedReadings, has_saved, can_write }: Props) {
    const [readings, setReadings] = useState<Readings>(() => ({ ...savedReadings }));
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const machineName = options.machines.find((m) => m.id === filters.machine_id)?.name;
    const query = { unit_id: filters.unit_id, month: filters.month, year: filters.year, ...(filters.machine_id ? { machine_id: filters.machine_id } : {}) };

    const visit = (patch: Partial<Props['filters']>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(parameterRoutes.index().url, { ...query, ...patch }, { preserveScroll: true });
    };

    const setValue = (day: string, key: string, value: string) => {
        setReadings((current) => {
            const next = { ...(current[day] ?? {}) };

            if (value === '') {
                delete next[key];
            } else {
                next[key] = value;
            }

            return { ...current, [day]: next };
        });
        setDirty(true);
    };

    const save = () => {
        setSaving(true);
        router.post(
            parameterRoutes.store().url,
            { ...query, readings },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setReadings({ ...(page.props as unknown as Props).readings });
                    setErrors({});
                    setDirty(false);
                },
                onError: (bag) => setErrors(bag),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title={`${sheet.title} - ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={sheet.title}
                    description={`Pembacaan harian parameter engine, generator, trafo & baterai per mesin — ${unit.name} · ${OPERASI_MONTHS[filters.month - 1]} ${filters.year}${machineName ? ` · ${machineName}` : ''}.`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" onClick={() => router.get(harInput.index().url)}>
                                Kembali
                            </Button>
                            <Button variant="outline" onClick={() => window.open(parameterRoutes.pdf({ query }).url, '_blank')} className="gap-1.5">
                                <Download className="size-4 text-rose-600" />
                                PDF
                            </Button>
                            {can_write && (
                                <Button onClick={save} disabled={saving || !dirty || !filters.machine_id} className="gap-1.5">
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <div className="flex flex-wrap items-end justify-between gap-3 rounded-md border border-border bg-card p-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <OperasiSelect label="Unit" value={String(filters.unit_id)} onChange={(v) => visit({ unit_id: Number(v), machine_id: null })} options={options.units.map((u) => ({ value: String(u.id), label: u.name }))} />
                        <OperasiSelect label="Mesin" value={String(filters.machine_id ?? '')} onChange={(v) => visit({ machine_id: Number(v) })} options={options.machines.map((m) => ({ value: String(m.id), label: m.name }))} />
                        <OperasiSelect label="Bulan" value={String(filters.month)} onChange={(v) => visit({ month: Number(v) })} options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))} />
                        <OperasiSelect label="Tahun" value={String(filters.year)} onChange={(v) => visit({ year: Number(v) })} options={options.years.map((y) => ({ value: String(y), label: String(y) }))} />
                    </div>
                    {dirty ? (
                        <StatusBadge tone="warning">Ada perubahan belum disimpan</StatusBadge>
                    ) : has_saved ? (
                        <StatusBadge tone="success">Tersimpan</StatusBadge>
                    ) : (
                        <StatusBadge tone="neutral">Belum ada data</StatusBadge>
                    )}
                </div>

                {options.machines.length === 0 && <p className="text-[13px] text-amber-600">Unit ini belum punya data mesin — tambahkan di Master Mesin.</p>}

                <div className="grid grid-cols-[64px_1fr_64px] items-center gap-3 rounded-md border border-border bg-muted/10 p-4 sm:grid-cols-[140px_1fr_140px]">
                    <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-8 w-auto rounded-sm bg-white object-contain p-0.5 sm:h-11" />
                    <div className="text-center">
                        <div className="text-xs font-semibold uppercase text-muted-foreground">Jasa Pendukung Teknis 6 KIT</div>
                        <div className="text-xs font-semibold uppercase text-muted-foreground">Laporan Project {unit.name}</div>
                        <div className="text-base font-bold uppercase text-foreground">Patrol Check Pemeliharaan</div>
                        <div className="mt-1 text-xs font-semibold uppercase">
                            {OPERASI_MONTHS[filters.month - 1]} {filters.year}
                            {machineName ? ` · Mesin: ${machineName}` : ''}
                        </div>
                    </div>
                    <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="ml-auto h-8 w-auto rounded-sm bg-white object-contain p-0.5 sm:h-11" />
                </div>

                {Object.keys(errors).length > 0 && (
                    <ul className="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-xs text-destructive">
                        {Object.entries(errors).map(([key, message]) => (
                            <li key={key}>{message}</li>
                        ))}
                    </ul>
                )}

                <p className="text-xs text-red-600">Baris merah = akhir pekan / hari libur. Kolom angka menerima desimal koma atau titik.</p>

                <div className="overflow-x-auto rounded-md border border-border">
                    <table className="w-full min-w-[1400px] table-fixed border-collapse text-xs">
                        <colgroup>
                            {sheet.widths.map((width, index) => (
                                <col key={index} style={{ width: `${width}%` }} />
                            ))}
                        </colgroup>
                        <thead className="bg-muted/60 text-center">
                            {sheet.header_rows.map((cells, level) => (
                                <tr key={level}>
                                    {cells.map((cell, index) => (
                                        <th key={`${level}-${index}`} colSpan={cell.colspan} rowSpan={cell.rowspan} className={th}>
                                            {cell.label}
                                        </th>
                                    ))}
                                </tr>
                            ))}
                        </thead>
                        <tbody>
                            {days.map((day) => (
                                <tr key={day.key} className={day.is_red ? 'bg-red-500/25' : ''}>
                                    <td className="border border-border text-center font-semibold" title={day.sub ?? undefined}>
                                        {day.label}
                                    </td>
                                    {sheet.columns.map((column) => (
                                        <td key={column.key} className={`border border-border p-0 ${errors[`readings.${day.label}.${column.key}`] ? 'bg-destructive/20' : ''}`}>
                                            <input
                                                type="text"
                                                inputMode={column.type === 'number' ? 'decimal' : 'text'}
                                                value={readings[day.label]?.[column.key] ?? ''}
                                                onChange={(e) => setValue(day.label, column.key, e.target.value)}
                                                className={cellInput}
                                                disabled={!can_write || !filters.machine_id}
                                                aria-label={`${column.path.join(' ')} ${column.label} tanggal ${day.label}`}
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

HarPatrolCheckParameterPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harInput.index() },
        { title: 'Patrol Check Parameter Mesin', href: parameterRoutes.index() },
    ],
};
