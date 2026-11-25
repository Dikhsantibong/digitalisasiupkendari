import { Head, router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Clock, Wrench } from 'lucide-react';
import { useState } from 'react';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import flmOperasi from '@/routes/har/flm-operasi';
import type { IdName } from '@/types';

type Status = 'open' | 'close' | 'semua';

type Row = {
    id: number;
    periode: string;
    tanggal: string | null;
    mesin: string | null;
    masalah: string | null;
    kondisi_awal: string[];
    kondisi_akhir: string | null;
    catatan: string | null;
    status: 'open' | 'close';
    reporter_name: string | null;
    reporter_shift: string | null;
    closed_by_name: string | null;
    closed_at: string | null;
    open_days: number | null;
    foto_awal_url: string | null;
};

type Props = {
    filters: { unit_id: number; status: Status };
    options: { units: IdName[] };
    rows: Row[];
    counts: { open: number; overdue: number };
    remind_after_days: number;
    can_close: boolean;
};

const formatDate = (date: string | null) => {
    if (!date) {
        return '—';
    }

    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

/**
 * FLM dari Operator (Pemeliharaan): the operators' Monitoring FLM findings with
 * who reported them (name & shift) and how long they stay open; Pemeliharaan
 * closes an open finding. Open findings past the limit are reminded daily.
 */
export default function FlmOperasiPage({
    filters,
    options,
    rows,
    counts,
    remind_after_days,
    can_close,
}: Props) {
    const [closing, setClosing] = useState<Row | null>(null);
    const [kondisiAkhir, setKondisiAkhir] = useState('');
    const [catatan, setCatatan] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const visit = (patch: Partial<Props['filters']>) =>
        router.get(
            flmOperasi.index().url,
            { ...filters, ...patch },
            { preserveScroll: true, replace: true },
        );

    const openClose = (row: Row) => {
        setClosing(row);
        setKondisiAkhir(row.kondisi_akhir ?? '');
        setCatatan('');
        setErrors({});
    };

    const submitClose = () => {
        if (!closing) {
            return;
        }

        setSaving(true);
        router.post(
            flmOperasi.close(closing.id).url,
            { kondisi_akhir: kondisiAkhir, catatan },
            {
                preserveScroll: true,
                onSuccess: () => setClosing(null),
                onError: (errs) => setErrors(errs),
                onFinish: () => setSaving(false),
            },
        );
    };

    const overdue = (row: Row) =>
        row.status === 'open' && (row.open_days ?? 0) >= remind_after_days;

    return (
        <>
            <Head title="FLM dari Operator" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="FLM dari Operator"
                    description={`Temuan Monitoring FLM yang diinput operator (nama & shift pelapor). Tutup FLM yang masih open setelah ditangani; FLM yang open ${remind_after_days} hari atau lebih diingatkan setiap hari.`}
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    {options.units.length > 1 && (
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
                    )}
                    <div
                        className="flex gap-1.5"
                        role="tablist"
                        aria-label="Status FLM"
                    >
                        {(['open', 'close', 'semua'] as const).map((status) => (
                            <Button
                                key={status}
                                size="sm"
                                variant={
                                    filters.status === status
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => visit({ status })}
                                role="tab"
                                aria-selected={filters.status === status}
                            >
                                {status === 'open'
                                    ? `Open (${counts.open})`
                                    : status === 'close'
                                      ? 'Close'
                                      : 'Semua'}
                            </Button>
                        ))}
                    </div>
                    {counts.overdue > 0 && (
                        <span className="flex items-center gap-1.5 rounded-md bg-amber-500/10 px-2.5 py-1.5 text-[12.5px] font-medium text-amber-700 dark:text-amber-300">
                            <AlertTriangle className="size-4" />
                            {counts.overdue} FLM open ≥ {remind_after_days} hari
                        </span>
                    )}
                </div>

                {rows.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        {filters.status === 'open'
                            ? 'Tidak ada FLM yang masih open.'
                            : 'Belum ada data FLM.'}
                    </div>
                ) : (
                    <>
                        {/* Phone: cards */}
                        <div className="flex flex-col gap-2 md:hidden">
                            {rows.map((row) => (
                                <div
                                    key={row.id}
                                    className={cn(
                                        'flex flex-col gap-2 rounded-lg border p-3',
                                        overdue(row)
                                            ? 'border-amber-500/50 bg-amber-500/5'
                                            : 'border-border bg-card',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="font-semibold">
                                                {row.mesin || '—'}
                                            </p>
                                            <p className="text-[13px]">
                                                {row.masalah || '—'}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            row={row}
                                            late={overdue(row)}
                                        />
                                    </div>
                                    <p className="text-[12px] text-muted-foreground">
                                        {formatDate(row.tanggal)} · Dilaporkan{' '}
                                        {row.reporter_name ?? '—'}
                                        {row.reporter_shift
                                            ? ` (${row.reporter_shift})`
                                            : ''}
                                    </p>
                                    {row.status === 'close' && (
                                        <p className="text-[12px] text-emerald-700 dark:text-emerald-400">
                                            Ditutup {row.closed_by_name ?? '—'}{' '}
                                            · {row.closed_at ?? ''} —{' '}
                                            {row.kondisi_akhir}
                                        </p>
                                    )}
                                    {can_close && row.status === 'open' && (
                                        <Button
                                            size="sm"
                                            onClick={() => openClose(row)}
                                        >
                                            <CheckCircle2 className="size-4" />{' '}
                                            Close FLM
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>

                        {/* Desktop: table */}
                        <div className="hidden overflow-x-auto rounded-md border border-border bg-card md:block">
                            <table className="w-full border-collapse text-[13px]">
                                <thead className="bg-muted/60 text-left text-[12px] text-muted-foreground uppercase">
                                    <tr>
                                        {[
                                            'Tanggal',
                                            'Mesin',
                                            'Masalah',
                                            'Kondisi Awal',
                                            'Dilaporkan',
                                            'Shift',
                                            'Status',
                                            'Kondisi Akhir / Ditutup',
                                            '',
                                        ].map((h) => (
                                            <th
                                                key={h}
                                                className="border-b border-border px-3 py-2 font-semibold whitespace-nowrap"
                                            >
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row) => (
                                        <tr
                                            key={row.id}
                                            className={cn(
                                                'border-b border-border align-top',
                                                overdue(row) &&
                                                    'bg-amber-500/5',
                                            )}
                                        >
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {formatDate(row.tanggal)}
                                            </td>
                                            <td className="px-3 py-2 font-medium">
                                                {row.mesin || '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.masalah || '—'}
                                                {row.foto_awal_url && (
                                                    <a
                                                        href={row.foto_awal_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="ml-1 text-[12px] text-primary underline"
                                                    >
                                                        foto
                                                    </a>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-[12px]">
                                                {row.kondisi_awal.join(', ') ||
                                                    '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.reporter_name ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {row.reporter_shift ?? '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                <StatusBadge
                                                    row={row}
                                                    late={overdue(row)}
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-[12px]">
                                                {row.status === 'close' ? (
                                                    <>
                                                        {row.kondisi_akhir ||
                                                            '—'}
                                                        <span className="block text-muted-foreground">
                                                            {row.closed_by_name ??
                                                                '—'}{' '}
                                                            ·{' '}
                                                            {row.closed_at ??
                                                                ''}
                                                        </span>
                                                    </>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {can_close &&
                                                    row.status === 'open' && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                openClose(row)
                                                            }
                                                        >
                                                            <CheckCircle2 className="size-4" />{' '}
                                                            Close
                                                        </Button>
                                                    )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>

            <Dialog
                open={closing !== null}
                onOpenChange={(open) => !open && setClosing(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogTitle>Close FLM</DialogTitle>
                    {closing && (
                        <div className="flex flex-col gap-3">
                            <p className="text-[13px] text-muted-foreground">
                                <span className="font-medium text-foreground">
                                    {closing.mesin}
                                </span>{' '}
                                — {closing.masalah}
                                <br />
                                Dilaporkan {closing.reporter_name ?? '—'}
                                {closing.reporter_shift
                                    ? ` (${closing.reporter_shift})`
                                    : ''}{' '}
                                · {formatDate(closing.tanggal)}
                            </p>
                            <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                Kondisi akhir (hasil penanganan)
                                <Input
                                    value={kondisiAkhir}
                                    onChange={(e) =>
                                        setKondisiAkhir(e.target.value)
                                    }
                                    placeholder="mis. Sudah dikencangkan, normal"
                                />
                                {errors.kondisi_akhir && (
                                    <span className="text-destructive">
                                        {errors.kondisi_akhir}
                                    </span>
                                )}
                            </label>
                            <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                                Catatan (opsional)
                                <Textarea
                                    value={catatan}
                                    onChange={(e) => setCatatan(e.target.value)}
                                    rows={2}
                                />
                            </label>
                            <div className="flex justify-end gap-2">
                                <Button
                                    variant="secondary"
                                    onClick={() => setClosing(null)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    onClick={submitClose}
                                    disabled={
                                        saving || kondisiAkhir.trim() === ''
                                    }
                                >
                                    <Wrench className="size-4" />{' '}
                                    {saving ? 'Menyimpan…' : 'Close FLM'}
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

function StatusBadge({ row, late }: { row: Row; late: boolean }) {
    if (row.status === 'close') {
        return (
            <span className="rounded-full bg-emerald-600/10 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400">
                CLOSE
            </span>
        );
    }

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap',
                late
                    ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                    : 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
            )}
        >
            <Clock className="size-3" />
            OPEN{row.open_days !== null ? ` · ${row.open_days} hari` : ''}
        </span>
    );
}

FlmOperasiPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'FLM dari Operator', href: flmOperasi.index() },
    ],
};
