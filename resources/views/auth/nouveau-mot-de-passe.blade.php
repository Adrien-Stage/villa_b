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
                    <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" autofocus
                           aria-describedby="password-aide @error('password') password-erreur @enderror"
                           class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
                    <p id="password-aide" class="mt-1 text-[11px] text-primary/50">8 caractères au moins.</p>
                    @error('password')<p id="password-erreur" class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="text-xs font-semibold text-primary">Confirmez-le</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                           class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
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
</body>
</html>
