import { Link, router } from '@inertiajs/react';
import {
    CalendarClock,
    CheckCircle2,
    Circle,
    CircleDot,
    Clock,
    Eye,
    FileText,
    PenLine,
    ShieldCheck,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { formatStamp } from '@/components/monitoring/shared';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import pengajuan from '@/routes/operator/pengajuan';
import reportWorkflow from '@/routes/report-workflow';

/**
 * The approval inbox (App\Services\Reports\ApprovalInbox): reports at the
 * user's step of the workflow, the operators' pengajuan, the reports they
 * signed and the Laporan Project they sign "Mengetahui" in. Shared by the
 * Manager UL home, the dashboard (Team Leader, Koordinator, Project Leader)
 * and the Persetujuan page; every action posts to the existing routes.
 */

type Links = { view_url: string; download_url: string; document_url: string };

export type QueueItem = Links & {
    key: number;
    module: string;
    unit_id: number;
    month: number;
    year: number;
    unit: string;
    title: string;
    period: string;
    status: string;
    action: 'verify' | 'approve' | 'ratify';
    action_label: string;
    submitted_by: string | null;
    submitted_at: string | null;
    waiting_days: number;
    steps: {
        caption: string;
        position: string;
        name: string | null;
        signed_at: string | null;
        current: boolean;
    }[];
};

export type HistoryItem = Links & {
    key: number;
    unit: string;
    title: string;
    period: string;
    caption: string;
    signed_at?: string | null;
    status: string;
    final: boolean;
};

export type RequestItem = {
    id: number;
    type_label: string;
    unit: string;
    employee: string;
    regu: string | null;
    substitute: string | null;
    start_date: string;
    end_date: string;
    days: number;
    reason: string | null;
    created_at: string | null;
    url: string;
};

const ACTION_TEXT: Record<
    QueueItem['action'],
    { button: string; title: string; done: string }
> = {
    verify: {
        button: 'Verifikasi',
        title: 'Verifikasi laporan',
        done: 'memverifikasi',
    },
    approve: {
        button: 'Setujui',
        title: 'Setujui laporan',
        done: 'menyetujui',
    },
    ratify: { button: 'Sahkan', title: 'Sahkan laporan', done: 'mengesahkan' },
};

const dateLabel = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

/** One decision dialog: a note (optional) to approve, a reason (required) to reject. */
function DecisionDialog({
    open,
    title,
    description,
    reject,
    confirmLabel,
    minLength,
    onClose,
    onSubmit,
}: {
    open: boolean;
    title: string;
    description: string;
    reject: boolean;
    confirmLabel: string;
    minLength: number;
    onClose: () => void;
    onSubmit: (
        text: string,
        done: () => void,
        fail: (message: string) => void,
    ) => void;
}) {
    const [text, setText] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const close = () => {
        setText('');
        setError(null);
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={(value) => !value && close()}>
            <DialogContent>
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>
                <Textarea
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    placeholder={
                        reject
                            ? `Alasan penolakan (wajib, min. ${minLength} karakter)`
                            : 'Catatan (opsional)'
                    }
                    rows={4}
                />
                {error && <p className="text-[13px] text-red-700">{error}</p>}
                <DialogFooter className="gap-2">
                    <Button
                        variant="outline"
                        onClick={close}
                        disabled={processing}
                    >
                        Batal
                    </Button>
                    <Button
                        variant={reject ? 'destructive' : 'default'}
                        disabled={
                            processing ||
                            (reject && text.trim().length < minLength)
                        }
                        onClick={() => {
                            setProcessing(true);
                            onSubmit(
                                text,
                                () => {
                                    setProcessing(false);
                                    close();
                                },
                                (message) => {
                                    setProcessing(false);
                                    setError(message);
                                },
                            );
                        }}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

const firstError = (errors: Record<string, string>) =>
    Object.values(errors)[0] ?? 'Gagal menyimpan.';

/** The signers so far: ✓ signed, ● your turn, ○ still to come. */
export function SignerSteps({ steps }: { steps: QueueItem['steps'] }) {
    return (
        <ol className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[12px]">
            {steps.map((step) => (
                <li
                    key={step.caption + step.position}
                    className="flex items-center gap-1"
                >
                    {step.signed_at ? (
                        <CheckCircle2 className="size-3.5 text-emerald-600" />
                    ) : step.current ? (
                        <CircleDot className="size-3.5 text-primary" />
                    ) : (
                        <Circle className="size-3.5 text-muted-foreground" />
                    )}
                    <span
                        className={cn(
                            step.current
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        {step.caption} · {step.name ?? step.position}
                    </span>
                </li>
            ))}
        </ol>
    );
}

/** Reports at the user's step: Review, PDF, Tolak and Verifikasi / Setujui / Sahkan. */
export function ReportQueue({
    items,
    emptyText = 'Tidak ada laporan yang menunggu Anda.',
    emptyHint,
}: {
    items: QueueItem[];
    emptyText?: string;
    emptyHint?: string;
}) {
    const [dialog, setDialog] = useState<{
        item: QueueItem;
        reject: boolean;
    } | null>(null);

    if (items.length === 0) {
        return (
            <div className="flex flex-col items-center gap-2 px-4 py-8 text-center">
                <ShieldCheck className="size-8 text-emerald-600" />
                <p className="text-sm font-medium text-foreground">
                    {emptyText}
                </p>
                {emptyHint && (
                    <p className="text-[13px] text-muted-foreground">
                        {emptyHint}
                    </p>
                )}
            </div>
        );
    }

    return (
        <>
            <ul className="divide-y divide-border">
                {items.map((item) => (
                    <li
                        key={item.key}
                        className="flex flex-col gap-3 px-4 py-4 lg:flex-row lg:items-center"
                    >
                        <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="text-sm font-semibold text-foreground">
                                    {item.title}
                                </p>
                                <StatusBadge tone="info">
                                    {item.status}
                                </StatusBadge>
                                {item.waiting_days >= 3 && (
                                    <StatusBadge tone="warning">
                                        <Clock className="size-3" />{' '}
                                        {item.waiting_days} hari menunggu
                                    </StatusBadge>
                                )}
                            </div>
                            <p className="text-[13px] text-muted-foreground">
                                {item.unit} · {item.period}
                                {item.submitted_by
                                    ? ` · diajukan ${item.submitted_by}`
                                    : ''}
                                {item.submitted_at
                                    ? ` (${formatStamp(item.submitted_at)})`
                                    : ''}
                            </p>
                            <SignerSteps steps={item.steps} />
                        </div>
                        <div className="flex shrink-0 flex-wrap gap-2">
                            <Button size="sm" variant="outline" asChild>
                                <Link href={item.document_url}>
                                    <Eye className="size-4" /> Review
                                </Link>
                            </Button>
                            <Button size="sm" variant="outline" asChild>
                                <a
                                    href={item.view_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FileText className="size-4" /> PDF
                                </a>
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                className="text-red-700 hover:text-red-800"
                                onClick={() =>
                                    setDialog({ item, reject: true })
                                }
                            >
                                <XCircle className="size-4" /> Tolak
                            </Button>
                            <Button
                                size="sm"
                                onClick={() =>
                                    setDialog({ item, reject: false })
                                }
                            >
                                <PenLine className="size-4" />{' '}
                                {ACTION_TEXT[item.action].button}
                            </Button>
                        </div>
                    </li>
                ))}
            </ul>

            {dialog && (
                <DecisionDialog
                    open
                    reject={dialog.reject}
                    minLength={5}
                    title={
                        dialog.reject
                            ? 'Tolak laporan'
                            : ACTION_TEXT[dialog.item.action].title
                    }
                    confirmLabel={
                        dialog.reject
                            ? 'Tolak laporan'
                            : ACTION_TEXT[dialog.item.action].button
                    }
                    description={`${dialog.item.title} · ${dialog.item.unit} · ${dialog.item.period}. ${
                        dialog.reject
                            ? 'Laporan dikembalikan ke pembuat untuk diperbaiki. Tulis alasannya.'
                            : `Dengan ini Anda ${ACTION_TEXT[dialog.item.action].done} laporan${dialog.item.action === 'ratify' ? ' dan statusnya menjadi FINAL' : ''}. Pastikan sudah direview.`
                    }`}
                    onClose={() => setDialog(null)}
                    onSubmit={(text, done, fail) => {
                        const { item, reject } = dialog;
                        const route = reject
                            ? reportWorkflow.reject(item.module)
                            : reportWorkflow[item.action](item.module);
                        router.post(
                            route.url,
                            {
                                unit_id: item.unit_id,
                                month: item.month,
                                year: item.year,
                                [reject ? 'reason' : 'note']: text || null,
                            },
                            {
                                preserveScroll: true,
                                onSuccess: () => done(),
                                onError: (errors) => fail(firstError(errors)),
                            },
                        );
                    }}
                />
            )}
        </>
    );
}

/** The operators' pengajuan waiting for a decision: Setujui / Tolak. */
export function RequestQueue({ items }: { items: RequestItem[] }) {
    const [dialog, setDialog] = useState<{
        item: RequestItem;
        reject: boolean;
    } | null>(null);

    if (items.length === 0) {
        return (
            <p className="px-4 py-6 text-center text-[13px] text-muted-foreground">
                Tidak ada pengajuan operator yang menunggu keputusan.
            </p>
        );
    }

    return (
        <>
            <ul className="divide-y divide-border">
                {items.map((item) => (
                    <li
                        key={item.id}
                        className="flex flex-col gap-2 px-4 py-3 lg:flex-row lg:items-center"
                    >
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold text-foreground">
                                {item.type_label} · {item.employee}
                                {item.regu ? ` (Regu ${item.regu})` : ''}
                            </p>
                            <p className="flex flex-wrap items-center gap-1 text-[13px] text-muted-foreground">
                                <CalendarClock className="size-3.5" />
                                {dateLabel(item.start_date)}
                                {item.end_date !== item.start_date
                                    ? ` – ${dateLabel(item.end_date)}`
                                    : ''}{' '}
                                ({item.days} hari) · {item.unit}
                                {item.substitute
                                    ? ` · pengganti ${item.substitute}`
                                    : ''}
                            </p>
                            {item.reason && (
                                <p className="text-[12px] text-muted-foreground italic">
                                    "{item.reason}"
                                </p>
                            )}
                        </div>
                        <div className="flex shrink-0 flex-wrap gap-2">
                            <Button size="sm" variant="outline" asChild>
                                <Link href={item.url}>
                                    <Eye className="size-4" /> Detail
                                </Link>
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                className="text-red-700 hover:text-red-800"
                                onClick={() =>
                                    setDialog({ item, reject: true })
                                }
                            >
                                <XCircle className="size-4" /> Tolak
                            </Button>
                            <Button
                                size="sm"
                                onClick={() =>
                                    setDialog({ item, reject: false })
                                }
                            >
                                <CheckCircle2 className="size-4" /> Setujui
                            </Button>
                        </div>
                    </li>
                ))}
            </ul>

            {dialog && (
                <DecisionDialog
                    open
                    reject={dialog.reject}
                    minLength={3}
                    title={
                        dialog.reject ? 'Tolak pengajuan' : 'Setujui pengajuan'
                    }
                    confirmLabel={dialog.reject ? 'Tolak' : 'Setujui'}
                    description={`${dialog.item.type_label} ${dialog.item.employee}, ${dateLabel(dialog.item.start_date)}${
                        dialog.reject
                            ? '. Tulis alasan penolakan.'
                            : '. Jadwal Shift diperbarui otomatis setelah disetujui.'
                    }`}
                    onClose={() => setDialog(null)}
                    onSubmit={(text, done, fail) => {
                        const { item, reject } = dialog;
                        router.post(
                            (reject
                                ? pengajuan.reject(item.id)
                                : pengajuan.approve(item.id)
                            ).url,
                            { note: text || null },
                            {
                                preserveScroll: true,
                                onSuccess: () => done(),
                                onError: (errors) => fail(firstError(errors)),
                            },
                        );
                    }}
                />
            )}
        </>
    );
}

/** Reports the user signed, or signs "Mengetahui" in: status and PDF. */
export function SignedList({
    items,
    emptyText,
}: {
    items: HistoryItem[];
    emptyText: string;
}) {
    if (items.length === 0) {
        return (
            <p className="px-4 py-6 text-center text-[13px] text-muted-foreground">
                {emptyText}
            </p>
        );
    }

    return (
        <ul className="divide-y divide-border">
            {items.map((item) => (
                <li
                    key={item.key}
                    className="flex items-center gap-3 px-4 py-3"
                >
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-foreground">
                            {item.title}
                        </p>
                        <p className="text-[12px] text-muted-foreground">
                            {item.unit} · {item.period} · {item.caption}{' '}
                            {item.signed_at ? formatStamp(item.signed_at) : ''}
                        </p>
                    </div>
                    <StatusBadge tone={item.final ? 'success' : 'info'}>
                        {item.status}
                    </StatusBadge>
                    <Button size="sm" variant="ghost" asChild>
                        <a
                            href={item.view_url}
                            target="_blank"
                            rel="noreferrer"
                            aria-label="Lihat PDF"
                        >
                            <FileText className="size-4" />
                        </a>
                    </Button>
                </li>
            ))}
        </ul>
    );
}
