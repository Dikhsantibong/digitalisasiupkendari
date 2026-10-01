import {
    Activity,
    Bell,
    ClipboardList,
    Factory,
    FileCheck2,
    HardHat,
    Package,
    TriangleAlert,
    UserRoundCheck,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import PushSubscriptionController from '@/actions/App/Http/Controllers/PushSubscriptionController';

export type AppNotification = {
    id: string;
    category: string | null;
    module: string;
    title: string;
    body: string;
    url: string | null;
    read: boolean;
    created_at: string | null;
};

export type NotificationFeed = { unread: number; items: AppNotification[] };

/** Label, icon and tone of each module a notification can come from. */
export const NOTIFICATION_MODULES: Record<string, { label: string; icon: LucideIcon; tone: string }> = {
    operasi: { label: 'Operasi', icon: Factory, tone: 'bg-sky-500/10 text-sky-600 dark:text-sky-400' },
    operator: { label: 'Absensi', icon: UserRoundCheck, tone: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' },
    har: { label: 'Pemeliharaan', icon: Wrench, tone: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' },
    k3: { label: 'K3 & Keamanan', icon: HardHat, tone: 'bg-rose-500/10 text-rose-600 dark:text-rose-400' },
    pdm: { label: 'PdM & MATLEV', icon: Activity, tone: 'bg-violet-500/10 text-violet-600 dark:text-violet-400' },
    logistik: { label: 'Logistik & Gudang', icon: Package, tone: 'bg-teal-500/10 text-teal-600 dark:text-teal-400' },
    umum: { label: 'Umum', icon: Bell, tone: 'bg-primary/10 text-primary' },
};

/** Event categories that stand out from the module colour (kejadian = alert, laporan = approval). */
const CATEGORY_LOOK: Record<string, { icon: LucideIcon; tone: string }> = {
    kejadian: { icon: TriangleAlert, tone: 'bg-red-500/10 text-red-600 dark:text-red-400' },
    pekerjaan: { icon: ClipboardList, tone: 'bg-amber-500/10 text-amber-600 dark:text-amber-400' },
    laporan: { icon: FileCheck2, tone: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400' },
};

export function notificationModule(module: string, category?: string | null) {
    const base = NOTIFICATION_MODULES[module] ?? NOTIFICATION_MODULES.umum;
    const look = category ? CATEGORY_LOOK[category] : undefined;

    return look ? { ...base, ...look } : base;
}

const RELATIVE = new Intl.RelativeTimeFormat('id-ID', { numeric: 'auto' });
const DATE_TIME = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

/** "5 menit lalu", "kemarin", or a date for older ones — formatted in the viewer's own time zone. */
export function timeAgo(iso: string | null, now: number): string {
    if (!iso) {
        return '';
    }

    const seconds = Math.round((new Date(iso).getTime() - now) / 1000);
    const abs = Math.abs(seconds);

    if (abs < 60) {
        return 'baru saja';
    }

    if (abs < 3600) {
        return RELATIVE.format(Math.round(seconds / 60), 'minute');
    }

    if (abs < 86400) {
        return RELATIVE.format(Math.round(seconds / 3600), 'hour');
    }

    if (abs < 7 * 86400) {
        return RELATIVE.format(Math.round(seconds / 86400), 'day');
    }

    return DATE_TIME.format(new Date(iso));
}

function xsrfToken(): string {
    const row = document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    return row ? decodeURIComponent(row.split('=')[1]) : '';
}

/** A same-origin JSON request with the session's CSRF token (outside Inertia page visits). */
export async function jsonRequest<T>(method: 'GET' | 'POST' | 'DELETE', url: string, body?: unknown): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`Request failed: ${response.status}`);
    }

    return (await response.json()) as T;
}

export function fetchFeed(): Promise<NotificationFeed> {
    return jsonRequest<NotificationFeed>('GET', NotificationController.feed().url);
}

export function markRead(id: string): Promise<{ unread: number }> {
    return jsonRequest<{ unread: number }>('POST', NotificationController.markRead(id).url);
}

export function markAllRead(): Promise<{ unread: number }> {
    return jsonRequest<{ unread: number }>('POST', NotificationController.markAllRead().url);
}

export function sendTestNotification(): Promise<{ unread: number; pushed: boolean }> {
    return jsonRequest<{ unread: number; pushed: boolean }>('POST', NotificationController.test().url);
}

/* ---------------------------------------------------------------------------
 * Push to the device ("Izinkan notifikasi")
 * ------------------------------------------------------------------------- */

export type PushState = 'unsupported' | 'insecure' | 'denied' | 'default' | 'granted' | 'subscribed';

export function pushSupported(): boolean {
    return typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

/** Registers the service worker once per page load (push needs it). */
export function registerServiceWorker(): Promise<ServiceWorkerRegistration> | null {
    if (!pushSupported() || !window.isSecureContext) {
        return null;
    }

    return navigator.serviceWorker.register('/sw.js', { scope: '/' });
}

export async function currentPushState(): Promise<PushState> {
    if (typeof window === 'undefined' || !('Notification' in window) || !('serviceWorker' in navigator)) {
        return 'unsupported';
    }

    if (!window.isSecureContext) {
        return 'insecure';
    }

    if (!pushSupported()) {
        return 'unsupported';
    }

    if (Notification.permission === 'denied') {
        return 'denied';
    }

    if (Notification.permission === 'default') {
        return 'default';
    }

    const registration = await navigator.serviceWorker.getRegistration('/');
    const subscription = await registration?.pushManager.getSubscription();

    return subscription ? 'subscribed' : 'granted';
}

function urlBase64ToUint8Array(base64: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64.length % 4)) % 4);
    const raw = window.atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));

    for (let i = 0; i < raw.length; i++) {
        bytes[i] = raw.charCodeAt(i);
    }

    return bytes;
}

