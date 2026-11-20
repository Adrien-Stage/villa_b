@extends('layouts.hotel')

@section('title', 'Dossier journalier hôtelier')

@section('content')
@php
    $date = ['jour' => $jour->toDateString()];
    $selection = $date + ($feuille === null ? [] : ['feuille' => $feuille]);
    $bouton = 'rounded-lg border border-secondary/30 px-3 py-2 text-sm font-semibold text-primary hover:bg-accent/20';
@endphp
<div class="mb-5">
    <a href="{{ route('editions.index') }}" class="text-sm text-primary/70">&larr; Éditions</a>
    <h1 class="mt-1 font-heading text-2xl font-semibold text-primary">Dossier journalier hôtelier</h1>
    <p class="mt-1 text-sm text-primary/70">{{ $documents[array_key_first($documents)]->lesFiltres()['État du dossier'] }}</p>
</div>

<section class="mb-5 rounded-xl border border-secondary/20 bg-white p-4" aria-label="Date et actions du dossier">
    <form method="GET" action="{{ route('editions.dossier') }}" class="flex flex-wrap items-end gap-3">
        <label class="text-sm font-semibold text-primary">
            Date d’exploitation
            <input type="date" name="jour" required value="{{ $jour->toDateString() }}"
                   class="mt-1 block rounded-lg border border-secondary/30 px-3 py-2">
        </label>
        <button class="{{ $bouton }}" type="submit">Afficher</button>
        @if($derniereCloture && $derniereCloture->toDateString() !== $jour->toDateString())
            <a class="{{ $bouton }}" href="{{ route('editions.dossier', ['jour' => $derniereCloture->toDateString()]) }}">
                Dernière clôture
            </a>
        @endif
    </form>
    <div class="mt-4 flex flex-wrap gap-2">
        @if($feuille !== null)
            <a class="{{ $bouton }}" href="{{ route('editions.dossier', $date) }}">Voir le dossier complet</a>
        @endif
        @include('editions.dossier-actions', ['selection' => $selection, 'contexte' => $feuille ? 'la feuille' : 'le dossier'])
    </div>
</section>

@if(count($documents) > 1)
    <nav class="mb-5 flex flex-wrap gap-2" aria-label="Feuilles du dossier">
        @foreach($documents as $cle => $document)
            <a class="{{ $bouton }}" href="#feuille-{{ $cle }}">{{ $document->titre }}</a>
        @endforeach
    </nav>
@endif

@foreach($documents as $cle => $document)
    <section id="feuille-{{ $cle }}" class="mb-6 scroll-mt-24" aria-label="{{ $document->titre }}">
        @include('editions.apercu', ['apercu' => 300, 'devise' => $document->enTeteEtablissement()['devise'] ?? 'FCFA'])
        @if($feuille === null)
            <div class="mt-2 flex flex-wrap gap-2">
                <a class="{{ $bouton }}" href="{{ route('editions.dossier', $date + ['feuille' => $cle]) }}">
                    Ouvrir la feuille
                </a>
                @include('editions.dossier-actions', ['selection' => $date + ['feuille' => $cle], 'contexte' => $document->titre])
            </div>
        @endif
    </section>
@endforeach
@endsection
