@php
    $currentTenant = Auth::user()->tenant ?? \App\Models\Tenant::first();
    $tenantName = $currentTenant?->name ?? 'Établissement';
    // Logo importé par le manager depuis les paramètres de l'application
    // (stocké localement dans le storage de ce tenant, pas de l'admin).
    $tenantLogo = !empty($currentTenant?->settings['logo']) ? asset('storage/' . $currentTenant->settings['logo']) : null;

    // Generate initials
    $words = explode(' ', $tenantName);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    $initials = substr($initials, 0, 2);
    if (empty($initials)) {
        $initials = 'ET';
    }
@endphp
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $tenantName . ' PMS')</title>
    @include('partials.pwa-head')
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

        /*
         * Barre latérale réduite : il ne reste que les icônes, et l'écran
         * principal récupère la largeur libérée — il est en flex-1 derrière
         * une barre en flux, donc il s'élargit de lui-même.
         *
         * L'état ne concerne que le grand écran : en dessous, la barre est un
         * tiroir superposé, que réduire n'aurait aucun sens.
         */
        #mobile-sidebar,
        #mobile-sidebar .sidebar-libelle,
        #mobile-sidebar .sidebar-identite {
            transition: width 200ms ease, opacity 150ms ease;
        }

        @media (min-width: 1024px) {
            /* !important : la largeur est posée par une classe utilitaire
               (lg:w-48) qu'une règle d'auteur ne doit pas avoir à concurrencer
               au jeu de la spécificité. */
            html.sidebar-reduite #mobile-sidebar {
                width: 3.75rem !important;
            }

            /* Tout ce qui n'est pas une icône s'efface. */
            html.sidebar-reduite #mobile-sidebar .sidebar-libelle,
            html.sidebar-reduite #mobile-sidebar .sidebar-groupe-titre,
            html.sidebar-reduite #mobile-sidebar .sidebar-sous-menu,
            html.sidebar-reduite #mobile-sidebar .sidebar-identite {
                display: none;
            }

            /* Les icônes se recentrent dans la gouttière restante. */
            html.sidebar-reduite #mobile-sidebar .sidebar-lien,
            html.sidebar-reduite #mobile-sidebar .sidebar-pied {
                justify-content: center;
                padding-left: 0.25rem;
                padding-right: 0.25rem;
                gap: 0;
            }

            html.sidebar-reduite #mobile-sidebar nav,
            html.sidebar-reduite #mobile-sidebar .sidebar-entete,
            html.sidebar-reduite #mobile-sidebar .sidebar-bloc {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }

            html.sidebar-reduite #mobile-sidebar .sidebar-entete > div {
                justify-content: center;
                gap: 0;
            }

            /* Une pastille de non-lu reste visible, posée sur l'icône. */
            html.sidebar-reduite #mobile-sidebar #sidebar-discussions-dot {
                position: absolute;
                top: 0.35rem;
                right: 0.6rem;
            }

            html.sidebar-reduite #mobile-sidebar .sidebar-lien-discussions {
                position: relative;
            }

            /* Les groupes se séparent par un filet plutôt que par leur titre. */
            html.sidebar-reduite #mobile-sidebar nav > div + div {
                border-top: 1px solid var(--color-surface-dark);
                padding-top: 0.75rem;
            }

            html.sidebar-reduite #sidebar-bascule-ouvrir { display: inline-flex; }
            html.sidebar-reduite #sidebar-bascule-fermer { display: none; }
        }

        #sidebar-bascule-ouvrir { display: none; }
    </style>
    <script>
        // Avant peinture : la barre latérale reprend la largeur choisie la
        // dernière fois, sans clignotement au chargement de chaque page.
        try {
            if (localStorage.getItem('sidebar-reduite') === '1') {
                document.documentElement.classList.add('sidebar-reduite');
            }
        } catch (e) { /* navigation privée : la barre s'ouvre en grand */ }
    </script>
</head>

