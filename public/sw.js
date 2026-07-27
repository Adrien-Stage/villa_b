/**
 * Service Worker — WeTchah.
 *
 * Deux responsabilités :
 *  1. Notifications Web Push : reçoit les messages du serveur et affiche une
 *     notification système même quand l'application n'est pas ouverte.
 *  2. Application installable (PWA) : met en cache la coquille et les assets
 *     pour un démarrage rapide, et affiche une page dédiée hors connexion.
 *
 * Principe de prudence : ce cache ne sert QUE des ressources publiques et des
 * pages consultées. Les requêtes qui modifient des données ne sont jamais
 * interceptées — un ERP ne doit pas rejouer une écriture depuis un cache.
 */

const VERSION = 'v2';
const SHELL_CACHE = `wetchah-shell-${VERSION}`;
const ASSET_CACHE = `wetchah-assets-${VERSION}`;
const PAGE_CACHE  = `wetchah-pages-${VERSION}`;

const OFFLINE_URL = '/offline';

// Ressources indispensables pour afficher quelque chose sans réseau.
const SHELL_URLS = [OFFLINE_URL, '/manifest.webmanifest'];

/**
 * Chemins jamais mis en cache : authentification, paiements, caisse et toute
 * donnée sensible ou fortement volatile. Servir une version périmée y serait
 * pire que d'afficher une erreur réseau.
 */
const NEVER_CACHE = [
	'/login', '/logout', '/admin',
	'/api/', '/broadcasting/', '/push/',
	'/bookings/', '/payments', '/cash-register', '/caisse',
];

// ── Installation / activation ────────────────────────────────────────────

self.addEventListener('install', (event) => {
	event.waitUntil(
		caches.open(SHELL_CACHE)
			// addAll échoue en bloc si une URL manque : on tolère l'absence.
			.then((cache) => Promise.allSettled(SHELL_URLS.map((url) => cache.add(url))))
			.then(() => self.skipWaiting())
	);
});

self.addEventListener('activate', (event) => {
	event.waitUntil(
		caches.keys()
			.then((keys) => Promise.all(
				keys
					// Purge les caches des versions précédentes du service worker.
					.filter((key) => key.startsWith('wetchah-') && !key.endsWith(VERSION))
					.map((key) => caches.delete(key))
			))
			.then(() => self.clients.claim())
	);
});

// Permet à la page de demander l'activation immédiate d'une nouvelle version.
self.addEventListener('message', (event) => {
	if (event.data === 'SKIP_WAITING') self.skipWaiting();
});

// ── Interception réseau ──────────────────────────────────────────────────

self.addEventListener('fetch', (event) => {
	const { request } = event;
	const url = new URL(request.url);

	// Jamais de cache pour : les écritures, les autres domaines, les chemins
	// sensibles. On laisse le navigateur faire son travail normalement.
	if (request.method !== 'GET') return;
	if (url.origin !== self.location.origin) return;
	if (NEVER_CACHE.some((path) => url.pathname.startsWith(path))) return;

	// Navigation (pages HTML) : réseau d'abord, cache en secours, puis page
	// hors-ligne. L'utilisateur voit toujours la donnée la plus fraîche.
	if (request.mode === 'navigate') {
		event.respondWith(networkFirst(request));
		return;
	}

	// Assets versionnés (build Vite) et icônes : cache d'abord, c'est immuable.
	if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/pwa/') || url.pathname.startsWith('/storage/')) {
		event.respondWith(cacheFirst(request, ASSET_CACHE));
		return;
	}
});

/** Réseau d'abord : réponse fraîche mise en cache, repli hors-ligne sinon. */
async function networkFirst(request) {
	try {
		const response = await fetch(request);

		if (response && response.ok) {
			const cache = await caches.open(PAGE_CACHE);
			cache.put(request, response.clone());
		}

		return response;
	} catch (error) {
		const cached = await caches.match(request);
		if (cached) return cached;

		const offline = await caches.match(OFFLINE_URL);
		return offline || new Response('Hors connexion', {
			status: 503,
			headers: { 'Content-Type': 'text/plain; charset=utf-8' },
		});
	}
}

/** Cache d'abord : idéal pour les fichiers au nom versionné (jamais modifiés). */
async function cacheFirst(request, cacheName) {
	const cached = await caches.match(request);
	if (cached) return cached;

	try {
		const response = await fetch(request);
		if (response && response.ok) {
			const cache = await caches.open(cacheName);
			cache.put(request, response.clone());
		}
		return response;
	} catch (error) {
		return new Response('', { status: 504 });
	}
}

// ── Notifications Web Push (comportement d'origine, inchangé) ────────────

self.addEventListener('push', (event) => {
	let payload = {};
	try {
		payload = event.data ? event.data.json() : {};
	} catch (e) {
		payload = { title: 'Notification', body: event.data ? event.data.text() : '' };
	}

	const title = payload.title || 'WeTchah';
	const options = {
		body: payload.body || '',
		icon: payload.icon || '/pwa/icon-192.png',
		badge: payload.badge || '/pwa/icon-192.png',
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
