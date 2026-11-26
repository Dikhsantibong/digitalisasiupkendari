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
export function AttendanceAlertBanner() {
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
                'mx-4 mt-3 flex items-start gap-3 rounded-md border px-3 py-2.5 text-[13px] md:mx-6',
                STYLE[alert.kind],
            )}
        >
            <Icon className="mt-0.5 size-4 shrink-0" />
            <div className="min-w-0 flex-1">
                <p className="font-semibold">{alert.title}</p>
                <p className="mt-0.5">{alert.message}</p>
            </div>
            <Link
                href={alert.url}
                className="shrink-0 rounded-md bg-primary px-3 py-1.5 text-[12px] font-semibold text-primary-foreground hover:bg-primary/90"
            >
                {alert.kind === 'pulang' ? 'Absen Pulang' : 'Absen Sekarang'}
            </Link>
            <button
                type="button"
                onClick={dismiss}
                className="shrink-0 rounded-md p-1 opacity-70 hover:opacity-100"
                aria-label="Tutup pemberitahuan absen"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}
