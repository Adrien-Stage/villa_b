{{--
  Système universel de notifications toast & alertes — Wetchah PMS
  Architecture Senior Developer :
  - Écoute automatique des sessions flash Laravel (success, error, warning, info, status)
  - Prise en charge native des erreurs de validation form ($errors->any()) avec synthèse claire
  - API JavaScript globale accessible partout : window.toast.success(), window.toast.error(), etc.
  - Animations fluides Alpine.js, temporisateur avec jauge de progression, pause au survol
  - Rétrocompatibilité totale avec window.showSystemToast()
--}}

<div id="wetchah-toast-root"
     x-data="toastNotificationManager()"
     x-init="init()"
     class="fixed top-4 right-4 sm:top-6 sm:right-6 z-[99999] flex flex-col gap-3 w-full max-w-[calc(100vw-2rem)] sm:max-w-md pointer-events-none"
     role="region"
     aria-live="polite"
     aria-label="Notifications du système">

    <template x-for="item in toasts" :key="item.id">
        <div x-show="item.visible"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="opacity-0 translate-y-[-10px] sm:translate-y-0 sm:translate-x-6 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0 scale-100"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95 sm:translate-x-6"
             @mouseenter="pause(item.id)"
             @mouseleave="resume(item.id)"
             :class="{
                 'border-emerald-200 bg-white text-emerald-900 shadow-emerald-500/10': item.type === 'success',
                 'border-rose-200 bg-white text-rose-900 shadow-rose-500/10': item.type === 'error',
                 'border-amber-200 bg-white text-amber-900 shadow-amber-500/10': item.type === 'warning',
                 'border-sky-200 bg-white text-sky-900 shadow-sky-500/10': item.type === 'info'
             }"
             class="pointer-events-auto relative overflow-hidden rounded-2xl border shadow-xl p-4 transition-all select-none">

            <div class="flex items-start gap-3.5">
                {{-- Icône dynamique selon le type --}}
                <div class="shrink-0 mt-0.5">
                    <template x-if="item.type === 'success'">
                        <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </template>
                    <template x-if="item.type === 'error'">
                        <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 7.5h.01" />
                            </svg>
                        </div>
                    </template>
                    <template x-if="item.type === 'warning'">
                        <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </template>
                    <template x-if="item.type === 'info'">
                        <div class="w-8 h-8 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </template>
                </div>

                {{-- Corps du message --}}
                <div class="flex-1 min-w-0 pr-6">
                    <h4 class="text-sm font-semibold tracking-tight text-gray-900" x-text="item.title"></h4>
                    <p class="text-xs leading-relaxed text-gray-600 mt-0.5 break-words" x-show="item.message" x-text="item.message"></p>

                    {{-- Liste détaillée optionnelle (ex: liste des erreurs de validation) --}}
                    <template x-if="item.list && item.list.length > 0">
                        <ul class="mt-2 space-y-1 text-xs text-rose-700 bg-rose-50/70 p-2.5 rounded-xl border border-rose-100 list-disc list-inside">
                            <template x-for="(sub, idx) in item.list" :key="idx">
                                <li class="leading-snug" x-text="sub"></li>
                            </template>
                        </ul>
                    </template>
                </div>

                {{-- Bouton de fermeture --}}
                <button type="button"
                        @click="remove(item.id)"
                        class="absolute top-3 right-3 text-gray-400 hover:text-gray-700 p-1 rounded-lg hover:bg-gray-100 transition-colors"
                        aria-label="Fermer cette alerte">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Jauge de temps restante --}}
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gray-100 overflow-hidden">
                <div class="h-full transition-all duration-100 ease-linear"
                     :style="'width: ' + item.progress + '%;'"
                     :class="{
                         'bg-emerald-500': item.type === 'success',
                         'bg-rose-500': item.type === 'error',
                         'bg-amber-500': item.type === 'warning',
                         'bg-sky-500': item.type === 'info'
                     }"></div>
            </div>
        </div>
    </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('toastNotificationManager', () => ({
        toasts: [],
        counter: 0,

        init() {
            // Exposer l'API globale pour JS direct et Alpine
            window.toast = {
                success: (msg, title = 'Opération réussie', opts = {}) => this.push({ type: 'success', title, message: msg, ...opts }),
                error: (msg, title = 'Erreur constatée', opts = {}) => this.push({ type: 'error', title, message: msg, ...opts }),
                warning: (msg, title = 'Vigilance requise', opts = {}) => this.push({ type: 'warning', title, message: msg, ...opts }),
                info: (msg, title = 'Information', opts = {}) => this.push({ type: 'info', title, message: msg, ...opts }),
                push: (opts) => this.push(opts),
            };

            // Rétrocompatibilité avec les anciens appels window.showSystemToast()
            window.showSystemToast = (title, message, onClickUrl = null) => {
                this.push({
                    type: 'info',
                    title: title || 'Notification',
                    message: message || '',
                    duration: 6000,
                });
            };

            // Écouteur d'évènement personnalisé pour composants découplés
            window.addEventListener('toast', (event) => {
                if (event.detail) {
                    this.push(event.detail);
                }
            });

            // Amorçage automatique des messages de session Laravel au chargement de page
            @if(session('success'))
                this.push({
                    type: 'success',
                    title: 'Opération réussie',
                    message: @json(session('success')),
                    duration: 6000
                });
            @endif

            @if(session('error'))
                this.push({
                    type: 'error',
                    title: 'Erreur',
                    message: @json(session('error')),
                    duration: 8000
                });
            @endif

            @if(session('warning'))
                this.push({
                    type: 'warning',
                    title: 'Attention',
                    message: @json(session('warning')),
                    duration: 8000
                });
            @endif

            @if(session('info'))
                this.push({
                    type: 'info',
                    title: 'Information',
                    message: @json(session('info')),
                    duration: 6000
                });
            @endif

            @if(session('status') && !session('success'))
                this.push({
                    type: 'info',
                    title: 'Statut',
                    message: @json(session('status')),
                    duration: 6000
                });
            @endif

            @if($errors->any())
                this.push({
                    type: 'error',
                    title: 'Formulaire non validé',
                    message: 'Certaines informations obligatoires sont manquantes ou incorrectes :',
                    list: @json($errors->all()),
                    duration: 10000
                });
            @endif
        },

        push({ type = 'info', title = '', message = '', list = [], duration = null }) {
            const id = ++this.counter;
            const finalDuration = duration || (type === 'error' ? 8000 : 5000);
            const interval = 50;
            const step = (interval / finalDuration) * 100;

            const item = {
                id,
                type,
                title,
                message,
                list,
                visible: true,
                progress: 100,
                paused: false,
                timer: null,
            };

            item.timer = setInterval(() => {
                if (!item.paused) {
                    item.progress -= step;
                    if (item.progress <= 0) {
                        this.remove(id);
                    }
                }
            }, interval);

            this.toasts.push(item);
        },

        remove(id) {
            const index = this.toasts.findIndex(t => t.id === id);
            if (index !== -1) {
                const toast = this.toasts[index];
                if (toast.timer) clearInterval(toast.timer);
                toast.visible = false;
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 300);
            }
        },

        pause(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) toast.paused = true;
        },

        resume(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) toast.paused = false;
        }
    }));
});
</script>
