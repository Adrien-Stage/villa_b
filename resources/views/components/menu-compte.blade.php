{{--
    Le compte de la personne connectée, en haut à droite : son avatar ouvre un
    menu pour modifier son profil ou se déconnecter. La déconnexion garde son
    contrôle de caisse ouverte (AuthenticatedSessionController).
--}}
@props(['sombre' => false])

@php
    $moi = auth()->user();
    $mots = preg_split('/\s+/', trim((string) $moi?->name)) ?: [];
    $initialesCompte = mb_strtoupper(collect($mots)->filter()->take(2)->map(fn ($m) => mb_substr($m, 0, 1))->implode('')) ?: '?';
    $fonctionsCompte = collect($moi?->rolesDetenus() ?? [])
        ->map(fn ($slug) => \App\Support\RoleCatalog::find($slug)['name'] ?? null)
        ->filter()
        ->implode(' · ');
@endphp

<div class="relative ml-1 sm:ml-2" x-data="{ ouvert: false }"
     @keydown.escape.window="ouvert = false" @click.outside="ouvert = false">
    <button type="button" @click="ouvert = !ouvert"
            :aria-expanded="ouvert.toString()" aria-expanded="false" aria-haspopup="menu" aria-controls="menu-compte"
            title="Mon compte"
            class="flex items-center gap-1.5 rounded-full p-0.5 pr-1 transition-colors focus:outline-none focus-visible:ring-2 {{ $sombre ? 'hover:bg-white/15 focus-visible:ring-accent/60' : 'hover:bg-accent/40 focus-visible:ring-primary/40' }}">
        <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold tracking-wide {{ $sombre ? 'bg-accent text-primary' : 'bg-primary text-accent' }}" aria-hidden="true">
            {{ $initialesCompte }}
        </span>
        <span class="sr-only">Mon compte : {{ $moi?->name }}</span>
        <i data-lucide="chevron-down" class="hidden sm:block w-3.5 h-3.5 {{ $sombre ? 'text-white/70' : 'text-primary/50' }}" aria-hidden="true"></i>
    </button>

    <div id="menu-compte" x-show="ouvert" x-cloak
         x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
         role="menu" aria-label="Mon compte"
         class="absolute right-0 mt-2 w-64 origin-top-right rounded-xl border border-secondary/20 bg-white py-1 shadow-lg z-50">
        <div class="px-4 py-3 border-b border-secondary/15">
            <p class="text-sm font-semibold text-primary truncate">{{ $moi?->name }}</p>
            @if($fonctionsCompte !== '')
                <p class="text-xs text-primary/55 truncate">{{ $fonctionsCompte }}</p>
            @endif
            <p class="text-[11px] text-primary/40 truncate">{{ $moi?->email }}</p>
        </div>
        <a href="{{ route('profile.edit') }}" role="menuitem"
           class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-primary hover:bg-accent/20 focus:bg-accent/20 focus:outline-none">
            <i data-lucide="user-cog" class="w-4 h-4 text-primary/60" aria-hidden="true"></i>
            Modifier mon profil
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" role="menuitem"
                    class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-700 hover:bg-red-50 focus:bg-red-50 focus:outline-none">
                <i data-lucide="log-out" class="w-4 h-4" aria-hidden="true"></i>
                Se déconnecter
            </button>
        </form>
    </div>
</div>
