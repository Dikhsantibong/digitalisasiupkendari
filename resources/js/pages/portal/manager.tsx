import { Head, Link, router } from '@inertiajs/react';
import {
    ChartNoAxesCombined,
    CheckCircle2,
    FileCheck2,
    Inbox,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { ReportQueue, SignedList } from '@/components/laporan/approval-inbox';
import type {
    HistoryItem,
    QueueItem,
} from '@/components/laporan/approval-inbox';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import resume from '@/routes/laporan/resume';
import portal from '@/routes/portal';
import type { Tone } from '@/types';

type Cell = {
    status: string;
    label: string;
    tone: Tone;
    waiting_for: string | null;
    days: number | null;
    stuck: boolean;
    url: string;
};

type Props = {
    period: { month: number; year: number; label: string };
    scope_label: string;
    unit_count: number;
    employee: { name: string; position: string } | null;
    counts: {
        waiting: number;
        signed_this_month: number;
        final: number;
        in_progress: number;
        rejected: number;
        stuck: number;
    };
    queue: QueueItem[];
    history: HistoryItem[];
    status: {
        modules: { key: string; label: string }[];
        matrix: { id: number; name: string; cells: Record<string, Cell> }[];
    };
    resume_modules: { key: string; label: string }[];
    options: { years: number[] };
};

function Section({
    title,
    icon,
    count,
    children,
    action,
}: {
    title: string;
    icon: ReactNode;
    count?: number;
    children: ReactNode;
    action?: ReactNode;
}) {
    return (
        <section className="flex flex-col rounded-md border border-border bg-card">
            <div className="flex flex-wrap items-center gap-2 border-b border-border px-4 py-3">
                <span className="text-primary">{icon}</span>
                <h2 className="flex-1 text-base font-semibold text-foreground">
                    {title}
                    {count !== undefined && (
                        <span className="ml-2 rounded-sm bg-muted px-1.5 text-[12px] font-medium text-muted-foreground tabular-nums">
                            {count}
                        </span>
                    )}
                </h2>
                {action}
            </div>
            {children}
        </section>
    );
}

function Stat({
    label,
    value,
    tone,
    hint,
}: {
    label: string;
    value: number;
    tone: 'primary' | 'success' | 'warning' | 'danger' | 'muted';
    hint: string;
}) {
    return (
        <div className="flex flex-col gap-1 rounded-md border border-border bg-card p-3.5">
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p
                className={cn(
                    'text-[26px] leading-none font-semibold tabular-nums',
                    tone === 'primary' && 'text-primary',
                    tone === 'success' && 'text-emerald-700',
                    tone === 'warning' && 'text-amber-700',
                    tone === 'danger' && 'text-red-700',
                    tone === 'muted' && 'text-foreground',
                )}
            >
                {value}
            </p>
            <p className="text-xs text-muted-foreground">{hint}</p>
        </div>
    );
}

/**
 * Beranda Manager UL: only what the Manager UL does — review the reports that
 * wait for their signature and sahkan or tolak them, see what they signed, and
 * the status of every report of their units. The full monitoring pages stay
 * one click away in the menu.
 */
export default function PortalManager({
    period,
    scope_label,
    unit_count,
    employee,
    counts,
    queue,
    history,
    status,
    resume_modules,
    options,
}: Props) {
    const visit = (patch: Partial<{ month: number; year: number }>) =>
        router.get(
            portal.index().url,
            { month: period.month, year: period.year, ...patch },
            { preserveScroll: true, preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Beranda Manager UL" />
            <div className="flex min-w-0 flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-xl font-semibold text-foreground">
                        Selamat datang{employee ? `, ${employee.name}` : ''}
                    </h1>
                    <p className="text-[13px] text-muted-foreground">
                        {employee?.position ?? 'Manager UL'} · {scope_label} ·{' '}
                        {unit_count} unit pembangkit. Tugas Anda di sini:{' '}
                        <b>review</b> laporan lalu <b>sahkan</b> atau{' '}
                        <b>tolak</b>.
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <Stat
                        label="Menunggu Anda"
                        value={counts.waiting}
                        tone={counts.waiting > 0 ? 'primary' : 'muted'}
                        hint="Perlu review & tanda tangan"
                    />
                    <Stat
                        label="Ditandatangani"
                        value={counts.signed_this_month}
                        tone="success"
                        hint={`Oleh Anda, ${period.label}`}
                    />
                    <Stat
                        label="Final"
                        value={counts.final}
                        tone="success"
                        hint={`Laporan ${period.label}`}
                    />
                    <Stat
                        label="Dalam proses"
                        value={counts.in_progress}
                        tone="muted"
                        hint="Belum sampai ke Anda / di tahap lain"
                    />
                    <Stat
                        label="Ditolak"
                        value={counts.rejected}
                        tone={counts.rejected > 0 ? 'danger' : 'muted'}
                        hint="Dikembalikan untuk perbaikan"
                    />
                    <Stat
                        label="Tertahan ≥ 3 hari"
                        value={counts.stuck}
                        tone={counts.stuck > 0 ? 'warning' : 'muted'}
                        hint="Menunggu di satu tahap"
                    />
                </div>

                <Section
                    title="Perlu Pengesahan Anda"
                    icon={<Inbox className="size-5" />}
                    count={queue.length}
                >
                    <ReportQueue
                        items={queue}
                        emptyHint="Laporan akan muncul di sini (dan lewat notifikasi) setelah disetujui Team Leader."
                    />
                </Section>

                <Section
                    title={`Status Laporan — ${period.label}`}
                    icon={<FileCheck2 className="size-5" />}
                    action={
                        <div className="flex items-end gap-2">
                            <OperasiSelect
                                label="Bulan"
                                value={String(period.month)}
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
                                value={String(period.year)}
                                onChange={(value) =>
                                    visit({ year: Number(value) })
                                }
                                options={options.years.map((y) => ({
                                    value: String(y),
                                    label: String(y),
                                }))}
                            />
                        </div>
                    }
                >
                    <div className="overflow-x-auto">
                        <table className="w-full text-[13px]">
                            <thead>
                                <tr className="border-b border-border bg-muted/50 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    <th className="px-4 py-2">Unit</th>
                                    {status.modules.map((m) => (
                                        <th key={m.key} className="px-3 py-2">
                                            {m.label.replace('Laporan ', '')}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {status.matrix.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-2 font-medium whitespace-nowrap text-foreground">
                                            {row.name}
                                        </td>
                                        {status.modules.map((m) => {
                                            const cell = row.cells[m.key];

                                            return (
                                                <td
                                                    key={m.key}
                                                    className="px-3 py-2 align-top"
                                                >
                                                    {cell ? (
                                                        <Link
                                                            href={cell.url}
                                                            className="flex flex-col gap-0.5 hover:opacity-80"
                                                            title={
                                                                cell.waiting_for ??
                                                                undefined
                                                            }
                                                        >
                                                            <StatusBadge
                                                                tone={
                                                                    cell.stuck
                                                                        ? 'warning'
                                                                        : cell.tone
                                                                }
                                                            >
                                                                {cell.label}
                                                            </StatusBadge>
                                                            {cell.waiting_for && (
                                                                <span className="text-[11px] text-muted-foreground">
                                                                    {
                                                                        cell.waiting_for
                                                                    }
                                                                </span>
                                                            )}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Section>

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <Section
                        title="Riwayat Tanda Tangan Anda"
                        icon={<CheckCircle2 className="size-5" />}
                        count={history.length}
                    >
                        <SignedList
                            items={history}
                            emptyText="Belum ada laporan yang Anda tanda tangani."
                        />
                    </Section>

                    <Section
                        title="Resume Bulanan (Grafik & Akumulasi)"
                        icon={<ChartNoAxesCombined className="size-5" />}
                    >
                        <div className="flex flex-col gap-2 p-4">
                            <p className="text-[13px] text-muted-foreground">
                                Ringkasan grafik, akumulasi & arah data per
                                modul untuk bahan review — bisa diunduh PDF /
                                Excel.
                            </p>
                            <div className="flex flex-wrap gap-2">
                                {resume_modules.map((m) => (
                                    <Button
                                        key={m.key}
                                        size="sm"
                                        variant="outline"
                                        asChild
                                    >
                                        <Link
                                            href={
                                                resume.show(m.key, {
                                                    query: {
                                                        month: period.month,
                                                        year: period.year,
                                                    },
                                                }).url
                                            }
                                        >
                                            {m.label}
                                        </Link>
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </Section>
                </div>
            </div>
        </>
    );
}

PortalManager.layout = { breadcrumbs: [] };
