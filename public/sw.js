/*
 * Service worker of the web app: shows push notifications (pengingat jadwal &
 * absen) on the device, even when the app is closed, and opens the right page
 * when one is tapped. It caches nothing.
 */

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        payload = { title: 'Notifikasi', body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'Notifikasi';
    const options = {
        body: payload.body || '',
        icon: payload.icon || '/apple-touch-icon.png',
        badge: payload.badge || '/apple-touch-icon.png',
        tag: payload.tag,
        renotify: Boolean(payload.tag),
        lang: payload.lang || 'id',
        data: payload.data || {},
    };

    event.waitUntil(
        Promise.all([
            self.registration.showNotification(title, options),
            // Open tabs refresh their bell right away.
            self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) =>
                clients.forEach((client) => client.postMessage({ type: 'notifications:refresh' })),
            ),
        ]),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const data = event.notification.data || {};
    // Through the "open" route so the notification is marked read before its page opens.
    const target = new URL(data.id ? `/notifikasi/${data.id}` : data.url || '/notifikasi', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const client = clients.find((c) => new URL(c.url).origin === self.location.origin);

            if (client) {
                return client.focus().then(() => client.navigate(target));
            }

            return self.clients.openWindow(target);
        }),
    );
});