/**
 * Asks the browser for permission (must run from a tap), subscribes this
 * device and stores the subscription for the signed-in account.
 */
export async function enablePush(publicKey: string): Promise<PushState> {
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        return permission === 'denied' ? 'denied' : 'default';
    }

    const registration = (await navigator.serviceWorker.getRegistration('/')) ?? (await navigator.serviceWorker.register('/sw.js', { scope: '/' }));
    await navigator.serviceWorker.ready;

    const subscription =
        (await registration.pushManager.getSubscription()) ??
        (await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(publicKey) }));

    await storeSubscription(subscription);

    return 'subscribed';
}

async function storeSubscription(subscription: PushSubscription): Promise<void> {
    const json = subscription.toJSON();
    const encodings = (PushManager as unknown as { supportedContentEncodings?: string[] }).supportedContentEncodings ?? ['aes128gcm'];

    await jsonRequest('POST', PushSubscriptionController.store().url, {
        endpoint: json.endpoint,
        keys: json.keys,
        contentEncoding: encodings.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm',
    });
}

let synced = false;

/**
 * Once per page load of a signed-in user: re-links this device's existing
 * subscription to the current account (after a log-in, or when another
 * account used the device before). Asks nothing of the user.
 */
export async function syncPushSubscription(): Promise<void> {
    if (synced || !pushSupported() || !window.isSecureContext || Notification.permission !== 'granted') {
        return;
    }

    synced = true;

    try {
        const registration = await navigator.serviceWorker.getRegistration('/');
        const subscription = await registration?.pushManager.getSubscription();

        if (subscription) {
            await storeSubscription(subscription);
        }
    } catch {
        synced = false;
    }
}

/**
 * Before log-out: unlink this device from the account so a signed-out phone
 * gets no more reminders. The browser keeps its subscription, so the next
 * log-in re-links it without asking again.
 */
export async function detachPushForLogout(): Promise<void> {
    if (!pushSupported() || !window.isSecureContext) {
        return;
    }

    try {
        const registration = await navigator.serviceWorker.getRegistration('/');
        const subscription = await registration?.pushManager.getSubscription();

        if (subscription) {
            await jsonRequest('DELETE', PushSubscriptionController.destroy().url, { endpoint: subscription.endpoint });
        }
    } catch {
        // Logging out must never be blocked by this.
    }

    synced = false;
}

/** Stops push on this device (the account keeps its other devices). */
export async function disablePush(): Promise<PushState> {
    const registration = await navigator.serviceWorker.getRegistration('/');
    const subscription = await registration?.pushManager.getSubscription();

    if (subscription) {
        await jsonRequest('DELETE', PushSubscriptionController.destroy().url, { endpoint: subscription.endpoint });
        await subscription.unsubscribe();
    }

    return 'granted';
}
