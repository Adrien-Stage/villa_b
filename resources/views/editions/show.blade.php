@extends('layouts.hotel')

@section('title', $edition->titre())

@php
    $lignes = $document->lesLignes();
    $colonnes = $document->lesColonnes();
    $totaux = $document->lesTotaux();
    $champ = 'w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm text-primary outline-none focus:border-secondary';
@endphp

@section('content')
<div class="mb-5">
    <a href="{{ route('editions.index') }}" class="text-xs text-primary/50 hover:text-primary">&larr; Éditions</a>
    <h1 class="mt-1 font-heading text-2xl font-semibold text-primary">{{ $edition->titre() }}</h1>
    <p class="mt-0.5 max-w-3xl text-sm text-primary/55">{{ $edition->description() }}</p>
</div>

{{-- Les filtres ; l'impression et les exports reprennent ceux affichés. --}}
<form method="GET" action="{{ route('editions.show', $edition->cle()) }}" class="mb-5 rounded-xl border border-secondary/20 bg-white p-4 shadow-sm">
    @if($edition->filtres() !== [])
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach($edition->filtres() as $filtre)
                @php $valeur = $valeurs[$filtre->cle] ?? null; @endphp
                @if($filtre->type === \App\Editions\Filtre::PERIODE)
                    <label class="block">
                        <span class="text-xs font-semibold text-primary/70">Du</span>
                        <input type="date" name="du" value="{{ $valeur[0]->toDateString() }}" class="mt-1 {{ $champ }}">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-primary/70">Au</span>
                        <input type="date" name="au" value="{{ $valeur[1]->toDateString() }}" class="mt-1 {{ $champ }}">
                    </label>
                @elseif($filtre->type === \App\Editions\Filtre::JOUR || $filtre->type === \App\Editions\Filtre::SEMAINE)
                    <label class="block">
                        <span class="text-xs font-semibold text-primary/70">{{ $filtre->libelle }}@if($filtre->type === \App\Editions\Filtre::SEMAINE) (un jour de la semaine)@endif</span>
                        <input type="date" name="{{ $filtre->cle }}" value="{{ $valeur->toDateString() }}" class="mt-1 {{ $champ }}">
                    </label>
                @else
                    <label class="block">
                        <span class="text-xs font-semibold text-primary/70">{{ $filtre->libelle }}</span>
                        <select name="{{ $filtre->cle }}" class="mt-1 {{ $champ }}">
                            <option value="">{{ $filtre->libelleTous }}</option>
                            @foreach($filtre->options(auth()->user()) as $cle => $libelle)
                                <option value="{{ $cle }}" @selected((string) $valeur === (string) $cle)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            @endforeach
        </div>
    @endif

    <div class="{{ $edition->filtres() !== [] ? 'mt-4 border-t border-secondary/10 pt-4' : '' }} flex flex-wrap items-center gap-2">
        @if($edition->filtres() !== [])
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:opacity-95">
                <i data-lucide="eye" class="h-3.5 w-3.5" aria-hidden="true"></i> Afficher
            </button>
        @endif
        <button type="submit" formaction="{{ route('editions.print', $edition->cle()) }}" formtarget="_blank"
                class="inline-flex items-center gap-1.5 rounded-lg border border-secondary/30 bg-white px-4 py-2 text-xs font-semibold text-primary hover:bg-accent/20">
            <i data-lucide="printer" class="h-3.5 w-3.5" aria-hidden="true"></i> Imprimer
        </button>
        @droit('editions.export')
            @foreach(['pdf' => ['PDF', 'file-text'], 'excel' => ['Excel', 'sheet'], 'word' => ['Word', 'file-type']] as $format => [$libelle, $icone])
                <button type="submit" name="format" value="{{ $format }}" formaction="{{ route('editions.export', $edition->cle()) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-secondary/30 bg-white px-3 py-2 text-xs font-semibold text-primary hover:bg-accent/20">
                    <i data-lucide="{{ $icone }}" class="h-3.5 w-3.5" aria-hidden="true"></i> {{ $libelle }}
                </button>
            @endforeach
        @enddroit
    </div>
</form>

{{-- Aperçu à l'écran : le même document que celui qui s'imprime. --}}
<x-table :rows="$lignes" inline="never" empty="Rien pour ces filtres." empty-icon="file-search" :caption="$document->titre">
    <x-slot:toolbar>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-primary">{{ $document->titre }}</p>
            @if($document->leSousTitre())<p class="text-xs text-primary/55">{{ $document->leSousTitre() }}</p>@endif
            <p class="mt-1 flex flex-wrap gap-1.5">
                @foreach($document->lesFiltres() as $libelle => $valeurFiltre)
                    <span class="rounded-md border border-secondary/20 bg-accent/15 px-2 py-0.5 text-[11px] text-primary/70"><b class="font-semibold text-primary">{{ $libelle }}</b> : {{ $valeurFiltre }}</span>
                @endforeach
            </p>
        </div>
        <span class="text-xs text-primary/50">{{ $lignes->count() }} ligne(s)</span>
    </x-slot:toolbar>
    <x-slot:head>
        @foreach($colonnes as $colonne)
            <x-table.col :align="$colonne->alignementDroite() ? 'right' : 'left'">{{ $colonne->libelle }}</x-table.col>
        @endforeach
    </x-slot:head>
    @foreach($lignes->take($apercu) as $ligne)
        <x-table.row>
            @foreach($colonnes as $colonne)
                <x-table.cell :align="$colonne->alignementDroite() ? 'right' : 'left'" :nowrap="$colonne->alignementDroite()"
                              class="{{ $loop->first ? 'font-medium text-primary' : 'text-primary/75' }}">{{ $colonne->formater($document->valeur($ligne, $colonne), $devise) }}</x-table.cell>
            @endforeach
        </x-table.row>
    @endforeach
    @if($totaux !== [] && $lignes->isNotEmpty())
        <x-slot:foot>
            <tr class="border-t-2 border-primary/20 bg-accent/15 font-semibold text-primary">
                @foreach($colonnes as $colonne)
                    <td class="px-4 py-2.5 text-sm {{ $colonne->alignementDroite() ? 'text-right whitespace-nowrap' : '' }}">
                        @if($loop->first && ! array_key_exists($colonne->cle, $totaux))
                            Total
                        @elseif(array_key_exists($colonne->cle, $totaux))
                            {{ $colonne->formater($totaux[$colonne->cle], $devise) }}
                        @endif
                    </td>
                @endforeach
            </tr>
        </x-slot:foot>
    @endif
</x-table>

@if($lignes->count() > $apercu)
    <p class="mt-2 text-xs text-primary/55">L'écran montre les {{ $apercu }} premières lignes ; l'impression et les exports les contiennent toutes.</p>
@endif
@if($document->laNote())
    <p class="mt-3 rounded-lg border border-secondary/20 bg-accent/10 px-4 py-3 text-xs text-primary/70">{{ $document->laNote() }}</p>
@endif
@endsection
