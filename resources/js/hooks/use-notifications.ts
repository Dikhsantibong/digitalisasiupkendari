import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { fetchFeed, markAllRead, markRead, syncPushSubscription } from '@/lib/notifications';
import type { AppNotification } from '@/lib/notifications';

/** How often an open, visible tab refreshes the bell. */
const POLL_MS = 60_000;

/**
 * The bell's state: the unread count (first from the shared page prop) and the
 * latest notifications, refreshed every minute while the tab is visible, when
 * it becomes visible again, and right away when a push arrives.
 */
export function useNotifications() {
    const shared = usePage().props.notifications;
    const [unread, setUnread] = useState(shared?.unread ?? 0);
    const [items, setItems] = useState<AppNotification[] | null>(null);
    const [loading, setLoading] = useState(false);

    // A page visit brings a fresh count from the server.
    const [seen, setSeen] = useState(shared?.unread ?? 0);

    if ((shared?.unread ?? 0) !== seen) {
        setSeen(shared?.unread ?? 0);
        setUnread(shared?.unread ?? 0);
    }

    const refresh = useCallback(async () => {
        setLoading(true);

        try {
            const feed = await fetchFeed();
            setUnread(feed.unread);
            setItems(feed.items);
        } catch {
            // Offline or signed out: keep what is shown.
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        // Re-link this device's push subscription to the signed-in account (no prompt).
        void syncPushSubscription();

        const tick = () => {
            if (document.visibilityState === 'visible') {
                void refresh();
            }
        };
        const timer = window.setInterval(tick, POLL_MS);
        const onMessage = (event: MessageEvent) => {
            if (event.data?.type === 'notifications:refresh') {
                void refresh();
            }
        };

        document.addEventListener('visibilitychange', tick);
        navigator.serviceWorker?.addEventListener('message', onMessage);

        return () => {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', tick);
            navigator.serviceWorker?.removeEventListener('message', onMessage);
        };
    }, [refresh]);

    const read = useCallback(async (id: string) => {
        setItems((current) => current?.map((item) => (item.id === id ? { ...item, read: true } : item)) ?? null);

        try {
            setUnread((await markRead(id)).unread);
        } catch {
            // The open route marks it read anyway.
        }
    }, []);

    const readAll = useCallback(async () => {
        setItems((current) => current?.map((item) => ({ ...item, read: true })) ?? null);

        try {
            setUnread((await markAllRead()).unread);
        } catch {
            void refresh();
        }
    }, [refresh]);

    return { unread, items, loading, refresh, read, readAll };
}
