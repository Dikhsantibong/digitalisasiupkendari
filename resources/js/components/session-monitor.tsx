import { router } from '@inertiajs/react';
import { ClockAlert, LogIn } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    checkServerSession,
    isAuthenticatedSession,
    isIntentionalLogout,
    notifySessionExpired,
    saveSessionExpiredNotice,
    subscribeSessionExpired,
    syncAuthState,
    wasAuthenticatedSession,
} from '@/lib/session-guard';

const REDIRECT_SECONDS = 5;
const CHECK_THROTTLE_MS = 15_000;

/**
 * Global session-expiration monitor for both desktop and mobile layouts:
 * - Verifies session status when returning to an idle/backgrounded tab or phone
 *   (visibilitychange / focus) and when network connectivity returns (online);
 * - Intercepts Inertia 401 / 419 invalid responses and background JSON 401 / 419
 *   failures (e.g. notification polling);
 * - Displays an unmistakable notification modal with a short countdown and a
 *   "Masuk Kembali" button, then performs a clean redirect to /login where the
 *   expired-session banner is also shown.
 */
export function SessionMonitor() {
    const [notice, setNotice] = useState<string | null>(null);
    const [secondsLeft, setSecondsLeft] = useState(REDIRECT_SECONDS);
    const lastCheckAt = useRef(0);
    const redirecting = useRef(false);

    const redirectToLogin = useCallback((message: string) => {
        if (redirecting.current) {
            return;
        }

        redirecting.current = true;
        saveSessionExpiredNotice(message);
        window.location.assign('/login');
    }, []);

    useEffect(() => {
        const unsubscribe = subscribeSessionExpired((message) => {
            setNotice(message);
            if (message !== null) {
                setSecondsLeft(REDIRECT_SECONDS);
            }
        });

        const maybeCheck = (force = false) => {
            if (!isAuthenticatedSession() || isIntentionalLogout()) {
                return;
            }

            const now = Date.now();
            if (!force && now - lastCheckAt.current < CHECK_THROTTLE_MS) {
                return;
            }

            lastCheckAt.current = now;
            void checkServerSession();
        };

        const onVisibility = () => {
            if (document.visibilityState === 'visible') {
                maybeCheck(false);
            }
        };
        const onFocus = () => maybeCheck(false);
        const onOnline = () => maybeCheck(true);

        const stopNavigate = router.on('navigate', (event) => {
            const page = (
                event as CustomEvent<{
                    page?: {
                        component?: string;
                        props?: {
                            auth?: { user?: unknown };
                            sessionExpired?: string | null;
                        };
                    };
                }>
            ).detail?.page;

            if (!page) {
                return;
            }

            const hasUser = Boolean(page.props?.auth?.user);
            const component = page.component;

            if (
                !hasUser &&
                component === 'auth/login' &&
                wasAuthenticatedSession() &&
                !isIntentionalLogout() &&
                !page.props?.sessionExpired
            ) {
                saveSessionExpiredNotice();
            }

            syncAuthState(hasUser, component);
        });

        const stopHttpException = router.on('httpException', (event) => {
            const status = event.detail.response.status;

            if (
                (status === 401 || status === 419) &&
                isAuthenticatedSession() &&
                !isIntentionalLogout()
            ) {
                event.preventDefault();
                notifySessionExpired();
            }
        });

        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('focus', onFocus);
        window.addEventListener('online', onOnline);

        return () => {
            unsubscribe();
            stopNavigate();
            stopHttpException();
            document.removeEventListener('visibilitychange', onVisibility);
            window.removeEventListener('focus', onFocus);
            window.removeEventListener('online', onOnline);
        };
    }, []);

    useEffect(() => {
        if (notice === null) {
            return;
        }

        if (secondsLeft <= 0) {
            redirectToLogin(notice);

            return;
        }

        const timer = window.setTimeout(() => {
            setSecondsLeft((prev) => prev - 1);
        }, 1000);

        return () => window.clearTimeout(timer);
    }, [notice, secondsLeft, redirectToLogin]);

    if (notice === null) {
        return null;
    }

    return (
        <div
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="session-expired-title"
            aria-describedby="session-expired-description"
            className="fixed inset-0 z-[80] flex items-center justify-center bg-black/55 p-4 backdrop-blur-xs"
        >
            <div className="w-full max-w-sm rounded-2xl border border-border bg-card p-5 text-card-foreground shadow-2xl sm:p-6">
                <div className="flex flex-col items-center text-center">
                    <span className="mb-3.5 flex size-13 items-center justify-center rounded-2xl bg-amber-500/15 text-amber-600 dark:text-amber-400">
                        <ClockAlert className="size-7" />
                    </span>

                    <h2
                        id="session-expired-title"
                        className="text-lg font-semibold tracking-tight text-foreground"
                    >
                        Sesi Anda Telah Berakhir
                    </h2>

                    <p
                        id="session-expired-description"
                        className="mt-2 text-[13.5px] leading-relaxed text-muted-foreground"
                    >
                        {notice}
                    </p>

                    <div className="mt-4 w-full rounded-xl bg-muted/60 px-3 py-2 text-xs font-medium text-muted-foreground tabular-nums">
                        Mengalihkan ke halaman login dalam{' '}
                        <span className="font-semibold text-foreground">
                            {secondsLeft} detik
                        </span>
                        …
                    </div>

                    <Button
                        type="button"
                        size="lg"
                        onClick={() => redirectToLogin(notice)}
                        className="mt-4 w-full gap-2"
                        data-test="session-expired-login-button"
                    >
                        <LogIn className="size-4" />
                        Masuk Kembali Sekarang
                    </Button>
                </div>
            </div>
        </div>
    );
}
