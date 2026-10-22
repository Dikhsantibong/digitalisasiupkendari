/**
 * "Install aplikasi" (PWA, see public/manifest.webmanifest). Chrome / Edge /
 * Android fire `beforeinstallprompt` once, early — often before the page that
 * shows the button mounts — so it is captured here at start-up and replayed
 * by {@see promptInstall}. iPhone/iPad Safari has no prompt: the user adds the
 * app from the Share menu instead.
 */
type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

let deferred: BeforeInstallPromptEvent | null = null;
const listeners = new Set<() => void>();
const notify = () => listeners.forEach((listener) => listener());

if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferred = event as BeforeInstallPromptEvent;
        notify();
    });
    window.addEventListener('appinstalled', () => {
        deferred = null;
        notify();
    });
}

/** Whether the browser can show its own install prompt right now. */
export const canPromptInstall = (): boolean => deferred !== null;

/** Already opened as the installed app. */
export const isInstalled = (): boolean =>
    typeof window !== 'undefined' &&
    (window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone ===
            true);

export const isIos = (): boolean =>
    typeof navigator !== 'undefined' &&
    (/iphone|ipad|ipod/i.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1));

/** Show the browser's install prompt; false when it is not available. */
export async function promptInstall(): Promise<boolean> {
    if (!deferred) {
        return false;
    }

    const event = deferred;
    deferred = null;
    await event.prompt();
    const { outcome } = await event.userChoice;
    notify();

    return outcome === 'accepted';
}

export function onInstallChange(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}
