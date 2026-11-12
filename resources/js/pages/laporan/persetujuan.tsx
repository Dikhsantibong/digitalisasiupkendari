import { Head } from '@inertiajs/react';
import { BookOpenCheck, CheckCircle2, Inbox, UserCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    ReportQueue,
    RequestQueue,
    SignedList,
} from '@/components/laporan/approval-inbox';
import type {
    HistoryItem,
    QueueItem,
    RequestItem,
} from '@/components/laporan/approval-inbox';
import { PageHeader } from '@/components/page-header';
import { dashboard } from '@/routes';
import approvals from '@/routes/approvals';

type Props = {
    employee: { name: string; position: string } | null;
    queue: QueueItem[];
    requests: RequestItem[];
    history: HistoryItem[];
    acknowledgements: HistoryItem[];
    decides_requests: boolean;
};

function Section({
    title,
    icon,
    count,
    children,
}: {
    title: string;
    icon: ReactNode;
    count?: number;
    children: ReactNode;
}) {
    return (
        <section className="flex flex-col rounded-md border border-border bg-card">
            <div className="flex items-center gap-2 border-b border-border px-4 py-3">
                <span className="text-primary">{icon}</span>
                <h2 className="flex-1 text-base font-semibold text-foreground">
                    {title}
                    {count !== undefined && (
                        <span className="ml-2 rounded-sm bg-muted px-1.5 text-[12px] font-medium text-muted-foreground tabular-nums">
                            {count}
                        </span>
                    )}
                </h2>
            </div>
            {children}
        </section>
    );
}

/**
 * Persetujuan: the Team Leader, Koordinator and Project Leader (who also input)
 * find here everything that waits for their decision — reports at their step,
 * the operators' pengajuan — plus what they signed and the Laporan Project
 * they sign "Mengetahui" in.
 */
export default function Persetujuan({
    employee,
    queue,
    requests,
    history,
    acknowledgements,
    decides_requests,
}: Props) {
    const waiting = queue.length + requests.length;

    return (
        <>
            <Head title="Persetujuan" />
            <div className="flex min-w-0 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Persetujuan"
                    description={`${employee ? `${employee.name} · ${employee.position} · ` : ''}${waiting > 0 ? `${waiting} hal menunggu keputusan Anda.` : 'Tidak ada yang menunggu keputusan Anda.'}`}
                />

                <Section
                    title="Laporan Menunggu Persetujuan Anda"
                    icon={<Inbox className="size-5" />}
                    count={queue.length}
                >
                    <ReportQueue
                        items={queue}
                        emptyHint="Laporan muncul di sini (dan lewat notifikasi) saat tiba giliran Anda di alur pengesahan."
                    />
                </Section>

                {decides_requests && (
                    <Section
                        title="Pengajuan Operator (Izin / Cuti / Tukar Shift)"
                        icon={<UserCheck className="size-5" />}
                        count={requests.length}
                    >
                        <RequestQueue items={requests} />
                    </Section>
                )}

                <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <Section
                        title="Riwayat Tanda Tangan Anda"
                        icon={<CheckCircle2 className="size-5" />}
                        count={history.length}
                    >
                        <SignedList
                            items={history}
                            emptyText="Belum ada laporan yang Anda tanda tangani di alur pengesahan."
                        />
                    </Section>

                    {acknowledgements.length > 0 && (
                        <Section
                            title="Laporan Project yang Anda Ketahui"
                            icon={<BookOpenCheck className="size-5" />}
                            count={acknowledgements.length}
                        >
                            <p className="border-b border-border px-4 py-2 text-[12px] text-muted-foreground">
                                Tanda tangan "Mengetahui" Anda tercetak otomatis
                                saat laporan FINAL — tidak perlu aksi, buka PDF
                                untuk mereview.
                            </p>
                            <SignedList items={acknowledgements} emptyText="" />
                        </Section>
                    )}
                </div>
            </div>
        </>
    );
}

Persetujuan.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Persetujuan', href: approvals.index() },
    ],
};
