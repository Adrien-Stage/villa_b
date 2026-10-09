@extends('layouts.hotel')

@section('title', 'Fiches de comptage — Inventaire')

@section('content')
<div class="max-w-4xl mx-auto" x-data="{ articles: 'tous', depuis: '' }">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Fiches de comptage</h1>
            <p class="text-sm text-primary/60 mt-0.5">
                Une fiche par service, à remplir pendant l'inventaire puis à saisir.
                @if($prochainInventaire)
                    Prochain inventaire général : <strong>{{ $prochainInventaire->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</strong>.
                @endif
            </p>
        </div>
        <form method="GET" action="{{ route('economat.count_sheets.print') }}" target="_blank">
            <input type="hidden" name="service" value="{{ \App\Services\CountSheetService::ALL }}">
            <input type="hidden" name="articles" :value="articles">
            <input type="hidden" name="depuis" :value="articles === 'mouvementes' ? depuis : ''">
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="printer" class="w-4 h-4"></i> Toutes les fiches
            </button>
        </form>
    </div>

    @include('economat.partials.flash')

    <div class="mb-4 px-4 py-3 bg-accent/10 border border-secondary/20 text-primary/70 text-xs rounded-lg flex gap-2">
        <i data-lucide="info" class="w-4 h-4 shrink-0 mt-0.5"></i>
        <p>
            La fiche « à l'aveugle » ne montre pas le stock théorique : le compteur compte ce qu'il voit, sans être tenté de recopier.
            La fiche « avec théorique » sert au contrôle. Si un inventaire est déjà ouvert, la fiche reprend son théorique figé.
        </p>
    </div>

    {{-- Les articles à compter : valable pour toutes les fiches ci-dessous. --}}
    <fieldset class="mb-4 bg-white border border-secondary/20 rounded-xl p-4">
        <legend class="sr-only">Articles à compter</legend>
        <p class="text-xs font-semibold uppercase tracking-wider text-primary/70 mb-2" aria-hidden="true">Articles à compter</p>
        <div class="space-y-2">
            @foreach($selections as $cle => $libelle)
                <label class="flex items-start gap-2 text-sm text-primary">
                    <input type="radio" name="choix-articles" value="{{ $cle }}" x-model="articles" class="mt-0.5 border-secondary/40 text-primary">
                    <span>{{ $libelle }}</span>
                </label>
            @endforeach
        </div>
        <div class="mt-3 pl-6" x-show="articles === 'mouvementes'" x-cloak>
            <label for="depuis" class="block text-xs text-primary/70">Mouvements depuis le</label>
            <input id="depuis" type="date" x-model="depuis" max="{{ now()->toDateString() }}"
                   class="mt-1 px-2.5 py-1.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary">
            <p class="text-[11px] text-primary/50 mt-1">Vide : depuis le dernier inventaire clôturé de chaque service (à défaut, depuis le début du mois). Pour la boutique, un produit a bougé s'il a été vendu.</p>
        </div>
        <p class="text-[11px] text-primary/50 mt-2" x-show="articles !== 'tous'" x-cloak>« En stock » veut dire un stock différent de 0 : un stock négatif au garde-manger se compte aussi.</p>
    </fieldset>

    <ul class="space-y-3">
        @foreach($services as $cle => $libelle)
            @php
                $categories = match (true) {
                    $cle === \App\Services\CountSheetService::ECONOMAT              => $stockCategories,
                    str_starts_with($cle, \App\Services\CountSheetService::PANTRY) => $pantryCategories,
                    default                                                         => collect(),
                };
            @endphp
            <li class="bg-white border border-secondary/20 rounded-xl p-4">
                <form method="GET" action="{{ route('economat.count_sheets.print') }}" target="_blank"
                      class="flex flex-wrap items-center justify-between gap-3">
                    <input type="hidden" name="service" value="{{ $cle }}">
                    <input type="hidden" name="articles" :value="articles">
                    <input type="hidden" name="depuis" :value="articles === 'mouvementes' ? depuis : ''">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-primary">{{ $libelle }}</p>
                        @if($categories->isNotEmpty())
                            <label for="categorie-{{ $cle }}" class="sr-only">Catégorie — {{ $libelle }}</label>
                            <select id="categorie-{{ $cle }}" name="categorie" class="mt-1.5 px-2 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary">
                                <option value="">Toutes les catégories</option>
                                @foreach($categories as $categorie)
                                    <option value="{{ $categorie->id }}">{{ $categorie->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" name="theorique" value="0"
                                class="inline-flex items-center gap-1.5 px-3 py-2 border border-secondary/30 text-primary text-xs font-medium rounded-lg hover:bg-accent/20">
                            <i data-lucide="eye-off" class="w-3.5 h-3.5"></i> À l'aveugle
                        </button>
                        <button type="submit" name="theorique" value="1"
                                class="inline-flex items-center gap-1.5 px-3 py-2 border border-secondary/30 text-primary text-xs font-medium rounded-lg hover:bg-accent/20">
                            <i data-lucide="eye" class="w-3.5 h-3.5"></i> Avec théorique
                        </button>
                    </div>
                </form>
            </li>
        @endforeach
    </ul>
</div>
@endsection
