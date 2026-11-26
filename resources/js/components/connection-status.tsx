import { router } from '@inertiajs/react';
import { CheckCircle2, ServerCrash, WifiOff } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Connection notice on every page, desktop and phone (user, 2026-11-26:
 * "pemberitahuan jika koneksi internet hilang … pastikan tidak ada fake
 * notif"). The browser's own online/offline flag is only a hint, so nothing
 * is shown until a real request fails: the app's health route (/up, no cache)
 * is asked CONFIRM_TRIES times, CONFIRM_GAP_MS apart, and every try must fail.
 * - offline: the device reports no network and /up is unreachable;
 * - server: the device says online but /up cannot be reached (Wi-Fi without
 *   internet, or the server is down) — said as such, not as "no internet".
 * While a notice shows, /up is retried every RETRY_MS; once it answers, a
 * short "Koneksi kembali" replaces it.
 */
type Status = 'online' | 'offline' | 'server';

const CONFIRM_TRIES = 2;
const CONFIRM_GAP_MS = 2500;
const RETRY_MS = 5000;
const TIMEOUT_MS = 6000;
const RESTORED_MS = 3000;

/** Whether the app's server answers at all (any HTTP status counts as reachable). */
async function reachable(): Promise<boolean> {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), TIMEOUT_MS);

    try {
        await fetch(`/up?_=${Date.now()}`, {
            method: 'GET',
            cache: 'no-store',
            credentials: 'same-origin',
            signal: controller.signal,
        });

        return true;
    } catch {
        return false;
    } finally {
        window.clearTimeout(timer);
    }
}

const wait = (ms: number) =>
    new Promise<void>((resolve) => window.setTimeout(resolve, ms));

export function ConnectionStatus() {
    const [status, setStatus] = useState<Status>('online');
    const [restored, setRestored] = useState(false);
    const checking = useRef(false);
    const statusRef = useRef<Status>('online');

    useEffect(() => {
        let cancelled = false;
        let retry: number | undefined;
        let restoredTimer: number | undefined;

        const apply = (next: Status) => {
            if (cancelled || statusRef.current === next) {
                return;
            }

            const wasDown = statusRef.current !== 'online';
            statusRef.current = next;
            setStatus(next);
            window.clearTimeout(restoredTimer);

            if (next === 'online' && wasDown) {
                setRestored(true);
                restoredTimer = window.setTimeout(
                    () => setRestored(false),
                    RESTORED_MS,
                );
            } else {
                setRestored(false);
            }
        };

        /** Confirm a lost connection with real requests before saying so. */
        const confirm = async () => {
            if (checking.current) {
                return;
            }

            checking.current = true;

            try {
                for (let attempt = 0; attempt < CONFIRM_TRIES; attempt++) {
                    if (attempt > 0) {
                        await wait(CONFIRM_GAP_MS);
                    }

                    if (await reachable()) {
                        apply('online');

                        return;
                    }
                }

                apply(navigator.onLine ? 'server' : 'offline');
            } finally {
                checking.current = false;
            }
        };

        /** While a notice shows: one request at a time until the server answers again. */
        const scheduleRetry = () => {
            window.clearTimeout(retry);
            retry = window.setTimeout(async () => {
                if (statusRef.current === 'online') {
                    return;
                }

                if (await reachable()) {
                    apply('online');
                } else {
                    apply(navigator.onLine ? 'server' : 'offline');
                    scheduleRetry();
                }
            }, RETRY_MS);
        };

        const onOffline = () => {
            void confirm().then(scheduleRetry);
        };
        const onOnline = () => {
            if (statusRef.current !== 'online') {
                void reachable().then((ok) => {
                    if (ok) {
                        apply('online');
                    }
                });
            }
        };

        window.addEventListener('offline', onOffline);
        window.addEventListener('online', onOnline);
        // A visit that failed on the network (not an HTTP error page).
        const stopNetworkError = router.on('networkError', onOffline);

        if (!navigator.onLine) {
            onOffline();
        }

        return () => {
            cancelled = true;
            window.removeEventListener('offline', onOffline);
            window.removeEventListener('online', onOnline);
            stopNetworkError();
            window.clearTimeout(retry);
            window.clearTimeout(restoredTimer);
        };
    }, []);

    if (status === 'online' && !restored) {
        return null;
    }

    const content =
        status === 'offline'
            ? {
                  icon: WifiOff,
                  title: 'Tidak ada koneksi internet',
                  text: 'Periksa data seluler atau Wi-Fi Anda. Perubahan yang belum tersimpan jangan ditutup dulu.',
                  tone: 'bg-red-600 text-white',
              }
            : status === 'server'
              ? {
                    icon: ServerCrash,
                    title: 'Tidak dapat terhubung ke server',
                    text: 'Jaringan tersambung, tetapi server tidak menjawab. Periksa koneksi internet Anda atau coba lagi sebentar.',
                    tone: 'bg-amber-500 text-amber-950',
                }
              : {
                    icon: CheckCircle2,
                    title: 'Koneksi kembali tersambung',
                    text: '',
                    tone: 'bg-emerald-600 text-white',
                };
    const Icon = content.icon;

    return (
        <div
            role={status === 'online' ? 'status' : 'alert'}
            aria-live="assertive"
            className="pointer-events-none fixed inset-x-0 top-[calc(var(--impersonation-h,0px)+0.5rem)] z-[70] flex justify-center px-3"
        >
            <div
                className={cn(
                    'pointer-events-auto flex max-w-md items-start gap-2.5 rounded-md px-3.5 py-2.5 text-[13px] shadow-lg',
                    content.tone,
                )}
            >
                <Icon className="mt-0.5 size-4 shrink-0" />
                <div className="min-w-0">
                    <p className="font-semibold">{content.title}</p>
                    {content.text && (
                        <p className="mt-0.5 text-[12px] opacity-90">
                            {content.text}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
