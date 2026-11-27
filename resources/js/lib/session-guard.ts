/**
 * Coordinates client-side session expiration detection across layouts (desktop,
 * mobile shell, portal, and settings) without false positives for guests,
 * manual logouts, or temporary network drops.
 */

const STORAGE_KEY = 'kit-sipp:session-expired-notice';
const CHECK_TIMEOUT_MS = 6000;

export const DEFAULT_SESSION_EXPIRED_MESSAGE =
    'Sesi Anda telah berakhir. Silakan masuk kembali untuk melanjutkan.';

type Listener = (message: string | null) => void;

let authenticated = false;
let hadAuthenticatedSession = false;
let intentionalLogout = false;
let expiredMessage: string | null = null;
let checking = false;

const listeners = new Set<Listener>();

function emit(): void {
    for (const listener of listeners) {
        listener(expiredMessage);
    }
}

export function syncAuthState(
    isAuthenticated: boolean,
    component?: string,
): void {
    if (isAuthenticated) {
        authenticated = true;
        hadAuthenticatedSession = true;
        intentionalLogout = false;

        if (expiredMessage !== null) {
            expiredMessage = null;
            emit();
        }

        return;
    }

    authenticated = false;

    if (component === 'auth/login') {
        intentionalLogout = false;
        hadAuthenticatedSession = false;

        if (expiredMessage !== null) {
            expiredMessage = null;
            emit();
        }
    }
}

export function isAuthenticatedSession(): boolean {
    return authenticated;
}

export function wasAuthenticatedSession(): boolean {
    return hadAuthenticatedSession;
}

export function isIntentionalLogout(): boolean {
    return intentionalLogout;
}

/** Call before POST /logout so intentional sign-out never shows an expiry alert. */
export function markIntentionalLogout(): void {
    intentionalLogout = true;
    hadAuthenticatedSession = false;
}

export function notifySessionExpired(
    message: string = DEFAULT_SESSION_EXPIRED_MESSAGE,
): void {
    if (intentionalLogout || !authenticated) {
        return;
    }

    if (expiredMessage === null) {
        expiredMessage = message;
        emit();
    }
}

export function subscribeSessionExpired(listener: Listener): () => void {
    listeners.add(listener);

    if (expiredMessage !== null) {
        listener(expiredMessage);
    }

    return () => {
        listeners.delete(listener);
    };
}

export function saveSessionExpiredNotice(
    message: string = DEFAULT_SESSION_EXPIRED_MESSAGE,
): void {
    try {
        window.sessionStorage.setItem(STORAGE_KEY, message);
    } catch {
        // Private browsing or disabled storage: ignore.
    }
}

export function consumeSessionExpiredNotice(): string | null {
    try {
        const message = window.sessionStorage.getItem(STORAGE_KEY);
        if (message !== null) {
            window.sessionStorage.removeItem(STORAGE_KEY);
        }

        return message;
    } catch {
        return null;
    }
}

/**
 * Checks with the server whether the current authenticated session is still
 * active. Only explicit 401/419 (or account-deactivated 403) triggers expiry;
 * offline / network / 5xx errors are ignored so ConnectionStatus handles them.
 */
export async function checkServerSession(): Promise<void> {
    if (
        checking ||
        !authenticated ||
        intentionalLogout ||
        expiredMessage !== null ||
        typeof navigator === 'undefined' ||
        !navigator.onLine
    ) {
        return;
    }

    checking = true;
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), CHECK_TIMEOUT_MS);

    try {
        const response = await fetch(`/session/check?_=${Date.now()}`, {
            method: 'GET',
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        });

        if (response.status === 401 || response.status === 419) {
            notifySessionExpired();

            return;
        }

        if (response.status === 403) {
            try {
                const payload = (await response.json()) as {
                    message?: string;
                };
                if (
                    payload?.message &&
                    payload.message.toLowerCase().includes('nonaktif')
                ) {
                    notifySessionExpired(payload.message);
                }
            } catch {
                // Non-JSON 403: ignore.
            }
        }
    } catch {
        // Network drop / timeout: ConnectionStatus handles offline state.
    } finally {
        window.clearTimeout(timer);
        checking = false;
    }
}
