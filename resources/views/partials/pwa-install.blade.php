{{--
    Enregistrement du service worker et invite d'installation.

    L'enregistrement est fait ici, indépendamment des notifications push : la
    PWA doit s'installer même si l'utilisateur refuse les notifications.
--}}
<div id="pwa-install-banner"
     class="hidden fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 sm:w-80 z-[9998] bg-white border border-secondary/25 rounded-2xl shadow-2xl p-4">
    <div class="flex items-start gap-3">
        <div class="w-10 h-10 rounded-xl bg-primary text-white flex items-center justify-center shrink-0">
            <i data-lucide="download" class="w-5 h-5"></i>
        </div>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-primary">Installer l'application</p>
            <p class="text-[11px] text-primary/55 mt-0.5 leading-relaxed" id="pwa-install-text">
                Accès direct depuis votre écran d'accueil, sans passer par le navigateur.
            </p>
            <div class="flex items-center gap-2 mt-3">
                <button type="button" id="pwa-install-accept"
                        class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                    Installer
                </button>
                <button type="button" id="pwa-install-dismiss"
                        class="px-3 py-1.5 text-xs text-primary/55 hover:text-primary transition-colors">
                    Plus tard
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const DISMISS_KEY = 'pwa-install-dismissed-at';
    // Un refus vaut 14 jours : on ne harcèle pas l'utilisateur à chaque visite.
    const DISMISS_DAYS = 14;

    // ── Enregistrement du service worker ──────────────────────────────
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch((error) => {
                console.warn('Service worker non enregistré :', error);
            });
        });
    }

    // ── Invite d'installation ─────────────────────────────────────────
    const banner  = document.getElementById('pwa-install-banner');
    const accept  = document.getElementById('pwa-install-accept');
    const dismiss = document.getElementById('pwa-install-dismiss');
    if (!banner) return;

    // Déjà installée (lancée en mode autonome) : aucune invite.
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    function recentlyDismissed() {
        const at = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
        return at && (Date.now() - at) < DISMISS_DAYS * 86400000;
    }

    function show() {
        if (isStandalone || recentlyDismissed()) return;
        banner.classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
    }

    function hide() {
        banner.classList.add('hidden');
    }

    dismiss.addEventListener('click', () => {
        localStorage.setItem(DISMISS_KEY, String(Date.now()));
        hide();
    });

    // Android / Chrome / Edge : le navigateur signale que l'app est installable.
    let deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();          // on choisit nous-mêmes le moment
        deferredPrompt = event;
        show();
    });

    accept.addEventListener('click', async () => {
        if (!deferredPrompt) { hide(); return; }
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        hide();
    });

    window.addEventListener('appinstalled', () => {
        localStorage.setItem(DISMISS_KEY, String(Date.now()));
        hide();
    });

    // iOS/Safari n'émet pas beforeinstallprompt : on explique le geste manuel.
    const ua = window.navigator.userAgent;
    const isIos = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    if (isIos && !isStandalone) {
        document.getElementById('pwa-install-text').textContent =
            "Dans Safari : bouton Partager, puis « Sur l'écran d'accueil ».";
        accept.classList.add('hidden');
        dismiss.textContent = 'Compris';
        setTimeout(show, 2500);
    }
})();
</script>
