{{--
    Gestion de la déconnexion automatique après 20 minutes d'inactivité.
    - Écoute les interactions utilisateur (souris, clavier, tactile, défilement)
    - Synchronise l'état d'activité entre tous les onglets via localStorage
    - Ferme la session côté serveur puis redirige vers la page de veille dès que le délai est atteint
--}}
@auth
<script>
(function() {
    const TIMEOUT_MS = {{ (int) config('session.lifetime', 20) * 60 * 1000 }};
    const STORAGE_KEY = 'wetchah_last_user_activity';
    const STANDBY_URL = '{{ route('login', ['expired' => 'inactivity'], false) }}';
    const LOGOUT_URL = '{{ route('logout', [], false) }}';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    let expiring = false;
    let lastRecorded = Date.now();
    try {
        localStorage.setItem(STORAGE_KEY, String(lastRecorded));
    } catch (e) {}

    // Throttle l'enregistrement d'activité à 5 secondes pour économiser les ressources CPU
    const onUserActivity = () => {
        if (expiring) return;
        const now = Date.now();
        if (now - lastRecorded < 5000) return;
        lastRecorded = now;
        try {
            localStorage.setItem(STORAGE_KEY, String(now));
        } catch (e) {}
    };

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'].forEach(eventName => {
        window.addEventListener(eventName, onUserActivity, { passive: true });
    });

    const triggerStandby = () => {
        if (expiring) return;
        expiring = true;
        clearInterval(intervalId);

        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {}

        fetch(LOGOUT_URL, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new URLSearchParams({
                _token: CSRF_TOKEN,
                force: '1',
                inactivity: '1',
            }),
        })
            .catch(() => null)
            .finally(() => {
                window.location.replace(STANDBY_URL);
            });
    };

    const checkInactivity = () => {
        if (expiring) return;
        let lastActivity = lastRecorded;
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                lastActivity = parseInt(stored, 10) || lastRecorded;
            }
        } catch (e) {}

        if (Date.now() - lastActivity >= TIMEOUT_MS) {
            triggerStandby();
        }
    };

    // Vérification immédiate lorsque l'utilisateur revient sur un onglet resté en veille
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            checkInactivity();
        }
    });

    // Vérification périodique toutes les 5 secondes
    const intervalId = setInterval(checkInactivity, 5000);
})();
</script>
@endauth
