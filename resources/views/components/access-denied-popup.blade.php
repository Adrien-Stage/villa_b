{{-- Popup d'accès refusé --}}
<div id="access-denied-popup"
     class="fixed inset-0 z-50 hidden"
     role="dialog"
     aria-modal="true"
     aria-labelledby="access-denied-title">

    {{-- Overlay --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
         onclick="hideAccessDeniedPopup()"></div>

    {{-- Modal --}}
    <div class="relative flex min-h-full items-center justify-center p-4">
        <div class="w-full max-w-md transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all">

            {{-- Header --}}
            <div class="flex items-center gap-3 bg-red-50 px-6 py-4 border-b border-red-100">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <div>
                    <h3 id="access-denied-title" class="text-lg font-heading font-semibold text-red-900">
                        Accès refusé
                    </h3>
                    <p class="text-sm text-red-700">
                        Vous n'avez pas les permissions nécessaires
                    </p>
                </div>
            </div>

            {{-- Content --}}
            <div class="px-6 py-4">
                <p id="access-denied-message" class="text-sm text-primary/80 leading-relaxed">
                    Message d'erreur personnalisé sera affiché ici.
                </p>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 px-6 py-4 bg-accent/20 border-t border-secondary/10">
                <button type="button"
                        onclick="hideAccessDeniedPopup()"
                        class="px-4 py-2 text-sm font-medium text-primary/70 hover:text-primary transition-colors">
                    Fermer
                </button>
                <button type="button"
                        onclick="redirectToDashboard()"
                        class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    Retour au tableau de bord
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Fonctions globales pour le popup d'accès refusé
window.showAccessDeniedPopup = function(message = null) {
    console.log('showAccessDeniedPopup called with message:', message);
    const popup = document.getElementById('access-denied-popup');
    const messageEl = document.getElementById('access-denied-message');

    if (message) {
        messageEl.textContent = message;
    }

    popup.classList.remove('hidden');

    // Focus trap pour accessibilité
    const closeBtn = popup.querySelector('[onclick="hideAccessDeniedPopup()"]');
    if (closeBtn) closeBtn.focus();

    // Empêcher le scroll du body
    document.body.style.overflow = 'hidden';
};

window.hideAccessDeniedPopup = function() {
    console.log('hideAccessDeniedPopup called');
    const popup = document.getElementById('access-denied-popup');
    popup.classList.add('hidden');

    // Restaurer le scroll du body
    document.body.style.overflow = '';
};

window.redirectToDashboard = function() {
    console.log('redirectToDashboard called');
    window.location.href = '{{ route("dashboard") }}';
};

// Interception globale des erreurs 403
document.addEventListener('DOMContentLoaded', function() {
    console.log('Popup JavaScript loaded');

    // Intercepter les réponses fetch (AJAX)
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        return originalFetch.apply(this, args).then(response => {
            console.log('Fetch intercepted, status:', response.status);
            if (response.status === 403) {
                return response.clone().json().then(data => {
                    console.log('403 response data:', data);
                    if (data.access_denied) {
                        showAccessDeniedPopup(data.message);
                        // Retourner une réponse rejetée pour éviter les callbacks success
                        return Promise.reject(new Error('Access denied'));
                    }
                    return response;
                }).catch(() => {
                    // Si pas de JSON, continuer normalement
                    return response;
                });
            }
            return response;
        });
    };

    // Note:
    // On ne fait plus d'interception AJAX globale des formulaires.
    // Les soumissions HTML classiques garantissent:
    // - redirections serveur (302) suivies correctement
    // - flash messages (success/errors) visibles
    // - modals qui se referment naturellement via rechargement/navigation
});
document.addEventListener('DOMContentLoaded', function() {
    @if(session('access_denied_popup'))
    showAccessDeniedPopup(@json(session('access_denied_message')));
    @endif
});
</script>
