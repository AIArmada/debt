self.addEventListener('push', (event) => {
    const data = event.data ? event.data.json() : {};
    const title = data.title || 'Debt Management';
    const options = {
        body: data.body || 'A private record needs your attention.',
        icon: '/favicon.svg',
        badge: '/favicon.svg',
        data: { url: data.url || '/dashboard' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = new URL(event.notification.data?.url || '/dashboard', self.location.origin).href;

    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
        const existing = clientList.find((client) => 'focus' in client);
        if (existing) {
            existing.navigate(targetUrl);

            return existing.focus();
        }

        return clients.openWindow(targetUrl);
    }));
});
