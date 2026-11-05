{{-- Popup d'accès refusé / information contextuelle --}}
<div id="access-denied-popup"
     class="fixed inset-0 z-50 hidden"
     role="dialog"
     aria-modal="true"
     aria-labelledby="access-denied-title">

    {{-- Overlay --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
         onclick="redirectToLogin()"></div>

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
                    <p id="access-denied-subtitle" class="text-sm text-red-700">
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

            {{-- Actions : UN SEUL bouton renvoyant vers /login --}}
            <div class="flex items-center justify-end px-6 py-4 bg-accent/20 border-t border-secondary/10">
                <button type="button"
                        id="access-denied-action-btn"
                        onclick="redirectToLogin()"
                        class="w-full sm:w-auto px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-surface-dark transition-colors text-center shadow-xs">
                    Se connecter
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.__accessDeniedTargetUrl = '{{ route("login") }}';

// Fonctions globales pour le popup d'accès refusé
window.showAccessDeniedPopup = function(message = null, title = null, subtitle = null, targetUrl = null, actionLabel = null) {
    const popup = document.getElementById('access-denied-popup');
    const titleEl = document.getElementById('access-denied-title');
    const subtitleEl = document.getElementById('access-denied-subtitle');
    const messageEl = document.getElementById('access-denied-message');
    const actionBtn = document.getElementById('access-denied-action-btn');

    let resolvedTitle = title || 'Accès refusé';
    let resolvedSubtitle = subtitle || 'Vous n\'avez pas les permissions nécessaires';
    let resolvedMessage = message || 'Accès non autorisé.';
    let resolvedUrl = targetUrl || '{{ route("login") }}';
    let resolvedLabel = actionLabel || 'Se connecter';

    const lowerMessage = (resolvedMessage || '').toLowerCase();
    if (lowerMessage.includes('désactivé') || lowerMessage.includes('desactive')) {
        resolvedTitle = title || 'Compte désactivé';
        resolvedSubtitle = subtitle || 'Votre compte a été désactivé';
        resolvedMessage = message || 'Ce compte a été désactivé par l\'administration.';
        resolvedUrl = '{{ route("login") }}';
        resolvedLabel = 'Se connecter';
    } else if (lowerMessage.includes('inactivité') || lowerMessage.includes('inactivite') || lowerMessage.includes('expiré') || lowerMessage.includes('expire')) {
        resolvedTitle = title || 'Session expirée';
        resolvedSubtitle = subtitle || 'Délai d\'inactivité dépassé';
        resolvedMessage = message || 'Votre session a expiré après 20 minutes d\'inactivité. Veuillez vous reconnecter.';
        resolvedUrl = '{{ route("login") }}';
        resolvedLabel = 'Se connecter';
    }

    if (titleEl) titleEl.textContent = resolvedTitle;
    if (subtitleEl) subtitleEl.textContent = resolvedSubtitle;
    if (messageEl) messageEl.textContent = resolvedMessage;
    if (actionBtn) actionBtn.textContent = resolvedLabel;

    window.__accessDeniedTargetUrl = resolvedUrl;

    popup.classList.remove('hidden');

    if (actionBtn) actionBtn.focus();
    document.body.style.overflow = 'hidden';
};

window.hideAccessDeniedPopup = function() {
    window.redirectToLogin();
};

window.redirectToLogin = function() {
    const url = window.__accessDeniedTargetUrl || '{{ route("login") }}';
    window.location.href = url;
};

window.redirectToDashboard = function() {
    window.redirectToLogin();
};

// Interception globale des erreurs 403
document.addEventListener('DOMContentLoaded', function() {
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        return originalFetch.apply(this, args).then(response => {
            if (response.status === 403) {
                return response.clone().json().then(data => {
                    if (data && (data.access_denied || data.message)) {
                        showAccessDeniedPopup(
                            data.message,
                            data.title,
                            data.subtitle,
                            data.action_url || data.target_url || '{{ route("login") }}',
                            data.action_label || 'Se connecter'
                        );
                        return Promise.reject(new Error(data.message || 'Access denied'));
                    }
                    return response;
                }).catch(() => {
                    return response;
                });
            }
            return response;
        });
    };
});

document.addEventListener('DOMContentLoaded', function() {
    @if(session('access_denied_popup'))
    showAccessDeniedPopup(
        @json(session('access_denied_message')),
        @json(session('access_denied_title')),
        @json(session('access_denied_subtitle')),
        @json(session('access_denied_url', route('login'))),
        @json(session('access_denied_action_label', 'Se connecter'))
    );
    @endif
});
</script>
