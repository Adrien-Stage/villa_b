@php
    $tenant = \App\Models\Tenant::first();
    $tenantName = $tenant?->name ?? 'Établissement';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nouveau mot de passe — {{ $tenantName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-primary: {{ $tenant->settings['theme']['primary'] ?? '#391F0E' }};
            --color-secondary: {{ $tenant->settings['theme']['secondary'] ?? '#CCAB87' }};
            --color-accent: {{ $tenant->settings['theme']['accent'] ?? '#EED4A3' }};
        }
    </style>
</head>
<body class="min-h-screen bg-[color-mix(in_oklab,var(--color-accent)_25%,white)] font-body">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
        <div class="rounded-2xl bg-white p-6 shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-primary/50">{{ $tenantName }}</p>
            <h1 class="mt-1 font-heading text-2xl font-semibold text-primary">Choisissez votre mot de passe</h1>
            <p class="mt-2 text-sm text-primary/60">
                Bonjour {{ auth()->user()->name }}. Votre mot de passe a été réinitialisé : celui que vous venez
                d'utiliser était provisoire. Choisissez-en un que personne d'autre ne connaît.
            </p>

            <form method="POST" action="{{ route('password.nouveau.update') }}" class="mt-5 space-y-4">
                @csrf @method('PUT')
                <div>
                    <label for="password" class="text-xs font-semibold text-primary">Nouveau mot de passe</label>
                    <div class="relative mt-1">
                        <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" autofocus
                               aria-describedby="password-aide @error('password') password-erreur @enderror"
                               class="w-full rounded-lg border border-secondary/30 px-3 py-2 pr-10 text-sm outline-none focus:border-secondary">
                        <button type="button"
                                onclick="togglePasswordVisibility('password', this)"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-primary/40 hover:text-primary transition focus:outline-none cursor-pointer"
                                title="Afficher ou masquer le mot de passe"
                                aria-label="Afficher ou masquer le mot de passe">
                            <svg class="h-4 w-4 icon-eye" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg class="h-4 w-4 icon-eye-off hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                    <p id="password-aide" class="mt-1 text-[11px] text-primary/50">8 caractères au moins.</p>
                    @error('password')<p id="password-erreur" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="text-xs font-semibold text-primary">Confirmez-le</label>
                    <div class="relative mt-1">
                        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                               class="w-full rounded-lg border border-secondary/30 px-3 py-2 pr-10 text-sm outline-none focus:border-secondary">
                        <button type="button"
                                onclick="togglePasswordVisibility('password_confirmation', this)"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-primary/40 hover:text-primary transition focus:outline-none cursor-pointer"
                                title="Afficher ou masquer le mot de passe"
                                aria-label="Afficher ou masquer le mot de passe">
                            <svg class="h-4 w-4 icon-eye" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg class="h-4 w-4 icon-eye-off hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:opacity-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                    Enregistrer et continuer
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">
                @csrf
                <button type="submit" class="text-xs text-primary/50 underline-offset-2 hover:text-primary hover:underline">Se déconnecter</button>
            </form>
        </div>
    </main>

    <script>
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        const eye = btn.querySelector('.icon-eye');
        const eyeOff = btn.querySelector('.icon-eye-off');
        if (eye && eyeOff) {
            eye.classList.toggle('hidden', isPassword);
            eyeOff.classList.toggle('hidden', !isPassword);
        }
    }
    </script>
</body>
</html>
