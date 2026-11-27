import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BellRing,
    CalendarCheck2,
    CheckCircle2,
    Clock,
    ShieldAlert,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

export type ScheduleTaskItem = {
    id: string;
    table_key: string;
    module: string;
    module_label: string;
    label: string;
    title: string;
    unit_id: number;
    unit_name: string;
    date: string;
    assignment_type: 'personal' | 'regu';
    regu: string | null;
    leader_name: string | null;
    requires_input: boolean;
    completed: boolean;
    action_label: string | null;
    url: string | null;
};

/** Re-check live schedule completion every 5 minutes on an open tab. */
const REFRESH_MS = 5 * 60 * 1000;

/** Snooze duration (30 minutes) when dismissed, so reminders come back until input is completed. */
const SNOOZE_MS = 30 * 60 * 1000;

const SNOOZE_STORAGE_KEY = 'schedule-task-banner-snoozed-until';

function isSameUrlPath(urlA: string | null, currentPath: string): boolean {
    if (!urlA) {
        return false;
    }

    try {
        const parsed = new URL(urlA, window.location.origin);
        return parsed.pathname.replace(/\/+$/, '') === currentPath.replace(/\/+$/, '');
    } catch {
        return false;
    }
}

/**
 * Periodic in-app reminder banner across application pages while the user still
 * has today's scheduled inputs pending completion. Snoozes for 30 minutes on
 * dismiss so it reappears periodically until submitted.
 */
export function ScheduleTaskBanner({ className }: { className?: string } = {}) {
    const { props, component } = usePage();
    const tasks = ((props.scheduleTasks ?? []) as ScheduleTaskItem[]).filter(
        (task) => task.requires_input && !task.completed,
    );
    const [snoozedUntil, setSnoozedUntil] = useState<number>(() => {
        try {
            const raw = window.sessionStorage.getItem(SNOOZE_STORAGE_KEY);
            return raw ? Number(raw) : 0;
        } catch {
            return 0;
        }
    });
    const [nowMs, setNowMs] = useState<number>(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => {
            setNowMs(Date.now());
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['scheduleTasks'] });
            }
        }, REFRESH_MS);

        return () => window.clearInterval(timer);
    }, []);

    if (tasks.length === 0 || component === 'dashboard') {
        return null;
    }

    const currentPath =
        typeof window !== 'undefined' ? window.location.pathname : '';
    const onTargetInputPage = tasks.some((t) =>
        isSameUrlPath(t.url, currentPath),
    );
    if (onTargetInputPage) {
        return null;
    }

    if (snoozedUntil > nowMs) {
        return null;
    }

    const primary = tasks[0];
    const labels = tasks
        .slice(0, 3)
        .map((t) => (t.regu ? `${t.label} (Regu ${t.regu})` : t.label))
        .join(', ');
    const extraCount = tasks.length > 3 ? ` +${tasks.length - 3} lainnya` : '';

    const dismiss = () => {
        const until = Date.now() + SNOOZE_MS;
        try {
            window.sessionStorage.setItem(SNOOZE_STORAGE_KEY, String(until));
        } catch {
            // Storage blocked: snoozed in memory only.
        }
        setSnoozedUntil(until);
    };

    return (
        <div
            role="status"
            className={cn(
                'mx-4 mt-3 flex flex-col gap-2.5 rounded-xl border border-amber-300 bg-amber-50 px-3.5 py-3 text-[13px] text-amber-950 shadow-xs sm:flex-row sm:items-center sm:gap-3 sm:rounded-md sm:px-3 sm:py-2.5 md:mx-6 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100',
                className,
            )}
        >
            <div className="flex items-start gap-3 sm:min-w-0 sm:flex-1">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/15 text-amber-800 sm:mt-0.5 sm:size-auto sm:rounded-none sm:bg-transparent dark:text-amber-200">
                    <BellRing className="size-4 shrink-0" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="leading-snug font-semibold">
                        {tasks.length === 1
                            ? `Jadwal hari ini belum diinput: ${primary.label}`
                            : `${tasks.length} jadwal hari ini belum diinput`}
                    </p>
                    <p className="mt-0.5 leading-snug opacity-90">
                        {labels}
                        {extraCount} · {primary.unit_name}. Pemberitahuan akan terus
                        muncul hingga penginputan selesai.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={dismiss}
                    className="-mt-0.5 -mr-1 shrink-0 rounded-md p-1 opacity-70 hover:opacity-100 sm:hidden"
                    aria-label="Ingatkan nanti"
                >
                    <X className="size-4" />
                </button>
            </div>
            <div className="flex items-center gap-2 sm:shrink-0">
                {primary.url && (
                    <Link
                        href={primary.url}
                        className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-primary px-3.5 py-2 text-[12.5px] font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 active:scale-[0.99] sm:w-auto sm:rounded-md sm:px-3 sm:py-1.5 sm:text-[12px]"
                    >
                        {primary.action_label ?? 'Isi Sekarang'}
                        <ArrowRight className="size-3.5" />
                    </Link>
                )}
                <button
                    type="button"
                    onClick={dismiss}
                    className="hidden shrink-0 rounded-md p-1 opacity-70 hover:opacity-100 sm:inline-flex"
                    aria-label="Ingatkan lagi 30 menit"
                    title="Ingatkan lagi 30 menit"
                >
                    <X className="size-4" />
                </button>
            </div>
        </div>
    );
}

/**
 * Live card showing all today's scheduled duties assigned directly to the signed-in user
 * (personally or via their regu) on Mobile Home and Desktop Dashboard.
 */
