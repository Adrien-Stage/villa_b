@extends('layouts.hotel')

@section('title', 'Applications')

@section('content')
<div class="max-w-6xl mx-auto w-full py-2 sm:py-6">

    {{-- Bandeau des Quarts en cours --}}
    <div class="mb-6">
        @include('planning.partials.en-service')
    </div>

    {{-- Pour l'économe : alertes de stock & indicateurs clés --}}
    @if(auth()->user()->exerce(['storekeeper']) && isset($cards) && !empty($cards))
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            @foreach($cards as $card)
                <a href="{{ $card['href'] ?? '#' }}" class="bg-white rounded-2xl p-4 border border-secondary/15 shadow-sm hover:shadow-md transition flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-primary/50">{{ $card['label'] }}</p>
                        <p class="font-heading text-xl font-bold text-primary mt-1">{{ $card['value'] }}</p>
                        <p class="text-[11px] text-primary/60 mt-0.5">{{ $card['subtitle'] }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-accent/20 text-primary flex items-center justify-center">
                        <i data-lucide="{{ $card['icon'] ?? 'package' }}" class="w-5 h-5"></i>
                    </div>
                </a>
            @endforeach
        </div>

        @if(isset($panels['economat_alerts']) && $panels['economat_alerts']->isNotEmpty())
            <div class="bg-white rounded-2xl border border-secondary/15 p-4 shadow-sm mb-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold text-primary flex items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-500"></i>
                        <span>À réapprovisionner (Économat)</span>
                    </h3>
                    <a href="{{ route('economat.items.index') }}" class="text-[11px] font-semibold text-secondary hover:text-primary transition">
                        Voir tout →
                    </a>
                </div>
                <div class="divide-y divide-secondary/10">
                    @foreach($panels['economat_alerts'] as $article)
                        <div class="py-2 flex items-center justify-between text-xs">
                            <span class="font-semibold text-primary">{{ $article->name }}</span>
                            <div class="flex items-center gap-2">
                                @if($article->isOutOfStock())
                                    <span class="px-2 py-0.5 rounded-md bg-red-100 text-red-700 text-[10px] font-bold">Rupture</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-700 text-[10px] font-bold">Stock bas</span>
                                @endif
                                <span class="text-primary/60">{{ $article->current_stock }} {{ $article->unit }} (seuil {{ $article->min_stock }})</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- En-tête discret du lanceur d'applications --}}
    <div class="text-center mb-8 sm:mb-10">
        <h2 class="text-xl sm:text-2xl font-heading font-extrabold text-primary tracking-tight">Espace de Travail</h2>
        <p class="text-xs sm:text-sm text-primary/60 mt-1 max-w-md mx-auto">
            Sélectionnez une application pour accéder aux outils et opérations de votre service.
        </p>
    </div>

    {{-- Grille des Tuiles d'Applications (Style Odoo / Launchpad) --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6 lg:gap-7 justify-items-center">
        @foreach($launcherGroups as $group)
            <a href="{{ $group['url'] }}"
               class="group w-full max-w-[160px] aspect-square rounded-3xl bg-white/95 backdrop-blur-xs p-4 flex flex-col items-center justify-center gap-2.5 shadow-[0_4px_20px_rgba(0,0,0,0.03)] hover:shadow-[0_12px_30px_rgba(0,0,0,0.09)] hover:-translate-y-1.5 transition-all duration-200 border border-secondary/20 hover:border-primary/30 relative cursor-pointer">

                <!-- Icône carrée stylisée aux coins arrondis -->
                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br {{ $group['bg_gradient'] }} flex items-center justify-center text-white shadow-md shadow-primary/10 transition-transform duration-200 group-hover:scale-108 flex-shrink-0">
                    <i data-lucide="{{ $group['icon'] }}" class="w-7 h-7 sm:w-8 sm:h-8 stroke-[1.8]"></i>
                </div>

                <!-- Libellé de l'application -->
                <div class="text-center w-full px-1">
                    <span class="block text-xs sm:text-sm font-bold text-primary group-hover:text-primary transition-colors truncate">
                        {{ $group['title'] }}
                    </span>
                    <span class="hidden sm:block text-[10px] text-primary/50 group-hover:text-primary/70 transition-colors truncate mt-0.5">
                        {{ $group['subtitle'] }}
                    </span>
                </div>

                <!-- Pastille si module avec menu latéral dédié -->
                @if($group['type'] === \App\Support\Navigation::TYPE_SIDEBAR)
                    <span class="absolute top-2.5 right-2.5 w-1.5 h-1.5 rounded-full bg-secondary/40 group-hover:bg-primary transition-colors" title="Module complet avec menu dédié"></span>
                @endif
            </a>
        @endforeach
    </div>

    <!-- Pied de page discret du lanceur -->
    <div class="mt-12 text-center text-xs text-primary/40 font-medium">
        <span>{{ $tenantName ?? 'Établissement' }} &bull; Wetchah PMS v1.0</span>
    </div>
</div>
@endsection
