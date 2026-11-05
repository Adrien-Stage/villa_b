@php
    $tenant = \App\Models\Tenant::first();
    $tenantName = $tenant?->name ?? 'Établissement';
    // Logo importé par le manager depuis les paramètres de l'application
    // (stocké localement dans le storage de ce tenant, pas de l'admin).
    $tenantLogo = ($tenant && !empty($tenant->settings['logo'])) ? asset('storage/' . $tenant->settings['logo']) : null;
    $tenantInitial = mb_strtoupper(mb_substr($tenantName, 0, 1));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — {{ $tenantName }}</title>
    @include('partials.pwa-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: {{ $tenant->settings['theme']['primary'] ?? '#391F0E' }};
            --color-secondary: {{ $tenant->settings['theme']['secondary'] ?? '#CCAB87' }};
            --color-accent: {{ $tenant->settings['theme']['accent'] ?? '#EED4A3' }};
            --color-dark: {{ $tenant->settings['theme']['dark'] ?? '#0F0201' }};
            --color-surface-dark: {{ $tenant->settings['theme']['surface_dark'] ?? '#2C1810' }};
            --color-text-on-light: {{ $tenant->settings['theme']['text_on_light'] ?? '#391F0E' }};
            --color-text-on-dark: {{ $tenant->settings['theme']['text_on_dark'] ?? '#CCAB87' }};
        }
        /* Animations fade-in */
        @keyframes fadeInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes fadeInRight {
            from { opacity: 0; transform: translateX(30px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-left  { animation: fadeInLeft  0.7s ease forwards; }
        .animate-right { animation: fadeInRight 0.7s ease 0.2s forwards; opacity: 0; }
        .animate-up-1  { animation: fadeInUp 0.6s ease 0.3s forwards; opacity: 0; }
        .animate-up-2  { animation: fadeInUp 0.6s ease 0.45s forwards; opacity: 0; }
        .animate-up-3  { animation: fadeInUp 0.6s ease 0.6s forwards; opacity: 0; }
        .animate-up-4  { animation: fadeInUp 0.6s ease 0.75s forwards; opacity: 0; }

        /* Cercles décoratifs */
        .circle-deco {
            position: absolute;
            border-radius: 50%;
            border: 1px solid var(--color-secondary);
            opacity: 0.15;
        }

        /* Neutraliser le fond jaune d'autofill sur le thème sombre */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-text-fill-color: var(--color-text-on-dark) !important;
            -webkit-box-shadow: 0 0 0 1000px #180d07 inset !important;
            box-shadow: 0 0 0 1000px #180d07 inset !important;
            caret-color: var(--color-text-on-dark) !important;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>
{{-- min-h-screen plutôt que height/overflow fixes : sur mobile le clavier
     virtuel réduit la hauteur utile, un overflow:hidden couperait le formulaire. --}}
<body class="bg-dark font-body" style="min-height:100vh;">

<div class="flex min-h-screen">

    {{-- ===== PANNEAU GAUCHE — Branding (écrans larges uniquement) =====
         Décoratif : masqué sur mobile pour laisser toute la place au formulaire. --}}
    <div class="hidden lg:flex w-5/12 relative flex-col items-center justify-center bg-surface-dark animate-left overflow-hidden"
         style="background: radial-gradient(ellipse at 30% 50%, var(--color-surface-dark) 0%, var(--color-dark) 70%);">

        {{-- Cercles décoratifs --}}
        <div class="circle-deco" style="width:400px; height:400px; top:-80px; left:-80px;"></div>
        <div class="circle-deco" style="width:250px; height:250px; bottom:60px; right:-60px;"></div>
        <div class="circle-deco" style="width:150px; height:150px; bottom:120px; right:20px;"></div>

        {{-- Contenu centré --}}
        <div class="relative z-10 flex flex-col items-center text-center px-12">

            {{-- Logo --}}
            <div class="animate-up-1 w-28 h-28 rounded-full shadow-2xl mb-6 flex items-center justify-center overflow-hidden"
                 style="box-shadow: 0 0 40px rgba(204,171,135,0.2); background: {{ $tenantLogo ? 'white' : 'var(--color-secondary)' }};">
                @if($tenantLogo)
                    <img src="{{ $tenantLogo }}"
                         alt="{{ $tenantName }}"
                         class="w-full h-full object-cover rounded-full p-1">
                @else
                    <span class="font-heading text-5xl font-semibold" style="color: var(--color-text-on-light);">{{ $tenantInitial }}</span>
                @endif
            </div>

            {{-- Nom --}}
            <h1 class="animate-up-2 font-heading text-4xl font-semibold text-text-on-dark mb-3">
                {{ $tenantName }}
            </h1>

            {{-- Sous-titre --}}
            <p class="animate-up-3 text-sm leading-relaxed font-light"
               style="color: var(--color-text-on-dark); opacity: 0.6;">
                Plateforme de gestion hôtelière<br>
                pour la conservation du patrimoine culturel
            </p>

            {{-- Séparateur ONG --}}
            <div class="animate-up-4 flex items-center gap-3 mt-6">
                <div class="h-px w-10" style="background: var(--color-text-on-dark); opacity: 0.3;"></div>
                <span class="text-xs tracking-[0.3em] font-medium"
                      style="color: var(--color-text-on-dark); opacity: 0.4;">ONG</span>
                <div class="h-px w-10" style="background: var(--color-text-on-dark); opacity: 0.3;"></div>
            </div>
        </div>
    </div>

    {{-- ===== PANNEAU DROIT — Formulaire (pleine largeur sur mobile) ===== --}}
    <div class="w-full lg:w-7/12 flex items-center justify-center animate-right py-10 px-5"
         style="background: #080100;">

        <div class="w-full max-w-sm">

            {{-- Identité de l'établissement : visible uniquement sur mobile,
                 où le panneau de marque latéral est masqué. --}}
            <div class="lg:hidden flex flex-col items-center text-center mb-8">
                <div class="w-20 h-20 rounded-full shadow-2xl mb-4 flex items-center justify-center overflow-hidden"
                     style="box-shadow: 0 0 30px rgba(204,171,135,0.2); background: {{ $tenantLogo ? 'white' : 'var(--color-secondary)' }};">
                    @if($tenantLogo)
                        <img src="{{ $tenantLogo }}" alt="{{ $tenantName }}" class="w-full h-full object-cover rounded-full p-1">
                    @else
                        <span class="font-heading text-3xl font-semibold" style="color: var(--color-text-on-light);">{{ $tenantInitial }}</span>
                    @endif
                </div>
                <h1 class="font-heading text-2xl font-semibold text-text-on-dark">{{ $tenantName }}</h1>
                <p class="text-xs mt-1" style="color: var(--color-text-on-dark); opacity: 0.5;">
                    Plateforme de gestion hôtelière
                </p>
            </div>

            {{-- Titre formulaire --}}
            <div class="animate-up-1 mb-8">
                <h2 class="font-heading text-2xl sm:text-3xl font-semibold text-text-on-dark mb-1">Connexion</h2>
                <p class="text-sm" style="color: var(--color-text-on-dark); opacity: 0.5;">
                    Entrez vos identifiants pour continuer
                </p>
            </div>

            @php
                // Initialisation sécurisée du sac d'erreurs
                $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
                $mainError = $errors->first('login') ?: $errors->first('email') ?: $errors->first('password');
                $isDeactivated = $mainError && (
                    str_contains(mb_strtolower($mainError), 'désactivé') || 
                    str_contains(mb_strtolower($mainError), 'desactive')
                );
                $isThrottled = $mainError && (
                    str_contains(mb_strtolower($mainError), 'tentative') || 
                    str_contains(mb_strtolower($mainError), 'secondes')
                );
                $isAuthFailed = $mainError && ! $isDeactivated && ! $isThrottled && (
                    $mainError === trans('auth.failed') ||
                    $mainError === trans('auth.password') ||
                    str_contains(mb_strtolower($mainError), 'identifiant') ||
                    str_contains(mb_strtolower($mainError), 'mot de passe') ||
                    str_contains(mb_strtolower($mainError), 'correspondent pas')
                );
            @endphp

            {{-- Message d'état de session optionnel (hors expiration d'inactivité) --}}
            @if(session('status') && ! str_contains(mb_strtolower(session('status')), 'inactivit') && ! str_contains(mb_strtolower(session('status')), 'expir'))
                <div class="mb-6 p-4 rounded-xl text-xs font-medium border border-secondary/30 bg-secondary/10 text-text-on-dark flex items-center gap-2.5 animate-up-1">
                    <svg class="w-4 h-4 flex-shrink-0 text-secondary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            {{-- Bannière d'erreur contextuelle haut de gamme --}}
            @if($mainError)
                @if($isDeactivated)
                    <div class="mb-6 p-4 rounded-2xl border border-red-500/30 bg-gradient-to-br from-red-950/60 via-red-900/20 to-surface-dark shadow-[0_4px_24px_-4px_rgba(239,68,68,0.25)] backdrop-blur-md flex items-start gap-3.5 animate-up-1">
                        <div class="flex-shrink-0 w-9 h-9 rounded-xl bg-red-500/15 border border-red-500/25 flex items-center justify-center text-red-400 mt-0.5 shadow-inner">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-red-200 tracking-wide">Compte désactivé</h3>
                            <p class="text-xs text-red-300/80 mt-1 leading-relaxed">
                                Votre compte utilisateur a été désactivé par l'administration. Veuillez contacter votre responsable ou le support pour y accéder.
                            </p>
                        </div>
                    </div>
                @elseif($isThrottled)
                    <div class="mb-6 p-4 rounded-2xl border border-amber-500/30 bg-gradient-to-br from-amber-950/50 via-amber-900/20 to-surface-dark shadow-[0_4px_24px_-4px_rgba(245,158,11,0.2)] backdrop-blur-md flex items-start gap-3.5 animate-up-1">
                        <div class="flex-shrink-0 w-9 h-9 rounded-xl bg-amber-500/15 border border-amber-500/25 flex items-center justify-center text-amber-400 mt-0.5 shadow-inner">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-amber-200 tracking-wide">Trop de tentatives</h3>
                            <p class="text-xs text-amber-300/80 mt-1 leading-relaxed">
                                {{ $mainError }}
                            </p>
                        </div>
                    </div>
                @elseif($isAuthFailed)
                    <div class="mb-6 p-4 rounded-2xl border border-red-500/30 bg-gradient-to-br from-red-950/45 via-red-900/15 to-surface-dark shadow-[0_4px_24px_-4px_rgba(239,68,68,0.2)] backdrop-blur-md flex items-start gap-3.5 animate-up-1">
                        <div class="flex-shrink-0 w-9 h-9 rounded-xl bg-red-500/15 border border-red-500/25 flex items-center justify-center text-red-400 mt-0.5 shadow-inner">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-red-200 tracking-wide">Identifiants incorrects</h3>
                            <p class="text-xs text-red-300/80 mt-1 leading-relaxed">
                                L'adresse email ou le mot de passe est incorrect. Veuillez vérifier vos informations de connexion.
                            </p>
                        </div>
                    </div>
                @else
                    <div class="mb-6 p-4 rounded-2xl border border-red-500/30 bg-gradient-to-br from-red-950/45 via-red-900/15 to-surface-dark shadow-[0_4px_24px_-4px_rgba(239,68,68,0.2)] backdrop-blur-md flex items-start gap-3.5 animate-up-1">
                        <div class="flex-shrink-0 w-9 h-9 rounded-xl bg-red-500/15 border border-red-500/25 flex items-center justify-center text-red-400 mt-0.5 shadow-inner">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-red-200 tracking-wide">Erreur de connexion</h3>
                            <p class="text-xs text-red-300/80 mt-1 leading-relaxed">
                                {{ $mainError }}
                            </p>
                        </div>
                    </div>
                @endif
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                {{-- Email --}}
                <div class="animate-up-2 mb-4">
                    <label class="block text-xs font-semibold tracking-widest mb-2"
                           style="color: var(--color-text-on-dark); opacity: 0.6;">
                        ADRESSE EMAIL
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="vous@villaboutanga.com"
                        required
                        autofocus
                        class="w-full px-4 py-3 rounded-lg text-sm text-text-on-dark placeholder-opacity-30 outline-none transition-all"
                        style="
                            background: color-mix(in srgb, var(--color-secondary) 7%, transparent);
                            border: 1px solid {{ $errors->has('email') || ($errors->has('login') && !old('email')) ? 'rgba(239, 68, 68, 0.6)' : 'color-mix(in srgb, var(--color-secondary) 15%, transparent)' }};
                            color: var(--color-text-on-dark);
                            {{ $errors->has('email') || ($errors->has('login') && !old('email')) ? 'box-shadow: 0 0 12px rgba(239, 68, 68, 0.15);' : '' }}
                        "
                        onfocus="this.style.borderColor='{{ $errors->has('email') ? 'rgba(239, 68, 68, 0.8)' : 'rgba(204,171,135,0.4)' }}'"
                        onblur="this.style.borderColor='{{ $errors->has('email') ? 'rgba(239, 68, 68, 0.6)' : 'color-mix(in srgb, var(--color-secondary) 15%, transparent)' }}'"
                    >
                    @error('email')
                        @if(! $isDeactivated && ! $isAuthFailed && ! $isThrottled)
                            <p class="mt-2 text-xs text-red-400 flex items-center gap-1.5 font-medium">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>{{ $message }}</span>
                            </p>
                        @endif
                    @enderror
                </div>

                {{-- Mot de passe --}}
                <div class="animate-up-3 mb-6">
                    <label class="block text-xs font-semibold tracking-widest mb-2"
                           style="color: var(--color-text-on-dark); opacity: 0.6;">
                        MOT DE PASSE
                    </label>
                    <div class="relative">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="••••••••"
                            required
                            class="w-full px-4 py-3 rounded-lg text-sm outline-none transition-all pr-12"
                            style="
                                background: color-mix(in srgb, var(--color-secondary) 7%, transparent);
                                border: 1px solid {{ $errors->has('password') || ($errors->has('email') && $isAuthFailed) ? 'rgba(239, 68, 68, 0.6)' : 'color-mix(in srgb, var(--color-secondary) 15%, transparent)' }};
                                color: var(--color-text-on-dark);
                                {{ $errors->has('password') || ($errors->has('email') && $isAuthFailed) ? 'box-shadow: 0 0 12px rgba(239, 68, 68, 0.15);' : '' }}
                            "
                            onfocus="this.style.borderColor='{{ $errors->has('password') || ($errors->has('email') && $isAuthFailed) ? 'rgba(239, 68, 68, 0.8)' : 'rgba(204,171,135,0.4)' }}'"
                            onblur="this.style.borderColor='{{ $errors->has('password') || ($errors->has('email') && $isAuthFailed) ? 'rgba(239, 68, 68, 0.6)' : 'color-mix(in srgb, var(--color-secondary) 15%, transparent)' }}'"
                        >
                        {{-- Toggle mot de passe visible --}}
                        <button type="button"
                                onclick="togglePassword()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 transition-opacity hover:opacity-100"
                                style="color: var(--color-text-on-dark); opacity: 0.4;">
                            <svg id="eye-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        @if(! $isDeactivated && ! $isAuthFailed && ! $isThrottled)
                            <p class="mt-2 text-xs text-red-400 flex items-center gap-1.5 font-medium">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>{{ $message }}</span>
                            </p>
                        @endif
                    @enderror
                </div>

                {{-- Le survol conserve la teinte du bouton : l'accent du thème peut être trop clair pour son texte. --}}
                <div class="animate-up-4">
                    <button type="submit"
                            class="w-full py-3 rounded-lg bg-secondary text-text-on-light text-sm font-semibold tracking-wide transition-colors duration-200 flex items-center justify-center gap-2 cursor-pointer hover:bg-[color-mix(in_srgb,var(--color-secondary)_75%,black)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-secondary">
                        Se connecter
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>

            </form>

            {{-- Footer --}}
            <p class="mt-10 text-center text-xs"
               style="color: var(--color-text-on-dark); opacity: 0.25;">
                {{ $tenantName }} PMS · v1.0
            </p>
        </div>
    </div>

</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eye-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
        `;
    } else {
        input.type = 'password';
        icon.innerHTML = `
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        `;
    }
}
</script>

@include('partials.pwa-install')

</body>
</html>