export function TodayScheduleCard({
    variant = 'desktop',
    className,
}: {
    variant?: 'mobile' | 'desktop';
    className?: string;
}) {
    const { props } = usePage();
    const tasks = (props.scheduleTasks ?? []) as ScheduleTaskItem[];

    if (tasks.length === 0) {
        return null;
    }

    const pendingCount = tasks.filter(
        (t) => t.requires_input && !t.completed,
    ).length;

    return (
        <section
            aria-label="Tugas Jadwal Hari Ini"
            className={cn(
                'flex flex-col border bg-card text-card-foreground',
                variant === 'mobile'
                    ? 'gap-2.5 rounded-2xl border-border/60 p-3.5 shadow-sm'
                    : 'gap-3 rounded-lg border-border p-4 shadow-xs',
                pendingCount > 0 && 'border-amber-300/80 dark:border-amber-700/70',
                className,
            )}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2.5">
                    <span
                        className={cn(
                            'flex size-8 shrink-0 items-center justify-center rounded-xl',
                            pendingCount > 0
                                ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300'
                                : 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
                        )}
                    >
                        <CalendarCheck2 className="size-4" />
                    </span>
                    <div className="min-w-0">
                        <h2
                            className={cn(
                                'font-semibold text-foreground',
                                variant === 'mobile'
                                    ? 'text-[14.5px] leading-tight'
                                    : 'text-sm leading-tight',
                            )}
                        >
                            Tugas Jadwal Hari Ini
                        </h2>
                        <p className="text-[12px] text-muted-foreground">
                            Penugasan sesuai jadwal hari ini pada akun &amp; regu
                            Anda
                        </p>
                    </div>
                </div>

                <span
                    className={cn(
                        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11.5px] font-semibold tabular-nums',
                        pendingCount > 0
                            ? 'bg-amber-500/15 text-amber-800 dark:text-amber-200'
                            : 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-200',
                    )}
                >
                    {pendingCount > 0
                        ? `${pendingCount} belum diinput`
                        : 'Semua tugas selesai / aktif'}
                </span>
            </div>

            <div
                className={cn(
                    'flex flex-col gap-2',
                    variant === 'desktop' &&
                        tasks.length > 1 &&
                        'grid grid-cols-1 gap-2.5 md:grid-cols-2',
                )}
            >
                {tasks.map((task) => {
                    const isPending = task.requires_input && !task.completed;
                    const isDone = task.requires_input && task.completed;

                    return (
                        <div
                            key={task.id}
                            className={cn(
                                'flex flex-col justify-between gap-2.5 rounded-xl border p-3 sm:flex-row sm:items-center',
                                isPending
                                    ? 'border-amber-300/80 bg-amber-50/60 dark:border-amber-800 dark:bg-amber-950/20'
                                    : isDone
                                      ? 'border-emerald-200 bg-emerald-50/40 dark:border-emerald-900 dark:bg-emerald-950/20'
                                      : 'border-sky-200 bg-sky-50/40 dark:border-sky-900 dark:bg-sky-950/20',
                            )}
                        >
                            <div className="min-w-0 flex-1 space-y-1">
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <span className="rounded bg-background/90 px-1.5 py-0.5 text-[10.5px] font-semibold tracking-wide text-muted-foreground uppercase ring-1 ring-border">
                                        {task.module_label}
                                    </span>
                                    {task.regu && (
                                        <span className="rounded bg-primary/10 px-1.5 py-0.5 text-[10.5px] font-semibold text-primary">
                                            Regu {task.regu}
                                        </span>
                                    )}
                                    {isPending && (
                                        <span className="inline-flex items-center gap-1 rounded bg-amber-500/20 px-1.5 py-0.5 text-[10.5px] font-semibold text-amber-900 dark:text-amber-200">
                                            <Clock className="size-3" />
                                            Belum Dikerjakan
                                        </span>
                                    )}
                                    {isDone && (
                                        <span className="inline-flex items-center gap-1 rounded bg-emerald-500/20 px-1.5 py-0.5 text-[10.5px] font-semibold text-emerald-800 dark:text-emerald-200">
                                            <CheckCircle2 className="size-3" />
                                            Sudah Diinput
                                        </span>
                                    )}
                                    {!task.requires_input && (
                                        <span className="inline-flex items-center gap-1 rounded bg-sky-500/20 px-1.5 py-0.5 text-[10.5px] font-semibold text-sky-900 dark:text-sky-200">
                                            <ShieldAlert className="size-3" />
                                            Siaga / On Call
                                        </span>
                                    )}
                                </div>
                                <p className="text-[13.5px] leading-snug font-semibold text-foreground">
                                    {task.label}
                                </p>
                                <p className="text-[12px] leading-snug text-muted-foreground">
                                    {task.title} · {task.unit_name}
                                    {task.leader_name
                                        ? ` · Leader Shift: ${task.leader_name}`
                                        : ''}
                                </p>
                            </div>

                            {task.url && (
                                <Link
                                    href={task.url}
                                    className={cn(
                                        'inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-[12px] font-semibold transition active:scale-[0.98]',
                                        isPending
                                            ? 'bg-primary text-primary-foreground shadow-xs hover:bg-primary/90'
                                            : 'border border-border bg-background text-foreground hover:bg-muted',
                                    )}
                                >
                                    {isPending
                                        ? (task.action_label ?? 'Kerjakan')
                                        : 'Buka'}
                                    <ArrowRight className="size-3.5" />
                                </Link>
                            )}
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
