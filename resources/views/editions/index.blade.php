@extends('layouts.hotel')

@section('title', 'Éditions')

@php
    $icones = [
        \App\Editions\Edition::FINANCES => 'landmark',
        \App\Editions\Edition::HEBERGEMENT => 'bed-double',
        \App\Editions\Edition::RESTAURATION => 'utensils',
        \App\Editions\Edition::ACHATS => 'package',
        \App\Editions\Edition::PERSONNEL => 'users',
    ];
@endphp

@section('content')
<div class="mb-6">
    <h1 class="font-heading text-2xl font-semibold text-primary">Éditions</h1>
    <p class="mt-0.5 max-w-3xl text-sm text-primary/55">
        Tout ce qui s'imprime, en un seul endroit : registres, situations, journaux et pièces. Choisissez un document,
        réglez ses filtres, lisez-le à l'écran, imprimez-le ou exportez-le.
    </p>
</div>

{{-- Retrouver une pièce par son numéro. --}}
<section class="mb-8 rounded-xl border border-secondary/20 bg-white p-5 shadow-sm" aria-labelledby="pieces">
    <h2 id="pieces" class="flex items-center gap-2 text-sm font-semibold text-primary">
        <i data-lucide="search" class="h-4 w-4 text-secondary" aria-hidden="true"></i> Retrouver une pièce
    </h2>
    <p class="mt-0.5 text-xs text-primary/55">Facture, réservation, bon de commande, bon d'entrée, réquisition, vente, procès-verbal : tapez tout ou partie de son numéro.</p>
    <form method="GET" action="{{ route('editions.index') }}" class="mt-3 flex flex-wrap gap-2" role="search">
        <label for="recherche-piece" class="sr-only">Numéro de la pièce</label>
        <input id="recherche-piece" type="search" name="q" value="{{ $recherche }}" minlength="2" placeholder="Ex. FAC-2026, BC-0042, BKG…"
               class="min-w-0 flex-1 rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary sm:max-w-md">
        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:opacity-95">Chercher</button>
    </form>

    @if($recherche !== '')
        <div class="mt-4">
            <x-table :rows="$pieces" inline="never" empty="Aucune pièce ne correspond à « {{ $recherche }} »." empty-icon="file-search" caption="Pièces trouvées">
                <x-slot:head>
                    <x-table.col>Pièce</x-table.col>
                    <x-table.col>Numéro</x-table.col>
                    <x-table.col hide="sm">Date</x-table.col>
                    <x-table.col hide="md">Détail</x-table.col>
                    <x-table.col actions />
                </x-slot:head>
                @foreach($pieces as $piece)
                    <x-table.row :href="$piece['url']">
                        <x-table.cell class="text-primary/70">{{ $piece['sorte'] }}</x-table.cell>
                        <x-table.cell nowrap class="font-semibold text-primary">{{ $piece['numero'] }}</x-table.cell>
                        <x-table.cell hide="sm" nowrap class="text-primary/60">{{ $piece['date'] ? \Carbon\Carbon::parse($piece['date'])->format('d/m/Y') : '—' }}</x-table.cell>
                        <x-table.cell hide="md" class="text-primary/60">{{ $piece['detail'] }}</x-table.cell>
                        <x-table.actions :label="'Actions pour '.$piece['numero']">
                            <x-table.action :href="$piece['url']" target="_blank" icon="printer">Ouvrir et imprimer</x-table.action>
                        </x-table.actions>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>
    @endif
</section>

@if($familles->isEmpty() && $specialisees->isEmpty())
    <p class="rounded-xl border border-dashed border-secondary/30 bg-white px-4 py-10 text-center text-sm text-primary/50">
        Aucune édition ne relève de vos fonctions.
    </p>
@endif

@foreach(\App\Editions\Edition::FAMILLES as $famille)
    @if(($familles[$famille] ?? collect())->isNotEmpty() || ($specialisees[$famille] ?? collect())->isNotEmpty())
        <section class="mb-8" aria-labelledby="famille-{{ $loop->index }}">
            <h2 id="famille-{{ $loop->index }}" class="mb-3 flex items-center gap-2 font-heading text-lg font-semibold text-primary">
                <i data-lucide="{{ $icones[$famille] ?? 'file-text' }}" class="h-5 w-5 text-secondary" aria-hidden="true"></i> {{ $famille }}
            </h2>
            <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($familles[$famille] ?? [] as $edition)
                    <li>
                        <a href="{{ route('editions.show', $edition->cle()) }}"
                           class="group flex h-full flex-col rounded-xl border border-secondary/20 bg-white p-4 shadow-sm transition-colors hover:border-secondary/50 hover:bg-accent/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                            <span class="flex items-start justify-between gap-2">
                                <span class="text-sm font-semibold text-primary">{{ $edition->titre() }}</span>
                                <i data-lucide="file-text" class="h-4 w-4 shrink-0 text-secondary" aria-hidden="true"></i>
                            </span>
                            <span class="mt-1 text-xs text-primary/55">{{ $edition->description() }}</span>
                            <span class="mt-auto pt-3 text-xs font-semibold text-primary group-hover:underline">Préparer l'édition →</span>
                        </a>
                    </li>
                @endforeach
                @foreach($specialisees[$famille] ?? [] as $lien)
                    <li>
                        <a href="{{ $lien['url'] }}"
                           class="group flex h-full flex-col rounded-xl border border-dashed border-secondary/30 bg-white/70 p-4 transition-colors hover:border-secondary/50 hover:bg-accent/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
                            <span class="flex items-start justify-between gap-2">
                                <span class="text-sm font-semibold text-primary">{{ $lien['titre'] }}</span>
                                <i data-lucide="external-link" class="h-4 w-4 shrink-0 text-secondary" aria-hidden="true"></i>
                            </span>
                            <span class="mt-1 text-xs text-primary/55">{{ $lien['description'] }}</span>
                            <span class="mt-auto pt-3 text-xs font-semibold text-primary group-hover:underline">Ouvrir l'écran dédié →</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endforeach
@endsection
