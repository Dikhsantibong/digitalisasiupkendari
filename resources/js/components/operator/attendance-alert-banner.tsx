import { Link, router, usePage } from '@inertiajs/react';
import { AlarmClock, LogIn, LogOut, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

type AttendanceAlert = {
    kind: 'segera' | 'terlambat' | 'pulang';
    work_date: string;
    title: string;
    message: string;
    url: string;
};

/** How often an open page re-checks the absen still owed (only that prop is reloaded). */
const REFRESH_MS = 5 * 60 * 1000;

const STYLE: Record<AttendanceAlert['kind'], string> = {
    segera: 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100',
    terlambat:
        'border-red-300 bg-red-50 text-red-950 dark:border-red-800 dark:bg-red-950/40 dark:text-red-100',
    pulang: 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100',
};

const dismissKey = (alert: AttendanceAlert) =>
    `attendance-alert:${alert.kind}:${alert.work_date}`;

/**
 * The absen the signed-in user still owes by their Jadwal Shift (user,
 * 2026-11-26): before / during a working shift without absen masuk, or after
 * it without absen pulang — on every page of the account, with a link to
 * Absen. Computed by the server on each visit (AttendanceReminders::pendingFor),
 * so it shows even when the reminder cron does not run; hidden on the Absen
 * page itself. "Tutup" hides it for this browser session only.
 */
export function AttendanceAlertBanner({ className }: { className?: string } = {}) {
    const { props, component } = usePage();
    const alert = (props.attendanceAlert ?? null) as AttendanceAlert | null;
    const [dismissed, setDismissed] = useState<string | null>(null);

    // A page left open still notices a shift starting: re-ask for just this prop now and then.
    useEffect(() => {
        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['attendanceAlert'] });
            }
        }, REFRESH_MS);

        return () => window.clearInterval(timer);
    }, []);

    if (!alert || component === 'operator/presensi/index') {
        return null;
    }

    const key = dismissKey(alert);
    let hidden = dismissed === key;

    try {
        hidden ||= window.sessionStorage.getItem(key) === '1';
    } catch {
        // Storage blocked: the banner just stays.
    }

    if (hidden) {
        return null;
    }

    const Icon =
        alert.kind === 'pulang'
            ? LogOut
            : alert.kind === 'segera'
              ? AlarmClock
              : LogIn;

    const dismiss = () => {
        try {
            window.sessionStorage.setItem(key, '1');
        } catch {
            // Storage blocked: hidden until the next visit only.
        }

        setDismissed(key);
    };

    return (
        <div
            role="alert"
            className={cn(
                'mx-4 mt-3 flex flex-col gap-2.5 rounded-xl border px-3.5 py-3 text-[13px] shadow-xs sm:flex-row sm:items-center sm:gap-3 sm:rounded-md sm:px-3 sm:py-2.5 md:mx-6',
                STYLE[alert.kind],
                className,
            )}
        >
            <div className="flex items-start gap-3 sm:min-w-0 sm:flex-1">
                <span
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center rounded-lg sm:mt-0.5 sm:size-auto sm:rounded-none sm:bg-transparent',
                        alert.kind === 'terlambat'
                            ? 'bg-red-500/15 text-red-700 dark:text-red-200'
                            : 'bg-amber-500/15 text-amber-800 dark:text-amber-200',
                    )}
                >
                    <Icon className="size-4 shrink-0" />
                </span>
                <div className="min-w-0 flex-1">
                    <p className=" leading-snug font-semibold">{alert.title}</p>
                    <p className="mt-0.5 leading-snug opacity-90">
                        {alert.message}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={dismiss}
                    className="-mt-0.5 -mr-1 shrink-0 rounded-md p-1 opacity-70 hover:opacity-100 sm:hidden"
                    aria-label="Tutup pemberitahuan absen"
                >
                    <X className="size-4" />
                </button>
            </div>
            <div className="flex items-center gap-2 sm:shrink-0">
                <Link
                    href={alert.url}
                    className="inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-primary px-3.5 py-2 text-[12.5px] font-semibold text-primary-foreground shadow-xs hover:bg-primary/90 active:scale-[0.99] sm:w-auto sm:rounded-md sm:px-3 sm:py-1.5 sm:text-[12px]"
                >
                    <Icon className="size-3.5 sm:hidden" />
                    {alert.kind === 'pulang' ? 'Absen Pulang' : 'Absen Sekarang'}
                </Link>
                <button
                    type="button"
                    onClick={dismiss}
                    className="hidden shrink-0 rounded-md p-1 opacity-70 hover:opacity-100 sm:inline-flex"
                    aria-label="Tutup pemberitahuan absen"
                >
                    <X className="size-4" />
                </button>
            </div>
        </div>
    );
}
