<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-neutral-50 text-neutral-950 antialiased">
    <main class="flex min-h-screen items-center justify-center px-6">
        <section class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <p class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-neutral-400">Admin global</p>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-950">{{ \App\Models\Tenant::first()?->name ?? 'Villa Boutanga' }}</h1>
                <p class="mt-2 text-sm leading-6 text-neutral-500">Acces administration plateforme.</p>
            </div>

            <form method="POST" action="{{ route('admin.login.store') }}" class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
                @csrf

                <div>
                    <label for="login" class="mb-2 block text-sm font-medium text-neutral-700">Login</label>
                    <input
                        id="login"
                        name="login"
                        type="text"
                        value="{{ old('login') }}"
                        required
                        autofocus
                        autocomplete="username"
                        class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2.5 text-sm text-neutral-950 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-200"
                    >
                    @error('login')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-5">
                    <label for="password" class="mb-2 block text-sm font-medium text-neutral-700">Mot de passe</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="block w-full rounded-md border border-neutral-300 bg-white px-3 py-2.5 text-sm text-neutral-950 outline-none transition focus:border-neutral-900 focus:ring-2 focus:ring-neutral-200"
                    >
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="mt-6 inline-flex w-full items-center justify-center rounded-md bg-neutral-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-neutral-800 focus:outline-none focus:ring-2 focus:ring-neutral-900 focus:ring-offset-2"
                >
                    Connexion
                </button>
            </form>
        </section>
    </main>
</body>
</html>
