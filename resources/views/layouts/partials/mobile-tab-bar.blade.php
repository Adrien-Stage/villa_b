@props(['tabs' => []])

@if(!empty($tabs))
<nav aria-label="Navigation mobile"
     x-data
     x-init="$nextTick(() => window.refreshLucideIcons && window.refreshLucideIcons())"
     class="fixed bottom-3 sm:bottom-4 inset-x-0 mx-auto z-40 w-fit max-w-[calc(100vw-1.5rem)] lg:hidden print:hidden select-none">
    <div class="flex items-center gap-1 sm:gap-1.5 p-1.5 bg-[#14131b]/95 border border-white/10 rounded-full shadow-2xl shadow-black/60 backdrop-blur-md overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @foreach($tabs as $tab)
            @if($tab['active'] ?? false)
                {{-- Onglet actif : pilule blanche avec icône et libellé textuel --}}
                <a href="{{ $tab['url'] }}"
                   class="flex items-center gap-2 bg-white text-zinc-950 font-bold px-3.5 py-2 rounded-full shadow-sm text-xs transition-all duration-300 shrink-0 select-none">
                    <i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4 text-zinc-950 stroke-[2.2]"></i>
                    <span class="tracking-tight leading-none whitespace-nowrap">{{ $tab['label'] }}</span>
                </a>
            @else
                {{-- Onglet inactif : bouton circulaire discret avec icône seule --}}
                <a href="{{ $tab['url'] }}"
                   title="{{ $tab['label'] }}"
                   aria-label="{{ $tab['label'] }}"
                   class="w-10 h-10 min-w-10 rounded-full flex items-center justify-center text-zinc-400 hover:text-white hover:bg-white/10 transition-all duration-200 shrink-0 select-none">
                    <i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4 stroke-[1.8]"></i>
                </a>
            @endif
        @endforeach

        {{-- Bouton d'accès au menu complet de la barre latérale --}}
        <button type="button"
                onclick="openMobileSidebar()"
                aria-controls="mobile-sidebar"
                aria-label="Menu complet"
                title="Menu complet"
                class="w-10 h-10 min-w-10 rounded-full flex items-center justify-center text-zinc-400 hover:text-white hover:bg-white/10 transition-all duration-200 shrink-0 select-none">
            <i data-lucide="menu" class="w-4 h-4 stroke-[1.8]"></i>
        </button>
    </div>
</nav>
@endif
