/**
 * Widget de recherche et de filtrage en direct (Alpine.js).
 * 
 * Permet une recherche asynchrone sans rechargement de page (DOMParser),
 * avec debouncing, spinner de chargement, bouton d'effacement,
 * synchronisation de l'historique (URL) et réinitialisation de Lucide et Alpine.
 */
export default function liveSearch(config = {}) {
    return {
        target: config.target || '#table-container',
        name: config.name || 'search',
        debounceTime: config.debounce ?? 250,
        query: config.value ?? '',
        loading: false,
        controller: null,
        formEl: null,
        targetEl: null,
        syncSelectors: Array.isArray(config.sync) ? config.sync : (config.sync ? [config.sync] : []),

        init() {
            this.targetEl = document.querySelector(this.target);
            this.formEl = this.$el.closest('form') || (config.form ? document.querySelector(config.form) : null);

            // Intercepter la soumission du formulaire parent
            if (this.formEl) {
                this.formEl.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.search(true);
                });

                // Déclencher automatiquement la recherche sur changement de selects/checkboxes
                this.formEl.addEventListener('change', (e) => {
                    if (e.target !== this.$refs.input) {
                        this.search(true);
                    }
                });
            }

            // Lier la pagination dans le conteneur cible
            this.bindPagination();

            // Gestion de l'historique navigateur (boutons retour/avant)
            window.addEventListener('popstate', () => {
                this.fetchUrl(window.location.href, false);
            });
        },

        bindPagination() {
            if (!this.targetEl) return;
            this.targetEl.addEventListener('click', (e) => {
                const link = e.target.closest('a');
                if (!link) return;
                const href = link.getAttribute('href');
                if (!href) return;
                try {
                    const url = new URL(href, window.location.origin);
                    if (url.pathname === window.location.pathname && url.searchParams.has('page')) {
                        e.preventDefault();
                        this.fetchUrl(href);
                        this.targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                } catch (_) {}
            });
        },

        buildUrl(overrides = {}) {
            const params = new URLSearchParams();
            if (this.formEl) {
                const fd = new FormData(this.formEl);
                for (const [k, v] of fd.entries()) {
                    if (v !== '' && v !== null && v !== undefined) {
                        params.set(k, v);
                    }
                }
            }
            if (this.query && this.query.trim()) {
                params.set(this.name, this.query.trim());
            } else {
                params.delete(this.name);
            }
            for (const [k, v] of Object.entries(overrides)) {
                if (v === null || v === '' || v === undefined) {
                    params.delete(k);
                } else {
                    params.set(k, v);
                }
            }
            const base = this.formEl?.action || window.location.pathname;
            const qs = params.toString();
            return qs ? `${base}?${qs}` : base;
        },

        search(immediate = false) {
            const url = this.buildUrl({ page: null });
            this.fetchUrl(url);
        },

        clear() {
            this.query = '';
            if (this.$refs.input) {
                this.$refs.input.value = '';
                this.$refs.input.focus();
            }
            this.search(true);
        },

        async fetchUrl(url, updateHistory = true) {
            if (!this.targetEl) {
                this.targetEl = document.querySelector(this.target);
            }
            if (this.controller) {
                this.controller.abort();
            }
            this.controller = new AbortController();
            this.loading = true;

            if (this.targetEl) {
                this.targetEl.classList.add('opacity-50', 'pointer-events-none');
            }

            try {
                const res = await fetch(url, {
                    signal: this.controller.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html'
                    }
                });

                if (!res.ok) {
                    window.location.href = url;
                    return;
                }

                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newTarget = doc.querySelector(this.target);

                if (newTarget && this.targetEl) {
                    this.targetEl.innerHTML = newTarget.innerHTML;
                }

                // Synchroniser les sélecteurs additionnels demandés
                const selectorsToSync = [
                    ...this.syncSelectors,
                    '#status-filters',
                    '#status-tabs',
                    '#users-status-tabs',
                    '#users-view-toggle',
                    '#pagination-container',
                ];

                selectorsToSync.forEach((sel) => {
                    const el = document.querySelector(sel);
                    const newEl = doc.querySelector(sel);
                    if (el && newEl) {
                        el.innerHTML = newEl.innerHTML;
                    }
                });

                if (updateHistory) {
                    window.history.replaceState(null, '', url);
                }

                if (typeof window.refreshLucideIcons === 'function') {
                    window.refreshLucideIcons();
                } else if (window.lucide?.createIcons) {
                    window.lucide.createIcons();
                }

                if (window.Alpine && this.targetEl) {
                    window.Alpine.initTree(this.targetEl);
                }
            } catch (e) {
                if (e.name !== 'AbortError') {
                    console.error('Erreur liveSearch:', e);
                    window.location.href = url;
                }
            } finally {
                this.loading = false;
                if (this.targetEl) {
                    this.targetEl.classList.remove('opacity-50', 'pointer-events-none');
                }
            }
        }
    };
}
