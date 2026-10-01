@php
    $currentTenant = Auth::user()->tenant ?? \App\Models\Tenant::first();
    $tenantName = $currentTenant?->name ?? 'Établissement';
    $tenantLogo = !empty($currentTenant?->settings['logo']) ? asset('storage/' . $currentTenant->settings['logo']) : null;

    $words = explode(' ', $tenantName);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    $initials = substr($initials, 0, 2) ?: 'VB';
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Mode POS Réception — ' . $tenantName)</title>
    @include('partials.pwa-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

<body class="min-h-screen bg-accent/20 font-body text-primary flex flex-col antialiased">

    {{-- Topbar Fixe Plein Écran pour le Mode POS --}}
    <header class="bg-primary text-white shadow-md sticky top-0 z-30 flex-shrink-0 border-b border-surface-dark">
        <div class="px-4 sm:px-6 py-3 flex items-center justify-between gap-4">
            
            {{-- Gauche: Logo & Titre Mode POS --}}
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl overflow-hidden bg-white/10 flex-shrink-0 flex items-center justify-center border border-white/20">
                    @if($tenantLogo)
                        <img src="{{ $tenantLogo }}" alt="{{ $tenantName }}" class="w-full h-full object-cover">
                    @else
                        <span class="font-heading font-bold text-sm text-white">{{ $initials }}</span>
                    @endif
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-heading font-bold text-sm sm:text-base leading-tight tracking-wide">{{ $tenantName }}</span>
                        <span class="inline-flex items-center gap-1 text-[10px] uppercase font-bold tracking-widest bg-white/20 text-white px-2 py-0.5 rounded-md">
                            <i data-lucide="store" class="w-3 h-3"></i> Mode POS Réception
                        </span>
                    </div>
                    <p class="text-[11px] text-text-on-dark/80 font-medium">Terminal de vente & d'encaissement de la journée</p>
                </div>
            </div>

            {{-- Centre: Horloge en temps réel & Date --}}
            <div class="hidden md:flex flex-col items-center justify-center"
                 x-data="{ time: new Date().toLocaleTimeString('fr-FR') }"
                 x-init="setInterval(() => time = new Date().toLocaleTimeString('fr-FR'), 1000)">
                <span class="font-mono font-bold text-lg text-white tracking-widest" x-text="time"></span>
                <span class="text-[11px] text-text-on-dark font-medium capitalize">
                    {{ \Carbon\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                </span>
            </div>

            {{-- Droite: Utilisateur, Bouton Quitter le mode POS & Déconnexion --}}
            <div class="flex items-center gap-2.5">
                <div class="hidden sm:flex flex-col text-right pr-2 border-r border-white/20">
                    <span class="text-xs font-bold text-white">{{ Auth::user()->name }}</span>
                    <span class="text-[10px] text-text-on-dark uppercase font-semibold">{{ Auth::user()->exerce(['manager']) ? 'Directeur' : (Auth::user()->exerce(['reception_chief']) ? 'Chef de réception' : 'Réceptionniste') }}</span>
                </div>

                {{-- Bouton Quitter le mode POS (Retour au PMS sans fermer la session) --}}
                <a href="{{ route('dashboard') }}"
                   title="Retourner au PMS standard tout en conservant la caisse active"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-white bg-white/15 hover:bg-white/25 border border-white/20 transition-all shadow-xs">
                    <i data-lucide="arrow-left-circle" class="w-4 h-4 text-accent"></i>
                    <span class="hidden sm:inline">Quitter le mode POS</span>
                </a>

                {{-- Bouton Déconnexion bien visible --}}
                <form method="POST" action="{{ route('logout') }}" class="inline-flex items-center">
                    @csrf
                    <button type="submit"
                            title="Se déconnecter de l'application"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-red-100 bg-red-600 hover:bg-red-700 transition-all shadow-xs">
                        <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                        <span class="hidden sm:inline">Déconnexion</span>
                    </button>
                </form>
            </div>

        </div>
    </header>

    {{-- Contenu Plein Écran --}}
    <main class="flex-1 w-full overflow-y-auto">
        @yield('content')
    </main>

    {{-- Access Denied Popup --}}
    <x-access-denied-popup />

    {{-- Modal de confirmation si déconnexion avec caisse ouverte --}}
    @if(session('confirm_logout_caisse_open'))
    <div id="caisse-logout-modal"
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
         role="dialog"
         aria-modal="true">
        <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all z-10">
            <div class="flex items-center gap-3 bg-yellow-50 px-6 py-4 border-b border-yellow-100">
                <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-600">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-heading font-bold text-yellow-900">Caisse Réception Ouverte</h3>
                    <p class="text-xs text-yellow-700">Votre session de caisse est toujours en cours</p>
                </div>
            </div>
            <div class="px-6 py-4 text-xs text-primary/80 leading-relaxed space-y-2">
                <p>Vous avez une caisse active. Que souhaitez-vous faire avant de quitter ?</p>
                <div class="p-3 bg-accent/20 rounded-xl border border-secondary/15 text-primary text-[11px]">
                    <p>• <strong>Mettre en pause</strong> : pour reprendre plus tard dans la journée.</p>
                    <p>• <strong>Clôturer la caisse</strong> : pour effectuer le comptage physique de fin de service.</p>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row items-center justify-end gap-2 px-6 py-4 bg-gray-50 border-t border-secondary/10">
                <button type="button"
                        onclick="document.getElementById('caisse-logout-modal').classList.add('hidden')"
                        class="w-full sm:w-auto px-4 py-2 text-xs font-semibold text-primary/70 hover:text-primary transition-colors">
                    Annuler
                </button>
                <form method="POST" action="{{ route('logout') }}" class="w-full sm:w-auto">
                    @csrf
                    <input type="hidden" name="force" value="1">
                    <button type="submit"
                            class="w-full px-4 py-2 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-xl hover:bg-slate-50 transition-colors shadow-xs">
                        Déconnexion (Pause)
                    </button>
                </form>
                <a href="{{ route('bookings.cash_register.close') }}"
                   class="w-full sm:w-auto px-4 py-2 bg-primary text-white text-xs font-bold rounded-xl hover:bg-surface-dark transition-colors shadow-xs text-center">
                    Fermer la caisse
                </a>
            </div>
        </div>
    </div>
    @endif

    @stack('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    </script>
</body>
</html>
