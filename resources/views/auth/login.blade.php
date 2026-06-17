@php
    $tenant = \App\Models\Tenant::first();
    $tenantName = $tenant?->name ?? 'Villa Boutanga';
    $tenantLogo = !empty($tenant->settings['logo']) ? asset('storage/' . $tenant->settings['logo']) : asset('images/logo.png');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — {{ $tenantName }}</title>
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
    </style>
</head>
<body class="bg-dark font-body" style="height:100vh; overflow:hidden;">

<div class="flex h-screen">

    {{-- ===== PANNEAU GAUCHE — Branding ===== --}}
    <div class="w-5/12 relative flex flex-col items-center justify-center bg-surface-dark animate-left"
         style="background: radial-gradient(ellipse at 30% 50%, var(--color-surface-dark) 0%, var(--color-dark) 70%);">

        {{-- Cercles décoratifs --}}
        <div class="circle-deco" style="width:400px; height:400px; top:-80px; left:-80px;"></div>
        <div class="circle-deco" style="width:250px; height:250px; bottom:60px; right:-60px;"></div>
        <div class="circle-deco" style="width:150px; height:150px; bottom:120px; right:20px;"></div>

        {{-- Contenu centré --}}
        <div class="relative z-10 flex flex-col items-center text-center px-12">

            {{-- Logo --}}
            <div class="animate-up-1 w-28 h-28 rounded-full bg-white p-1 shadow-2xl mb-6"
                 style="box-shadow: 0 0 40px rgba(204,171,135,0.2);">
                <img src="{{ $tenantLogo }}"
                     alt="{{ $tenantName }}"
                     class="w-full h-full object-cover rounded-full">
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

    {{-- ===== PANNEAU DROIT — Formulaire ===== --}}
    <div class="w-7/12 flex items-center justify-center animate-right"
         style="background: #080100;">

        <div class="w-full max-w-sm px-4">

            {{-- Titre formulaire --}}
            <div class="animate-up-1 mb-8">
                <h2 class="font-heading text-3xl font-semibold text-text-on-dark mb-1">Connexion</h2>
                <p class="text-sm" style="color: var(--color-text-on-dark); opacity: 0.5;">
                    Entrez vos identifiants pour continuer
                </p>
            </div>

            {{-- Erreurs de session --}}
            @if(session('status'))
                <div class="mb-4 text-sm text-text-on-dark">{{ session('status') }}</div>
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
                            border: 1px solid color-mix(in srgb, var(--color-secondary) 15%, transparent);
                            color: var(--color-text-on-dark);
                        "
                        onfocus="this.style.borderColor='rgba(204,171,135,0.4)'"
                        onblur="this.style.borderColor='rgba(204,171,135,0.15)'"
                    >
                    @error('email')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
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
                                border: 1px solid color-mix(in srgb, var(--color-secondary) 15%, transparent);
                                color: var(--color-text-on-dark);
                            "
                            onfocus="this.style.borderColor='rgba(204,171,135,0.4)'"
                            onblur="this.style.borderColor='rgba(204,171,135,0.15)'"
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
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Bouton connexion --}}
                <div class="animate-up-4">
                    <button type="submit"
                            class="w-full py-3 rounded-lg text-sm font-semibold tracking-wide transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer"
                            style="background: var(--color-secondary); color: var(--color-text-on-light);"
                            onmouseover="this.style.background='var(--color-accent)'"
                            onmouseout="this.style.background='var(--color-secondary)'">
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

</body>
</html>