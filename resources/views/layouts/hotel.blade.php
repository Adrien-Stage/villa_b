@php
    $currentTenant = Auth::user()->tenant ?? \App\Models\Tenant::first();
    $tenantName = $currentTenant?->name ?? 'Villa Boutanga';
    $tenantLogo = !empty($currentTenant?->settings['logo']) ? asset('storage/' . $currentTenant->settings['logo']) : asset('images/logo.png');
    
    // Generate initials
    $words = explode(' ', $tenantName);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    $initials = substr($initials, 0, 2);
    if (empty($initials)) {
        $initials = 'VB';
    }
@endphp
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $tenantName . ' PMS')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: {{ $currentTenant->settings['theme']['primary'] ?? '#391F0E' }};
            --color-secondary: {{ $currentTenant->settings['theme']['secondary'] ?? '#CCAB87' }};
            --color-accent: {{ $currentTenant->settings['theme']['accent'] ?? '#EED4A3' }};
            --color-dark: {{ $currentTenant->settings['theme']['dark'] ?? '#0F0201' }};
            --color-surface-dark: {{ $currentTenant->settings['theme']['surface_dark'] ?? '#2C1810' }};
            --color-text-on-light: {{ $currentTenant->settings['theme']['text_on_light'] ?? '#391F0E' }};
            --color-text-on-dark: {{ $currentTenant->settings['theme']['text_on_dark'] ?? '#CCAB87' }};
        }
    </style>
</head>

