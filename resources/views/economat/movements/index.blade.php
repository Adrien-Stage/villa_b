@extends('layouts.hotel')

@section('title', ($article ? 'Fiche de stock — ' . $article->name : 'Mouvements de stock') . ' — Économat')

@section('content')
@php
    $qte = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');
    $fcfa = fn (int $v) => number_format(abs($v) / 100, 0, ',', ' ');
    $styles = [
        'in'         => 'bg-green-50 text-green-700 border-green-200',
        'out'        => 'bg-red-50 text-red-700 border-red-200',
        'adjustment' => 'bg-amber-50 text-amber-800 border-amber-200',
    ];
    $champ = 'mt-1 rounded-lg border border-secondary/30 bg-white px-2.5 py-1.5 text-sm text-primary outline-none focus:border-primary';
@endphp

<div class="max-w-7xl mx-auto space-y-5">
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="arrow-left-right" class="w-7 h-7 text-primary"></i>
                <span>{{ $article ? 'Fiche de stock' : 'Mouvements de stock' }}</span>
            </h1>
            <p class="text-sm text-primary/60 mt-1 max-w-3xl">
                @if($article)
                    Tous les mouvements de <strong>{{ $article->name }}</strong> dans l'ordre du temps, avec le stock avant et après chacun.
                @else
                    Chaque entrée, sortie et ajustement du magasin central, avec le stock avant et après, la valeur et le document d'origine.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @droit('economat.movements.export')
                <x-barre-export route="economat.movements.export" />
            @enddroit
            @if($article)
                <a href="{{ route('economat.movements.index', array_filter(['du' => request('du', $filtres['du']?->toDateString()), 'au' => request('au', $filtres['au']?->toDateString())])) }}"
                   class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors">
                    <i data-lucide="list" class="w-4 h-4"></i> Tous les articles
                </a>
            @endif
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- Filtres : en GET, repris tels quels par l'export --}}
    <form method="GET" action="{{ route('economat.movements.index') }}" class="rounded-xl border border-secondary/20 bg-white px-4 py-3">
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[14rem] flex-1">
                <label for="article" class="block text-[11px] font-medium text-primary/50">Article</label>
                <select name="article" id="article" class="{{ $champ }} w-full">
                    <option value="">Tous les articles</option>
                    @foreach($articles as $a)
                        <option value="{{ $a->id }}" @selected($filtres['article'] === $a->id)>{{ $a->name }}@if($a->reference) ({{ $a->reference }})@endif{{ $a->is_active ? '' : ' — inactif' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="du" class="block text-[11px] font-medium text-primary/50">Du</label>
                <input type="date" name="du" id="du" value="{{ $filtres['du']?->toDateString() }}" class="{{ $champ }}">
            </div>
            <div>
                <label for="au" class="block text-[11px] font-medium text-primary/50">Au</label>
                <input type="date" name="au" id="au" value="{{ $filtres['au']?->toDateString() }}" class="{{ $champ }}">
            </div>
            <div>
                <label for="categorie" class="block text-[11px] font-medium text-primary/50">Catégorie</label>
                <select name="categorie" id="categorie" class="{{ $champ }}">
                    <option value="">Toutes</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->id }}" @selected($filtres['categorie'] === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="type" class="block text-[11px] font-medium text-primary/50">Mouvement</label>
                <select name="type" id="type" class="{{ $champ }}">
                    <option value="">Tous</option>
                    @foreach(\App\Models\StockMovement::TYPES as $cle => $libelle)
                        <option value="{{ $cle }}" @selected($filtres['type'] === $cle)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="source" class="block text-[11px] font-medium text-primary/50">Origine</label>
                <select name="source" id="source" class="{{ $champ }}">
                    <option value="">Toutes</option>
                    @foreach(\App\Models\StockMovement::SOURCES as $cle => $libelle)
                        <option value="{{ $cle }}" @selected($filtres['source'] === $cle)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[10rem]">
                <label for="recherche" class="block text-[11px] font-medium text-primary/50">Motif contient</label>
                <input type="search" name="recherche" id="recherche" value="{{ $filtres['recherche'] }}" placeholder="Inventaire, casse…" class="{{ $champ }} w-full">
            </div>
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:bg-surface-dark">Filtrer</button>
            <a href="{{ route('economat.movements.index', ['du' => '', 'au' => '']) }}" class="text-xs text-primary/60 underline hover:text-primary pb-2">Tout l'historique</a>
        </div>
    </form>

    {{-- Fiche de l'article, ou synthèse de la sélection --}}
    @if($article && $fiche)
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
            <div class="bg-white rounded-xl border border-secondary/20 p-4">
                <p class="text-[11px] uppercase tracking-wider text-primary/50">Stock au début</p>
                <p class="text-xl font-bold font-mono text-primary mt-1">{{ $qte($fiche['debut']) }} <span class="text-xs font-sans text-primary/50">{{ $article->unit }}</span></p>
            </div>
            <div class="bg-white rounded-xl border border-secondary/20 p-4">
                <p class="text-[11px] uppercase tracking-wider text-green-700">Entrées</p>
                <p class="text-xl font-bold font-mono text-green-700 mt-1">+{{ $qte($fiche['entrees']) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-secondary/20 p-4">
                <p class="text-[11px] uppercase tracking-wider text-red-700">Sorties</p>
                <p class="text-xl font-bold font-mono text-red-700 mt-1">−{{ $qte($fiche['sorties']) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-secondary/20 p-4">
                <p class="text-[11px] uppercase tracking-wider text-amber-700">Ajustements</p>
                <p class="text-xl font-bold font-mono text-amber-800 mt-1">{{ $fiche['ajustements'] > 0 ? '+' : ($fiche['ajustements'] < 0 ? '−' : '') }}{{ $qte(abs($fiche['ajustements'])) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-primary/30 p-4">
                <p class="text-[11px] uppercase tracking-wider text-primary/60">Stock en fin</p>
                <p class="text-xl font-bold font-mono text-primary mt-1">{{ $qte($fiche['fin']) }} <span class="text-xs font-sans text-primary/50">{{ $article->unit }}</span></p>
            </div>
            <div class="bg-white rounded-xl border border-secondary/20 p-4">
                <p class="text-[11px] uppercase tracking-wider text-primary/50">Aujourd'hui</p>
                <p class="text-sm font-semibold text-primary mt-1">{{ $qte($article->current_stock) }} {{ $article->unit }}</p>
                <p class="text-[11px] text-primary/50">CUMP {{ $fcfa((int) $article->average_cost) }} F · valeur {{ $fcfa($article->stockValue()) }} F</p>
            </div>
        </div>
    @else
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-xl border border-secondary/20 p-4">
                <p class="text-[11px] uppercase tracking-wider text-primary/50">Mouvements</p>
                <p class="text-2xl font-bold font-mono text-primary mt-1">{{ number_format($synthese['nombre'], 0, ',', ' ') }}</p>
            </div>
            <div class="bg-white rounded-xl border border-secondary/20 p-4">
                <p class="text-[11px] uppercase tracking-wider text-green-700">Valeur entrée</p>
                <p class="text-2xl font-bold font-mono text-green-700 mt-1">{{ $fcfa($synthese['entrees']) }} <span class="text-xs font-sans">F</span></p>
            </div>
            <div class="bg-white rounded-xl border border-secondary/20 p-4">
                <p class="text-[11px] uppercase tracking-wider text-red-700">Valeur sortie</p>
                <p class="text-2xl font-bold font-mono text-red-700 mt-1">{{ $fcfa($synthese['sorties']) }} <span class="text-xs font-sans">F</span></p>
            </div>
            <div class="bg-white rounded-xl border border-secondary/20 p-4">
                <p class="text-[11px] uppercase tracking-wider text-amber-700">Ajustements (écart net)</p>
                <p class="text-2xl font-bold font-mono {{ $synthese['ajustements'] < 0 ? 'text-red-700' : 'text-amber-800' }} mt-1">{{ $synthese['ajustements'] > 0 ? '+' : ($synthese['ajustements'] < 0 ? '−' : '') }}{{ $fcfa($synthese['ajustements']) }} <span class="text-xs font-sans">F</span></p>
            </div>
        </div>
    @endif

    <x-table :rows="$mouvements" empty="Aucun mouvement pour ces critères." empty-icon="arrow-left-right" caption="Mouvements de stock">
        <x-slot:head>
            <x-table.col>Date</x-table.col>
            @unless($article)<x-table.col>Article</x-table.col>@endunless
            <x-table.col>Mouvement</x-table.col>
            <x-table.col hide="lg">Document</x-table.col>
            <x-table.col align="right">Stock avant</x-table.col>
            <x-table.col align="right">Entrée</x-table.col>
            <x-table.col align="right">Sortie</x-table.col>
            <x-table.col align="right">Stock après</x-table.col>
            <x-table.col align="right" hide="xl">Coût unit.</x-table.col>
            <x-table.col align="right">Valeur</x-table.col>
            <x-table.col hide="2xl">Motif</x-table.col>
            <x-table.col hide="3xl">Par</x-table.col>
        </x-slot:head>

        @foreach($lignes as $i => $l)
            <x-table.row>
                <x-table.cell nowrap class="font-mono text-xs text-primary/60">{{ $l['date']?->format('d/m/Y H:i') }}</x-table.cell>
                @unless($article)
                    <x-table.cell>
                        <a href="{{ route('economat.movements.index', array_merge(request()->query(), ['article' => $mouvements->items()[$i]->stock_item_id, 'page' => null])) }}"
                           class="font-medium text-primary hover:underline" title="Fiche de stock de cet article">{{ $l['article'] }}</a>
                    </x-table.cell>
                @endunless
                <x-table.cell nowrap>
                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $styles[$l['type']] ?? 'bg-gray-100 text-gray-600' }}">{{ $l['nature'] }}</span>
                </x-table.cell>
                <x-table.cell hide="lg" class="text-xs">
                    @if($l['url'])
                        <a href="{{ $l['url'] }}" class="text-primary/80 hover:underline">{{ $l['origine'] }}</a>
                    @else
                        <span class="text-primary/60">{{ $l['origine'] }}</span>
                    @endif
                </x-table.cell>
                <x-table.cell align="right" nowrap class="font-mono text-primary/70">{{ $qte($l['avant']) }}</x-table.cell>
                <x-table.cell align="right" nowrap class="font-mono text-green-700">{{ $l['entree'] !== null ? '+' . $qte($l['entree']) : '' }}</x-table.cell>
                <x-table.cell align="right" nowrap class="font-mono text-red-700">{{ $l['sortie'] !== null ? '−' . $qte($l['sortie']) : '' }}</x-table.cell>
                <x-table.cell align="right" nowrap class="font-mono font-bold text-primary">
                    {{ $qte($l['apres']) }} <span class="text-[10px] font-sans font-normal text-primary/50">{{ $l['unite'] }}</span>
                    @if($l['apres_decompose'])<span class="block text-[10px] font-sans font-normal text-primary/45">{{ $l['apres_decompose'] }}</span>@endif
                </x-table.cell>
                <x-table.cell align="right" hide="xl" nowrap class="font-mono text-xs text-primary/60">{{ $fcfa($l['cout']) }}</x-table.cell>
                <x-table.cell align="right" nowrap class="font-mono {{ $l['valeur'] < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $l['valeur'] < 0 ? '−' : '+' }}{{ $fcfa($l['valeur']) }}</x-table.cell>
                <x-table.cell hide="2xl" class="max-w-xs text-xs text-primary/60">
                    {{ $l['motif'] }}
                    @if($l['ouvertures'])<span class="block text-amber-700">{{ ucfirst($l['ouvertures']) }}</span>@endif
                </x-table.cell>
                <x-table.cell hide="3xl" class="text-xs text-primary/60">{{ $l['par'] }}</x-table.cell>
            </x-table.row>
        @endforeach
    </x-table>
</div>
@endsection