<body class="min-h-screen bg-accent/30 font-body lg:flex lg:h-screen lg:overflow-hidden">

    <div id="mobile-sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden" onclick="closeMobileSidebar()"></div>

    <aside id="mobile-sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-72 max-w-[85vw] bg-primary lg:static lg:flex lg:w-48 lg:max-w-none lg:flex-shrink-0 lg:flex-col lg:h-full">
        <div class="flex h-full w-full flex-col">
            <div class="sidebar-entete px-4 py-5 border-b border-surface-dark">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full overflow-hidden flex-shrink-0">
                        @if($tenantLogo)
                            <img src="{{ $tenantLogo }}"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'"
                                class="bg-white w-full h-full object-cover">
                        @endif
                        <div class="w-full h-full bg-secondary rounded-full items-center justify-center {{ $tenantLogo ? 'hidden' : 'flex' }}">
                            <span class="text-text-on-light font-heading font-bold text-sm">{{ $initials }}</span>
                        </div>
                    </div>
                    <div class="sidebar-identite">
                        <p class="text-white font-heading font-semibold text-sm leading-tight">{{ $tenantName }}</p>
                        <p class="text-text-on-dark text-xs font-medium">PMS v1.0</p>
                    </div>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-5">
                <div>
                    <p class="sidebar-groupe-titre text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Général</p>
                    <ul class="space-y-0.5">
                        <x-sidebar-link route="dashboard" icon="grid">Tableau de bord</x-sidebar-link>
                    </ul>
                </div>

                @role('manager')
                    @module('analytics')
                    <div>
                        <p class="sidebar-groupe-titre text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Analytique</p>
                        <ul class="space-y-0.5">
                            <x-sidebar-link route="analytics.index" icon="bar-chart-2">Tour de contrôle</x-sidebar-link>
                        </ul>
                    </div>
                    @endmodule
                @endrole

                @role('manager','reception','housekeeping_leader','housekeeping_staff','housekeeping')
                    <div>
                        <p class="sidebar-groupe-titre text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Hôtel</p>
                        <ul class="space-y-0.5">
                            {{-- Chambres : plus visible pour le housekeeping, qui pilote les statuts depuis son module. --}}
                            @role('manager','reception')
                                <x-sidebar-link route="rooms.index" icon="door">Chambres</x-sidebar-link>
                            @endrole

                            @role('manager','reception')
                                {{-- L'agenda est un écran à part entière : le calendrier
                                     des séjours n'est plus une vue de la liste. --}}
                                <x-sidebar-link route="agenda.index" icon="calendar-days">Agenda</x-sidebar-link>

                                <li>
                                    <a href="{{ route('bookings.index') }}"
                                        title="Réservations"
                                        class="sidebar-lien flex items-center gap-2.5 px-2 py-1.5 rounded-md text-xs font-medium transition-all
                                        {{ request()->routeIs('bookings.*') || request()->routeIs('groups.*')
                                            ? 'bg-surface-dark text-white'
                                            : 'text-text-on-dark hover:bg-surface-dark hover:text-white' }}">
                                        <i data-lucide="calendar" class="w-3.5 h-3.5 flex-shrink-0"></i>
                                        <span class="sidebar-libelle">Réservations</span>
                                    </a>

                                    @if(request()->routeIs('bookings.*') || request()->routeIs('groups.*'))
                                    <ul class="sidebar-sous-menu mt-0.5 ml-4 space-y-0.5 border-l border-text-on-dark/20 pl-3">
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
                                @module('housekeeping')
                                    <x-sidebar-link route="housekeeping.index" icon="sparkles">Housekeeping</x-sidebar-link>
                                @endmodule
                            @endrole

                            @role('manager')
                                <x-sidebar-link route="rooms.cost_sheets.index" icon="calculator">Fiches techniques</x-sidebar-link>
                            @endrole
                        </ul>
                    </div>
                @endrole

                @role('manager','restaurant_chief','restaurant_staff','restaurant_cook','cashier')
                    @module('restaurant')
                    <div>
                        <p class="sidebar-groupe-titre text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Restaurant</p>
                        <ul class="space-y-0.5">
                            @role('manager','restaurant_chief','restaurant_staff')
                                <x-sidebar-link route="restaurant.orders.index" icon="receipt">Commandes</x-sidebar-link>
                            @endrole

                            @role('restaurant_chief','restaurant_cook')
                                <x-sidebar-link route="restaurant.kitchen.index" icon="cooking-pot">Cuisine</x-sidebar-link>
                            @endrole

                            @role('manager','restaurant_chief','restaurant_staff')
                                {{-- Pour le serveur, « Menus » est l'écran de prise de
                                     commande ; pour le chef, la carte à administrer. --}}
                                <x-sidebar-link route="restaurant.menus.index" icon="book">Menus</x-sidebar-link>
                            @endrole

                            {{-- Coûts, stocks et inventaires relèvent de la gestion :
                                 la salle n'a pas à les voir. --}}
                            @role('manager','restaurant_chief')
                                <x-sidebar-link route="restaurant.recipes.index" icon="chef-hat">Fiches techniques</x-sidebar-link>
                                <x-sidebar-link route="restaurant.pantry.index" icon="warehouse">Garde-manger</x-sidebar-link>
                                <x-sidebar-link route="restaurant.stock_counts.index" icon="clipboard-list">Inventaires</x-sidebar-link>
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
                                       title="Portail (QR)"
                                       class="sidebar-lien flex items-center gap-2.5 px-2 py-1.5 rounded-md text-xs font-medium transition-all text-text-on-dark hover:bg-surface-dark hover:text-white">
                                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 6v-4h2v4h-2zm4 0v-2h2v2h-2zm-4-6v-2h2v2h-2zm4 2v-2h2v2h-2z"/>
                                        </svg>
                                        <span class="sidebar-libelle">Portail (QR)</span>
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </div>
                    @endmodule
                @endrole

                @role('manager','reception','cashier')
                    <div>
                        <p class="sidebar-groupe-titre text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Gestion</p>
                        <ul class="space-y-0.5">
                            <x-sidebar-link route="customers.index" icon="users">Clients</x-sidebar-link>
                            @role('manager')
                                <x-sidebar-link route="users.index" icon="user-cog">Utilisateurs</x-sidebar-link>
                            @endrole
                        </ul>
                    </div>
                @endrole

                @role('shop_manager','shop_cashier','manager')
                    @module('shop')
                    <div>
                        <p class="sidebar-groupe-titre text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Boutique</p>
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
                    @endmodule
                @endrole

                @role('econome','manager','admin')
                    <div>
                        <p class="sidebar-groupe-titre text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Économat</p>
                        <ul class="space-y-0.5">
                            <x-sidebar-link route="economat.index" icon="warehouse">Tableau de bord</x-sidebar-link>
                            <x-sidebar-link route="economat.items.index" icon="boxes">Articles</x-sidebar-link>
                            <x-sidebar-link route="economat.suppliers.index" icon="truck">Fournisseurs</x-sidebar-link>
                            <x-sidebar-link route="economat.orders.index" icon="clipboard-list">Bons de commande</x-sidebar-link>
                            <x-sidebar-link route="economat.requisitions.index" icon="inbox">Demandes</x-sidebar-link>
                        </ul>
                    </div>
                @endrole

                {{-- Lien de demande à l'économat, pour les responsables de département --}}
                @role('reception','housekeeping_leader','restaurant_chief','shop_manager')
                    <div>
                        <p class="sidebar-groupe-titre text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Économat</p>
                        <ul class="space-y-0.5">
                            <x-sidebar-link route="economat.requisitions.index" icon="inbox">Mes demandes</x-sidebar-link>
                        </ul>
                    </div>
                @endrole

                @role('accountant','manager','admin')
                    <div>
                        <p class="sidebar-groupe-titre text-text-on-dark/40 text-[10px] font-semibold uppercase tracking-widest mb-2 px-2">Comptabilité</p>
                        <ul class="space-y-0.5">
                            <x-sidebar-link route="accounting.index" icon="wallet">Comptabilité</x-sidebar-link>
                            @module('ledger')
                                <x-sidebar-link route="accounting.ledger.index" icon="book-open">Grand livre</x-sidebar-link>
                            @endmodule
                            {{-- Le manager voit déjà les fiches techniques dans la section Hôtel. --}}
                            @role('accountant','admin')
                                <x-sidebar-link route="rooms.cost_sheets.index" icon="calculator">Fiches techniques</x-sidebar-link>
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

            @module('discussions')
            <div class="sidebar-bloc px-3 pb-3">
                @php $isDiscussionActive = request()->routeIs('discussions.*'); @endphp
                <a id="sidebar-discussions-link"
                   href="{{ route('discussions.index') }}"
                   title="Discussions"
                   class="sidebar-lien sidebar-lien-discussions flex items-center justify-between gap-2.5 px-2 py-2 rounded-md text-xs font-medium transition-all {{ $isDiscussionActive ? 'bg-surface-dark text-white' : 'text-text-on-dark hover:bg-surface-dark hover:text-white' }}">
                    <span class="flex items-center gap-2.5 min-w-0">
                        <i data-lucide="message-circle" class="w-3.5 h-3.5 flex-shrink-0"></i>
                        <span class="sidebar-libelle truncate">Discussions</span>
                    </span>
                    <span id="sidebar-discussions-dot"
                          class="h-2 w-2 rounded-full bg-secondary {{ !($hasUnreadDiscussions ?? false) ? 'hidden' : '' }}"></span>
                </a>
            </div>
            @endmodule

            <div class="sidebar-bloc px-3 py-4 border-t border-surface-dark">
                <div class="sidebar-pied flex items-center justify-between gap-2">
                    <a href="{{ route('profile.edit') }}"
                       title="{{ Auth::user()->name }}"
                       class="sidebar-lien flex items-center gap-2 flex-1 min-w-0 rounded-lg px-1 py-1 hover:bg-surface-dark transition-colors">
                        <div class="w-8 h-8 rounded-full bg-secondary flex items-center justify-center flex-shrink-0">
                            <span class="text-text-on-light font-semibold text-xs">
                                {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                            </span>
                        </div>
                        <div class="sidebar-libelle min-w-0">
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

                {{-- Largeur de la barre latérale. Le bouton vit dans l'en-tête
                     plutôt que dans la barre : réduite à 60 px, celle-ci n'a
                     plus la place d'accueillir une commande atteignable. --}}
                <button type="button" onclick="basculerSidebar()"
                        aria-controls="mobile-sidebar"
                        class="hidden h-10 w-10 items-center justify-center rounded-lg border border-secondary/20 bg-white text-primary/70 hover:text-primary hover:bg-accent/30 transition-colors lg:inline-flex">
                    <svg id="sidebar-bascule-fermer" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <title>Réduire la barre latérale</title>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 5v14M17 10l-3 2 3 2" />
                    </svg>
                    <svg id="sidebar-bascule-ouvrir" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <title>Étirer la barre latérale</title>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 5v14M14 10l3 2-3 2" />
                    </svg>
                </button>
                <p class="text-primary font-medium text-sm">@yield('title', 'Tableau de bord')</p>
            </div>
            <div class="flex items-center gap-4">
                {{-- Bouton d'activation des notifications push (visible tant que la
                     permission n'est pas accordée). Requiert un geste utilisateur. --}}
                <button type="button" id="enable-push-btn" onclick="window.enablePushNotifications && window.enablePushNotifications()"
                        class="hidden items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-secondary/30 text-primary/70 hover:text-primary hover:bg-accent/30 transition-colors text-xs font-medium"
                        title="Activer les notifications système (même hors de l'application)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span class="hidden sm:inline">Activer les notifications</span>
                </button>

                {{-- Bouton « Suggestion » : ouvert à tout le personnel, quel que
                     soit son rôle. Les tickets partent vers le support technique,
                     qui les traite depuis l'ERP et répond dans le même ticket. --}}
                <div x-data="supportTicketCenter()" class="relative">
                    <button type="button" @click="ouvrir()"
                            class="relative inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-secondary/30 text-primary/70 hover:text-primary hover:bg-accent/30 transition-colors text-xs font-medium"
                            title="Signaler un problème ou proposer une amélioration">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                        </svg>
                        <span class="hidden sm:inline">Suggestion</span>
                        <span x-show="ouverts > 0" style="display: none;"
                              class="absolute -top-1.5 -right-1.5 min-w-4 h-4 px-1 flex items-center justify-center bg-secondary text-text-on-light rounded-full text-[9px] font-bold border border-white"
                              x-text="ouverts"></span>
                    </button>

                    {{-- Fenêtre de saisie --}}
                    <div x-show="modal" style="display: none;"
                         class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:p-8"
                         @click.self="modal = false" @keydown.escape.window="modal = false">
                        <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">

                            <div class="flex items-center justify-between gap-3 bg-primary px-5 py-3.5">
                                <div>
                                    <h3 class="text-sm font-heading font-semibold text-white">Remonter au support</h3>
                                    <p class="text-[10px] text-text-on-dark/70">Un problème rencontré, une idée d'amélioration.</p>
                                </div>
                                <button type="button" @click="modal = false" class="text-text-on-dark/60 hover:text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>

                            <div class="flex border-b border-secondary/20">
                                <button type="button" @click="vue = 'nouveau'"
                                        class="px-4 py-2.5 text-xs font-semibold transition-colors border-b-2 -mb-px"
                                        :class="vue === 'nouveau' ? 'text-primary border-primary' : 'text-primary/40 border-transparent hover:text-primary/70'">
                                    Nouveau ticket
                                </button>
                                <button type="button" @click="vue = 'miens'; charger()"
                                        class="px-4 py-2.5 text-xs font-semibold transition-colors border-b-2 -mb-px"
                                        :class="vue === 'miens' ? 'text-primary border-primary' : 'text-primary/40 border-transparent hover:text-primary/70'">
                                    Mes tickets <span x-show="tickets.length > 0" style="display: none;" x-text="'(' + tickets.length + ')'"></span>
                                </button>
                            </div>

                            {{-- Formulaire --}}
                            <div x-show="vue === 'nouveau'" style="display: none;" class="p-5">
                                <div x-show="envoye" style="display: none;" class="rounded-xl border border-green-200 bg-green-50 p-5 text-center">
                                    <svg class="w-8 h-8 mx-auto text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <p class="mt-2 text-sm font-semibold text-green-800">Ticket transmis au support</p>
                                    <p class="mt-1 text-xs text-green-700">Vous pourrez suivre son traitement dans « Mes tickets ».</p>
                                    <button type="button" @click="reinitialiser()" class="mt-4 rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:opacity-90 transition-opacity">
                                        Écrire un autre ticket
                                    </button>
                                </div>

                                <form x-show="!envoye" style="display: none;" @submit.prevent="envoyer()" class="space-y-4">
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" @click="type = 'probleme'"
                                                class="rounded-xl border px-3 py-2.5 text-left transition-colors"
                                                :class="type === 'probleme' ? 'border-primary bg-accent/30' : 'border-secondary/30 hover:bg-accent/10'">
                                            <span class="block text-xs font-semibold text-primary">Problème</span>
                                            <span class="block text-[10px] text-primary/50">Quelque chose ne marche pas</span>
                                        </button>
                                        <button type="button" @click="type = 'suggestion'"
                                                class="rounded-xl border px-3 py-2.5 text-left transition-colors"
                                                :class="type === 'suggestion' ? 'border-primary bg-accent/30' : 'border-secondary/30 hover:bg-accent/10'">
                                            <span class="block text-xs font-semibold text-primary">Suggestion</span>
                                            <span class="block text-[10px] text-primary/50">Une idée d'amélioration</span>
                                        </button>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-semibold uppercase tracking-wider text-primary/50 mb-1">Objet</label>
                                        <input type="text" x-model="subject" required minlength="5" maxlength="160"
                                               placeholder="Ex : impossible d'encaisser une commande"
                                               class="w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm text-primary outline-none focus:border-primary">
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-semibold uppercase tracking-wider text-primary/50 mb-1">Description</label>
                                        <textarea x-model="message" rows="4" required minlength="10" maxlength="2000"
                                                  placeholder="Ce que vous faisiez, ce qui s'est passé, et ce que vous attendiez."
                                                  class="w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm text-primary outline-none focus:border-primary"></textarea>
                                        <p class="mt-1 text-[10px] text-primary/40">
                                            La page courante est jointe automatiquement au ticket.
                                        </p>
                                    </div>

                                    <p x-show="erreur" style="display: none;" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700" x-text="erreur"></p>

                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" @click="modal = false" class="rounded-lg border border-secondary/30 px-4 py-2 text-xs font-semibold text-primary/70 hover:bg-accent/20 transition-colors">
                                            Annuler
                                        </button>
                                        <button type="submit" :disabled="envoiEnCours"
                                                class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:opacity-90 transition-opacity disabled:opacity-50">
                                            <span x-show="!envoiEnCours">Envoyer au support</span>
                                            <span x-show="envoiEnCours" style="display: none;">Envoi…</span>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            {{-- Suivi de mes tickets --}}
                            <div x-show="vue === 'miens'" style="display: none;" class="max-h-96 overflow-y-auto p-5">
                                <p x-show="chargement" style="display: none;" class="py-6 text-center text-xs text-primary/40">Chargement…</p>

                                <template x-if="!chargement && tickets.length === 0">
                                    <p class="py-6 text-center text-xs text-primary/40">Vous n'avez encore envoyé aucun ticket.</p>
                                </template>

                                <ul class="space-y-3">
                                    <template x-for="t in tickets" :key="t.id">
                                        <li class="rounded-xl border border-secondary/20 p-3">
                                            <div class="flex items-start justify-between gap-2">
                                                <span class="text-xs font-semibold text-primary" x-text="t.subject"></span>
                                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider"
                                                      :class="t.status === 'resolu' ? 'bg-green-100 text-green-700' : (t.status === 'en_cours' ? 'bg-amber-100 text-amber-700' : (t.status === 'rejete' ? 'bg-slate-100 text-slate-500' : 'bg-sky-100 text-sky-700'))"
                                                      x-text="t.label"></span>
                                            </div>
                                            <p class="mt-1 text-[10px] text-primary/40" x-text="t.at + ' · ' + t.ago"></p>
                                            <template x-if="t.reply">
                                                <p class="mt-2 rounded-lg bg-accent/30 px-2.5 py-2 text-[11px] text-primary/80">
                                                    <span class="font-semibold">Réponse du support :</span> <span x-text="t.reply"></span>
                                                </p>
                                            </template>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

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

                @if(\App\Support\TenantModules::has('website'))
                    {{-- Liaison avec le site vitrine : vert = le container web répond,
                         rouge = injoignable (vérifié au chargement puis toutes les 60s) --}}
                    <span class="hidden sm:flex items-center gap-1.5 text-xs font-medium"
                          x-data="{ online: null, async check() { try { const r = await fetch('{{ route('site-sync.status') }}', { headers: { 'Accept': 'application/json' } }); this.online = (await r.json()).online; } catch (e) { this.online = false; } } }"
                          x-init="check(); setInterval(() => check(), 60000)"
                          :class="online === false ? 'text-red-600' : 'text-green-600'"
                          :title="online === false ? 'Le site vitrine ne répond plus' : (online === null ? 'Vérification de la liaison avec le site vitrine…' : 'Site vitrine joignable')">
                        <span class="w-2 h-2 rounded-full" :class="online === false ? 'bg-red-500' : 'bg-green-500 animate-pulse'"></span>
                        <span x-text="online === false ? 'Site hors ligne' : 'Site en ligne'">Site en ligne</span>
                    </span>
                @else
                    <span class="hidden sm:flex items-center gap-1.5 text-xs text-green-600 font-medium">
                        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                        En ligne
                    </span>
                @endif
                <span class="text-xs text-primary/50">
                    {{ ucfirst(\Carbon\Carbon::now()->locale('fr')->isoFormat('ddd. D MMM')) }}
                </span>
            </div>
        </header>

        @if(session('assistance_mode'))
            {{-- Bannière permanente : session ouverte par le support technique (PMS) --}}
            <div class="bg-amber-500 text-white px-4 lg:px-8 py-2 flex items-center justify-between gap-3 flex-shrink-0">
                <div class="flex items-center gap-2 text-xs font-semibold">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>
                    <span>Mode assistance — session ouverte par le support technique ({{ session('assistance_mode')['admin'] ?? 'Support' }}). Vos actions sont enregistrées.</span>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="rounded-md bg-white/20 hover:bg-white/30 px-3 py-1 text-xs font-bold transition">Quitter</button>
                </form>
            </div>
        @endif

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

    /**
     * Étire ou réduit la barre latérale. Le choix est mémorisé : personne
     * n'a envie de la replier à chaque changement de page.
     */
    window.basculerSidebar = function() {
        const reduite = document.documentElement.classList.toggle('sidebar-reduite');

        try {
            localStorage.setItem('sidebar-reduite', reduite ? '1' : '0');
        } catch (e) { /* navigation privée : le choix ne tient que pour la page */ }
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
        // Tickets de support : saisie et suivi. Le compteur du bouton ne montre
        // que les tickets encore ouverts, pour ne pas rester allumé à vie.
        Alpine.data('supportTicketCenter', () => ({
            modal: false,
            vue: 'nouveau',
            type: 'probleme',
            subject: '',
            message: '',
            erreur: '',
            envoye: false,
            envoiEnCours: false,
            chargement: false,
            tickets: [],
            ouverts: 0,

            init() {
                this.charger();
            },

            ouvrir() {
                this.modal = true;
                this.vue = 'nouveau';
                this.charger();
            },

            reinitialiser() {
                this.envoye = false;
                this.erreur = '';
                this.subject = '';
                this.message = '';
                this.type = 'probleme';
            },

            async charger() {
                this.chargement = true;
                try {
                    const r = await fetch('{{ route('support-tickets.index') }}', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (r.ok) {
                        const d = await r.json();
                        this.tickets = d.tickets ?? [];
                        this.ouverts = d.ouverts ?? 0;
                    }
                } catch (e) {
                    // Silencieux : le suivi n'est pas critique, la saisie reste possible.
                }
                this.chargement = false;
            },

            async envoyer() {
                this.envoiEnCours = true;
                this.erreur = '';
                try {
                    const r = await fetch('{{ route('support-tickets.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            type: this.type,
                            subject: this.subject,
                            message: this.message,
                            context_url: window.location.pathname + window.location.search,
                        }),
                    });

                    if (r.ok) {
                        this.envoye = true;
                        this.subject = '';
                        this.message = '';
                        this.charger();
                    } else {
                        const d = await r.json().catch(() => ({}));
                        this.erreur = d.message || 'Envoi impossible. Réessayez dans un instant.';
                    }
                } catch (e) {
                    this.erreur = 'Envoi impossible : vérifiez votre connexion.';
                }
                this.envoiEnCours = false;
            },
        }));

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

    {{-- ===== WEB PUSH : notifications système même application fermée ===== --}}
    <script>
    (function () {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

        const CSRF = '{{ csrf_token() }}';
        const VAPID_URL = '{{ route('push.vapid') }}';
        const SUBSCRIBE_URL = '{{ route('push.subscribe') }}';

        // Affiche le bouton « Activer les notifications » tant que la
        // permission n'a pas été accordée (ni définitivement refusée).
        window.updatePushButton = function () {
            const btn = document.getElementById('enable-push-btn');
            if (!btn) return;
            const show = ('Notification' in window) && Notification.permission === 'default';
            btn.classList.toggle('hidden', !show);
            btn.classList.toggle('flex', show);
        };

        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const raw = atob(base64);
            const output = new Uint8Array(raw.length);
            for (let i = 0; i < raw.length; i++) output[i] = raw.charCodeAt(i);
            return output;
        }

        async function subscribe(registration) {
            const res = await fetch(VAPID_URL, { headers: { 'Accept': 'application/json' } });
            const { key } = await res.json();
            if (!key) { console.warn('[push] Clé VAPID absente — abonnement impossible.'); return false; }

            let sub = await registration.pushManager.getSubscription();
            if (!sub) {
                sub = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(key),
                });
            }

            const json = sub.toJSON();
            await fetch(SUBSCRIBE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({
                    endpoint: sub.endpoint,
                    keys: json.keys,
                    content_encoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
                }),
            });
            return true;
        }

        async function register() {
            try {
                // Même URL que l'enregistrement PWA : l'appel est idempotent,
                // le navigateur renvoie l'enregistrement déjà en place.
                const registration = await navigator.serviceWorker.register('/sw.js');
                // Reflète l'état de permission sur le bouton d'activation du header
                window.updatePushButton && window.updatePushButton();
                if (Notification.permission === 'granted') {
                    await subscribe(registration);
                }
            } catch (e) {
                console.error('[push] Enregistrement du service worker échoué', e);
            }
        }

        // Déclenché par le bouton « Activer les notifications » (geste utilisateur
        // requis par les navigateurs pour demander la permission).
        window.enablePushNotifications = async function () {
            try {
                const permission = await Notification.requestPermission();
                window.updatePushButton && window.updatePushButton();
                if (permission !== 'granted') {
                    alert('Notifications refusées. Vous pouvez les réactiver dans les paramètres du navigateur.');
                    return;
                }
                const registration = await navigator.serviceWorker.ready;
                const ok = await subscribe(registration);
                if (ok && window.showSystemToast) {
                    window.showSystemToast('Notifications activées', 'Vous recevrez les alertes même hors de l\'application.');
                }
            } catch (e) {
                console.error('[push] Activation échouée', e);
            }
        };

        window.addEventListener('load', register);
    })();
    </script>

    <script>
    // ── Suggestion automatique de code à la création ───────────────────────
    // Génère un code lisible à partir d'un libellé : initiales pour un intitulé
    // à plusieurs mots (« Total Energies Cameroun » → « TEC »), quatre premières
    // lettres pour un mot unique (« Standard » → « STAN »). Toujours modifiable.
    window.suggestCode = function (name) {
        if (!name) return '';
        const words = name.trim().split(/[\s\-_/]+/).filter(Boolean);
        let code = words.length >= 2
            ? words.map(w => w[0]).join('')
            : (words[0] || '').slice(0, 4);
        return code
            .normalize('NFD').replace(/[̀-ͯ]/g, '') // enlève les accents
            .replace(/[^A-Za-z0-9]/g, '')
            .toUpperCase();
    };

    // Relie un champ « nom » à un champ « code » pour les formulaires en HTML
    // simple : le code suit le nom tant que l'utilisateur ne l'a pas édité.
    window.wireAutoCode = function (nameEl, codeEl) {
        if (!nameEl || !codeEl) return;
        // Un code déjà rempli (ré-affichage après erreur) est réputé maîtrisé.
        let manual = codeEl.value.trim() !== '';
        codeEl.addEventListener('input', () => { manual = true; });
        nameEl.addEventListener('input', () => {
            if (!manual) codeEl.value = window.suggestCode(nameEl.value);
        });
    };
    </script>

    @include('partials.pwa-install')

    @stack('scripts')

</body>

</html>
