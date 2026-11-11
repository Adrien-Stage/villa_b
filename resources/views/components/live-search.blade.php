@props([
    'name' => 'search',
    'value' => null,
    'placeholder' => 'Rechercher...',
    'target' => '#table-container',
    'form' => null,
    'debounce' => 250,
    'id' => null,
    'class' => 'w-full sm:w-64',
    'containerClass' => 'relative min-w-0 flex-1 sm:flex-none',
    'sync' => [],
])

@php
    $actualValue = $value ?? request($name, '');
    $inputId = $id ?? ('search-' . \Illuminate\Support\Str::slug($name) . '-' . \Illuminate\Support\Str::random(4));
    $syncList = is_array($sync) ? $sync : (empty($sync) ? [] : [$sync]);
@endphp

<div x-data="liveSearch({
        target: @js($target),
        name: @js($name),
        debounce: {{ $debounce }},
        value: @js($actualValue),
        form: @js($form),
        sync: @js($syncList)
     })"
     class="{{ $containerClass }}">
    <input type="text"
           x-ref="input"
           id="{{ $inputId }}"
           name="{{ $name }}"
           x-model="query"
           @input.debounce.{{ $debounce }}ms="search()"
           @keydown.enter.prevent="search(true)"
           @keydown.escape.prevent="clear()"
           placeholder="{{ $placeholder }}"
           autocomplete="off"
           class="pl-9 pr-8 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary transition-all {{ $class }}">

    <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30 pointer-events-none"></i>

    <span x-show="loading" x-cloak class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none">
        <svg class="animate-spin h-3.5 w-3.5 text-primary/40" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
        </svg>
    </span>

    <button type="button"
            x-show="query && !loading"
            x-cloak
            @click="clear()"
            aria-label="Effacer la recherche"
            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-primary/30 hover:text-primary transition-colors p-0.5 rounded-full">
        <i data-lucide="x" class="w-3.5 h-3.5"></i>
    </button>
</div>

@once
<script>
if (typeof window !== 'undefined') {
    const registerLiveSearch = () => {
        if (window.Alpine && !window.Alpine.data('liveSearch')) {
            window.Alpine.data('liveSearch', (config = {}) => ({
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

                    if (this.formEl) {
                        this.formEl.addEventListener('submit', (e) => {
                            e.preventDefault();
                            this.search(true);
                        });

                        this.formEl.addEventListener('change', (e) => {
                            if (e.target !== this.$refs.input) {
                                this.search(true);
                            }
                        });
                    }

                    this.bindPagination();

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
            }));
        }
    };
    if (window.Alpine) {
        registerLiveSearch();
    } else {
        document.addEventListener('alpine:init', registerLiveSearch);
    }
}
</script>
@endonce