<body class="min-h-screen bg-accent/30 font-body lg:flex lg:h-screen lg:overflow-hidden">

    <div id="mobile-sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden" onclick="closeMobileSidebar()"></div>

    <aside id="mobile-sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-72 max-w-[85vw] bg-primary lg:static lg:flex lg:w-48 lg:max-w-none lg:flex-shrink-0 lg:flex-col lg:h-full">
        <div class="flex h-full w-full flex-col">
            <div class="px-4 py-5 border-b border-surface-dark">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full overflow-hidden flex-shrink-0">
                        <img src="{{ $tenantLogo }}"
                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'"
                            class="bg-white w-full h-full object-cover">
                        <div class="w-full h-full bg-secondary rounded-full items-center justify-center hidden">
                            <span class="text-text-on-light font-heading font-bold text-sm">{{ $initials }}</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-white font-heading font-semibold text-sm leading-tight">{{ $tenantName }}</p>
                        <p class="text-text-on-dark text-xs font-medium">PMS v1.0</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-5">
                <div>
                    <p class="text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Général</p>
                    <ul class="space-y-0.5">
                        <x-sidebar-link route="dashboard" icon="grid">Tableau de bord</x-sidebar-link>
                    </ul>
                </div>

                @role('manager')
                    <div>
                        <p class="text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Analytique</p>
                        <ul class="space-y-0.5">
                            <x-sidebar-link route="analytics.index" icon="bar-chart-2">Tour de contrôle</x-sidebar-link>
                        </ul>
                    </div>
                @endrole

                @role('manager','reception','housekeeping_leader','housekeeping_staff','housekeeping')
                    <div>
                        <p class="text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Hôtel</p>
                        <ul class="space-y-0.5">
                            <x-sidebar-link route="rooms.index" icon="door">Chambres</x-sidebar-link>

                            @role('manager','reception')
                                <li>
                                    <a href="{{ route('bookings.index') }}"
                                        class="flex items-center gap-2.5 px-2 py-1.5 rounded-md text-xs font-medium transition-all
                                        {{ request()->routeIs('bookings.*') || request()->routeIs('groups.*')
                                            ? 'bg-surface-dark text-white'
                                            : 'text-text-on-dark hover:bg-surface-dark hover:text-white' }}">
                                        <i data-lucide="calendar" class="w-3.5 h-3.5 flex-shrink-0"></i>
                                        Réservations
                                    </a>

                                    @if(request()->routeIs('bookings.*') || request()->routeIs('groups.*'))
                                    <ul class="mt-0.5 ml-4 space-y-0.5 border-l border-text-on-dark/20 pl-3">
                                        <li>
                                            <a href="{{ route('bookings.index') }}"
                                                class="flex items-center gap-2 py-1.5 text-xs font-medium transition-all
                                                {{ request()->routeIs('bookings.*') && !request()->routeIs('bookings.cash_register.*')
                                                    ? 'text-white'
                                                    : 'text-text-on-dark hover:text-white' }}">
                                                <i data-lucide="user" class="w-3 h-3 flex-shrink-0"></i>
                                                Individuelles
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('groups.index') }}"
                                                class="flex items-center gap-2 py-1.5 text-xs font-medium transition-all
                                                {{ request()->routeIs('groups.*')
                                                    ? 'text-white'
                                                    : 'text-text-on-dark hover:text-white' }}">
                                                <i data-lucide="users" class="w-3 h-3 flex-shrink-0"></i>
                                                Groupes
                                            </a>
                                        </li>
                                        <li>
                                            <a href="{{ route('bookings.cash_register.index') }}"
                                                class="flex items-center gap-2 py-1.5 text-xs font-medium transition-all
                                                {{ request()->routeIs('bookings.cash_register.*')
                                                    ? 'text-white'
                                                    : 'text-text-on-dark hover:text-white' }}">
                                                <i data-lucide="calculator" class="w-3 h-3 flex-shrink-0"></i>
                                                Compta Réception
                                            </a>
                                        </li>
                                    </ul>
                                    @endif
                                </li>
                            @endrole

                            @role('manager','housekeeping_leader','housekeeping_staff','housekeeping')
                                <x-sidebar-link route="housekeeping.index" icon="sparkles">Housekeeping</x-sidebar-link>
                            @endrole
                        </ul>
                    </div>
                @endrole

                @role('manager','restaurant_chief','restaurant_staff','cashier')
                    <div>
                        <p class="text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Restaurant</p>
                        <ul class="space-y-0.5">
                            @role('manager','restaurant_chief','restaurant_staff')
                                <x-sidebar-link route="restaurant.orders.index" icon="receipt">Commandes</x-sidebar-link>
                                <x-sidebar-link route="restaurant.menus.index" icon="book">Menus</x-sidebar-link>
                                <x-sidebar-link route="restaurant.pantry.index" icon="warehouse">Garde-manger</x-sidebar-link>
                            @endrole

                            @role('manager','restaurant_chief','cashier')
                                <x-sidebar-link route="restaurant.billing.index" icon="credit-card">Facturation</x-sidebar-link>
                            @endrole
                            @php
                                $tenantSlug = Auth::user()->tenant?->slug ?? \App\Models\Tenant::first()?->slug;
                            @endphp
                            @if($tenantSlug)
                                <li>
                                    <a href="{{ route('portal.restaurant.menu', ['tenant' => $tenantSlug]) }}"
                                       target="_blank"
                                       rel="noopener"
                                       class="flex items-center gap-2.5 px-2 py-1.5 rounded-md text-xs font-medium transition-all text-text-on-dark hover:bg-surface-dark hover:text-white">
                                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 6v-4h2v4h-2zm4 0v-2h2v2h-2zm-4-6v-2h2v2h-2zm4 2v-2h2v2h-2z"/>
                                        </svg>
                                        Portail (QR)
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </div>
                @endrole

                @role('manager','reception','cashier')
                    <div>
                        <p class="text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Gestion</p>
                        <ul class="space-y-0.5">
                            <x-sidebar-link route="customers.index" icon="users">Clients</x-sidebar-link>
                            @role('manager')
                                <x-sidebar-link route="users.index" icon="user-cog">Utilisateurs</x-sidebar-link>
                            @endrole
                        </ul>
                    </div>
                @endrole

                @role('shop_manager','shop_cashier','manager')
                    <div>
                        <p class="text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Boutique</p>
                        <ul class="space-y-0.5">
                            @role('shop_manager','manager')
                                <x-sidebar-link route="shop.products.index" icon="package">Articles</x-sidebar-link>
                            @endrole
                            <x-sidebar-link route="shop.orders.index" icon="shopping-cart">Commandes</x-sidebar-link>
                            @role('shop_manager','manager')
                                <x-sidebar-link route="shop.cash_register.index" icon="calculator">Compta Boutique</x-sidebar-link>
                            @endrole
                        </ul>
                    </div>
                @endrole

                @role('manager','reception','housekeeping_leader','restaurant_chief','shop_manager')
                    <div class="mt-4 pt-4 border-t border-surface-dark">
                        <ul class="space-y-0.5">
                            <x-sidebar-link route="settings.index" icon="settings">Paramètres</x-sidebar-link>
                        </ul>
                    </div>
                @endrole
            </nav>

            <div class="px-3 pb-3">
                @php $isDiscussionActive = request()->routeIs('discussions.*'); @endphp
                <a id="sidebar-discussions-link"
                   href="{{ route('discussions.index') }}"
                   class="flex items-center justify-between gap-2.5 px-2 py-2 rounded-md text-xs font-medium transition-all {{ $isDiscussionActive ? 'bg-surface-dark text-white' : 'text-text-on-dark hover:bg-surface-dark hover:text-white' }}">
                    <span class="flex items-center gap-2.5 min-w-0">
                        <i data-lucide="message-circle" class="w-3.5 h-3.5 flex-shrink-0"></i>
                        <span class="truncate">Discussions</span>
                    </span>
                    <span id="sidebar-discussions-dot"
                          class="h-2 w-2 rounded-full bg-secondary {{ !($hasUnreadDiscussions ?? false) ? 'hidden' : '' }}"></span>
                </a>
            </div>

            <div class="px-3 py-4 border-t border-surface-dark">
                <div class="flex items-center justify-between gap-2">
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 flex-1 min-w-0 rounded-lg px-1 py-1 hover:bg-surface-dark transition-colors">
                        <div class="w-8 h-8 rounded-full bg-secondary flex items-center justify-center flex-shrink-0">
                            <span class="text-text-on-light font-semibold text-xs">
                                {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-white text-xs font-medium truncate">{{ \Illuminate\Support\Str::limit(Auth::user()->name, 13, '...') }}</p>
                            <p class="text-text-on-dark/60 text-[10px] capitalize truncate">{{ Auth::user()->role ?? 'Admin' }}</p>
                        </div>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex h-8 w-8 items-center justify-center flex-shrink-0 text-text-on-dark/40 hover:text-text-on-dark transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <div class="flex min-h-screen flex-1 flex-col lg:overflow-hidden">
        <header class="bg-accent/30 border-b border-secondary/20 px-4 py-3 lg:px-8 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
                <button type="button" onclick="openMobileSidebar()" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-secondary/20 bg-white text-primary lg:hidden">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <p class="text-primary font-medium text-sm">@yield('title', 'Tableau de bord')</p>
            </div>
            <div class="flex items-center gap-4">
                {{-- In-app Notifications Dropdown --}}
                <div x-data="notificationCenter()" class="relative">
                    <button @click="open = !open" class="relative p-1.5 rounded-full hover:bg-secondary/15 text-primary/70 hover:text-primary transition-colors focus:outline-none flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <span x-show="totalUnread > 0" class="absolute top-0 right-0 min-w-4 h-4 px-1 flex items-center justify-center bg-red-500 text-white rounded-full text-[9px] font-bold border border-white" style="display: none;" x-text="totalUnread"></span>
                    </button>
                    <div x-show="open" @click.outside="open = false" class="absolute right-0 mt-2 w-80 bg-white border border-secondary/20 rounded-xl shadow-xl z-50 py-2 pointer-events-auto" style="display: none;" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95">
                        <div class="px-4 py-2 border-b border-secondary/10 flex justify-between items-center">
                            <span class="text-xs font-semibold text-primary">Notifications</span>
                            <button x-show="totalUnread > 0" @click="markAllAsRead()" class="text-[10px] text-secondary hover:underline font-medium">Tout marquer comme lu</button>
                        </div>
                        <div class="max-h-64 overflow-y-auto">
                            <template x-if="notifications.length === 0">
                                <div class="px-4 py-6 text-center text-xs text-primary/40">
                                    <p>Aucune nouvelle notification</p>
                                </div>
                            </template>
                            <template x-for="item in notifications" :key="item.id">
                                <div @click="readNotification(item)" class="px-4 py-3 hover:bg-slate-50 border-b border-secondary/5 flex flex-col gap-1 cursor-pointer transition-colors">
                                    <div class="flex justify-between items-start gap-2">
                                        <span class="text-xs font-bold text-primary" x-text="item.data.title || 'Notification'"></span>
                                        <span class="text-[9px] text-primary/40 whitespace-nowrap" x-text="item.created_at"></span>
                                    </div>
                                    <p class="text-xs text-primary/70 line-clamp-2" x-text="item.data.message"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <span class="hidden sm:flex items-center gap-1.5 text-xs text-green-600 font-medium">
                    <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    En ligne
                </span>
                <span class="text-xs text-primary/50">
                    {{ ucfirst(\Carbon\Carbon::now()->locale('fr')->isoFormat('ddd. D MMM')) }}
                </span>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto px-4 py-4 lg:px-8 lg:py-6">
            @yield('content')
        </main>
    </div>

    <x-access-denied-popup />

    {{-- Modal de déconnexion - Caisse ouverte --}}
    @if(session('confirm_logout_caisse_open'))
    <div id="caisse-logout-modal"
         class="fixed inset-0 z-[100] flex items-center justify-center p-4"
         role="dialog"
         aria-modal="true"
         style="background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);">
        <div class="absolute inset-0" onclick="document.getElementById('caisse-logout-modal').classList.add('hidden')"></div>
        <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all z-10">
            {{-- Header --}}
            <div class="flex items-center gap-3 bg-yellow-50 px-6 py-4 border-b border-yellow-100">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-heading font-semibold text-yellow-900">
                        Caisse ouverte
                    </h3>
                    <p class="text-sm text-yellow-700">
                        Action requise avant déconnexion
                    </p>
                </div>
            </div>

            {{-- Content --}}
            <div class="px-6 py-4">
                <p class="text-sm text-primary/80 leading-relaxed">
                    Vous avez actuellement une session de caisse ouverte dans le module 
                    <strong>{{ session('caisse_module') === 'reception' ? 'Hébergement' : 'Boutique' }}</strong>.
                    Il est fortement recommandé de fermer votre caisse avant de quitter l'application.
                </p>
            </div>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row items-center justify-end gap-2 px-6 py-4 bg-accent/20 border-t border-secondary/10">
                <button type="button"
                        onclick="document.getElementById('caisse-logout-modal').classList.add('hidden')"
                        class="w-full sm:w-auto px-4 py-2 text-xs font-semibold text-primary/70 hover:text-primary transition-colors text-center">
                    Annuler
                </button>
                <form method="POST" action="{{ route('logout') }}" class="w-full sm:w-auto">
                    @csrf
                    <input type="hidden" name="force" value="1">
                    <button type="submit"
                            class="w-full px-4 py-2 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 transition-colors shadow-sm text-center">
                        Déconnexion (Pause)
                    </button>
                </form>
                <a href="{{ session('caisse_module') === 'reception' ? route('bookings.cash_register.close') : route('shop.cash_register.close') }}"
                   class="w-full sm:w-auto px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm text-center">
                    Clôturer la caisse
                </a>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal de reconnexion - Caisse en pause --}}
    @if(session('paused_caisse_session'))
    <div id="caisse-resume-modal"
         class="fixed inset-0 z-[100] flex items-center justify-center p-4"
         role="dialog"
         aria-modal="true"
         style="background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);">
        {{-- Pas de clic extérieur pour fermer --}}
        <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all z-10">
            {{-- Header --}}
            <div class="flex items-center gap-3 bg-purple-50 px-6 py-4 border-b border-purple-100">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                    <i data-lucide="calculator" class="w-5 h-5 text-purple-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-heading font-semibold text-purple-900">
                        Caisse en pause
                    </h3>
                    <p class="text-sm text-purple-700">
                        Bon retour parmi nous
                    </p>
                </div>
            </div>

            {{-- Content --}}
            <div class="px-6 py-4">
                <p class="text-sm text-primary/80 leading-relaxed">
                    Vous aviez une session de caisse en pause dans le module 
                    <strong>{{ session('paused_caisse_session')['module'] === 'reception' ? 'Hébergement' : 'Boutique' }}</strong>.
                    Que voulez-vous faire ?
                </p>
            </div>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row items-center justify-end gap-2 px-6 py-4 bg-accent/20 border-t border-secondary/10">
                <form method="POST" action="{{ route('cash_register.resume') }}" class="w-full sm:w-auto">
                    @csrf
                    <input type="hidden" name="session_id" value="{{ session('paused_caisse_session')['id'] }}">
                    <input type="hidden" name="redirect_to_close" value="1">
                    <button type="submit"
                            class="w-full px-4 py-2 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 transition-colors shadow-sm text-center">
                        Fermer la caisse
                    </button>
                </form>
                <form method="POST" action="{{ route('cash_register.resume') }}" class="w-full sm:w-auto">
                    @csrf
                    <input type="hidden" name="session_id" value="{{ session('paused_caisse_session')['id'] }}">
                    <button type="submit"
                            class="w-full px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm text-center">
                        Continuer la session
                    </button>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Assistant IA (Flottant) -->
    <x-ai-assistant />

    <!-- Notification Container -->
    <div id="system-toast-container" class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2 pointer-events-none"></div>

    <script>
    // Resume audio context on user interaction to bypass autoplay policy restrictions
    window.audioCtx = null;
    document.addEventListener('click', () => {
        if (window.audioCtx && window.audioCtx.state === 'suspended') {
            window.audioCtx.resume();
        }
    }, { once: false });

    window.playNotificationSound = function() {
        try {
            if (!window.audioCtx) {
                window.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            
            if (window.audioCtx.state === 'suspended') {
                window.audioCtx.resume();
            }

            const playNote = (frequency, startTime, duration) => {
                const osc = window.audioCtx.createOscillator();
                const gain = window.audioCtx.createGain();
                
                osc.connect(gain);
                gain.connect(window.audioCtx.destination);
                
                osc.type = 'triangle'; // softer than sine, mimics a professional physical chime/marimba
                osc.frequency.setValueAtTime(frequency, startTime);
                
                // Attack & Decay Envelope
                gain.gain.setValueAtTime(0, startTime);
                gain.gain.linearRampToValueAtTime(0.12, startTime + 0.015);
                gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
                
                osc.start(startTime);
                osc.stop(startTime + duration);
            };

            const now = window.audioCtx.currentTime;
            // Play a premium crystal double-tone chime (G5, then C6)
            playNote(783.99, now, 0.25); // G5
            playNote(1046.50, now + 0.08, 0.35); // C6
        } catch (e) {
            console.error('Play notification sound failed', e);
        }
    };

    window.showSystemToast = function(title, message, onClickUrl = null) {
        const container = document.getElementById('system-toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'bg-white border border-secondary/20 shadow-lg rounded-xl p-4 w-72 transform transition-all duration-300 translate-y-full opacity-0 pointer-events-auto cursor-pointer';
        
        toast.innerHTML = `
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-primary">${title}</h4>
                    <p class="text-xs text-primary/60 mt-0.5 line-clamp-2">${message}</p>
                </div>
            </div>
        `;

        if (onClickUrl) {
            toast.addEventListener('click', () => window.location.href = onClickUrl);
        } else {
            toast.addEventListener('click', () => toast.remove());
        }

        container.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-full', 'opacity-0');
        });

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    };

    window.openMobileSidebar = function() {
        document.getElementById('mobile-sidebar').classList.remove('hidden');
        document.getElementById('mobile-sidebar-backdrop').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    };

    window.closeMobileSidebar = function() {
        document.getElementById('mobile-sidebar').classList.add('hidden');
        document.getElementById('mobile-sidebar-backdrop').classList.add('hidden');
        document.body.style.overflow = '';
    };

    (function startDiscussionUnreadPolling() {
        const dot = document.getElementById('sidebar-discussions-dot');
        if (!dot) return;

        const endpoint = '{{ route('discussions.unreadSummary') }}';
        let previousTotalUnread = null;

        const refreshUnreadDot = async () => {
            try {
                const response = await fetch(endpoint, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) return;

                const payload = await response.json();
                if (!payload || !payload.ok) return;

                dot.classList.toggle('hidden', !payload.has_unread);

                const currentTotal = parseInt(payload.total_unread) || 0;
                
                if (previousTotalUnread !== null && currentTotal > previousTotalUnread) {
                    if (window.playNotificationSound) window.playNotificationSound();
                    
                    if (!window.location.pathname.includes('/discussions')) {
                        if (window.showSystemToast) {
                            window.showSystemToast(
                                'Nouveau message', 
                                'Vous avez reçu un nouveau message dans vos discussions.',
                                '{{ route('discussions.index') }}'
                            );
                        }
                    }
                }
                
                previousTotalUnread = currentTotal;
            } catch (error) {
                console.error('Unread summary polling failed', error);
            }
        };

        refreshUnreadDot();
        setInterval(refreshUnreadDot, 3000);
    })();

    document.addEventListener('alpine:init', () => {
        Alpine.data('notificationCenter', () => ({
            open: false,
            totalUnread: 0,
            notifications: [],
            isFirstPoll: true,

            init() {
                this.poll();
                setInterval(() => this.poll(), 5000);
            },

            async poll() {
                try {
                    const response = await fetch('{{ route('notifications.unread') }}', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });
                    if (!response.ok) return;
                    const res = await response.json();
                    if (res.ok) {
                        const previousCount = this.totalUnread;
                        this.notifications = res.notifications;
                        this.totalUnread = res.total_unread;

                        if (!this.isFirstPoll && res.total_unread > previousCount) {
                            if (window.playNotificationSound) {
                                window.playNotificationSound();
                            }
                            
                            const newNotif = res.notifications[0];
                            if (newNotif && window.showSystemToast) {
                                window.showSystemToast(
                                    newNotif.data.title || 'Notification',
                                    newNotif.data.message,
                                    newNotif.data.url
                                );
                            }
                        }
                        this.isFirstPoll = false;
                    }
                } catch (e) {
                    console.error('Failed to poll notifications', e);
                }
            },

            async readNotification(item) {
                try {
                    const response = await fetch(`/notifications/${item.id}/read`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });
                    if (response.ok) {
                        this.poll();
                        if (item.data.url) {
                            window.location.href = item.data.url;
                        }
                    }
                } catch (e) {
                    console.error(e);
                }
            },

            async markAllAsRead() {
                try {
                    const response = await fetch('{{ route('notifications.readAll') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });
                    if (response.ok) {
                        this.poll();
                    }
                } catch (e) {
                    console.error(e);
                }
            }
        }));
    });
    </script>

    @stack('scripts')

</body>

</html>
