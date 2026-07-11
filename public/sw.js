/**
 * Service Worker — Notifications Web Push (MEKA ERP).
 *
 * S'exécute en tâche de fond, indépendamment de l'onglet : reçoit les
 * messages push envoyés par le serveur (WebPushChannel) et affiche une
 * notification système à l'écran — même quand l'application n'est pas
 * ouverte (ex. l'utilisateur est sur un autre logiciel). Le son est celui
 * des notifications du système d'exploitation.
 */

self.addEventListener('push', (event) => {
	let payload = {};
	try {
		payload = event.data ? event.data.json() : {};
	} catch (e) {
		payload = { title: 'Notification', body: event.data ? event.data.text() : '' };
	}

	const title = payload.title || 'MEKA ERP';
	const options = {
		body: payload.body || '',
		icon: payload.icon || '/favicon.ico',
		badge: payload.badge || '/favicon.ico',
		tag: payload.tag || undefined,          // regroupe/évite les doublons
		renotify: Boolean(payload.tag),         // re-sonne même si tag identique
		requireInteraction: payload.requireInteraction || false,
		data: { url: payload.url || '/' },
		vibrate: [180, 80, 180],                // léger retour haptique (mobile)
	};

	event.waitUntil(self.registration.showNotification(title, options));
});

// Clic sur la notification : focalise un onglet existant de l'app ou en ouvre un.
self.addEventListener('notificationclick', (event) => {
	event.notification.close();
	const targetUrl = (event.notification.data && event.notification.data.url) || '/';

	event.waitUntil(
		clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
			for (const client of windowClients) {
				if ('focus' in client) {
					client.focus();
					if ('navigate' in client) {
						client.navigate(targetUrl).catch(() => {});
					}
					return;
				}
			}
			if (clients.openWindow) {
				return clients.openWindow(targetUrl);
			}
		})
	);
});

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
