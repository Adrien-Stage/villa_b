{{--
    Gestion de la déconnexion automatique après 20 minutes d'inactivité.
    - Écoute les interactions utilisateur (souris, clavier, tactile, défilement)
    - Synchronise l'état d'activité entre tous les onglets via localStorage
    - Déclenche la déconnexion et redirige vers /login dès que le délai est atteint
--}}
@auth
<script>
(function() {
    const TIMEOUT_MS = {{ (int) config('session.lifetime', 20) * 60 * 1000 }};
    const STORAGE_KEY = 'wetchah_last_user_activity';
    const LOGIN_URL = '{{ route('login') }}';

    let lastRecorded = Date.now();
    try {
        localStorage.setItem(STORAGE_KEY, String(lastRecorded));
    } catch (e) {}

    // Throttle l'enregistrement d'activité à 5 secondes pour économiser les ressources CPU
    const onUserActivity = () => {
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

    // Vérification périodique toutes les 5 secondes
    setInterval(() => {
        let lastActivity = lastRecorded;
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                lastActivity = parseInt(stored, 10) || lastRecorded;
            }
        } catch (e) {}

        const idleTime = Date.now() - lastActivity;
        if (idleTime >= TIMEOUT_MS) {
            try {
                localStorage.removeItem(STORAGE_KEY);
            } catch (e) {}
            window.location.href = LOGIN_URL;
        }
    }, 5000);
})();
</script>
@endauth
